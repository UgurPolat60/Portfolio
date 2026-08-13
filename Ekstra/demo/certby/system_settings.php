<?php
require_once 'config.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_auditor':
    addAuditor();
    break;
        case 'toggle_user_status':
            toggleUserStatus();
            break;
        case 'update_user':
            updateUser();
            break;
        case 'change_password':
            changePassword();
            break;
        case 'add_operator':
            addOperator();
            break;
        case 'add_user':
            addUser();
            break;
        case 'add_accountant':
            addAccountant();
            break;
        case 'get_users':
            getUsers();
            break;
        case 'get_user':
            getUser();
            break;
    }
}

function toggleUserStatus() {
    try {
        $userId = (int)$_POST['user_id'];
        $newStatus = $_POST['status'];
        
        if (empty($userId) || !in_array($newStatus, ['active', 'inactive'])) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'Kayıt bulunamadı');
            return;
        }
        
        if ($user['role'] === 'operator') {
            jsonResponse(false, 'İşlem gerçekleştirilemedi');
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$newStatus, $userId]);
        
        if ($result) {
            $statusText = $newStatus === 'active' ? 'aktif' : 'pasif';
            jsonResponse(true, "Kullanıcı başarıyla {$statusText} yapıldı");
        } else {
            jsonResponse(false, 'İşlem gerçekleştirilemedi');
        }
        
    } catch (Exception $e) {
        error_log("Kullanıcı durumu değiştirme hatası: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function updateUser() {
    try {
        $userId = (int)$_POST['user_id'];
        $username = sanitizeInput($_POST['username'] ?? '');
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($userId) || empty($username) || empty($full_name)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        if (!empty($email) && !validateEmail($email)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        
        if ($stmt->fetch()) {
            jsonResponse(false, 'Kayıt mevcut');
            return;
        }
        
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            
            if ($stmt->fetch()) {
                jsonResponse(false, 'Kayıt mevcut');
                return;
            }
        }
        
        $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$username, $full_name, $email, $userId]);
        
        if ($result) {
            jsonResponse(true, 'Kullanıcı bilgileri başarıyla güncellendi');
        } else {
            jsonResponse(false, 'İşlem gerçekleştirilemedi');
        }
        
    } catch (Exception $e) {
        error_log("Kullanıcı güncelleme hatası: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function changePassword() {
    try {
        $userId = (int)$_POST['user_id'];
        $newPassword = $_POST['new_password'] ?? '';
        
        if (empty($userId) || empty($newPassword)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        if (strlen($newPassword) < 8) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        $pdo = getConnection();
        $hashedPassword = hashPassword($newPassword);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$hashedPassword, $userId]);
        
        if ($result && $stmt->rowCount() > 0) {
            jsonResponse(true, 'Şifre başarıyla değiştirildi');
        } else {
            jsonResponse(false, 'İşlem gerçekleştirilemedi');
        }
        
    } catch (Exception $e) {
        error_log("Şifre değiştirme hatası: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function addOperator() {
    try {
        $username = sanitizeInput($_POST['username'] ?? '');
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($full_name) || empty($password)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        if (!empty($email) && !validateEmail($email)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        if (strlen($password) < 8) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            jsonResponse(false, 'Kayıt mevcut');
            return;
        }
        
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                jsonResponse(false, 'Kayıt mevcut');
                return;
            }
        }
        
        $hashedPassword = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'operator', 'active', NOW())");
        $result = $stmt->execute([$username, $full_name, $email, $hashedPassword]);
        
        if ($result) {
            jsonResponse(true, 'Operatör başarıyla eklendi');
        } else {
            jsonResponse(false, 'İşlem gerçekleştirilemedi');
        }
        
    } catch (Exception $e) {
        error_log("Operatör ekleme hatası: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function addUser() {
    try {
        $username = sanitizeInput($_POST['username'] ?? '');
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($full_name) || empty($password)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        if (!empty($email) && !validateEmail($email)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        if (strlen($password) < 8) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            jsonResponse(false, 'Kayıt mevcut');
            return;
        }
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                jsonResponse(false, 'Kayıt mevcut');
                return;
            }
        }
        $hashedPassword = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'user', 'active', NOW())");
        $result = $stmt->execute([$username, $full_name, $email, $hashedPassword]);
        
        if ($result) {
            jsonResponse(true, 'Kullanıcı başarıyla eklendi');
        } else {
            jsonResponse(false, 'İşlem gerçekleştirilemedi');
        }
        
    } catch (Exception $e) {
        error_log("Kullanıcı ekleme hatası: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function addAuditor() {
    try {
        $username = sanitizeInput($_POST['username'] ?? '');
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($full_name) || empty($password)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        if (!empty($email) && !validateEmail($email)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        if (strlen($password) < 8) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            jsonResponse(false, 'Kayıt mevcut');
            return;
        }
        
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                jsonResponse(false, 'Kayıt mevcut');
                return;
            }
        }
        
        $hashedPassword = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'auditor', 'active', NOW())");
        $result = $stmt->execute([$username, $full_name, $email, $hashedPassword]);
        
        if ($result) {
            jsonResponse(true, 'Denetçi başarıyla eklendi');
        } else {
            jsonResponse(false, 'İşlem gerçekleştirilemedi');
        }
        
    } catch (Exception $e) {
        error_log("Denetçi ekleme hatası: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function addAccountant() {
    try {
        $username = sanitizeInput($_POST['username'] ?? '');
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($full_name) || empty($password)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        if (!empty($email) && !validateEmail($email)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        if (strlen($password) < 8) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            jsonResponse(false, 'Kayıt mevcut');
            return;
        }
        
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                jsonResponse(false, 'Kayıt mevcut');
                return;
            }
        }
        
        $hashedPassword = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'muhasebeci', 'active', NOW())");
        $result = $stmt->execute([$username, $full_name, $email, $hashedPassword]);
        
        if ($result) {
            jsonResponse(true, 'Muhasebeci başarıyla eklendi');
        } else {
            jsonResponse(false, 'İşlem gerçekleştirilemedi');
        }
        
    } catch (Exception $e) {
        error_log("Muhasebeci ekleme hatası: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}
function getUsers() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 100");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        jsonResponse(true, '', $users);
        
    } catch (Exception $e) {
        error_log("Kullanıcılar alınamadı: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function getUser() {
    try {
        $userId = (int)$_POST['user_id'];
        
        if (empty($userId)) {
            jsonResponse(false, 'İstek geçersiz');
            return;
        }
        
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            jsonResponse(true, '', $user);
        } else {
            jsonResponse(false, 'Kayıt bulunamadı');
        }
        
    } catch (Exception $e) {
        error_log("Kullanıcı bilgisi alınamadı: " . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $totalUsers = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'operator'");
    $stmt->execute();
    $operatorCount = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stmt->execute();
    $userCount = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'auditor'");
    $stmt->execute();
    $auditorCount = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'active'");
    $stmt->execute();
    $activeCount = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $totalUsers = $operatorCount = $userCount = $auditorCount = 0; 
    error_log("İstatistikler alınamadı: " . $e->getMessage());
}

$currentUser = getUserData($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ayarları</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
        }
        
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: none;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .card-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            border: none;
            padding: 20px;
        }
        
        .table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: none;
            font-weight: 600;
            padding: 15px;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
        }
        
        .badge {
            font-size: 0.8em;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            margin-right: 15px;
            font-size: 1.1em;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .operator-bg { 
            background: linear-gradient(135deg, #fd7e14 0%, #ff9800 100%);
        }
        
        .user-bg { 
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .search-box input {
            border-radius: 15px;
            padding: 12px 20px;
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.9);
        }
        .auditor-bg { 
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}
        
        .search-box input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }
        
        .stats-card {
            color: white;
            border-radius: 20px;
        }
        
        .stats-card h4 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 10px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .dropdown-menu {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: none;
            padding: 10px 0;
        }
        
        .dropdown-item {
            padding: 10px 20px;
            border-radius: 10px;
            margin: 2px 10px;
        }
        
        .btn {
            border-radius: 15px;
            padding: 10px 20px;
            font-weight: 600;
        }
        
        .page-header {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            color: white;
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            margin-top: 1rem;
            padding: 15px 20px;
        }
        
        .alert-success {
            background: var(--success-gradient);
            color: white;
        }
        
        .alert-danger {
            background: var(--danger-gradient);
            color: white;
        }
        
        .custom-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--primary-gradient);
            color: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            z-index: 1060;
            min-width: 400px;
            max-width: 500px;
        }
        
        .custom-popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            z-index: 1055;
        }
        
        .custom-popup .form-control {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 15px;
        }
        
        .custom-popup .form-control:focus {
            background: white;
            border-color: rgba(255,255,255,0.8);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
        }
        
        .custom-popup .btn {
            margin: 5px;
            min-width: 100px;
        }
        
        .table-responsive {
            border-radius: 15px;
            overflow-y: auto;
            max-height: 500px;
        }

        .confirmation-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            color: #333;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            z-index: 1070;
            min-width: 400px;
            max-width: 500px;
            text-align: center;
        }

        .confirmation-dialog h5 {
            color: #dc3545;
            margin-bottom: 20px;
        }

        .confirmation-dialog .btn {
            min-width: 120px;
            margin: 0 10px;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs .nav-link {
            border: none !important;
            border-radius: 12px !important;
            padding: 15px 25px;
            margin: 0 4px;
            background: rgba(118, 75, 162, 0.1);
            color: #764ba2;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }

        .nav-tabs .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(118, 75, 162, 0.1) 0%, rgba(102, 126, 234, 0.1) 100%);
            border-radius: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-tabs .nav-link:hover::before {
            opacity: 1;
        }

        .nav-tabs .nav-link:hover {
            background: linear-gradient(135deg, rgba(118, 75, 162, 0.2) 0%, rgba(102, 126, 234, 0.2) 100%);
            color: #5a4a7a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.3);
            border-color: rgba(118, 75, 162, 0.3);
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%) !important;
            color: white !important;
            box-shadow: 0 8px 25px rgba(118, 75, 162, 0.4);
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .nav-tabs .nav-link.active::before {
            opacity: 0;
        }

        .nav-tabs .nav-link i {
            margin-right: 8px;
            transition: transform 0.3s ease;
        }

        .nav-tabs .nav-link:hover i {
            transform: scale(1.1);
        }

        .nav-tabs .nav-link.active i {
            transform: scale(1.05);
        }
        
        @media (max-width: 768px) {
            .custom-popup {
                min-width: 90%;
                padding: 20px;
                margin: 20px;
            }
            
            .confirmation-dialog {
                min-width: 90%;
                margin: 20px;
            }
            
            .stats-card h4 {
                font-size: 2rem;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
                font-size: 1em;
            }

            .nav-tabs .nav-link {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-cogs me-3"></i>Sistem Ayarları</h2>
                    <a href="dashboard.php" class="btn btn-light btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Geri Dön
                    </a>
                </div>
            </div>
        </div>
        <div id="alert-container"></div>
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card" style="background: var(--primary-gradient);">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <h4 id="total-users"><?php echo $totalUsers; ?></h4>
                        <p class="mb-0 fs-5">Toplam Kullanıcı</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card" style="background: var(--warning-gradient);">
                    <div class="card-body text-center">
                        <i class="fas fa-user-shield fa-3x mb-3"></i>
                        <h4 id="operator-count"><?php echo $operatorCount; ?></h4>
                        <p class="mb-0 fs-5">Operatör</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card" style="background: var(--info-gradient);">
                    <div class="card-body text-center">
                        <i class="fas fa-user fa-3x mb-3"></i>
                        <h4 id="user-count"><?php echo $userCount; ?></h4>
                        <p class="mb-0 fs-5">Kullanıcı</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card" style="background: var(--success-gradient);">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check fa-3x mb-3"></i>
                        <h4 id="active-count"><?php echo $activeCount; ?></h4>
                        <p class="mb-0 fs-5">Aktif Kullanıcı</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
    <div class="card stats-card" style="background: var(--danger-gradient);">
        <div class="card-body text-center">
            <i class="fas fa-user-tie fa-3x mb-3"></i>
            <h4 id="auditor-count"><?php echo $auditorCount; ?></h4>
            <p class="mb-0 fs-5">Denetçi</p>
        </div>
    </div>
</div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>Kullanıcı Yönetimi
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="search-box mb-4">
                            <input type="text" class="form-control" id="search-users" placeholder="🔍 Kullanıcı ara...">
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="users-table">
                                <thead>
                                    <tr>
                                        <th>Kullanıcı</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Durum</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody id="users-tbody">
                                </tbody>
                            </table>
                        </div>
                        <div id="table-loading" class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                            <p class="mt-2">Kullanıcılar yükleniyor...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-plus me-2"></i>Yeni Kullanıcı Ekle
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="add-user-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="operator-tab" data-bs-toggle="tab" data-bs-target="#operator-form" type="button" role="tab">
                                    <i class="fas fa-user-shield me-2"></i>Operatör
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user-form" type="button" role="tab">
                                    <i class="fas fa-user me-2"></i>Kullanıcı
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
    <button class="nav-link" id="auditor-tab" data-bs-toggle="tab" data-bs-target="#auditor-form" type="button" role="tab">
        <i class="fas fa-user-tie me-2"></i>Denetçi
    </button>
</li>
                            <li class="nav-item" role="presentation">
    <button class="nav-link" id="accountant-tab" data-bs-toggle="tab" data-bs-target="#accountant-form" type="button" role="tab">
        <i class="fas fa-calculator me-2"></i>Muhasebeci
    </button>
</li>
                        </ul>
                        <div class="tab-content" id="add-user-tab-content">
                            <div class="tab-pane fade show active" id="operator-form" role="tabpanel">
                                <form id="add-operator-form">
                                    <div class="mb-3">
                                        <label class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Şifre <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="password" required minlength="8">
                                        <div class="form-text">Şifre en az 8 karakter olmalı</div>
                                    </div>
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="fas fa-user-shield me-2"></i>Operatör Ekle
                                    </button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="user-form" role="tabpanel">
                                <form id="add-user-form">
                                    <div class="mb-3">
                                        <label class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Şifre <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" name="password" required minlength="8">
                                        <div class="form-text">Şifre en az 8 karakter olmalı</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-user me-2"></i>Kullanıcı Ekle
                                    </button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="auditor-form" role="tabpanel">
    <form id="add-auditor-form">
        <div class="mb-3">
            <label class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="username" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="full_name" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email">
        </div>
        <div class="mb-3">
            <label class="form-label">Şifre <span class="text-danger">*</span></label>
            <input type="password" class="form-control" name="password" required minlength="8">
            <div class="form-text">Şifre en az 8 karakter olmalı</div>
        </div>
        <button type="submit" class="btn btn-danger w-100">
            <i class="fas fa-user-tie me-2"></i>Denetçi Ekle
        </button>
    </form>
</div>
                            <div class="tab-pane fade" id="accountant-form" role="tabpanel">
    <form id="add-accountant-form">
        <div class="mb-3">
            <label class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="username" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="full_name" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email">
        </div>
        <div class="mb-3">
            <label class="form-label">Şifre <span class="text-danger">*</span></label>
            <input type="password" class="form-control" name="password" required minlength="8">
            <div class="form-text">Şifre en az 8 karakter olmalı</div>
        </div>
        <button type="submit" class="btn btn-success w-100">
            <i class="fas fa-calculator me-2"></i>Muhasebeci Ekle
        </button>
    </form>
</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let allUsers = [];
        let filteredUsers = [];
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
        });
        function loadUsers() {
            const formData = new FormData();
            formData.append('action', 'get_users');
            
            fetch('system_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allUsers = data.data;
                    filteredUsers = allUsers;
                    renderUsers();
                    updateStats();
                } else {
                    showAlert('danger', 'Kullanıcılar yüklenemedi');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Bir hata oluştu');
            })
            .finally(() => {
                document.getElementById('table-loading').style.display = 'none';
            });
        }
        function updateStats() {
    const totalUsers = allUsers.length;
    const operatorCount = allUsers.filter(user => user.role === 'operator').length;
    const userCount = allUsers.filter(user => user.role === 'user').length;
    const auditorCount = allUsers.filter(user => user.role === 'auditor').length;
    const activeCount = allUsers.filter(user => user.status === 'active').length;
    
    document.getElementById('total-users').textContent = totalUsers;
    document.getElementById('operator-count').textContent = operatorCount;
    document.getElementById('user-count').textContent = userCount;
    document.getElementById('auditor-count').textContent = auditorCount;
    document.getElementById('active-count').textContent = activeCount;
}
        function renderUsers() {
            const tbody = document.getElementById('users-tbody');
            
            if (filteredUsers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">Kullanıcı bulunamadı</td></tr>';
                return;
            }
            
            tbody.innerHTML = filteredUsers.map(user => `
                <tr data-user-id="${user.id}">
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="user-avatar ${user.role}-bg">
                                ${user.username.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <strong>${user.username}</strong><br>
                                <small class="text-muted">${user.full_name}</small>
                            </div>
                        </div>
                    </td>
                    <td>${user.email || '<span class="text-muted">-</span>'}</td>
                    <td>
                        <span class="badge ${user.role === 'operator' ? 'bg-warning' : user.role === 'auditor' ? 'bg-danger' : 'bg-secondary'}">
    ${user.role === 'operator' ? 'Operatör' : user.role === 'auditor' ? 'Denetçi' : 'Kullanıcı'}
</span>
                    </td>
                    <td>
                        <span class="badge ${user.status === 'active' ? 'bg-success' : 'bg-danger'}">
                            ${user.status === 'active' ? 'Aktif' : 'Pasif'}
                        </span>
                    </td>
                    <td>
                        <small>${formatDate(user.created_at)}</small>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="#" onclick="editUser(${user.id})">
                                        <i class="fas fa-edit me-2"></i>Düzenle
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="changeUserPassword(${user.id})">
                                        <i class="fas fa-key me-2"></i>Şifre Değiştir
                                    </a>
                                </li>
                                ${user.role !== 'operator' ? `
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item ${user.status === 'active' ? 'text-warning' : 'text-success'}" 
                                       href="#" onclick="confirmToggleUserStatus(${user.id}, '${user.status === 'active' ? 'inactive' : 'active'}', '${user.username}')">
                                        <i class="fas fa-user-${user.status === 'active' ? 'slash' : 'check'} me-2"></i>
                                        ${user.status === 'active' ? 'Pasif Yap' : 'Aktif Yap'}
                                    </a>
                                </li>
                                ` : ''}
                            </ul>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('tr-TR', {
                day: '2-digit',
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alert);
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 5000);
        }
        function confirmToggleUserStatus(userId, newStatus, username) {
            if (newStatus === 'inactive') {
                showConfirmationDialog(
                    'Kullanıcıyı Pasif Yap',
                    `<strong>${username}</strong> kullanıcısını pasif yapmak istediğinizden emin misiniz?<br><br>
                    <small class="text-muted">Pasif kullanıcılar sisteme giriş yapamaz.</small>`,
                    'Evet, Pasif Yap',
                    'İptal',
                    () => toggleUserStatus(userId, newStatus)
                );
            } else {
                toggleUserStatus(userId, newStatus);
            }
        }
        function showConfirmationDialog(title, message, confirmText, cancelText, confirmCallback) {
            const overlay = document.createElement('div');
            overlay.className = 'custom-popup-overlay';
            
            const dialog = document.createElement('div');
            dialog.className = 'confirmation-dialog';
            dialog.innerHTML = `
                <h5><i class="fas fa-exclamation-triangle me-3"></i>${title}</h5>
                <div class="mb-4">${message}</div>
                <div>
                    <button type="button" class="btn btn-secondary" onclick="closeConfirmationDialog()">
                        <i class="fas fa-times me-2"></i>${cancelText}
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmAction()">
                        <i class="fas fa-check me-2"></i>${confirmText}
                    </button>
                </div>
            `;
            
            document.body.appendChild(overlay);
            document.body.appendChild(dialog);
            window.currentConfirmCallback = confirmCallback;
            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    closeConfirmationDialog();
                }
            };
        }
        function closeConfirmationDialog() {
            const overlay = document.querySelector('.custom-popup-overlay');
            const dialog = document.querySelector('.confirmation-dialog');
            
            if (overlay) overlay.remove();
            if (dialog) dialog.remove();
            window.currentConfirmCallback = null;
        }
        function confirmAction() {
            if (window.currentConfirmCallback) {
                window.currentConfirmCallback();
            }
            closeConfirmationDialog();
        }
        function toggleUserStatus(userId, newStatus) {
            const formData = new FormData();
            formData.append('action', 'toggle_user_status');
            formData.append('user_id', userId);
            formData.append('status', newStatus);
            
            fetch('system_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    loadUsers(); 
                } else {
                    showAlert('danger', 'Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Bir hata oluştu');
            });
        }
        function editUser(userId) {
            const formData = new FormData();
            formData.append('action', 'get_user');
            formData.append('user_id', userId);
            
            fetch('system_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showEditUserPopup(data.data);
                } else {
                    showAlert('danger', 'Kullanıcı bulunamadı');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Bir hata oluştu');
            });
        }
        function showEditUserPopup(user) {
            const overlay = document.createElement('div');
            overlay.className = 'custom-popup-overlay';
            
            const popup = document.createElement('div');
            popup.className = 'custom-popup';
            
            popup.innerHTML = `
                <h5><i class="fas fa-user-edit me-3"></i>Kullanıcı Düzenle</h5>
                <form id="edit-user-popup-form">
                    <input type="hidden" value="${user.id}" name="user_id">
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" value="${user.username}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" value="${user.full_name}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="${user.email || ''}">
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light" onclick="closeCustomPopup()">
                            <i class="fas fa-times me-2"></i>İptal
                        </button>
                        <button type="submit" class="btn btn-light" style="background: white; color: #764ba2; font-weight: 700;">
                            <i class="fas fa-save me-2"></i>Kaydet
                        </button>
                    </div>
                </form>
            `;
            
            document.body.appendChild(overlay);
            document.body.appendChild(popup);
            document.getElementById('edit-user-popup-form').addEventListener('submit', function(e) {
                e.preventDefault();
                saveUserChanges(this);
            });
            
            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    closeCustomPopup();
                }
            };
        }
        function saveUserChanges(form) {
            const formData = new FormData(form);
            formData.append('action', 'update_user');
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Kaydediliyor...';
            
            fetch('system_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    closeCustomPopup();
                    loadUsers();
                } else {
                    showAlert('danger', 'Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Bir hata oluştu');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }
        function changeUserPassword(userId) {
            const formData = new FormData();
            formData.append('action', 'get_user');
            formData.append('user_id', userId);
            
            fetch('system_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showChangePasswordPopup(data.data);
                } else {
                    showAlert('danger', 'Kullanıcı bulunamadı');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Bir hata oluştu');
            });
        }
        function showChangePasswordPopup(user) {
            const overlay = document.createElement('div');
            overlay.className = 'custom-popup-overlay';
            
            const popup = document.createElement('div');
            popup.className = 'custom-popup';
            
            popup.innerHTML = `
                <h5><i class="fas fa-key me-3"></i>Şifre Değiştir</h5>
                <form id="change-password-popup-form">
                    <input type="hidden" value="${user.id}" name="user_id">
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı</label>
                        <input type="text" class="form-control" value="${user.username} (${user.full_name})" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control" name="new_password" required minlength="8" id="popup-new-password">
                        <small class="text-light">Şifre en az 8 karakter olmalı</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Şifre Tekrar</label>
                        <input type="password" class="form-control" required id="popup-confirm-password">
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light" onclick="closeCustomPopup()">
                            <i class="fas fa-times me-2"></i>İptal
                        </button>
                        <button type="submit" class="btn btn-light" style="background: white; color: #764ba2; font-weight: 700;">
                            <i class="fas fa-key me-2"></i>Şifre Değiştir
                        </button>
                    </div>
                </form>
            `;
            
            document.body.appendChild(overlay);
            document.body.appendChild(popup);
            const newPasswordInput = document.getElementById('popup-new-password');
            const confirmPasswordInput = document.getElementById('popup-confirm-password');
            
            confirmPasswordInput.addEventListener('input', function() {
                if (newPasswordInput.value !== this.value && this.value.length > 0) {
                    this.style.borderColor = '#ff6b6b';
                    this.style.backgroundColor = 'rgba(255, 107, 107, 0.1)';
                } else {
                    this.style.borderColor = '';
                    this.style.backgroundColor = 'rgba(255,255,255,0.9)';
                }
            });
            document.getElementById('change-password-popup-form').addEventListener('submit', function(e) {
                e.preventDefault();
                savePasswordChange(this);
            });
            
            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    closeCustomPopup();
                }
            };
        }
        function savePasswordChange(form) {
            const newPassword = form.querySelector('[name="new_password"]').value;
            const confirmPassword = document.getElementById('popup-confirm-password').value;
            
            if (newPassword !== confirmPassword) {
                showAlert('danger', 'Şifreler eşleşmiyor');
                return;
            }
            
            if (newPassword.length < 8) {
                showAlert('danger', 'Şifre en az 8 karakter olmalı');
                return;
            }
            
            const formData = new FormData(form);
            formData.append('action', 'change_password');
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Değiştiriliyor...';
            
            fetch('system_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    closeCustomPopup();
                } else {
                    showAlert('danger', 'Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Bir hata oluştu');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }
        function closeCustomPopup() {
            const overlay = document.querySelector('.custom-popup-overlay');
            const popup = document.querySelector('.custom-popup');
            
            if (overlay) overlay.remove();
            if (popup) popup.remove();
        }
        document.getElementById('add-operator-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_operator');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Ekleniyor...';
            
            fetch('system_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Operatör başarıyla eklendi');
                    this.reset();
                    loadUsers();
                } else {
                    showAlert('danger', 'Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Bir hata oluştu');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
        document.getElementById('add-user-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_user');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Ekleniyor...';
            
            fetch('system_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Kullanıcı başarıyla eklendi');
                    this.reset();
                    loadUsers();
                } else {
                    showAlert('danger', 'Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Bir hata oluştu');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
        document.getElementById('add-auditor-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_auditor');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Ekleniyor...';
    
    fetch('system_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Denetçi başarıyla eklendi');
            this.reset();
            loadUsers();
        } else {
            showAlert('danger', 'Hata: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Bir hata oluştu');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

        document.getElementById('add-accountant-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_accountant');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Ekleniyor...';
    
    fetch('system_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Muhasebeci başarıyla eklendi');
            this.reset();
            loadUsers();
        } else {
            showAlert('danger', 'Hata: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Bir hata oluştu');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

        document.getElementById('search-users').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            if (searchTerm === '') {
                filteredUsers = allUsers;
            } else {
                filteredUsers = allUsers.filter(user => 
                    user.username.toLowerCase().includes(searchTerm) ||
                    user.full_name.toLowerCase().includes(searchTerm) ||
                    (user.email && user.email.toLowerCase().includes(searchTerm))
                );
            }
            
            renderUsers();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCustomPopup();
                closeConfirmationDialog();
            }
        });
    </script>
</body>
</html>