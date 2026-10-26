<?php
/**
 * Local SSO / OIDC Test Simulator Script (Ignored in .gitignore).
 * Supports both response_mode=fragment (#token=) and response_mode=query (?token=).
 */

require_once(__DIR__ . '/../../config.php');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

global $DB, $CFG;

// 1. Fetch first registered client application or use fallback credentials.
$client_id     = 'client_eb1c70394387702a';
$client_secret = 'cfaa33234f11f834e95863211aeae98b16061c2944633daa';

$first_client = $DB->get_record('local_simple_sso_clients', ['enabled' => 1]);
if ($first_client) {
    $client_id     = $first_client->client_id;
    $client_secret = $first_client->client_secret;
}

$moodle_authorize_url = $CFG->wwwroot . '/local/simple_sso/authorize.php';
$protocol    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$current_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');

// Handle Query token parameter (response_mode=query)
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    try {
        $decoded = JWT::decode($token, new Key($client_secret, 'HS256'));
        $data    = (array)$decoded;
        echo "<h1>✅ SSO Authenticated Successfully (response_mode=query)!</h1><hr>";
        echo "<h3>Decoded JWT Token Payload:</h3>";
        echo "<pre style='background:#f4f4f4; padding:15px; border-radius:5px;'>";
        print_r($data);
        echo "</pre>";
        echo "<p><strong>Client ID (aud):</strong> " . htmlspecialchars($data['aud'] ?? '') . "</p>";
        echo "<p><strong>User (sub):</strong> " . htmlspecialchars($data['sub'] ?? '') . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($data['email'] ?? '') . "</p>";
        echo "<p><strong>Full Name:</strong> " . htmlspecialchars(($data['firstname'] ?? '') . ' ' . ($data['lastname'] ?? '')) . "</p>";
        $groups = isset($data['groups']) ? (array)$data['groups'] : [];
        echo "<p><strong>Groups/Cohorts:</strong> " . htmlspecialchars(implode(', ', $groups)) . "</p>";
        echo "<br><a href='" . htmlspecialchars($current_url) . "'>🔄 Test again</a>";
        exit;
    } catch (Exception $e) {
        echo "<h1 style='color:red;'>❌ Token Validation Failed</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<br><a href='" . htmlspecialchars($current_url) . "'>Try Again</a>";
        exit;
    }
}

// Build Authorize URLs for both modes
$sso_fragment_url = $moodle_authorize_url . '?client_id=' . urlencode($client_id) . '&redirect_uri=' . urlencode($current_url) . '&response_mode=fragment';
$sso_query_url    = $moodle_authorize_url . '?client_id=' . urlencode($client_id) . '&redirect_uri=' . urlencode($current_url) . '&response_mode=query';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SSO Test Simulator (response_mode)</title>
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
            margin-right: 10px;
        }
        .btn-query { background-color: #28a745; }
        .btn:hover { opacity: 0.9; }
        #fragment-result { display: none; margin-top: 20px; background: #eef7ff; padding: 20px; border-radius: 5px; border: 1px solid #b8daff; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 14px; }
    </style>
</head>
<body>
    <h2>SSO Integration Test Simulator</h2>
    <p>Client ID: <code><?php echo htmlspecialchars($client_id); ?></code></p>

    <div id="fragment-result">
        <h3 style="color: #0056b3;">✅ Token Received in URI Hash Fragment (#token=...)</h3>
        <p><strong>Raw Fragment Token:</strong></p>
        <code id="raw-token" style="word-break: break-all; background: #fff; padding: 5px; display: block; border: 1px solid #ccc;"></code>

        <h3>Decoded JWT Token Payload:</h3>
        <pre id="json-payload"></pre>
        <p><strong>Client ID (aud):</strong> <span id="jwt-aud"></span></p>
        <p><strong>User (sub):</strong> <span id="jwt-sub"></span></p>
        <p><strong>Email:</strong> <span id="jwt-email"></span></p>
        <p><strong>Full Name:</strong> <span id="jwt-name"></span></p>
        <p><strong>Groups/Cohorts:</strong> <span id="jwt-groups"></span></p>

        <br>
        <a href="<?php echo htmlspecialchars($current_url); ?>" class="btn">🔄 Reset Test</a>
    </div>

    <div id="test-actions">
        <h3>Choose Authentication Response Mode:</h3>
        <p>
            <a href="<?php echo htmlspecialchars($sso_fragment_url); ?>" class="btn">
                1. Login via Fragment Mode (#token=) [OIDC Strict]
            </a>
            <a href="<?php echo htmlspecialchars($sso_query_url); ?>" class="btn btn-query">
                2. Login via Query Mode (?token=) [Legacy Backend]
            </a>
        </p>
    </div>

    <script>
        function parseJwt(token) {
            try {
                const base64Url = token.split('.')[1];
                const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                return JSON.parse(jsonPayload);
            } catch (e) {
                return null;
            }
        }

        // Check for token in URL fragment (#token=)
        if (window.location.hash && window.location.hash.indexOf('token=') !== -1) {
            const hash = window.location.hash.substring(1);
            const params = new URLSearchParams(hash);
            const token = params.get('token');
            if (token) {
                document.getElementById('test-actions').style.display = 'none';
                document.getElementById('fragment-result').style.display = 'block';
                document.getElementById('raw-token').textContent = token;

                const payload = parseJwt(token);
                if (payload) {
                    document.getElementById('json-payload').textContent = JSON.stringify(payload, null, 4);
                    document.getElementById('jwt-aud').textContent = payload.aud || '';
                    document.getElementById('jwt-sub').textContent = payload.sub || '';
                    document.getElementById('jwt-email').textContent = payload.email || '';
                    document.getElementById('jwt-name').textContent = (payload.firstname || '') + ' ' + (payload.lastname || '');
                    document.getElementById('jwt-groups').textContent = Array.isArray(payload.groups) ? payload.groups.join(', ') : '';
                }
            }
        }
    </script>
</body>
</html>
