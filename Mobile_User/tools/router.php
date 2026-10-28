<?php
// Local server: php -S 127.0.0.1:8085 -t . tools/router.php
$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if ($path === '/') { header('Location: /public/login.php'); exit; }
if (in_array($path, ['/docs', '/swagger', '/swagger/'], true)) {
    header('Location: /public/swagger.php'); exit;
}
if ($path === '/public/openapi.json') {
    header('Content-Type: application/json; charset=utf-8');
    readfile($root . '/public/openapi.json');
    exit;
}
if (!preg_match('~^/(public|api)/~', $path)
    || preg_match('~(?:^|/)(?:\.|test|boot|send_tracking_email)~i', $path)) {
    http_response_code(404);
    exit;
}
$file = realpath($root . $path);
$public = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
$api = $root . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR;
if (!$file || !is_file($file)
    || (!str_starts_with($file, $public) && !str_starts_with($file, $api))) {
    http_response_code(404);
    exit;
}
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if ($extension === 'php') {
    chdir(dirname($file));
    require $file;
    return true;
}
if (!in_array($extension, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp', 'woff', 'woff2'], true)) {
    http_response_code(404);
    exit;
}
return false;
