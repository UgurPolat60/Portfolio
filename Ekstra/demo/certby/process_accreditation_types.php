<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    requireLogin();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Oturum geçersiz']);
    exit;
}

try {
    $pdo = getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS accreditation_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO accreditation_types (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
        $stmt->execute([$name]);
    } elseif ($action === 'delete') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM accreditation_types WHERE name = ?");
        $stmt->execute([$name]);
    }

    $rows = $pdo->query("SELECT name FROM accreditation_types ORDER BY name")->fetchAll();
    $types = array_map(function($r){ return $r['name']; }, $rows);

    echo json_encode(['success' => true, 'types' => $types]);
} catch (Exception $e) {
    error_log('process_accreditation_types error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
}
?>


