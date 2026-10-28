<?php
function app_url(string $path): string {
    $base = getenv('APP_URL');
    if (!$base) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8085';
        if (!preg_match('/^[a-zA-Z0-9.\\-]+(?::[0-9]+)?$/D', $host)) { $host = 'localhost:8085'; }
        $prefix = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'));
        $base = ($https ? 'https://' : 'http://') . $host . ($prefix === '/' ? '' : $prefix);
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}