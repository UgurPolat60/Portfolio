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
            echo json_encode(['success' => false, 'message' => 'Kuruluş adı boş olamaz']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE certifications SET certification_organization = NULL WHERE certification_organization = ?");
        $stmt->execute([$name]);
        
        echo json_encode(['success' => true, 'message' => 'Belgelendiren kuruluş silindi']);
    } else {
        $stmt = $pdo->prepare("SELECT DISTINCT certification_organization FROM certifications WHERE certification_organization IS NOT NULL AND certification_organization != '' ORDER BY certification_organization");
        $stmt->execute();
        $organizations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'organizations' => $organizations]);
    }
    
} catch (Exception $e) {
    error_log('Get certification organizations error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
}
