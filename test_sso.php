<?php
/**
 * SSO / OIDC Test Script (Client Application Simulator)
 */

require_once(__DIR__ . '/../../config.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

global $DB, $CFG;

// 1. APPLICATION DATA (Loads the first registered active client or uses default placeholders)
$client_id     = 'your_clientid_registered_here';
$client_secret = 'client_secret_generated_here';

$first_client = $DB->get_record('local_simple_sso_clients', ['enabled' => 1]);
if ($first_client) {
    $client_id     = $first_client->client_id;
    $client_secret = $first_client->client_secret;
}

// 2. URL Configuration
$moodle_authorize_url = $CFG->wwwroot . '/local/simple_sso/authorize.php';

$protocol    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$current_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');

// --------------------------------------------------------------------------
// STEP A: Receives JWT Token via URL and validates using Client Secret
// --------------------------------------------------------------------------
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Validates token signature exclusively using client secret
        $decoded = JWT::decode($token, new Key($client_secret, 'HS256'));
        $data    = (array)$decoded;

        echo "<h1>✅ Multi-Tenant SSO / OIDC Authenticated Successfully!</h1>";
        echo "<hr>";
        echo "<h3>Decoded JWT Token Payload:</h3>";
        echo "<pre style='background:#f4f4f4; padding:15px; border-radius:5px;'>";
        print_r($data);
        echo "</pre>";

        echo "<p><strong>Authorized Client (aud):</strong> " . htmlspecialchars($data['aud'] ?? '') . "</p>";
        echo "<p><strong>User (sub):</strong> " . htmlspecialchars($data['sub'] ?? '') . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($data['email'] ?? '') . "</p>";
        echo "<p><strong>Full Name:</strong> " . htmlspecialchars(($data['firstname'] ?? '') . ' ' . ($data['lastname'] ?? '')) . "</p>";
        
        $groups = isset($data['groups']) ? (array)$data['groups'] : [];
        echo "<p><strong>Groups/Cohorts:</strong> " . htmlspecialchars(implode(', ', $groups)) . "</p>";
        
        echo "<br><a href='" . htmlspecialchars($current_url) . "'>🔄 Test again</a>";
        exit;

    } catch (Exception $e) {
        echo "<h1 style='color:red;'>❌ Token Validation Failed</h1>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<br><a href='" . htmlspecialchars($current_url) . "'>Try Again</a>";
        exit;
    }
}

// --------------------------------------------------------------------------
// STEP B: Redirects to Moodle Authorization Endpoint
// --------------------------------------------------------------------------
$sso_login_url = $moodle_authorize_url . '?client_id=' . urlencode($client_id) . '&redirect_uri=' . urlencode($current_url);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Multi-Tenant SSO Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .btn {
            display: inline-block;
            background-color: #0f6cbf;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .btn:hover { background-color: #0b508f; }
    </style>
</head>
<body>
    <h2>Consumer Application (Multi-tenant / OIDC Test)</h2>
    <p>The application will send <code>client_id</code> (<strong><?php echo htmlspecialchars($client_id); ?></strong>) and <code>redirect_uri</code> to the <code>/authorize.php</code> endpoint.</p>
    
    <a href="<?php echo htmlspecialchars($sso_login_url); ?>" class="btn">
        Login via Moodle SSO
    </a>
</body>
</html>
