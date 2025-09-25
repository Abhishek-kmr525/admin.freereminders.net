<?php
// api/automation.php - Main automation handler
require_once '../config/database-config.php';
require_once '../config/oauth-config.php';
require_once 'ai_service.php';

class LinkedInAutomationSystem {
    private $db;
    private $aiService;
    
    public function __construct($database) {
        $this->db = $database;
        $this->aiService = new AIService();
    }
    
    /**
     * Create a new automation
     */
    public function createAutomation($customerId, $automationData) {
        try {
            $this->db->beginTransaction();
            
            // Insert automation
            $stmt = $this->db->prepare("
                INSERT INTO automations (
                    customer_id, name, topic, ai_model, duration_days, 
                    post_time, content_style, instructions, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            
            $stmt->execute([
                $customerId,
                $automationData['name'],
                $automationData['topic'],
                $automationData['ai_model'],
                $automationData['duration'],
                $automationData['post_time'],
                $automationData['content_style'],
                $automationData['instructions'] ?? ''
            ]);
            
            $automationId = $this->db->lastInsertId();
            
            // Generate and schedule posts
            $this->generateScheduledPosts($automationId, $automationData);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'automation_id' => $automationId,
                'message' => 'Automation created successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Automation creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create automation: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate scheduled posts for an automation
     */
    private function generateScheduledPosts($automationId, $automationData) {
        // Get automation details
        $stmt = $this->db->prepare("SELECT * FROM automations WHERE id = ?");
        $stmt->execute([$automationId]);
        $automation = $stmt->fetch();
        
        if (!$automation) {
            throw new Exception("Automation not found");
        }
        
        // Generate posts for each day
        for ($day = 0; $day < $automationData['duration']; $day++) {
            $scheduledTime = date('Y-m-d H:i:s', strtotime("+$day days " . $automationData['post_time']));
            
            // Generate AI content
            $contentPrompt = $this->buildContentPrompt($automationData, $day + 1);
            $aiContent = $this->aiService->generateContent($automationData['ai_model'], $contentPrompt);
            
            // Insert scheduled post
            $stmt = $this->db->prepare("
                INSERT INTO scheduled_posts (
                    automation_id, customer_id, content, scheduled_time, 
                    status, ai_model_used, created_at, updated_at
                ) VALUES (?, ?, ?, ?, 'pending', ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $automationId,
                $automation['customer_id'],
                $aiContent,
                $scheduledTime,
                $automationData['ai_model']
            ]);
        }
    }
    
    /**
     * Build content generation prompt
     */
    private function buildContentPrompt($automationData, $dayNumber) {
        $basePrompt = "Create a LinkedIn post about {$automationData['topic']}. ";
        $basePrompt .= "This is post {$dayNumber} of {$automationData['duration']} in a series. ";
        $basePrompt .= "Style: {$automationData['content_style']}. ";
        
        if (!empty($automationData['instructions'])) {
            $basePrompt .= "Additional instructions: {$automationData['instructions']}. ";
        }
        
        $basePrompt .= "Requirements:
        - Write an engaging LinkedIn post (150-300 words)
        - Include relevant hashtags
        - Make it professional and valuable
        - Ensure it's unique and not repetitive
        - Include a call-to-action if appropriate
        - Make sure it provides value to the audience";
        
        return $basePrompt;
    }
    
    /**
     * Get customer automations
     */
    public function getCustomerAutomations($customerId) {
        $stmt = $this->db->prepare("
            SELECT a.*, 
                   COUNT(sp.id) as total_posts,
                   COUNT(CASE WHEN sp.status = 'published' THEN 1 END) as published_posts,
                   COUNT(CASE WHEN sp.status = 'pending' THEN 1 END) as pending_posts
            FROM automations a 
            LEFT JOIN scheduled_posts sp ON a.id = sp.automation_id 
            WHERE a.customer_id = ? 
            GROUP BY a.id 
            ORDER BY a.created_at DESC
        ");
        
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Publish pending posts
     */
    public function publishPendingPosts() {
        $stmt = $this->db->prepare("
            SELECT sp.*, a.customer_id, c.name as customer_name
            FROM scheduled_posts sp 
            JOIN automations a ON sp.automation_id = a.id
            JOIN customers c ON sp.customer_id = c.id
            WHERE sp.status = 'pending' 
            AND sp.scheduled_time <= NOW()
            ORDER BY sp.scheduled_time ASC
            LIMIT 10
        ");
        
        $stmt->execute();
        $pendingPosts = $stmt->fetchAll();
        
        foreach ($pendingPosts as $post) {
            try {
                $result = $this->publishToLinkedIn($post);
                
                if ($result['success']) {
                    // Update post status
                    $updateStmt = $this->db->prepare("
                        UPDATE scheduled_posts 
                        SET status = 'published', published_at = NOW(), linkedin_post_id = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$result['post_id'], $post['id']]);
                    
                    // Log success
                    $this->logActivity($post['customer_id'], 'post_published', 
                        "Post published successfully: " . substr($post['content'], 0, 50) . "...");
                        
                } else {
                    // Mark as failed
                    $updateStmt = $this->db->prepare("
                        UPDATE scheduled_posts 
                        SET status = 'failed', error_message = ?, attempted_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$result['error'], $post['id']]);
                    
                    // Log error
                    $this->logActivity($post['customer_id'], 'post_failed', 
                        "Post publication failed: " . $result['error']);
                }
                
            } catch (Exception $e) {
                error_log("Post publication error: " . $e->getMessage());
            }
        }
        
        return count($pendingPosts);
    }
    
    /**
     * Publish content to LinkedIn
     */
    private function publishToLinkedIn($post) {
        try {
            // Get customer's LinkedIn token
            $stmt = $this->db->prepare("
                SELECT access_token FROM customer_linkedin_tokens 
                WHERE customer_id = ? ORDER BY updated_at DESC LIMIT 1
            ");
            $stmt->execute([$post['customer_id']]);
            $tokenData = $stmt->fetch();
            
            if (!$tokenData) {
                return ['success' => false, 'error' => 'LinkedIn token not found'];
            }
            
            // Get user's LinkedIn profile ID
            $profileId = $this->getLinkedInProfileId($tokenData['access_token']);
            
            if (!$profileId) {
                return ['success' => false, 'error' => 'Could not get LinkedIn profile ID'];
            }
            
            // Prepare LinkedIn API request
            $postData = [
                'author' => "urn:li:person:$profileId",
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $post['content']
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];
            
            // Make API call to LinkedIn
            $ch = curl_init('https://api.linkedin.com/v2/ugcPosts');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $tokenData['access_token'],
                    'Content-Type: application/json',
                    'X-Restli-Protocol-Version: 2.0.0'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'error' => 'cURL error: ' . $error];
            }
            
            if ($httpCode === 201) {
                $responseData = json_decode($response, true);
                $postId = $responseData['id'] ?? 'unknown';
                return ['success' => true, 'post_id' => $postId];
            } else {
                $errorResponse = json_decode($response, true);
                $errorMessage = $errorResponse['message'] ?? 'Unknown LinkedIn API error';
                return ['success' => false, 'error' => "LinkedIn API error ($httpCode): $errorMessage"];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get LinkedIn profile ID
     */
    private function getLinkedInProfileId($accessToken) {
        $ch = curl_init('https://api.linkedin.com/v2/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $userData = json_decode($response, true);
            return $userData['sub'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Log customer activity
     */
    private function logActivity($customerId, $action, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO customer_activity (customer_id, action, details, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$customerId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'system']);
        } catch (Exception $e) {
            error_log("Activity logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Get automation statistics
     */
    public function getAutomationStats($customerId) {
        $stats = [];
        
        // Active automations
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM automations 
            WHERE customer_id = ? AND status = 'active'
        ");
        $stmt->execute([$customerId]);
        $stats['active_automations'] = $stmt->fetchColumn();
        
        // Scheduled posts
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM scheduled_posts 
            WHERE customer_id = ? AND status = 'pending'
        ");
        $stmt->execute([$customerId]);
        $stats['scheduled_posts'] = $stmt->fetchColumn();
        
        // Published posts
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM scheduled_posts 
            WHERE customer_id = ? AND status = 'published'
        ");
        $stmt->execute([$customerId]);
        $stats['published_posts'] = $stmt->fetchColumn();
        
        // Success rate
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as successful
            FROM scheduled_posts 
            WHERE customer_id = ? AND status IN ('published', 'failed')
        ");
        $stmt->execute([$customerId]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            $stats['success_rate'] = round(($result['successful'] / $result['total']) * 100, 1);
        } else {
            $stats['success_rate'] = 100;
        }
        
        return $stats;
    }
    
    /**
     * Pause/Resume automation
     */
    public function toggleAutomation($automationId, $customerId, $action) {
        $status = ($action === 'pause') ? 'paused' : 'active';
        
        $stmt = $this->db->prepare("
            UPDATE automations 
            SET status = ?, updated_at = NOW() 
            WHERE id = ? AND customer_id = ?
        ");
        
        $result = $stmt->execute([$status, $automationId, $customerId]);
        
        if ($result) {
            $this->logActivity($customerId, "automation_$action", "Automation ID $automationId $action" . "d");
            return ['success' => true, 'message' => "Automation $action" . "d successfully"];
        } else {
            return ['success' => false, 'message' => "Failed to $action automation"];
        }
    }
    
    /**
     * Delete automation
     */
    public function deleteAutomation($automationId, $customerId) {
        try {
            $this->db->beginTransaction();
            
            // Delete scheduled posts
            $stmt = $this->db->prepare("
                DELETE FROM scheduled_posts 
                WHERE automation_id = ? AND customer_id = ?
            ");
            $stmt->execute([$automationId, $customerId]);
            
            // Delete automation
            $stmt = $this->db->prepare("
                DELETE FROM automations 
                WHERE id = ? AND customer_id = ?
            ");
            $result = $stmt->execute([$automationId, $customerId]);
            
            $this->db->commit();
            
            if ($result) {
                $this->logActivity($customerId, 'automation_deleted', "Automation ID $automationId deleted");
                return ['success' => true, 'message' => 'Automation deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Automation not found or access denied'];
            }
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Automation deletion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete automation'];
        }
    }
}

// AI Service Class
class AIService {
    private $geminiApiKey;
    private $openaiApiKey;
    
    public function __construct() {
        // Get API keys from database or environment
        $this->loadApiKeys();
    }
    
    /**
     * Load API keys from database
     */
    private function loadApiKeys() {
        global $db;
        
        try {
            $stmt = $db->prepare("
                SELECT setting_key, setting_value 
                FROM admin_settings 
                WHERE setting_key IN ('gemini_api_key', 'openai_api_key')
            ");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $this->geminiApiKey = $settings['gemini_api_key'] ?? '';
            $this->openaiApiKey = $settings['openai_api_key'] ?? '';
            
        } catch (Exception $e) {
            error_log("Failed to load AI API keys: " . $e->getMessage());
        }
    }
    
    /**
     * Generate content using specified AI model
     */
    public function generateContent($model, $prompt) {
        switch (strtolower($model)) {
            case 'gemini':
                return $this->generateWithGemini($prompt);
            case 'chatgpt':
            case 'openai':
                return $this->generateWithChatGPT($prompt);
            default:
                throw new Exception("Unsupported AI model: $model");
        }
    }
    
    /**
     * Generate content using Google Gemini
     */
    private function generateWithGemini($prompt) {
        if (empty($this->geminiApiKey)) {
            throw new Exception("Gemini API key not configured");
        }
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $this->geminiApiKey;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Gemini API cURL error: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Gemini API error: HTTP $httpCode - $response");
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        } else {
            throw new Exception("Invalid response from Gemini API");
        }
    }
    
    /**
     * Generate content using OpenAI ChatGPT
     */
    private function generateWithChatGPT($prompt) {
        if (empty($this->openaiApiKey)) {
            throw new Exception("OpenAI API key not configured");
        }
        
        $url = "https://api.openai.com/v1/chat/completions";
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1024,
            'temperature' => 0.7
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLOPT_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("OpenAI API cURL error: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("OpenAI API error: HTTP $httpCode - $response");
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return trim($result['choices'][0]['message']['content']);
        } else {
            throw new Exception("Invalid response from OpenAI API");
        }
    }
}

// API endpoint handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureSession();
    
    if (!isset($_SESSION['customer_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    $customerId = $_SESSION['customer_id'];
    $automation = new LinkedInAutomationSystem($db);
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'create':
            $result = $automation->createAutomation($customerId, $_POST);
            echo json_encode($result);
            break;
            
        case 'list':
            $automations = $automation->getCustomerAutomations($customerId);
            echo json_encode(['success' => true, 'automations' => $automations]);
            break;
            
        case 'stats':
            $stats = $automation->getAutomationStats($customerId);
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'toggle':
            $automationId = $_POST['automation_id'] ?? 0;
            $toggleAction = $_POST['toggle_action'] ?? 'pause';
            $result = $automation->toggleAutomation($automationId, $customerId, $toggleAction);
            echo json_encode($result);
            break;
            
        case 'delete':
            $automationId = $_POST['automation_id'] ?? 0;
            $result = $automation->deleteAutomation($automationId, $customerId);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
?>