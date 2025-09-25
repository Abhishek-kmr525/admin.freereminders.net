<?php
// includes/debug.php
// Centralized debug and error logging for the application.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only enable verbose display when explicitly requested via environment or session
$enableDisplay = false;
if (getenv('APP_DEBUG') === '1' || isset($_GET['debug']) || (isset($_SESSION['debug']) && $_SESSION['debug'])) {
    $enableDisplay = true;
}

// Set error reporting to the most verbose level for debugging
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', $enableDisplay ? '1' : '0');

// Debug log file
define('DEBUG_LOG_FILE', __DIR__ . '/../logs/debug.log');

function debug_log($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : '';
    $pid = getmypid();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $user = $_SESSION['customer_email'] ?? ($_SESSION['customer_name'] ?? 'guest');

    $logMessage = "[$timestamp] [PID:$pid] [IP:$ip] [USER:$user] $message";
    if ($contextStr) {
        $logMessage .= " | Context: $contextStr";
    }
    $logMessage .= PHP_EOL;

    $logPath = DEBUG_LOG_FILE;
    if (!is_dir(dirname($logPath))) {
        @mkdir(dirname($logPath), 0755, true);
    }

    @file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
}

function debug_error_handler($severity, $message, $file, $line) {
    // Respect error_reporting settings
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $level = php_error_level_to_string($severity);
    $msg = "PHP $level: $message in $file on line $line";
    debug_log($msg, ['severity' => $severity, 'file' => $file, 'line' => $line]);

    // Optionally display the error to the browser when debugging is enabled
    if (ini_get('display_errors')) {
        echo "<pre>" . htmlspecialchars($msg) . "</pre>";
    }

    // Don't execute PHP internal error handler
    return true;
}

function php_error_level_to_string($type) {
    switch ($type) {
        case E_ERROR: return 'E_ERROR';
        case E_WARNING: return 'E_WARNING';
        case E_PARSE: return 'E_PARSE';
        case E_NOTICE: return 'E_NOTICE';
        case E_CORE_ERROR: return 'E_CORE_ERROR';
        case E_CORE_WARNING: return 'E_CORE_WARNING';
        case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
        case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
        case E_USER_ERROR: return 'E_USER_ERROR';
        case E_USER_WARNING: return 'E_USER_WARNING';
        case E_USER_NOTICE: return 'E_USER_NOTICE';
        case E_STRICT: return 'E_STRICT';
        case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
        case E_DEPRECATED: return 'E_DEPRECATED';
        case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';
        default: return "UNKNOWN($type)";
    }
}

function debug_exception_handler($exception) {
    $msg = sprintf("Uncaught exception '%s' with message '%s' in %s:%s\nStack trace:\n%s",
        get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString());

    debug_log($msg);

    if (ini_get('display_errors')) {
        echo "<pre>" . htmlspecialchars($msg) . "</pre>";
    } else {
        // Generic message for users
        http_response_code(500);
        echo "An internal error occurred. If the problem persists, contact support.";
    }
}

function debug_shutdown_handler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $msg = sprintf("Fatal error: %s in %s on line %s", $error['message'], $error['file'], $error['line']);
        debug_log($msg, ['last_error' => $error]);
        if (ini_get('display_errors')) {
            echo "<pre>" . htmlspecialchars($msg) . "</pre>";
        }
    }
}

set_error_handler('debug_error_handler');
set_exception_handler('debug_exception_handler');
register_shutdown_function('debug_shutdown_handler');

// Helper to quickly turn debugging on for the session
function enable_session_debugging($on = true) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['debug'] = $on ? 1 : 0;
}

// Expose a simple function alias
function debug($message, $context = []) {
    debug_log($message, $context);
}

?>
