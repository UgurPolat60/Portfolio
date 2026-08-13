<?php

require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

try {
    $pdo = getConnection();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $name = trim($_POST['name'] ?? '');
            $standard = trim($_POST['standard'] ?? '');
            $validity_period = intval($_POST['validity_period'] ?? 0);
            $interim_audit_count = intval($_POST['interim_audit_count'] ?? 0);

            if (empty($name) || empty($standard) || $validity_period < 1 || $interim_audit_count < 0) {
                throw new Exception('İstek geçersiz');
            }
            $checkStmt = $pdo->prepare("SELECT id FROM document_types WHERE name = ? OR standard = ?");
            $checkStmt->execute([$name, $standard]);
            if ($checkStmt->fetch()) { throw new Exception('Kayıt mevcut'); }
            $stmt = $pdo->prepare("
                INSERT INTO document_types (name, standard, validity_period, interim_audit_count, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $standard, $validity_period, $interim_audit_count]);

            echo json_encode([
                'success' => true,
                'message' => 'İşlem başarılı'
            ]);
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $standard = trim($_POST['standard'] ?? '');
            $validity_period = intval($_POST['validity_period'] ?? 0);
            $interim_audit_count = intval($_POST['interim_audit_count'] ?? 0);
            if ($id < 1 || empty($name) || empty($standard) || $validity_period < 1 || $interim_audit_count < 0) {
                throw new Exception('İstek geçersiz');
            }
            $checkStmt = $pdo->prepare("SELECT id FROM document_types WHERE (name = ? OR standard = ?) AND id != ?");
            $checkStmt->execute([$name, $standard, $id]);
            if ($checkStmt->fetch()) { throw new Exception('Kayıt mevcut'); }
            $stmt = $pdo->prepare("
                UPDATE document_types 
                SET name = ?, standard = ?, validity_period = ?, interim_audit_count = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $standard, $validity_period, $interim_audit_count, $id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'İşlem başarılı'
                ]);
            } else {
                throw new Exception('Kayıt bulunamadı');
            }
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);

            if ($id < 1) { throw new Exception('İstek geçersiz'); }
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) as total_count,
                       COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count
                FROM certifications 
                WHERE document_type_id = ?
            ");
            $checkStmt->execute([$id]);
            $usage = $checkStmt->fetch();
            if ($usage['active_count'] > 0) { throw new Exception('İşlem gerçekleştirilemedi'); }
            if ($usage['total_count'] > 0) {
                $deleteRelatedStmt = $pdo->prepare("DELETE FROM certifications WHERE document_type_id = ? AND status != 'active'");
                $deleteRelatedStmt->execute([$id]);
            }
            $stmt = $pdo->prepare("DELETE FROM document_types WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'İşlem başarılı'
                ]);
            } else {
                throw new Exception('Kayıt bulunamadı');
            }
            break;

        default:
            throw new Exception('İstek geçersiz');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu'
    ]);
}
?>