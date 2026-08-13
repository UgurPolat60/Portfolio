<?php

require_once 'config.php';

error_reporting(0);

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Oturum geçersiz']);
    exit();
}

$userData = getUserData($_SESSION['user_id']);
if (!$userData || !in_array($userData['role'], ['operator','user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'İşlem gerçekleştirilemedi']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'İstek geçersiz']);
    exit();
}

$type = $input['type'];

try {
    $pdo = getConnection();
    
    $exists = false;
    $message = '';
    
    if ($type === 'tax_office_and_number') {
        $taxOffice = isset($input['tax_office']) ? trim($input['tax_office']) : '';
        $taxNumber = isset($input['tax_number']) ? trim($input['tax_number']) : '';
        $excludeId = isset($input['exclude_id']) ? intval($input['exclude_id']) : 0;
        
        if (!empty($taxOffice) && !empty($taxNumber)) {
            if ($excludeId > 0) {
                $stmt = $pdo->prepare("SELECT short_name FROM companies WHERE tax_office = ? AND tax_number = ? AND is_active = 1 AND id != ?");
                $stmt->execute([$taxOffice, $taxNumber, $excludeId]);
            } else {
                $stmt = $pdo->prepare("SELECT short_name FROM companies WHERE tax_office = ? AND tax_number = ? AND is_active = 1");
                $stmt->execute([$taxOffice, $taxNumber]);
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $exists = true;
                $message = "Kayıt mevcut";
            }
        }
    }
    elseif ($type === 'tax_number_only') {
        $taxNumber = isset($input['tax_number']) ? trim($input['tax_number']) : '';
        $excludeId = isset($input['exclude_id']) ? intval($input['exclude_id']) : 0;
        
        if (!empty($taxNumber)) {
            if ($excludeId > 0) {
                $stmt = $pdo->prepare("SELECT short_name FROM companies WHERE tax_number = ? AND status = 'active' AND id != ?");
                $stmt->execute([$taxNumber, $excludeId]);
            } else {
                $stmt = $pdo->prepare("SELECT short_name FROM companies WHERE tax_number = ? AND status = 'active'");
                $stmt->execute([$taxNumber]);
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $exists = true;
                $message = "Kayıt mevcut";
            }
        }
    }
    elseif ($type === 'short_name') {
        $shortName = isset($input['short_name']) ? trim($input['short_name']) : '';
        $excludeId = isset($input['exclude_id']) ? intval($input['exclude_id']) : 0;
        
        if (!empty($shortName)) {
            if ($excludeId > 0) {
                $stmt = $pdo->prepare("SELECT id FROM companies WHERE short_name = ? AND status = 'active' AND id != ?");
                $stmt->execute([$shortName, $excludeId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM companies WHERE short_name = ? AND status = 'active'");
                $stmt->execute([$shortName]);
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $exists = true;
                $message = "Kayıt mevcut";
            }
        }
    }
    elseif ($type === 'trade_name') {
        $tradeName = isset($input['trade_name']) ? trim($input['trade_name']) : '';
        $excludeId = isset($input['exclude_id']) ? intval($input['exclude_id']) : 0;
        
        if (!empty($tradeName)) {
            if ($excludeId > 0) {
                $stmt = $pdo->prepare("SELECT id FROM companies WHERE trade_name = ? AND status = 'active' AND id != ?");
                $stmt->execute([$tradeName, $excludeId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM companies WHERE trade_name = ? AND status = 'active'");
                $stmt->execute([$tradeName]);
            }
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $exists = true;
                $message = "Kayıt mevcut";
            }
        }
    }
    
    echo json_encode([
        'exists' => $exists,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Bir hata oluştu']);
}
?>