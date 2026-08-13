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
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS certification_organizations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    switch ($action) {
        case 'add':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Kuruluş adı boş olamaz']);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO certification_organizations (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
            $stmt->execute([$name]);
            
            echo json_encode(['success' => true, 'message' => 'Belgelendiren kuruluş eklendi']);
            break;
            
        case 'delete':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Kuruluş adı boş olamaz']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM certification_organizations WHERE name = ?");
            $stmt->execute([$name]);
            
            echo json_encode(['success' => true, 'message' => 'Belgelendiren kuruluş silindi']);
            break;
            
        default:
            $stmt = $pdo->prepare("SELECT name FROM certification_organizations ORDER BY name");
            $stmt->execute();
            $organizations = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode(['success' => true, 'organizations' => $organizations]);
            break;
    }
    
} catch (Exception $e) {
    error_log('Certification organizations error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
}
