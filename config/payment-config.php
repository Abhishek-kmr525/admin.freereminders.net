<?php
// config/payment-config.php
require_once 'database-config.php';

// Razorpay Configuration
define('RAZORPAY_KEY_ID', 'rzp_test_your_key_id_here');
define('RAZORPAY_KEY_SECRET', 'your_secret_key_here');

// Payment settings by country
$PAYMENT_SETTINGS = array(
    'us' => array(
        'currency' => 'USD',
        'gateway' => 'razorpay',
        'min_amount' => 100, // $1.00 in cents
        'tax_rate' => 0.00 // No tax for international
    ),
    'in' => array(
        'currency' => 'INR',
        'gateway' => 'razorpay', 
        'min_amount' => 100, // ₹1.00 in paise
        'tax_rate' => 0.18 // 18% GST in India
    )
);

// Get payment settings for country
function getPaymentSettings($country = null) {
    global $PAYMENT_SETTINGS;
    
    if (!$country) {
        $country = getCustomerCountry();
    }
    
    return $PAYMENT_SETTINGS[$country] ?? $PAYMENT_SETTINGS['us'];
}

// Convert amount to smallest currency unit (cents/paise)
function convertToSmallestUnit($amount, $country = null) {
    $settings = getPaymentSettings($country);
    return (int)($amount * 100); // Convert to cents/paise
}

// Convert from smallest unit to decimal
function convertFromSmallestUnit($amount, $country = null) {
    return $amount / 100;
}

// Calculate total with tax
function calculateTotal($amount, $country = null) {
    $settings = getPaymentSettings($country);
    $tax = $amount * $settings['tax_rate'];
    
    return array(
        'subtotal' => $amount,
        'tax' => $tax,
        'total' => $amount + $tax,
        'tax_rate' => $settings['tax_rate']
    );
}

// Create Razorpay order
function createRazorpayOrder($amount, $currency, $customerId, $planName, $country = null) {
    global $db;
    
    try {
        // Calculate total with tax
        $pricing = calculateTotal($amount, $country);
        $totalAmount = convertToSmallestUnit($pricing['total'], $country);
        
        // Create order data
        $orderData = array(
            'amount' => $totalAmount,
            'currency' => $currency,
            'receipt' => 'order_' . $customerId . '_' . time(),
            'payment_capture' => 1,
            'notes' => array(
                'customer_id' => $customerId,
                'plan_name' => $planName,
                'country' => $country ?? getCustomerCountry()
            )
        );
        
        // Make API call to Razorpay
        $response = makeRazorpayRequest('/orders', $orderData);
        
        if (isset($response['id'])) {
            // Save order to database
            $stmt = $db->prepare("
                INSERT INTO payment_orders (
                    customer_id, gateway_order_id, amount, currency, 
                    plan_name, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'created', NOW())
            ");
            
            $stmt->execute(array(
                $customerId,
                $response['id'],
                $pricing['total'],
                $currency,
                $planName
            ));
            
            return array(
                'order_id' => $response['id'],
                'amount' => $totalAmount,
                'currency' => $currency,
                'pricing' => $pricing
            );
        }
        
        throw new Exception('Failed to create Razorpay order');
        
    } catch (Exception $e) {
        logError("Create Razorpay order error: " . $e->getMessage());
        throw $e;
    }
}

// Verify Razorpay payment
function verifyRazorpayPayment($razorpayOrderId, $razorpayPaymentId, $razorpaySignature) {
    $expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, RAZORPAY_KEY_SECRET);
    
    return hash_equals($expectedSignature, $razorpaySignature);
}

// Make Razorpay API request
function makeRazorpayRequest($endpoint, $data = null, $method = 'POST') {
    $url = 'https://api.razorpay.com/v1' . $endpoint;
    
    $ch = curl_init();
    
    $headers = array(
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET)
    );
    
    $curlOptions = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false
    );
    
    if ($method === 'POST' && $data) {
        $curlOptions[CURLOPT_POST] = true;
        $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    
    curl_setopt_array($ch, $curlOptions);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Razorpay API request failed: $error");
    }
    
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    
    if ($httpCode >= 400) {
        $errorMsg = isset($decoded['error']['description']) ? $decoded['error']['description'] : 'Payment processing failed';
        throw new Exception("Razorpay API error: $errorMsg");
    }
    
    return $decoded;
}

// Process successful payment
function processSuccessfulPayment($orderId, $paymentId, $customerId) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // Get order details
        $stmt = $db->prepare("SELECT * FROM payment_orders WHERE gateway_order_id = ?");
        $stmt->execute(array($orderId));
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // Update order status
        $stmt = $db->prepare("
            UPDATE payment_orders 
            SET status = 'completed', gateway_payment_id = ?, completed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute(array($paymentId, $order['id']));
        
        // Create subscription
        $subscriptionEndDate = date('Y-m-d H:i:s', strtotime('+1 month'));
        
        $stmt = $db->prepare("
            INSERT INTO subscriptions (
                customer_id, plan_name, plan_price, currency, 
                status, starts_at, ends_at, payment_gateway, 
                gateway_subscription_id, created_at
            ) VALUES (?, ?, ?, ?, 'active', NOW(), ?, 'razorpay', ?, NOW())
        ");
        
        $stmt->execute(array(
            $customerId,
            $order['plan_name'],
            $order['amount'],
            $order['currency'],
            $subscriptionEndDate,
            $paymentId
        ));
        
        // Update customer subscription status
        $stmt = $db->prepare("
            UPDATE customers 
            SET subscription_status = 'active', subscription_plan = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute(array($order['plan_name'], $customerId));
        
        // Record payment
        $stmt = $db->prepare("
            INSERT INTO payments (
                customer_id, amount, currency, status, 
                payment_gateway, gateway_payment_id, created_at
            ) VALUES (?, ?, ?, 'completed', 'razorpay', ?, NOW())
        ");
        $stmt->execute(array($customerId, $order['amount'], $order['currency'], $paymentId));
        
        $db->commit();
        
        // Log activity
        logCustomerActivity($customerId, 'payment_successful', "Paid for {$order['plan_name']} plan");
        
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        logError("Process payment error: " . $e->getMessage());
        throw $e;
    }
}

// Get customer's pricing plans
function getCustomerPricingPlans($country = null) {
    global $db;
    
    if (!$country) {
        $country = getCustomerCountry();
    }
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM pricing_plans 
            WHERE country = ? AND is_active = 1 
            ORDER BY plan_price ASC
        ");
        $stmt->execute(array($country));
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logError("Get pricing plans error: " . $e->getMessage());
        return array();
    }
}

// Check if customer has active subscription
function hasActiveSubscription($customerId) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM subscriptions 
            WHERE customer_id = ? 
            AND status = 'active' 
            AND ends_at > NOW()
        ");
        $stmt->execute(array($customerId));
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    } catch (Exception $e) {
        logError("Check subscription error: " . $e->getMessage());
        return false;
    }
}

// Skip payment for trial users (optional)
function extendTrialPeriod($customerId, $days = 7) {
    global $db;
    
    try {
        $newTrialEnd = date('Y-m-d H:i:s', strtotime("+$days days"));
        
        $stmt = $db->prepare("
            UPDATE customers 
            SET trial_ends_at = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        return $stmt->execute(array($newTrialEnd, $customerId));
    } catch (Exception $e) {
        logError("Extend trial error: " . $e->getMessage());
        return false;
    }
}
?>