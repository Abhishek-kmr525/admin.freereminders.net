<?php


// Prevent direct browser access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from command line');
}

// Set base directory
define('BASE_DIR', dirname(__DIR__));
define('CRON_DIR', __DIR__);

// Start output buffering and set error reporting
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', CRON_DIR . '/cron_errors.log');

// Set execution time limit and memory limit
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Include required files with correct paths
require_once BASE_DIR . '/config/database-config.php';

// Check if automation class exists, if not include it
if (!class_exists('LinkedInAutomationSystem')) {
    require_once BASE_DIR . '/api/automation.php';
}

// Log function
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = CRON_DIR . '/cron.log';
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

// Lock file to prevent multiple instances
$lockFile = CRON_DIR . '/publisher.lock';
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    if (time() - $lockTime < 300) { // 5 minutes
        logMessage("Another instance is already running. Exiting.");
        exit(0);
    } else {
        // Remove stale lock file
        unlink($lockFile);
        logMessage("Removed stale lock file");
    }
}

// Create lock file
file_put_contents($lockFile, getmypid());

// Start cron job
logMessage("=== LinkedIn Post Publisher Started ===");

try {
    // Verify database connection
    if (!isset($db) || !$db) {
        throw new Exception("Database connection not available");
    }
    
    // Test database connection
    $db->query('SELECT 1');
    logMessage("Database connection verified");
    
    // Initialize automation system
    $automation = new LinkedInAutomationSystem($db);
    
    // Get current time for logging
    $startTime = microtime(true);
    
    // Get pending posts count before processing
    $pendingCount = getPendingPostsCount($db);
    logMessage("Found $pendingCount pending posts to process");
    
    // Publish pending posts
    $publishedCount = $automation->publishPendingPosts();
    
    // Calculate execution time
    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    
    // Log results
    if ($publishedCount > 0) {
        logMessage("Successfully processed $publishedCount posts in {$executionTime}ms");
        
        // Update automation statistics
        updateAutomationStats($db);
        
        // Send notifications if enabled
        sendSuccessNotifications($db, $publishedCount);
        
    } else {
        logMessage("No posts ready for publishing at this time");
    }
    
    // Retry failed posts
    $retriedCount = retryFailedPosts($db);
    if ($retriedCount > 0) {
        logMessage("Retried $retriedCount failed posts");
    }
    
    // Mark completed automations
    markCompletedAutomations($db);
    
    // Perform health check
    performHealthCheck($db);
    
    // Clean up old logs (every 100 runs)
    if (rand(1, 100) === 1) {
        cleanupLogs();
    }
    
    logMessage("=== Cron job completed successfully ===");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    
    // Send error notification to admin
    notifyAdminOfError($e);
    
} finally {
    // Always remove lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

/**
 * Get count of pending posts
 */
function getPendingPostsCount($db) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM scheduled_posts 
            WHERE status = 'pending' 
            AND scheduled_time <= NOW()
        ");
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        logMessage("Error getting pending posts count: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

/**
 * Update automation statistics
 */
function updateAutomationStats($db) {
    try {
        $stmt = $db->prepare("
            UPDATE automations a
            SET 
                total_posts_generated = (
                    SELECT COUNT(*) FROM scheduled_posts 
                    WHERE automation_id = a.id
                ),
                successful_posts = (
                    SELECT COUNT(*) FROM scheduled_posts 
                    WHERE automation_id = a.id AND status = 'published'
                ),
                failed_posts = (
                    SELECT COUNT(*) FROM scheduled_posts 
                    WHERE automation_id = a.id AND status = 'failed'
                ),
                last_generated_at = (
                    SELECT MAX(published_at) FROM scheduled_posts 
                    WHERE automation_id = a.id AND status = 'published'
                ),
                updated_at = NOW()
            WHERE status IN ('active', 'paused')
        ");
        
        $stmt->execute();
        $updatedCount = $stmt->rowCount();
        if ($updatedCount > 0) {
            logMessage("Updated statistics for $updatedCount automations");
        }
        
    } catch (Exception $e) {
        logMessage("Failed to update automation stats: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Mark automations as completed when all posts are done
 */
function markCompletedAutomations($db) {
    try {
        $stmt = $db->prepare("
            UPDATE automations 
            SET status = 'completed', updated_at = NOW()
            WHERE status = 'active' 
            AND id NOT IN (
                SELECT DISTINCT automation_id 
                FROM scheduled_posts 
                WHERE status = 'pending'
            )
            AND (
                SELECT COUNT(*) FROM scheduled_posts 
                WHERE automation_id = automations.id
            ) > 0
        ");
        
        $stmt->execute();
        $completedCount = $stmt->rowCount();
        
        if ($completedCount > 0) {
            logMessage("Marked $completedCount automations as completed");
        }
        
    } catch (Exception $e) {
        logMessage("Error marking completed automations: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Retry failed posts with exponential backoff
 */
function retryFailedPosts($db) {
    try {
        // Get failed posts that can be retried
        $stmt = $db->prepare("
            SELECT sp.*, a.customer_id, c.name as customer_name
            FROM scheduled_posts sp
            JOIN automations a ON sp.automation_id = a.id
            JOIN customers c ON sp.customer_id = c.id
            WHERE sp.status = 'failed' 
            AND sp.retry_count < 3
            AND sp.attempted_at < DATE_SUB(NOW(), INTERVAL POW(2, sp.retry_count) * 10 MINUTE)
            ORDER BY sp.scheduled_time ASC
            LIMIT 3
        ");
        
        $stmt->execute();
        $failedPosts = $stmt->fetchAll();
        
        $retriedCount = 0;
        
        foreach ($failedPosts as $post) {
            try {
                // Reset status to pending for retry
                $updateStmt = $db->prepare("
                    UPDATE scheduled_posts 
                    SET status = 'pending', 
                        retry_count = retry_count + 1,
                        error_message = CONCAT(IFNULL(error_message, ''), ' | Retry attempt: ', retry_count + 1),
                        updated_at = NOW(),
                        attempted_at = NULL
                    WHERE id = ?
                ");
                $updateStmt->execute([$post['id']]);
                
                logMessage("Retrying failed post ID: {$post['id']} (attempt #{$post['retry_count']})");
                $retriedCount++;
                
                // Add activity log
                $activityStmt = $db->prepare("
                    INSERT INTO customer_activity (customer_id, action, details, created_at)
                    VALUES (?, 'post_retry', ?, NOW())
                ");
                $activityStmt->execute([
                    $post['customer_id'],
                    "Retrying failed post (attempt #{$post['retry_count']}): " . substr($post['content'], 0, 50) . "..."
                ]);
                
                // Small delay to prevent rate limiting
                sleep(1);
                
            } catch (Exception $e) {
                logMessage("Failed to retry post ID {$post['id']}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        return $retriedCount;
        
    } catch (Exception $e) {
        logMessage("Error in retry failed posts: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

/**
 * Send success notifications
 */
function sendSuccessNotifications($db, $publishedCount) {
    try {
        // Check if notifications are enabled
        $stmt = $db->prepare("
            SELECT setting_value 
            FROM admin_settings 
            WHERE setting_key = 'enable_email_notifications'
        ");
        $stmt->execute();
        $enableNotifications = $stmt->fetchColumn();
        
        if ($enableNotifications !== 'true') {
            return;
        }
        
        // Get customers who had posts published recently
        $stmt = $db->prepare("
            SELECT DISTINCT c.id, c.email, c.name, COUNT(sp.id) as posts_published
            FROM customers c
            JOIN scheduled_posts sp ON c.id = sp.customer_id
            WHERE sp.status = 'published' 
            AND sp.published_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            GROUP BY c.id, c.email, c.name
            HAVING posts_published > 0
        ");
        
        $stmt->execute();
        $customers = $stmt->fetchAll();
        
        foreach ($customers as $customer) {
            sendEmailNotification($customer, $customer['posts_published']);
        }
        
    } catch (Exception $e) {
        logMessage("Error sending notifications: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Send email notification
 */
function sendEmailNotification($customer, $postCount) {
    try {
        $subject = "LinkedIn Posts Published Successfully";
        $message = "Hi {$customer['name']},\n\n";
        $message .= "Great news! $postCount of your scheduled LinkedIn posts have been published successfully.\n\n";
        $message .= "You can view your posts and analytics in your dashboard at: https://postautomator.com/customer/dashboard.php\n\n";
        $message .= "Best regards,\nLinkedIn Automation Team";
        
        $headers = [
            'From: noreply@postautomator.com',
            'Reply-To: support@postautomator.com',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PostAutomator v1.0'
        ];
        
        if (mail($customer['email'], $subject, $message, implode("\r\n", $headers))) {
            logMessage("Success notification sent to {$customer['email']}");
        } else {
            logMessage("Failed to send notification to {$customer['email']}", 'WARNING');
        }
        
    } catch (Exception $e) {
        logMessage("Error sending email to {$customer['email']}: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Notify admin of critical errors
 */
function notifyAdminOfError($exception) {
    try {
        $adminEmail = 'admin@postautomator.com'; // Update with your admin email
        $subject = 'Critical Error - LinkedIn Automation Cron Job';
        $message = "Critical error occurred in LinkedIn automation cron job:\n\n";
        $message .= "Server: " . gethostname() . "\n";
        $message .= "Time: " . date('Y-m-d H:i:s T') . "\n";
        $message .= "File: " . __FILE__ . "\n\n";
        $message .= "Error Details:\n";
        $message .= "Message: {$exception->getMessage()}\n";
        $message .= "File: {$exception->getFile()}\n";
        $message .= "Line: {$exception->getLine()}\n\n";
        $message .= "Stack Trace:\n{$exception->getTraceAsString()}\n\n";
        $message .= "Please check the server logs and resolve this issue immediately.";
        
        $headers = [
            'From: cron@postautomator.com',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Priority: 1',
            'X-Mailer: PostAutomator Cron v1.0'
        ];
        
        mail($adminEmail, $subject, $message, implode("\r\n", $headers));
        logMessage("Error notification sent to admin");
        
    } catch (Exception $e) {
        logMessage("Failed to send admin notification: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Perform system health check
 */
function performHealthCheck($db) {
    $issues = [];
    
    try {
        // Check database connection
        $db->query('SELECT 1');
        
        // Check for stuck posts (pending for more than 2 hours)
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM scheduled_posts 
            WHERE status = 'pending' 
            AND scheduled_time < DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ");
        $stmt->execute();
        $stuckPosts = $stmt->fetchColumn();
        
        if ($stuckPosts > 0) {
            $issues[] = "$stuckPosts posts are stuck in pending status for over 2 hours";
        }
        
        // Check for high failure rate in last 24 hours
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed
            FROM scheduled_posts 
            WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            OR published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result && $result['total'] > 10) {
            $failureRate = ($result['failed'] / $result['total']) * 100;
            if ($failureRate > 20) {
                $issues[] = "High failure rate: " . round($failureRate, 1) . "% in the last 24 hours";
            }
        }
        
        // Check API key configuration
        $stmt = $db->prepare("
            SELECT setting_key, setting_value 
            FROM admin_settings 
            WHERE setting_key IN ('gemini_api_key', 'openai_api_key')
            AND (setting_value IS NOT NULL AND setting_value != '')
        ");
        $stmt->execute();
        $apiKeys = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (empty($apiKeys)) {
            $issues[] = "No AI API keys configured";
        }
        
        // Check disk space in logs directory
        $freeBytes = disk_free_space(CRON_DIR);
        $freeMB = $freeBytes / 1024 / 1024;
        if ($freeMB < 100) {
            $issues[] = "Low disk space: " . round($freeMB, 1) . "MB free";
        }
        
        if (count($issues) > 0) {
            logMessage("Health check issues: " . implode('; ', $issues), 'WARNING');
            
            // If critical issues, notify admin
            if ($stuckPosts > 10 || (isset($failureRate) && $failureRate > 50)) {
                $exception = new Exception("Health check found critical issues: " . implode('; ', $issues));
                notifyAdminOfError($exception);
            }
            
            return false;
        } else {
            // Only log successful health check occasionally
            if (rand(1, 20) === 1) {
                logMessage("Health check passed - system running normally");
            }
            return true;
        }
        
    } catch (Exception $e) {
        logMessage("Health check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Clean up old log files
 */
function cleanupLogs() {
    $logFiles = [
        CRON_DIR . '/cron.log',
        CRON_DIR . '/cron_errors.log'
    ];
    
    foreach ($logFiles as $logFile) {
        try {
            if (file_exists($logFile)) {
                $fileSize = filesize($logFile);
                
                // If file is larger than 5MB, keep only last 1MB
                if ($fileSize > 5 * 1024 * 1024) {
                    $content = file_get_contents($logFile);
                    $lines = explode("\n", $content);
                    
                    // Keep last 2000 lines
                    if (count($lines) > 2000) {
                        $truncated = array_slice($lines, -2000);
                        file_put_contents($logFile, implode("\n", $truncated));
                        logMessage("Truncated log file: " . basename($logFile) . " (was " . round($fileSize/1024/1024, 2) . "MB)");
                    }
                }
            }
        } catch (Exception $e) {
            logMessage("Error cleaning log file $logFile: " . $e->getMessage(), 'ERROR');
        }
    }
    
    logMessage("Log cleanup completed");
}

// End output buffering
ob_end_flush();

exit(0);
?>