<?php

require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum geçersiz']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getConnection();
    
    switch ($action) {
        case 'add':
            $checkStmt = $pdo->prepare("SELECT id FROM consultants WHERE email = ?");
            $checkStmt->execute([$_POST['email']]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Kayıt mevcut']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO consultants (first_name, last_name, email, phone, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $_SESSION['user_id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
            break;
            
        case 'update':
            $checkStmt = $pdo->prepare("SELECT id FROM consultants WHERE email = ? AND id != ?");
            $checkStmt->execute([$_POST['email'], $_POST['id']]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Kayıt mevcut']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE consultants SET 
                    first_name = ?, last_name = ?, email = ?, phone = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
            break;
            
        case 'delete':
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM certifications WHERE consultant_id = ?");
            $checkStmt->execute([$_POST['id']]);
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM consultants WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Consultant error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
}
?>