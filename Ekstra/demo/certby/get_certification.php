<?php

require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Oturum geçersiz']);
    exit;
}

$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
    exit;
}

try {
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("
        SELECT c.*, 
               comp.short_name as company_name,
               dt.name as document_type_name
        FROM certifications c
        JOIN companies comp ON c.company_id = comp.id
        JOIN document_types dt ON c.document_type_id = dt.id
        WHERE c.id = ?
    ");
    
    $stmt->execute([$id]);
    $certification = $stmt->fetch();
    
    if ($certification) {
        echo json_encode([
            'success' => true, 
            'certification' => $certification
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı']);
    }
    
} catch (Exception $e) {
    error_log("Certification fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
}
?>