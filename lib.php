<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Valida se a redirect_uri informada está autorizada em relação às URLs permitidas (Lista Branca).
 *
 * @param string $redirect_uri URL enviada na requisição.
 * @param string $allowed_uris_text Texto contendo as URLs permitidas separadas por quebra de linha.
 * @return bool True se a URL for válida e autorizada, false caso contrário.
 */
function local_simple_sso_validate_redirect_uri(string $redirect_uri, string $allowed_uris_text): bool {
    if (empty($redirect_uri) || !filter_var($redirect_uri, FILTER_VALIDATE_URL)) {
        return false;
    }

    $target = parse_url($redirect_uri);
    if (!$target || empty($target['scheme']) || empty($target['host'])) {
        return false;
    }

    $allowed_uris = array_map('trim', explode("\n", str_replace("\r", "", $allowed_uris_text)));

    foreach ($allowed_uris as $allowed) {
        if (empty($allowed)) {
            continue;
        }

        // Validação exata primeiro
        if ($redirect_uri === $allowed) {
            return true;
        }

        $allowed_parsed = parse_url($allowed);
        if (!$allowed_parsed || empty($allowed_parsed['scheme']) || empty($allowed_parsed['host'])) {
            continue;
        }

        // 1. Validar Esquema (http/https)
        if (strtolower($target['scheme']) !== strtolower($allowed_parsed['scheme'])) {
            continue;
        }

        // 2. Validar Host
        if (strtolower($target['host']) !== strtolower($allowed_parsed['host'])) {
            continue;
        }

        // 3. Validar Porta (se especificada no allowed)
        if (isset($allowed_parsed['port']) && ($target['port'] ?? null) !== $allowed_parsed['port']) {
            continue;
        }

        // 4. Validar Caminho (Path)
        $allowed_path = $allowed_parsed['path'] ?? '/';
        $target_path  = $target['path'] ?? '/';

        if ($allowed_path === '' || $allowed_path === null) {
            $allowed_path = '/';
        }
        if ($target_path === '' || $target_path === null) {
            $target_path = '/';
        }

        if ($allowed_path === $target_path) {
            return true;
        }

        // Se o caminho fornecido começa com o caminho permitido
        if (strpos($target_path, $allowed_path) === 0) {
            if (substr($allowed_path, -1) === '/' || substr($target_path, strlen($allowed_path), 1) === '/') {
                return true;
            }
        }
    }

    return false;
}

/**
 * Registra um evento de auditoria ou tentativa de autenticação na tabela local_simple_sso_logs.
 *
 * @param string $eventtype Tipo do evento (ex: 'login_success', 'login_failed_uri', 'client_created', 'client_deleted', 'userinfo_access').
 * @param string $status Status do evento ('SUCCESS', 'BLOCKED', 'ERROR').
 * @param string $client_id ID do cliente/aplicação (opcional).
 * @param string $redirect_uri URL de redirecionamento enviada na requisição (opcional).
 * @param string $failure_reason Motivo da falha ou observação (opcional).
 * @param string $endpoint Nome do arquivo/endpoint executado (opcional, detectado automaticamente se vazio).
 * @param int|null $userid ID do usuário Moodle (se nulo, pega de $USER->id).
 * @return bool True se gravado com sucesso.
 */
function local_simple_sso_log_event(
    string $eventtype,
    string $status = 'SUCCESS',
    string $client_id = '',
    string $redirect_uri = '',
    string $failure_reason = '',
    string $endpoint = '',
    ?int $userid = null
): bool {
    global $DB, $USER;

    try {
        if ($userid === null) {
            $userid = (!empty($USER) && isset($USER->id)) ? (int)$USER->id : 0;
        }

        if (empty($endpoint)) {
            $endpoint = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? 'unknown');
        }

        $log = new \stdClass();
        $log->client_id      = substr($client_id, 0, 100);
        $log->userid         = $userid;
        $log->eventtype      = substr($eventtype, 0, 50);
        $log->status         = substr($status, 0, 20);
        $log->endpoint       = substr($endpoint, 0, 100);
        $log->http_method    = substr($_SERVER['REQUEST_METHOD'] ?? 'GET', 0, 10);
        $log->redirect_uri   = $redirect_uri;
        $log->failure_reason = $failure_reason;
        $log->ip             = getremoteaddr();
        $log->user_agent     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log->timecreated    = time();

        $DB->insert_record('local_simple_sso_logs', $log);
        return true;
    } catch (\Exception $e) {
        // Falha no log não deve interromper o fluxo principal
        return false;
    }
}
