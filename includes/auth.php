<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getBasePath() {
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $appRoot = realpath(dirname(__DIR__));
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

    if ($appRoot && $documentRoot) {
        $normalizedAppRoot = str_replace('\\', '/', $appRoot);
        $normalizedDocumentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');

        if (stripos($normalizedAppRoot, $normalizedDocumentRoot) === 0) {
            $relativePath = substr($normalizedAppRoot, strlen($normalizedDocumentRoot));
            $relativePath = '/' . trim($relativePath, '/');
            $basePath = $relativePath === '/' ? '' : $relativePath;
            return $basePath;
        }
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $knownRootFiles = [
        'index.php',
        'login.php',
        'logout.php',
        'signup.php',
        'chat.php',
        'send_message.php',
        'test.php',
        'update_request_status.php',
    ];

    foreach ($knownRootFiles as $file) {
        $suffix = '/' . $file;
        if ($scriptName !== '' && substr($scriptName, -strlen($suffix)) === $suffix) {
            $basePath = rtrim(substr($scriptName, 0, -strlen($suffix)), '/');
            return $basePath;
        }
    }

    $basePath = '';
    return $basePath;
}

function appUrl($path = '') {
    $normalizedPath = ltrim($path, '/');
    $basePath = getBasePath();

    if ($normalizedPath === '') {
        return $basePath !== '' ? $basePath . '/' : '/';
    }

    return ($basePath !== '' ? $basePath : '') . '/' . $normalizedPath;
}

function appAbsoluteUrl($path = '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . appUrl($path);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . appUrl('login.php'));
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if (getUserRole() !== $role) {
        header('Location: ' . appUrl('index.php'));
        exit;
    }
}

function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
}

function logoutUser() {
    session_destroy();
    header('Location: ' . appUrl('index.php'));
    exit;
}
?>
