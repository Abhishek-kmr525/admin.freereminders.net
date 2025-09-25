<?php
// admin/credentials.php - Manage API credentials (Gemini, ChatGPT/OpenAI, Google, LinkedIn)
session_start();
require_once '../config/database-config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Load settings
$settings = [];
try {
    $stmt = $db->prepare("SELECT * FROM api_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('Get API settings error: ' . $e->getMessage());
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['test_api'])) {
    $gemini = trim($_POST['gemini_api_key'] ?? '');
    $chatgpt = trim($_POST['chatgpt_api_key'] ?? '');
    $google_api_key = trim($_POST['google_api_key'] ?? '');
    $google_oauth_client_id = trim($_POST['google_oauth_client_id'] ?? '');
    $google_oauth_client_secret = trim($_POST['google_oauth_client_secret'] ?? '');
    $linkedinClientId = trim($_POST['linkedin_client_id'] ?? '');
    $linkedinClientSecret = trim($_POST['linkedin_client_secret'] ?? '');
    $linkedinAccessToken = trim($_POST['linkedin_access_token'] ?? '');

    try {
        // Ensure new OAuth columns exist (best-effort)
        $maybeAddCols = [
            'google_api_key', 'google_oauth_client_id', 'google_oauth_client_secret',
            'linkedin_client_id', 'linkedin_client_secret', 'linkedin_access_token'
        ];
        foreach ($maybeAddCols as $col) {
            try {
                $db->query("SELECT " . $col . " FROM api_settings LIMIT 1");
            } catch (Exception $colEx) {
                try { $db->exec("ALTER TABLE api_settings ADD COLUMN " . $col . " TEXT DEFAULT NULL"); } catch (Exception $ignore) {}
            }
        }

        $stmt = $db->prepare(
            "INSERT INTO api_settings (
                id, gemini_api_key, chatgpt_api_key, google_api_key, google_oauth_client_id, google_oauth_client_secret,
                linkedin_client_id, linkedin_client_secret, linkedin_access_token, updated_at
            ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                gemini_api_key = VALUES(gemini_api_key),
                chatgpt_api_key = VALUES(chatgpt_api_key),
                google_api_key = VALUES(google_api_key),
                google_oauth_client_id = VALUES(google_oauth_client_id),
                google_oauth_client_secret = VALUES(google_oauth_client_secret),
                linkedin_client_id = VALUES(linkedin_client_id),
                linkedin_client_secret = VALUES(linkedin_client_secret),
                linkedin_access_token = VALUES(linkedin_access_token),
                updated_at = NOW()
            "
        );

        $success = $stmt->execute([
            $gemini, $chatgpt, $google_api_key, $google_oauth_client_id, $google_oauth_client_secret,
            $linkedinClientId, $linkedinClientSecret, $linkedinAccessToken
        ]);

        if ($success) {
            $message = 'Credentials saved successfully';
            $stmt = $db->prepare("SELECT * FROM api_settings WHERE id = 1");
            $stmt->execute();
            $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } else {
            $error = 'Failed to save credentials';
        }

    } catch (Exception $e) {
        error_log('Save credentials error: ' . $e->getMessage());
        $error = 'An error occurred while saving credentials';
    }
}

// Test API endpoints via AJAX POST test_api
function testGeminiAPI($apiKey) {
    if (empty($apiKey)) return ['status' => false, 'message' => 'API key not set'];
    $data = ['contents' => [['parts' => [['text' => 'Test connection. Reply with OK']]]]];
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200) return ['status' => true, 'message' => 'Connected successfully'];
    return ['status' => false, 'message' => 'Connection failed: ' . $httpCode];
}

function testChatGPTAPI($apiKey) {
    if (empty($apiKey)) return ['status' => false, 'message' => 'API key not set'];
    $data = ['model' => 'gpt-3.5-turbo', 'messages' => [['role' => 'user', 'content' => 'Test connection. Reply with OK']], 'max_tokens' => 10];
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200) return ['status' => true, 'message' => 'Connected successfully'];
    return ['status' => false, 'message' => 'Connection failed: ' . $httpCode];
}

// Handle AJAX tests
if (isset($_POST['test_api'])) {
    $provider = $_POST['test_api'];
    $apiKey = $_POST['api_key'] ?? '';
    if ($provider === 'gemini') {
        $result = testGeminiAPI($apiKey);
    } elseif ($provider === 'chatgpt') {
        $result = testChatGPTAPI($apiKey);
    } else {
        $result = ['status' => false, 'message' => 'Unknown provider'];
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Credentials - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1 class="mb-3">Credentials</h1>
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Google Gemini API Key</label>
            <div class="input-group">
                <input id="gemini-key" type="password" name="gemini_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" placeholder="Gemini API Key">
                <button type="button" class="btn btn-outline-secondary" onclick="toggle('gemini-key')"><i class="fas fa-eye"></i></button>
                <button type="button" class="btn btn-outline-primary" onclick="testAPI('gemini')">Test</button>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">OpenAI (ChatGPT) API Key</label>
            <div class="input-group">
                <input id="chatgpt-key" type="password" name="chatgpt_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['chatgpt_api_key'] ?? ''); ?>" placeholder="OpenAI API Key">
                <button type="button" class="btn btn-outline-secondary" onclick="toggle('chatgpt-key')"><i class="fas fa-eye"></i></button>
                <button type="button" class="btn btn-outline-primary" onclick="testAPI('chatgpt')">Test</button>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Google API Key (optional)</label>
            <input type="password" name="google_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['google_api_key'] ?? ''); ?>" placeholder="Google API Key">
        </div>

        <div class="mb-3">
            <label class="form-label">Google OAuth Client ID</label>
            <input type="text" name="google_oauth_client_id" class="form-control" value="<?php echo htmlspecialchars($settings['google_oauth_client_id'] ?? ''); ?>" placeholder="Google OAuth Client ID">
        </div>

        <div class="mb-3">
            <label class="form-label">Google OAuth Client Secret</label>
            <input type="password" name="google_oauth_client_secret" class="form-control" value="<?php echo htmlspecialchars($settings['google_oauth_client_secret'] ?? ''); ?>" placeholder="Google OAuth Client Secret">
        </div>

        <div class="mb-3">
            <label class="form-label">LinkedIn Client ID</label>
            <input type="text" name="linkedin_client_id" class="form-control" value="<?php echo htmlspecialchars($settings['linkedin_client_id'] ?? ''); ?>" placeholder="LinkedIn Client ID">
        </div>

        <div class="mb-3">
            <label class="form-label">LinkedIn Client Secret</label>
            <input id="linkedin-secret" type="password" name="linkedin_client_secret" class="form-control" value="<?php echo htmlspecialchars($settings['linkedin_client_secret'] ?? ''); ?>" placeholder="LinkedIn Client Secret">
            <div class="form-text">You may also paste an access token below.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">LinkedIn Access Token</label>
            <input type="password" name="linkedin_access_token" class="form-control" value="<?php echo htmlspecialchars($settings['linkedin_access_token'] ?? ''); ?>" placeholder="LinkedIn Access Token">
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Save Credentials</button>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
<script>
function toggle(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.type = el.type === 'password' ? 'text' : 'password';
}

function testAPI(provider) {
    const keyEl = document.getElementById(provider + '-key');
    const apiKey = keyEl ? keyEl.value.trim() : '';
    if (!apiKey) { alert('Please enter API key first'); return; }
    const form = new FormData();
    form.append('test_api', provider);
    form.append('api_key', apiKey);

    fetch('credentials.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.status) alert('✅ ' + data.message); else alert('❌ ' + data.message);
        })
        .catch(e => alert('❌ ' + e.message));
}
</script>
</body>
</html>
