<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'certby');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* ------------------------------------------------------------------ DEMO --
   The public demo hands every visitor their own database, cloned from an empty
   template, and the janitor drops it again once the session goes cold. Nothing
   a visitor types can reach another visitor or outlive them, so the whole app
   can run with real write access and no pretending. */
define('DEMO_MODE', true);
define('DEMO_TEMPLATE', dirname(__DIR__) . '/template.sql');
/* 40 on a machine with room; the free container that hosts the public demo
   sets this far lower, because every live database costs it memory */
define('DEMO_MAX_DATABASES', (int)(getenv('DEMO_MAX_DATABASES') ?: 40));

function demoRootPdo() {
    return new PDO('mysql:host=' . DB_HOST, DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function demoDbName() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['demo_db'])) {
        return $_SESSION['demo_db'];
    }

    $root = demoRootPdo();
    /* a ceiling, so a crawler cannot fill the disk one session at a time */
    $live = $root->query("SELECT COUNT(*) FROM information_schema.schemata
                          WHERE schema_name LIKE 'certby\\_demo\\_%'")->fetchColumn();
    if ($live >= DEMO_MAX_DATABASES) {
        throw new Exception('Demo şu anda dolu — birkaç dakika sonra tekrar deneyin.');
    }

    $name = 'certby_demo_' . bin2hex(random_bytes(6));
    $root->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
    $cmd = sprintf('mariadb -u %s %s < %s 2>&1',
        escapeshellarg(DB_USER), escapeshellarg($name), escapeshellarg(DEMO_TEMPLATE));
    exec($cmd, $out, $rc);
    if ($rc !== 0) {
        $root->exec("DROP DATABASE IF EXISTS `$name`");
        throw new Exception('Demo veritabanı hazırlanamadı');
    }

    $_SESSION['demo_db'] = $name;
    $_SESSION['demo_started'] = time();
    return $name;
}

function getConnection() {
    try {
        $database = DEMO_MODE ? demoDbName() : DB_NAME;
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $database . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
        throw new Exception("Veritabanı bağlantısı kurulamadı");
    }
}

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_lifetime', '0');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
if (PHP_VERSION_ID >= 70300) {
    ini_set('session.cookie_samesite', 'Lax');
}

session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? '';
}

function requireCsrfOnPost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return; 
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $bodyToken = $_POST['csrf_token'] ?? '';
    if (!$sessionToken || (!hash_equals($sessionToken, $headerToken) && !hash_equals($sessionToken, $bodyToken))) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Geçersiz CSRF token';
        exit();
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.html');
        exit();
    }
}

function getUserData($userId) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Kullanıcı verisi alınamadı: " . $e->getMessage());
        return false;
    }
}

if (!defined('MAX_UPLOAD_BYTES')) {
    define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
}

if (!defined('ALLOWED_MIME_TYPES')) {
    define('ALLOWED_MIME_TYPES', serialize([
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/vnd.oasis.opendocument.text',
        'image/jpeg',
        'image/png',
        'image/gif'
    ]));
}

if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', serialize([
        'pdf', 'doc', 'docx', 'txt', 'odt', 'jpg', 'jpeg', 'png', 'gif'
    ]));
}

function isSecurePath($filePath, $allowedDir = 'uploads/') {
    $realPath = realpath($filePath);
    $allowedPath = realpath($allowedDir);
    
    if ($realPath === false || $allowedPath === false) {
        return false;
    }
    
    return strpos($realPath, $allowedPath) === 0;
}

function isAllowedFileType($filePath) {
    $allowedMimeTypes = unserialize(ALLOWED_MIME_TYPES);
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    return in_array($mimeType, $allowedMimeTypes);
}

function getFileExtensionSafe($fileName) {
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    return $extension;
}

function isAllowedFileExtension($fileName) {
    $allowedExtensions = unserialize(ALLOWED_EXTENSIONS);
    $ext = getFileExtensionSafe($fileName);
    return in_array($ext, $allowedExtensions, true);
}

function isValidUpload($tmpPath, $originalName, $sizeBytes) {
    if (!is_uploaded_file($tmpPath)) {
        return false;
    }
    if ($sizeBytes <= 0 || $sizeBytes > MAX_UPLOAD_BYTES) {
        return false;
    }
    if (!isAllowedFileType($tmpPath)) {
        return false;
    }
    if (!isAllowedFileExtension($originalName)) {
        return false;
    }
    return true;
}

if (!defined('APP_SECRET')) {
    $envSecret = getenv('APP_SECRET');
    if ($envSecret && strlen($envSecret) >= 16) {
        define('APP_SECRET', $envSecret);
    } else {
        define('APP_SECRET', hash('sha256', __DIR__ . php_uname() . DB_NAME));
    }
}

function encryptSecret($plaintext) {
    $key = hash('sha256', APP_SECRET, true);
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        return false;
    }
    $hmac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    return rtrim(strtr(base64_encode($iv . $hmac . $ciphertext), '+/', '-_'), '=');
}

function decryptSecret($encoded) {
    $data = base64_decode(strtr($encoded, '-_', '+/'), true);
    if ($data === false || strlen($data) < 16 + 32) {
        return false;
    }
    $key = hash('sha256', APP_SECRET, true);
    $iv = substr($data, 0, 16);
    $hmac = substr($data, 16, 32);
    $ciphertext = substr($data, 48);
    $calcHmac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    if (!hash_equals($hmac, $calcHmac)) {
        return false;
    }
    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plaintext;
}

function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

ini_set('display_errors', 0);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Istanbul');
?>
