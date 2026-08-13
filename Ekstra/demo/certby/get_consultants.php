<?php

require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Oturum geçersiz']);
    exit;
}

try {
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, phone 
        FROM consultants 
        WHERE status = 'active' 
        ORDER BY first_name, last_name
    ");
    
    $stmt->execute();
    $consultants = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true, 
        'consultants' => $consultants
    ]);
    
} catch (Exception $e) {
    error_log("Consultants fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
}
?>