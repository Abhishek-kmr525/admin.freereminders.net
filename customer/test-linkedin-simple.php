<?php
// customer/test-linkedin-simple.php - Standalone test without authentication
session_start();

// Simple database connection (adjust these values for your setup)
try {
    $db = new PDO("mysql:host=localhost;dbname=linkedin_auto;charset=utf8mb4", 
                  "linkedin_auto", "linkedin_auto", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$results = [];
$error = '';
$success = '';

// For testing, use customer ID 4 (you can change this)
$testCustomerId = 7; // Change this to your customer ID

// Get LinkedIn token for testing
$linkedinToken = null;
try {
    $stmt = $db->prepare("
        SELECT access_token 
        FROM customer_linkedin_tokens 
        WHERE customer_id = ? 
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$testCustomerId]);
    $tokenData = $stmt->fetch();
    $linkedinToken = $tokenData['access_token'] ?? null;
} catch (Exception $e) {
    $error = "Error getting LinkedIn token: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_connection') {
        // Test LinkedIn API connection
        if (!$linkedinToken) {
            $error = 'No LinkedIn token found for customer ID: ' . $testCustomerId;
        } else {
            $ch = curl_init('https://api.linkedin.com/v2/userinfo');
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $linkedinToken,
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $userInfo = json_decode($response, true);
                $success = 'LinkedIn connection test successful! User: ' . ($userInfo['name'] ?? 'Unknown');
                $results['connection_test'] = true;
                $results['user_info'] = $userInfo;
            } else {
                $error = 'LinkedIn connection test failed. HTTP Code: ' . $httpCode . ' Response: ' . $response;
                $results['connection_test'] = false;
            }
        }
        
    } elseif ($action === 'generate_sample') {
        // Generate sample content
        $topic = $_POST['topic'] ?? 'technology';
        $style = $_POST['content_style'] ?? 'professional';
        
        $sampleContent = generateSampleContent($topic, $style);
        $results['generated_content'] = $sampleContent;
        $results['content_generated'] = true;
        
    } elseif ($action === 'publish_test') {
        // Test publishing
        $content = $_POST['content'] ?? '';
        
        if (empty($content)) {
            $error = 'No content to publish';
        } elseif (!$linkedinToken) {
            $error = 'LinkedIn token not found';
        } else {
            try {
                $publishResult = publishToLinkedIn($content, $linkedinToken);
                
                if ($publishResult['success']) {
                    $success = 'Test post published successfully to LinkedIn!';
                    $results['published'] = true;
                    $results['linkedin_post_id'] = $publishResult['post_id'];
                    
                    // Save test record to database
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO scheduled_posts (
                                automation_id, customer_id, content, scheduled_time, 
                                published_at, status, ai_model_used, linkedin_post_id, created_at, updated_at
                            ) VALUES (0, ?, ?, NOW(), NOW(), 'published', 'test', ?, NOW(), NOW())
                        ");
                        $stmt->execute([$testCustomerId, $content, $publishResult['post_id']]);
                    } catch (Exception $e) {
                        // Ignore database save errors for this test
                    }
                    
                } else {
                    $error = 'Publishing failed: ' . $publishResult['error'];
                    $results['published'] = false;
                }
                
            } catch (Exception $e) {
                $error = 'Publishing error: ' . $e->getMessage();
            }
        }
    }
}

/**
 * Generate sample content for testing
 */
function generateSampleContent($topic, $style) {
    $templates = [
        'professional' => "🚀 The landscape of $topic continues to evolve at an unprecedented pace.

As professionals in this space, staying ahead requires continuous learning and strategic thinking. Here are key insights I've been reflecting on:

✅ Innovation thrives when we embrace change
✅ Collaboration drives breakthrough results  
✅ Data-driven decisions lead to better outcomes

The future belongs to those who adapt quickly and think creatively.

What's your experience with $topic? I'd love to hear your thoughts!

#$topic #Innovation #ProfessionalGrowth #LinkedIn",

        'casual' => "Hey LinkedIn! 👋

Just had some thoughts about $topic that I wanted to share with you all.

You know what I've learned? The best insights come from the most unexpected places. Whether you're just starting out or you've been in the game for years, there's always something new to discover.

Here's what's on my mind:
• Keep asking questions
• Stay curious about everything
• Don't be afraid to experiment

What's one thing about $topic that surprised you recently? Drop it in the comments! 👇

#$topic #Learning #Community",

        'educational' => "📚 Let's dive into $topic - here's what you need to know:

Understanding $topic is crucial in today's digital landscape. Here's a breakdown of the key concepts:

🔍 Core Principles:
• Focus on user value and experience
• Leverage data for informed decisions
• Build sustainable, scalable solutions

💡 Best Practices:
• Start with clear objectives
• Test and iterate frequently  
• Measure what matters

🎯 Action Steps:
1. Assess your current approach
2. Identify improvement opportunities
3. Implement changes systematically

Save this post for reference! What questions do you have about $topic?

#$topic #Education #BestPractices #Learning"
    ];
    
    return $templates[$style] ?? $templates['professional'];
}

/**
 * Publish content to LinkedIn
 */
function publishToLinkedIn($content, $accessToken) {
    try {
        // Get LinkedIn profile ID
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
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Could not get user info: HTTP ' . $httpCode];
        }
        
        $userData = json_decode($response, true);
        $profileId = $userData['sub'] ?? null;
        
        if (!$profileId) {
            return ['success' => false, 'error' => 'Could not extract profile ID'];
        }
        
        // Prepare LinkedIn post data
        $postData = [
            'author' => "urn:li:person:$profileId",
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $content
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
                'Authorization: Bearer ' . $accessToken,
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
            return ['success' => false, 'error' => "LinkedIn API error ($httpCode): $response"];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Publishing Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0">
                        <i class="fab fa-linkedin me-2"></i>LinkedIn Publishing Test
                    </h2>
                    <p class="mb-0 opacity-75">Test Customer ID: <?php echo $testCustomerId; ?></p>
                </div>
                
                <div class="card-body">
                    <!-- Status -->
                    <div class="alert <?php echo $linkedinToken ? 'alert-success' : 'alert-danger'; ?>">
                        <i class="fab fa-linkedin me-2"></i>
                        LinkedIn Token: <?php echo $linkedinToken ? 'Found' : 'Not Found'; ?>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Test 1: Connection Test -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Test 1: LinkedIn API Connection</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="test_connection">
                        <button type="submit" class="btn btn-primary" <?php echo $linkedinToken ? '' : 'disabled'; ?>>
                            <i class="fas fa-plug me-2"></i>Test Connection
                        </button>
                    </form>
                    
                    <?php if (isset($results['connection_test'])): ?>
                        <div class="mt-3">
                            <strong>Result:</strong>
                            <span class="badge <?php echo $results['connection_test'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $results['connection_test'] ? 'Success' : 'Failed'; ?>
                            </span>
                            
                            <?php if (isset($results['user_info'])): ?>
                                <div class="mt-2">
                                    <small>User: <?php echo htmlspecialchars($results['user_info']['name'] ?? 'Unknown'); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Test 2: Content Generation -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Test 2: Content Generation</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_sample">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Topic</label>
                                <input type="text" name="topic" class="form-control" 
                                       value="<?php echo $_POST['topic'] ?? 'artificial intelligence'; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Style</label>
                                <select name="content_style" class="form-select">
                                    <option value="professional">Professional</option>
                                    <option value="casual">Casual</option>
                                    <option value="educational">Educational</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-magic me-2"></i>Generate Sample Content
                        </button>
                    </form>
                    
                    <?php if (isset($results['generated_content'])): ?>
                        <div class="mt-3">
                            <label class="form-label">Generated Content:</label>
                            <textarea class="form-control" rows="8" readonly><?php echo htmlspecialchars($results['generated_content']); ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Test 3: Publish to LinkedIn -->
            <?php if (isset($results['generated_content'])): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Test 3: Publish to LinkedIn (LIVE TEST!)</h5>
                    <small>This will actually post to your LinkedIn profile</small>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="publish_test">
                        
                        <div class="mb-3">
                            <label class="form-label">Content to Publish</label>
                            <textarea name="content" class="form-control" rows="8"><?php echo htmlspecialchars($results['generated_content']); ?></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will post to your actual LinkedIn profile!
                        </div>
                        
                        <button type="submit" class="btn btn-warning" <?php echo $linkedinToken ? '' : 'disabled'; ?>>
                            <i class="fab fa-linkedin me-2"></i>Publish to LinkedIn
                        </button>
                    </form>
                    
                    <?php if (isset($results['published'])): ?>
                        <div class="mt-3">
                            <strong>Publishing Result:</strong>
                            <span class="badge <?php echo $results['published'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $results['published'] ? 'Success' : 'Failed'; ?>
                            </span>
                            
                            <?php if (isset($results['linkedin_post_id'])): ?>
                                <div class="mt-2">
                                    <small>LinkedIn Post ID: <?php echo htmlspecialchars($results['linkedin_post_id']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

</body>
</html>
