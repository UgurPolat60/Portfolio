<?php
require_once 'config.php';

$allowedOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowedOrigins = [
    (isset($_SERVER['HTTP_HOST']) ? ('http://' . $_SERVER['HTTP_HOST']) : ''),
    (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '')
];
if ($allowedOrigin && in_array($allowedOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'İstek geçersiz');
}

function getClientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

requireCsrfOnPost();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(false, 'İstek geçersiz');
}

$action = $input['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin($input);
        break;
    case 'register':
        handleRegister($input);
        break;
    default:
        jsonResponse(false, 'Geçersiz işlem');
}

function handleLogin($data) {
    try {
        $username = sanitizeInput($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $ip = getClientIp();
        $now = time();
        $windowSeconds = 600;
        $maxAttempts = 10;
        $_SESSION['login_attempts'][$ip] = array_filter($_SESSION['login_attempts'][$ip] ?? [], function($ts) use ($now, $windowSeconds) { return ($now - $ts) < $windowSeconds; });
        if (count($_SESSION['login_attempts'][$ip]) >= $maxAttempts) {
            jsonResponse(false, 'İşlem gerçekleştirilemedi');
        }
        
        if (empty($username) || empty($password)) {
            jsonResponse(false, 'İstek geçersiz');
        }
        
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("SELECT id, username, full_name, email, password, role, status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'Geçersiz bilgiler');
        }
        
        if ($user['status'] !== 'active') {
            jsonResponse(false, 'Geçersiz bilgiler');
        }
        
        if (!verifyPassword($password, $user['password'])) {
            jsonResponse(false, 'Geçersiz bilgiler');
        }
        
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        logActivity($user['id'], 'login', 'Giriş');
        
        jsonResponse(true, 'Giriş başarılı', [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]);
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function handleRegister($data) {
    try {
        $username = sanitizeInput($data['username'] ?? '');
        $fullName = sanitizeInput($data['full_name'] ?? '');
        $email = sanitizeInput($data['email'] ?? '');
        $password = $data['password'] ?? '';
        
        if (empty($username) || empty($fullName) || empty($password)) {
            jsonResponse(false, 'İstek geçersiz');
        }
        
        if (strlen($password) < 6) {
            jsonResponse(false, 'İstek geçersiz');
        }
        
        if (!empty($email) && !validateEmail($email)) {
            jsonResponse(false, 'İstek geçersiz');
        }
        
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'Kayıt mevcut');
        }
        
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                jsonResponse(false, 'Kayıt mevcut');
            }
        }
        
        $hashedPassword = hashPassword($password);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'user', 'active')");
        $stmt->execute([$username, $fullName, $email ?: null, $hashedPassword]);
        
        $userId = $pdo->lastInsertId();
        
        logActivity($userId, 'data_change', 'Yeni kullanıcı');
        
        jsonResponse(true, 'Kayıt başarılı', [
            'user_id' => $userId,
            'username' => $username
        ]);
        
    } catch (Exception $e) {
        error_log("Register error: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function logActivity($userId, $logType, $content) {
    try {
        $pdo = getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, content, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $logType, $content, $ipAddress]);
    } catch (Exception $e) {
        error_log("Log error: " . $e->getMessage());
    }
}
?>