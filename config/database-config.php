

<?php
// config/database-config.php - Complete Fixed Version
// Ensure debug handlers are available for scripts that include this config
if (file_exists(__DIR__ . '/../includes/debug.php')) {
    require_once __DIR__ . '/../includes/debug.php';
}
if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie parameters for production
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('FORCE_HTTPS') === '1';
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => parse_url((getenv('SITE_URL') ?: 'http://localhost'), PHP_URL_HOST) ?: '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
    }

    session_start();
}

// Configure PHP error logging (production safe defaults)
$logFile = __DIR__ . '/../logs/php-error.log';
if (!file_exists($logFile)) {
    // Try to create the logs directory if missing
    @mkdir(dirname($logFile), 0755, true);
    @touch($logFile);
    @chmod($logFile, 0664);
}
ini_set('display_errors', getenv('DISPLAY_ERRORS') === '1' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

// Database Configuration - read from environment variables (production safe)
class DatabaseConfig {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Fetch from environment variables, fallback to sane defaults for local dev
        $this->host = getenv('DB_HOST') ?: '127.0.0.1';
        $this->db_name = getenv('DB_NAME') ?: 'linkedin_auto';
        $this->username = getenv('DB_USER') ?: 'linkedin_auto';
        $this->password = getenv('DB_PASS') ?: 'linkedin_auto';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";

            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);

        } catch(PDOException $exception) {
            // Log and rethrow so calling code can handle gracefully
            error_log("Database Connection Error: " . $exception->getMessage());
            throw $exception;
        }
        return $this->conn;
    }
}

// Initialize database connection
try {
    $database = new DatabaseConfig();
    $db = $database->getConnection();
} catch (Exception $e) {
    // On production, avoid exposing internals — ensure errors are logged and show friendly page
    http_response_code(500);
    // You can render a friendly error page here or include a lightweight message
    error_log('Fatal: could not connect to database: ' . $e->getMessage());
    echo "<h1>Service temporarily unavailable</h1>";
    exit();
}

// Site Configuration - Update with your actual domain
define('SITE_URL', 'https://postautomator.com'); // Change this to your domain
define('SITE_NAME', 'LinkedIn Automation Tool');
define('ADMIN_EMAIL', 'admin@nexloadtrucking.com');

// Multi-country settings
define('SUPPORTED_COUNTRIES', ['us', 'in']);
define('DEFAULT_COUNTRY', 'us');

// Currency settings
$CURRENCY_SETTINGS = [
    'us' => ['currency' => 'USD', 'symbol' => '$', 'name' => 'United States'],
    'in' => ['currency' => 'INR', 'symbol' => '₹', 'name' => 'India']
];

// Payment Gateway Settings
define('PAYMENT_GATEWAYS', [
    'us' => 'stripe',
    'in' => 'razorpay'
]);

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('TRIAL_PERIOD_DAYS', 14);
define('MAX_LOGIN_ATTEMPTS', 5);

// Enhanced session check function
function isCustomerLoggedIn() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['customer_id']) || !isset($_SESSION['customer_email'])) {
        return false;
    }
    
    // Check session timeout (optional)
    if (defined('SESSION_TIMEOUT') && isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            return false;
        }
    }
    
    return true;
}

// Enhanced customer login requirement
function requireCustomerLogin($redirectUrl = null) {
    if (!isCustomerLoggedIn()) {
        $redirect = $redirectUrl ?: $_SERVER['REQUEST_URI'];
        header('Location: login.php?redirect=' . urlencode($redirect));
        exit();
    }
}

function getCustomerCountry() {
    if (isset($_SESSION['customer_country'])) {
        return $_SESSION['customer_country'];
    }
    
    // Default to US if not set
    return DEFAULT_COUNTRY;
}

function getCurrencySettings($country = null) {
    global $CURRENCY_SETTINGS;
    
    if (!$country) {
        $country = getCustomerCountry();
    }
    
    return $CURRENCY_SETTINGS[$country] ?? $CURRENCY_SETTINGS[DEFAULT_COUNTRY];
}

function formatPrice($amount, $country = null) {
    $currency = getCurrencySettings($country);
    return $currency['symbol'] . number_format($amount, 2);
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function logCustomerActivity($customerId, $action, $details = '') {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO customer_activity_logs (customer_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $customerId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Error logging customer activity: " . $e->getMessage());
    }
}

function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function redirectTo($url) {
    header("Location: $url");
    exit();
}

// Get customer details
function getCustomerDetails($customerId) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT id, name, email, country, phone, status, subscription_plan, subscription_status, 
                   trial_ends_at, subscription_ends_at, created_at, updated_at, oauth_provider, oauth_provider_id, password
            FROM customers 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Get customer details error: " . $e->getMessage());
        return false;
    }
}

// Get customer usage statistics
function getCustomerUsageStats($customerId = null) {
    global $db;
    
    $id = $customerId ?: ($_SESSION['customer_id'] ?? null);
    
    if (!$id) {
        return false;
    }
    
    try {
        // Get automation count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM customer_automations WHERE customer_id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $automations = $stmt->fetch()['count'];
        
        // Get posts this month
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM customer_generated_posts 
            WHERE customer_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $stmt->execute([$id]);
        $postsThisMonth = $stmt->fetch()['count'];
        
        // Get total posts
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM customer_generated_posts WHERE customer_id = ?");
        $stmt->execute([$id]);
        $totalPosts = $stmt->fetch()['count'];
        
        return [
            'automations' => $automations,
            'posts_this_month' => $postsThisMonth,
            'total_posts' => $totalPosts
        ];
        
    } catch (Exception $e) {
        error_log("Get customer usage stats error: " . $e->getMessage());
        return false;
    }
}

// Check if customer is in trial period
function isTrialActive($customerId = null) {
    global $db;
    
    $id = $customerId ?: ($_SESSION['customer_id'] ?? null);
    
    if (!$id) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT subscription_status, trial_ends_at 
            FROM customers 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            return false;
        }
        
        if ($customer['subscription_status'] === 'trial') {
            return strtotime($customer['trial_ends_at']) > time();
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Check trial status error: " . $e->getMessage());
        return false;
    }
}

// Get days remaining in trial
function getTrialDaysRemaining($customerId = null) {
    global $db;
    
    $id = $customerId ?: ($_SESSION['customer_id'] ?? null);
    
    if (!$id) {
        return 0;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT trial_ends_at 
            FROM customers 
            WHERE id = ? AND status = 'active' AND subscription_status = 'trial'
        ");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        
        if ($customer && $customer['trial_ends_at']) {
            $trialEnd = strtotime($customer['trial_ends_at']);
            $now = time();
            
            if ($trialEnd > $now) {
                return ceil(($trialEnd - $now) / (24 * 60 * 60));
            }
        }
        
        return 0;
        
    } catch (Exception $e) {
        error_log("Get trial days remaining error: " . $e->getMessage());
        return 0;
    }
}

// Update last activity
function updateLastActivity($customerId) {
    global $db;
    
    try {
        $stmt = $db->prepare("UPDATE customers SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$customerId]);
        return true;
        
    } catch (Exception $e) {
        error_log("Update last activity error: " . $e->getMessage());
        return false;
    }
}

// Check if customer has specific permission
function hasPermission($permission) {
    if (!isCustomerLoggedIn()) {
        return false;
    }
    
    // For now, all active customers have basic permissions
    // You can extend this for role-based permissions
    $subscriptionStatus = $_SESSION['subscription_status'] ?? 'trial';
    
    switch ($permission) {
        case 'create_automation':
            return in_array($subscriptionStatus, ['trial', 'active']);
        case 'unlimited_posts':
            return $subscriptionStatus === 'active';
        case 'analytics':
            return in_array($subscriptionStatus, ['trial', 'active']);
        case 'multiple_automations':
            return in_array($subscriptionStatus, ['active']);
        default:
            return true;
    }
}

// Clean expired sessions (call this periodically)
function cleanExpiredSessions() {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM customer_sessions WHERE expires_at < NOW()");
        $stmt->execute();
        
        $cleaned = $stmt->rowCount();
        if ($cleaned > 0) {
            error_log("Cleaned $cleaned expired sessions");
        }
        
        return $cleaned;
        
    } catch (Exception $e) {
        error_log("Clean expired sessions error: " . $e->getMessage());
        return false;
    }
}

// Security function to validate session token
function validateSessionToken($token) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT cs.customer_id, c.status 
            FROM customer_sessions cs
            JOIN customers c ON cs.customer_id = c.id
            WHERE cs.session_token = ? AND cs.expires_at > NOW() AND c.status = 'active'
        ");
        $stmt->execute([$token]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Validate session token error: " . $e->getMessage());
        return false;
    }
}

// Function to safely redirect
function safeRedirect($url, $allowedDomains = []) {
    // Default allowed domains
    $defaultDomains = [
        parse_url(SITE_URL, PHP_URL_HOST)
    ];
    
    $allowedDomains = array_merge($defaultDomains, $allowedDomains);
    
    // Parse the URL
    $parsedUrl = parse_url($url);
    
    // If it's a relative URL, it's safe
    if (!isset($parsedUrl['host'])) {
        header("Location: $url");
        exit();
    }
    
    // Check if the domain is allowed
    if (in_array($parsedUrl['host'], $allowedDomains)) {
        header("Location: $url");
        exit();
    }
    
    // If not safe, redirect to dashboard
    header('Location: dashboard.php');
    exit();
}

// Enhanced error logging with context
function logError($message, $context = [], $file = 'error.log') {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logMessage = "[$timestamp] $message";
    
    if ($contextStr) {
        $logMessage .= " | Context: $contextStr";
    }
    
    // Start session if not already started to access session variables
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isCustomerLoggedIn()) {
        $logMessage .= " | Customer: {$_SESSION['customer_id']} ({$_SESSION['customer_email']})";
    }
    
    $logMessage .= " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $logMessage .= " | User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    $logMessage .= PHP_EOL;
    
    $logPath = __DIR__ . '/../logs/' . $file;
    
    if (!is_dir(dirname($logPath))) {
        mkdir(dirname($logPath), 0755, true);
    }
    
    file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
}

// Set timezone
date_default_timezone_set('UTC');

// Create password_resets table if it doesn't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `customer_id` int(11) NOT NULL,
            `token` varchar(255) NOT NULL,
            `expires_at` timestamp NOT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_customer` (`customer_id`),
            KEY `token_index` (`token`),
            FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    // Table might already exist, ignore error
}
?>