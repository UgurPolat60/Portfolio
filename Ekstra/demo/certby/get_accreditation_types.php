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
    
    if ($action === 'delete_from_db') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Akreditasyon türü adı boş olamaz']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE certifications SET accreditation_type = NULL WHERE accreditation_type = ?");
        $stmt->execute([$name]);
        
        echo json_encode(['success' => true, 'message' => 'Akreditasyon türü silindi']);
    } else {
        $stmt = $pdo->prepare("SELECT DISTINCT accreditation_type FROM certifications WHERE accreditation_type IS NOT NULL AND accreditation_type != '' ORDER BY accreditation_type");
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'types' => $types]);
    }
    
} catch (Exception $e) {
    error_log('Get accreditation types error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
}
