<?php
/**
 * Script de Teste do SSO / OIDC (Simulador do Sistema Receptor)
 */

require_once(__DIR__ . '/../../config.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

global $DB, $CFG;

// 1. DADOS DA APLICAÇÃO (Carrega o primeiro cliente cadastrado e ativo ou usa padrão)
$client_id     = 'seu_clientid_cadastrado_aqui';
$client_secret = 'client_secret_gerado_aqui';

$first_client = $DB->get_record('local_simple_sso_clients', ['enabled' => 1]);
if ($first_client) {
    $client_id     = $first_client->client_id;
    $client_secret = $first_client->client_secret;
}

// 2. Configurações de URLs
$moodle_authorize_url = $CFG->wwwroot . '/local/simple_sso/authorize.php';

$protocol    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$current_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');

// --------------------------------------------------------------------------
// ETAPA A: Recebe o Token JWT via URL e valida usando o Client Secret
// --------------------------------------------------------------------------
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Valida a assinatura do token exclusivamente com o secret do cliente
        $decoded = JWT::decode($token, new Key($client_secret, 'HS256'));
        $data    = (array)$decoded;

        echo "<h1>✅ SSO Multi-Tenant / OIDC Autenticado com Sucesso!</h1>";
        echo "<hr>";
        echo "<h3>Payload do Token JWT Decodificado:</h3>";
        echo "<pre style='background:#f4f4f4; padding:15px; border-radius:5px;'>";
        print_r($data);
        echo "</pre>";

        echo "<p><strong>Cliente Autorizado (aud):</strong> " . htmlspecialchars($data['aud'] ?? '') . "</p>";
        echo "<p><strong>Usuário (sub):</strong> " . htmlspecialchars($data['sub'] ?? '') . "</p>";
        echo "<p><strong>E-mail:</strong> " . htmlspecialchars($data['email'] ?? '') . "</p>";
        echo "<p><strong>Nome Completo:</strong> " . htmlspecialchars(($data['firstname'] ?? '') . ' ' . ($data['lastname'] ?? '')) . "</p>";
        
        $groups = isset($data['groups']) ? (array)$data['groups'] : [];
        echo "<p><strong>Grupos/Coortes:</strong> " . htmlspecialchars(implode(', ', $groups)) . "</p>";
        
        echo "<br><a href='" . htmlspecialchars($current_url) . "'>🔄 Testar novamente</a>";
        exit;

    } catch (Exception $e) {
        echo "<h1 style='color:red;'>❌ Falha na Validação do Token</h1>";
        echo "<p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<br><a href='" . htmlspecialchars($current_url) . "'>Tentar Novamente</a>";
        exit;
    }
}

// --------------------------------------------------------------------------
// ETAPA B: Redireciona para o Endpoint de Autorização do Moodle
// --------------------------------------------------------------------------
$sso_login_url = $moodle_authorize_url . '?client_id=' . urlencode($client_id) . '&redirect_uri=' . urlencode($current_url);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Teste Multi-Tenant SSO</title>
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
    <h2>Sistema Consumidor (Teste Multi-tenant / OIDC)</h2>
    <p>A aplicação enviará o <code>client_id</code> (<strong><?php echo htmlspecialchars($client_id); ?></strong>) e a <code>redirect_uri</code> para o endpoint <code>/authorize.php</code>.</p>
    
    <a href="<?php echo htmlspecialchars($sso_login_url); ?>" class="btn">
        Entrar via Moodle SSO
    </a>
</body>
</html>
