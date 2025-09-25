<?php
// api/ai_service.php - Production AI Service
class AIService {
    private $geminiApiKey;
    private $openaiApiKey;
    
    public function __construct() {
        $this->loadApiKeys();
    }
    
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
    
    private function generateWithGemini($prompt) {
        if (empty($this->geminiApiKey)) {
            return $this->generateSampleContent($prompt);
        }
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $this->geminiApiKey;
        
        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024
            ]
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                return $result['candidates'][0]['content']['parts'][0]['text'];
            }
        }
        
        error_log("Gemini API error: $httpCode - $response");
        return $this->generateSampleContent($prompt);
    }
    
    private function generateWithChatGPT($prompt) {
        if (empty($this->openaiApiKey)) {
            return $this->generateSampleContent($prompt);
        }
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 1024,
            'temperature' => 0.7
        ];
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                return trim($result['choices'][0]['message']['content']);
            }
        }
        
        error_log("OpenAI API error: $httpCode - $response");
        return $this->generateSampleContent($prompt);
    }
    
    private function generateSampleContent($prompt) {
        preg_match('/about (.+?)\./i', $prompt, $matches);
        $topic = $matches[1] ?? 'innovation';
        
        $samples = [
            "The future of $topic is evolving rapidly. Here are 3 key insights I've learned:

1️⃣ Innovation thrives when we embrace change
2️⃣ Collaboration drives breakthrough results  
3️⃣ Persistence turns challenges into opportunities

What's your experience with $topic? Share your thoughts!

#Innovation #Growth #$topic #LinkedIn #ProfessionalDevelopment",

            "Today's thoughts on $topic and its impact on our industry.

The landscape is changing faster than ever, and those who adapt will lead the way. Here's what I'm seeing:

✅ New opportunities emerging daily
✅ Technology reshaping traditional approaches
✅ Collaboration becoming more critical

The question isn't whether $topic will transform our work—it's how quickly we can embrace the change.

What trends are you noticing? 

#$topic #Innovation #Future #Growth #Networking"
        ];
        
        return $samples[array_rand($samples)];
    }
}

// LinkedIn Publishing Functions
function publishToLinkedIn($content, $accessToken) {
    try {
        $profileId = getLinkedInProfileId($accessToken);
        
        if (!$profileId) {
            return ['success' => false, 'error' => 'Could not get LinkedIn profile ID'];
        }
        
        $postData = [
            'author' => "urn:li:person:$profileId",
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $content],
                    'shareMediaCategory' => 'NONE'
                ]
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
            ]
        ];
        
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
        curl_close($ch);
        
        if ($httpCode === 201) {
            $responseData = json_decode($response, true);
            return ['success' => true, 'post_id' => $responseData['id'] ?? 'unknown'];
        } else {
            return ['success' => false, 'error' => "LinkedIn API error ($httpCode): $response"];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getLinkedInProfileId($accessToken) {
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
?>