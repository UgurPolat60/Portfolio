<?php

require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Oturum geçersiz',
        'redirect' => 'index.html'
    ]);
    exit();
}

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    echo json_encode([
        'success' => false,
        'message' => 'Kayıt bulunamadı',
        'redirect' => 'index.html'
    ]);
    exit();
}

if (!in_array($userData['role'], ['operator','user'])) {
    echo json_encode([
        'success' => false,
        'message' => 'İşlem gerçekleştirilemedi',
        'redirect' => 'dashboard.php'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'İstek geçersiz'
    ]);
    exit();
}

try {
    $short_name = sanitizeInput($_POST['short_name'] ?? '');
    $trade_name = sanitizeInput($_POST['trade_name'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $invoice_address = sanitizeInput($_POST['invoice_address'] ?? '');
    $website = sanitizeInput($_POST['website'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $corporate_email = sanitizeInput($_POST['corporate_email'] ?? '');
    $authorized_person = sanitizeInput($_POST['authorized_person'] ?? '');
    $contact_person = sanitizeInput($_POST['contact_person'] ?? '');
    $contact_phone = sanitizeInput($_POST['contact_phone'] ?? '');
    $contact_email = sanitizeInput($_POST['contact_email'] ?? '');
    $tax_office = sanitizeInput($_POST['tax_office'] ?? '');
    $tax_number = sanitizeInput($_POST['tax_number'] ?? '');
    
    $errors = [];
    
    if (empty($short_name)) $errors['short_name'] = 'Zorunlu alan';
    if (empty($trade_name)) $errors['trade_name'] = 'Zorunlu alan';
    if (empty($address)) $errors['address'] = 'Zorunlu alan';
    if (empty($tax_number)) $errors['tax_number'] = 'Zorunlu alan';
    if (empty($tax_office)) $errors['tax_office'] = 'Zorunlu alan';
    if (empty($contact_person)) $errors['contact_person'] = 'Zorunlu alan';
    if (empty($contact_phone)) $errors['contact_phone'] = 'Zorunlu alan';
    if (empty($contact_email)) $errors['contact_email'] = 'Zorunlu alan';
    
    if (!empty($corporate_email) && !validateEmail($corporate_email)) {
        $errors['corporate_email'] = 'İstek geçersiz';
    }
    if (!empty($contact_email) && !validateEmail($contact_email)) {
        $errors['contact_email'] = 'İstek geçersiz';
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors['website'] = 'İstek geçersiz';
    }
    
    $tax_number_clean = preg_replace('/\D/', '', $tax_number);
    if (strlen($tax_number_clean) !== 10 && strlen($tax_number_clean) !== 11) {
        $errors['tax_number'] = 'İstek geçersiz';
    }
    
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'İstek geçersiz',
            'errors' => $errors
        ]);
        exit();
    }
    
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("SELECT id FROM companies WHERE short_name = ? AND status = 'active'");
    $stmt->execute([$short_name]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'errors' => [
                'short_name' => 'Kayıt mevcut'
            ]
        ]);
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT id FROM companies WHERE trade_name = ? AND status = 'active'");
    $stmt->execute([$trade_name]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'errors' => [
                'trade_name' => 'Kayıt mevcut'
            ]
        ]);
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT id FROM companies WHERE tax_number = ? AND status = 'active'");
    $stmt->execute([$tax_number_clean]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'errors' => [
                'tax_number' => 'Kayıt mevcut'
            ]
        ]);
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT id FROM companies WHERE tax_office = ? AND tax_number = ? AND status = 'active'");
    $stmt->execute([$tax_office, $tax_number_clean]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'errors' => [
                'tax_number' => 'Kayıt mevcut',
                'tax_office' => 'Kayıt mevcut'
            ]
        ]);
        exit();
    }
    
    if (empty($invoice_address)) {
        $invoice_address = $address;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO companies (
            short_name, trade_name, address, invoice_address, website, phone, 
            corporate_email, authorized_person, contact_person, 
            contact_phone, contact_email, tax_office, tax_number, 
            status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ");

    $result = $stmt->execute([
        $short_name,
        $trade_name,
        $address,
        $invoice_address,
        $website ?: null,
        $phone ?: null,
        $corporate_email ?: null,
        $authorized_person ?: null,
        $contact_person,
        $contact_phone,
        $contact_email,
        $tax_office,
        $tax_number_clean
    ]);

    if ($result) {
        $company_id = $pdo->lastInsertId();
        logCompanyActivity($company_id, 'create', "Yeni firma eklendi: $trade_name");
        echo json_encode([
            'success' => true,
            'message' => 'İşlem başarılı',
            'company_id' => $company_id
        ]);
        exit();
    } else {
        throw new Exception('İşlem gerçekleştirilemedi');
    }

} catch (Exception $e) {
    error_log("Company save error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu'
    ]);
    exit();
}

function logCompanyActivity($company_id, $action, $description) {
    try {
        $pdo = getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (user_id, log_type, content, ip_address, created_at) 
            VALUES (?, 'data_change', ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            "Firma ID: $company_id - $description",
            $ipAddress
        ]);
    } catch (Exception $e) {
        error_log("Log kayıt hatası: " . $e->getMessage());
    }
}
?>