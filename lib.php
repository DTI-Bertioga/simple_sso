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
