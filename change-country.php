<?php
// change-country.php - Handle country switching
require_once 'config/database-config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $country = $input['country'] ?? '';
    
    if (in_array($country, SUPPORTED_COUNTRIES)) {
        $_SESSION['customer_country'] = $country;
        
        // Update user's country in database if logged in
        if (isCustomerLoggedIn()) {
            try {
                $stmt = $db->prepare("UPDATE customers SET country = ? WHERE id = ?");
                $stmt->execute([$country, $_SESSION['customer_id']]);
            } catch (Exception $e) {
                error_log("Country update error: " . $e->getMessage());
            }
        }
        
        echo json_encode(['success' => true, 'country' => $country]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid country']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>