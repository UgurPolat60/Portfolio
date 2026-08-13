<?php

require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
    exit;
}

try {
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("
        SELECT id, name, standard, validity_period, interim_audit_count, created_at, updated_at
        FROM document_types 
        WHERE id = ?
    ");
    
    $stmt->execute([$id]);
    $documentType = $stmt->fetch();
    
    if (!$documentType) {
        echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'document_type' => $documentType
    ]);
    
} catch (Exception $e) {
    error_log('Document type fetch error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
}
?>