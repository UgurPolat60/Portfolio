<?php

require_once 'config.php';

header('Content-Type: application/json');


/* logging minimized */

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum geçersiz']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $pdo = getConnection();
    
    switch ($action) {
        case 'add':
            $checkDocNumStmt = $pdo->prepare("SELECT id, status FROM certifications WHERE document_number = ?");
            $checkDocNumStmt->execute([trim($_POST['document_number'])]);
            $existingCert = $checkDocNumStmt->fetch();
            if ($existingCert && $existingCert['status'] !== 'cancelled') {
                echo json_encode(['success' => false, 'message' => 'Kayıt mevcut']);
                exit;
            }
            
            $checkComboStmt = $pdo->prepare("SELECT id FROM certifications WHERE company_id = ? AND document_type_id = ? AND status IN ('active', 'inactive', 'suspended')");
            $checkComboStmt->execute([$_POST['company_id'], $_POST['document_type_id']]);
            if ($checkComboStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Kayıt mevcut']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO certifications (
                    company_id, document_type_id, consultant_id, document_number, scope, 
                    issue_date, expiry_date, level, status, accreditation_type, certification_organization, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $level = !empty($_POST['level']) ? $_POST['level'] : null;
            $accreditation_type = !empty($_POST['accreditation_type']) ? trim($_POST['accreditation_type']) : null;
            $certification_organization = !empty($_POST['certification_organization']) ? trim($_POST['certification_organization']) : null;
            $consultant_id = !empty($_POST['consultant_id']) ? $_POST['consultant_id'] : null;
            if ($accreditation_type) {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS accreditation_types (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL UNIQUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $ins = $pdo->prepare("INSERT INTO accreditation_types (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
                    $ins->execute([$accreditation_type]);
                } catch (Exception $e) {
                    error_log('Akreditasyon türü ekleme hatası: ' . $e->getMessage());
                }
            }
            
            $stmt->execute([
                $_POST['company_id'],
                $_POST['document_type_id'],
                $consultant_id,
                trim($_POST['document_number']),
                $_POST['scope'],
                $_POST['issue_date'],
                $_POST['expiry_date'],
                $level,
                $_POST['status'],
                $accreditation_type,
                $certification_organization,
                $_SESSION['user_id']
            ]);
            
            $updateStmt = $pdo->prepare("
                UPDATE document_types 
                SET used_count = COALESCE(used_count, 0) + 1 
                WHERE id = ?
            ");
            $updateStmt->execute([$_POST['document_type_id']]);
            
            echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
            break;
            
        case 'update':
			$currentStmt = $pdo->prepare("SELECT company_id, document_type_id FROM certifications WHERE id = ?");
			$currentStmt->execute([$_POST['id']]);
			$current = $currentStmt->fetch();

            $checkDocNumStmt = $pdo->prepare("SELECT id, status FROM certifications WHERE document_number = ? AND id != ?");
            $checkDocNumStmt->execute([trim($_POST['document_number']), $_POST['id']]);
            $existingCert = $checkDocNumStmt->fetch();
            if ($existingCert && $existingCert['status'] !== 'cancelled') {
                echo json_encode(['success' => false, 'message' => 'Kayıt mevcut']);
                exit;
            }
            
			if ($current && ($current['company_id'] != $_POST['company_id'] || $current['document_type_id'] != $_POST['document_type_id'])) {
				$checkComboStmt = $pdo->prepare("SELECT id FROM certifications WHERE company_id = ? AND document_type_id = ? AND id != ? AND status IN ('active', 'inactive', 'suspended')");
				$checkComboStmt->execute([$_POST['company_id'], $_POST['document_type_id'], $_POST['id']]);
				if ($checkComboStmt->fetch()) {
					echo json_encode(['success' => false, 'message' => 'Kayıt mevcut']);
					exit;
				}
			}
            
            $stmt = $pdo->prepare("
                UPDATE certifications SET 
                    company_id = ?, document_type_id = ?, consultant_id = ?, document_number = ?, 
                    scope = ?, issue_date = ?, expiry_date = ?, level = ?, 
                    status = ?, accreditation_type = ?, certification_organization = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $level = !empty($_POST['level']) ? $_POST['level'] : null;
            $accreditation_type = !empty($_POST['accreditation_type']) ? trim($_POST['accreditation_type']) : null;
            $certification_organization = !empty($_POST['certification_organization']) ? trim($_POST['certification_organization']) : null;
            $consultant_id = !empty($_POST['consultant_id']) ? $_POST['consultant_id'] : null;
            if ($accreditation_type) {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS accreditation_types (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL UNIQUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $ins = $pdo->prepare("INSERT INTO accreditation_types (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
                    $ins->execute([$accreditation_type]);
                } catch (Exception $e) {
                    error_log('Akreditasyon türü ekleme hatası: ' . $e->getMessage());
                }
            }
            
            $stmt->execute([
                $_POST['company_id'],
                $_POST['document_type_id'],
                $consultant_id,
                trim($_POST['document_number']),
                $_POST['scope'],
                $_POST['issue_date'],
                $_POST['expiry_date'],
                $level,
                $_POST['status'],
                $accreditation_type,
                $certification_organization,
                $_POST['id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
            break;
            
        case 'delete':
            $checkStmt = $pdo->prepare("SELECT document_type_id FROM certifications WHERE id = ?");
            $checkStmt->execute([$_POST['id']]);
            $cert = $checkStmt->fetch();
            
            if ($cert) {
                $stmt = $pdo->prepare("DELETE FROM certifications WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                $updateStmt = $pdo->prepare("
                    UPDATE document_types 
                    SET used_count = GREATEST(0, used_count - 1) 
                    WHERE id = ?
                ");
                $updateStmt->execute([$cert['document_type_id']]);
                
                echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("DB error: " . $e->getMessage());
    
    if ($e->getCode() == 23000) {
        if (strpos($e->getMessage(), 'document_number') !== false) {
            echo json_encode(['success' => false, 'message' => 'Kayıt mevcut']);
        } elseif (strpos($e->getMessage(), 'company_id') !== false && strpos($e->getMessage(), 'document_type_id') !== false) {
            echo json_encode(['success' => false, 'message' => 'Kayıt mevcut']);
        } else {
            echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
    }
} catch (Exception $e) {
    error_log("Certification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
}
?>