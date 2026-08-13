<?php
require_once 'config.php';

requireLogin();
$userData = getUserData($_SESSION['user_id']);

if (!$userData || $userData['role'] !== 'auditor') {
    header('Location: index.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        try { requireCsrfOnPost(); } catch (Throwable $csrfErr) {
            error_log('CSRF error: ' . $csrfErr->getMessage());
            jsonResponse(false, 'İşlem geçersiz');
        }
        $action = $_POST['action'] ?? '';
        
        if ($action === 'list_companies') {
            listCompanies();
        } elseif ($action === 'list_document_types') {
            listDocumentTypes();
        } elseif ($action === 'filter_inspections') {
            filterInspections($_POST);
        } elseif ($action === 'complete_inspection') {
            completeInspection($_POST);
        } elseif ($action === 'update_inspection') {
            updateInspection($_POST);
        } elseif ($action === 'get_inspection_details') {
            if (!isset($_POST['completed_inspection_id'])) { jsonResponse(false, 'İstek geçersiz'); }
            getInspectionDetails($_POST['completed_inspection_id']);
        } elseif ($action === 'upload_file') {
            uploadInspectionFile($_POST, $_FILES);
        } elseif ($action === 'get_document_validity') {
            if (!isset($_POST['document_type_id'])) { jsonResponse(false, 'İstek geçersiz'); }
            getDocumentValidity($_POST['document_type_id']);
        } elseif ($action === 'delete_inspection_file') {
            if (!isset($_POST['file_id'])) { jsonResponse(false, 'İstek geçersiz'); }
            deleteInspectionFile($_POST['file_id']);
        } elseif ($action === 'logout') {
            handleLogout();
        } else {
            jsonResponse(false, 'Geçersiz işlem');
        }
    } catch (Throwable $e) {
        error_log('auditor_dashboard POST handler error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        jsonResponse(false, 'Bir hata oluştu');
    }
    exit();
}

 
if (isset($_GET['download_inspection_file']) && is_numeric($_GET['download_inspection_file'])) {
    try {
        $pdo = getConnection();
        $fileId = intval($_GET['download_inspection_file']);
        $auditorId = $_SESSION['user_id'];
        
      
        $stmt = $pdo->prepare('
            SELECT inf.original_file_name, inf.mime_type, inf.file_content 
            FROM inspection_files inf
            JOIN completed_inspections ci ON inf.completed_inspection_id = ci.id
            WHERE inf.id = ? AND ci.auditor_id = ?
        ');
        $stmt->execute([$fileId, $auditorId]);
        $file = $stmt->fetch();
        
        if ($file && !empty($file['file_content'])) {
            $originalName = $file['original_file_name'] ?: 'download';
            $mimeType = $file['mime_type'] ?: 'application/octet-stream';
            
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $originalName . '"');
            header('Content-Length: ' . strlen($file['file_content']));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: must-revalidate');
            echo $file['file_content'];
            exit();
        } else {
            die('Dosya bulunamadı veya indirme yetkiniz yok');
        }
    } catch (Exception $e) {
        error_log('Dosya indirme hatası: ' . $e->getMessage());
        die('Bir hata oluştu');
    }
}

function handleLogout() {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        jsonResponse(true, 'İşlem başarılı');
    } catch (Exception $e) {
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function getQuickStats($auditorId) {
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_inspections 
        FROM auditor_assignments aa 
        WHERE aa.auditor_id = ? AND aa.assignment_status != 'cancelled'
    ");
    $stmt->execute([$auditorId]);
    $totalInspections = $stmt->fetch()['total_inspections'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as today_ending
        FROM auditor_assignments aa 
        JOIN plans p ON aa.plan_id = p.id 
        WHERE aa.auditor_id = ? AND p.audit_end_date = CURDATE() 
        AND aa.assignment_status IN ('assigned', 'in_progress')
    ");
    $stmt->execute([$auditorId]);
    $todayEnding = $stmt->fetch()['today_ending'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as upcoming
        FROM auditor_assignments aa 
        JOIN plans p ON aa.plan_id = p.id 
        WHERE aa.auditor_id = ? AND p.audit_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
        AND aa.assignment_status IN ('assigned', 'in_progress')
    ");
    $stmt->execute([$auditorId]);
    $upcoming = $stmt->fetch()['upcoming'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as overdue
        FROM auditor_assignments aa 
        JOIN plans p ON aa.plan_id = p.id 
        WHERE aa.auditor_id = ? AND p.audit_end_date < CURDATE()
        AND aa.assignment_status IN ('assigned', 'in_progress')
    ");
    $stmt->execute([$auditorId]);
    $overdue = $stmt->fetch()['overdue'];
    
    return [
        'total_inspections' => $totalInspections,
        'today_ending' => $todayEnding,
        'upcoming' => $upcoming,
        'overdue' => $overdue
    ];
}

function getAssignedInspections($auditorId, $filters = []) {
    $pdo = getConnection();
    
    $sql = "
        SELECT 
            aa.id as assignment_id,
            aa.assignment_date,
            aa.assignment_status,
            aa.assignment_notes,
            aa.completed_inspection_id,
            p.id as plan_id,
            p.audit_start_date,
            p.audit_end_date,
            p.inspection_type,
            p.completion_status,
            c.id as company_id,
            c.short_name as company_name,
            c.trade_name,
            cert.document_number,
            cert.id as certification_id,
            cert.certification_organization,
            dt.name as document_type_name,
            dt.standard,
            dt.validity_period,
            nci.inspection_title,
            nci.id as non_certified_id,
            DATEDIFF(p.audit_end_date, CURDATE()) as days_remaining,
            ci.inspection_result,
            ci.inspection_notes,
            ci.inspection_date
        FROM auditor_assignments aa
        JOIN plans p ON aa.plan_id = p.id
        JOIN companies c ON p.company_id = c.id
        LEFT JOIN certifications cert ON p.certification_id = cert.id
        LEFT JOIN document_types dt ON cert.document_type_id = dt.id
        LEFT JOIN non_certified_inspections nci ON p.non_certified_inspection_id = nci.id
        LEFT JOIN completed_inspections ci ON aa.completed_inspection_id = ci.id
        WHERE aa.auditor_id = ? AND aa.assignment_status != 'cancelled'
    ";
    
    $params = [$auditorId];
    
    if (!empty($filters['company_id'])) {
        $sql .= " AND c.id = ?";
        $params[] = $filters['company_id'];
    }
    
    if (!empty($filters['document_type_id'])) {
        $sql .= " AND dt.id = ?";
        $params[] = $filters['document_type_id'];
    }
    
    if (!empty($filters['start_date'])) {
        $sql .= " AND p.audit_start_date >= ?";
        $params[] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $sql .= " AND p.audit_end_date <= ?";
        $params[] = $filters['end_date'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND aa.assignment_status = ?";
        $params[] = $filters['status'];
    }
    
    $sql .= " ORDER BY 
        CASE 
            WHEN aa.assignment_status != 'completed' AND p.audit_end_date >= CURDATE() THEN 0
            WHEN aa.assignment_status = 'completed' THEN 1
            WHEN aa.assignment_status != 'completed' AND p.audit_end_date < CURDATE() THEN 2
            ELSE 3
        END ASC,
        CASE 
            WHEN aa.assignment_status != 'completed' AND p.audit_end_date >= CURDATE() THEN DATEDIFF(p.audit_end_date, CURDATE())
            WHEN aa.assignment_status = 'completed' THEN p.audit_end_date
            ELSE NULL
        END ASC,
        p.audit_end_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
function getInspectionDetails($completedInspectionId) {
    try {
        $pdo = getConnection();
        $auditorId = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                ci.*,
                p.inspection_type,
                cert.document_type_id,
                cert.accreditation_type,
                cert.certification_organization,
                cert.document_number as certificate_number,
                cert.level,
                cert.scope,
                cert.issue_date,
                cert.expiry_date,
                c.short_name as company_name,
                dt.name as document_type_name,
                dt.standard,
                dt.validity_period
            FROM completed_inspections ci
            JOIN plans p ON ci.plan_id = p.id
            JOIN companies c ON ci.company_id = c.id
            LEFT JOIN certifications cert ON ci.certification_id = cert.id
            LEFT JOIN document_types dt ON cert.document_type_id = dt.id
            WHERE ci.id = ? AND ci.auditor_id = ?
        ");
        $stmt->execute([$completedInspectionId, $auditorId]);
        $inspection = $stmt->fetch();
        
        if (!$inspection) {
            jsonResponse(false, 'Kayıt bulunamadı');
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT id, original_file_name, file_name, file_size, file_category, description, created_at
            FROM inspection_files 
            WHERE completed_inspection_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$completedInspectionId]);
        $files = $stmt->fetchAll();
        
        $inspection['files'] = $files;
        
        error_log('Inspection details: ' . json_encode($inspection));
        
        jsonResponse(true, '', $inspection);
        
    } catch (Exception $e) {
        error_log('getInspectionDetails error: ' . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function getDocumentValidity($documentTypeId) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT validity_period FROM document_types WHERE id = ?");
        $stmt->execute([$documentTypeId]);
        $docType = $stmt->fetch();
        
        if ($docType) {
            jsonResponse(true, '', $docType);
        } else {
            jsonResponse(false, 'Kayıt bulunamadı');
        }
    } catch (Exception $e) {
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function filterInspections($filters) {
    $auditorId = $_SESSION['user_id'];
    $inspections = getAssignedInspections($auditorId, $filters);
    
    jsonResponse(true, '', $inspections);
}

function completeInspection($data) {
    try {
        $pdo = getConnection();
        $pdo->beginTransaction();
        
        $assignmentId = $data['assignment_id'];
        $inspectionResult = $data['inspection_result'] ?? 'passed';
        $inspectionNotes = $data['inspection_notes'] ?? '';
        $auditorId = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("
            SELECT aa.*, p.*, p.company_id
            FROM auditor_assignments aa 
            JOIN plans p ON aa.plan_id = p.id 
            WHERE aa.id = ? AND aa.auditor_id = ?
        ");
        $stmt->execute([$assignmentId, $auditorId]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) {
            throw new Exception('Tetkik bulunamadı veya yetkiniz yok');
        }
        
        
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO completed_inspections 
                (plan_id, certification_id, non_certified_inspection_id, inspection_type, 
                 auditor_id, company_id, inspection_date) 
                VALUES (?, ?, ?, ?, ?, ?, CURDATE())"
            );
            
            $stmt->execute([
                $assignment['plan_id'],
                $assignment['certification_id'],
                $assignment['non_certified_inspection_id'],
                $assignment['inspection_type'],
                $auditorId,
                $assignment['company_id']
            ]);
        } catch (Exception $e) {
            error_log('completed_inspections insert fallback: ' . $e->getMessage());
            $stmt = $pdo->prepare(
                "INSERT INTO completed_inspections 
                (plan_id, inspection_type, auditor_id, company_id, inspection_date) 
                VALUES (?, ?, ?, ?, CURDATE())"
            );
            $stmt->execute([
                $assignment['plan_id'],
                $assignment['inspection_type'],
                $auditorId,
                $assignment['company_id']
            ]);
        }
        
        $completedInspectionId = $pdo->lastInsertId();
        
        
        try {
            $stmt = $pdo->prepare("UPDATE completed_inspections SET inspection_notes = ?, inspection_result = ? WHERE id = ?");
            $stmt->execute([$inspectionNotes, $inspectionResult, $completedInspectionId]);
        } catch (Exception $e) { }

        if ($assignment['inspection_type'] === 'non_certified' && $inspectionResult === 'passed') {
            $documentTypeId = $data['document_type_id'] ?? null;
            $accreditationType = trim($data['accreditation_type'] ?? '');
            $certificateNumber = $data['certificate_number'] ?? null;
            $level = $data['level'] ?? null;
            $issueDate = $data['issue_date'] ?? date('Y-m-d');
            $scope = $data['scope'] ?? '';

            if (!$documentTypeId || !$certificateNumber || !$issueDate || trim($scope) === '') {
                throw new Exception('Gerekli sertifika alanları eksik. Lütfen belge türü, numarası, basım tarihi ve kapsamı doldurun.');
            }
            
            $stmt = $pdo->prepare("SELECT validity_period FROM document_types WHERE id = ?");
            $stmt->execute([$documentTypeId]);
            $docType = $stmt->fetch();
            if (!$docType || !isset($docType['validity_period'])) {
                throw new Exception('Seçilen belge türü bulunamadı veya geçersiz.');
            }
            if ($accreditationType !== '') {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS accreditation_types (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL UNIQUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $ins = $pdo->prepare("INSERT INTO accreditation_types (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
                    $ins->execute([$accreditationType]);
                } catch (Exception $e) {
                    error_log('Akreditasyon türü ekleme hatası: ' . $e->getMessage());
                }
            }
            
            $validityYears = (int)$docType['validity_period'];
            if ($validityYears <= 0) { $validityYears = 3; }
            $expiryDate = date('Y-m-d', strtotime($issueDate . ' + ' . $validityYears . ' years'));
            
            $checkDocNumStmt = $pdo->prepare("SELECT id, status FROM certifications WHERE document_number = ?");
            $checkDocNumStmt->execute([$certificateNumber]);
            $existingCert = $checkDocNumStmt->fetch();
            if ($existingCert && $existingCert['status'] !== 'cancelled') {
                throw new Exception('Bu belge numarası zaten kullanılıyor. Lütfen farklı bir belge numarası giriniz.');
            }
            
            
            
            
            try {
                $stmt = $pdo->prepare("\n                    INSERT INTO certifications \n                    (company_id, document_type_id, accreditation_type, document_number, \n                     scope, issue_date, expiry_date, level) \n                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n                ");
                $stmt->execute([
                    $assignment['company_id'],
                    $documentTypeId,
                    $accreditationType,
                    $certificateNumber,
                    $scope,
                    $issueDate,
                    $expiryDate,
                    $level
                ]);
            } catch (Exception $e) {
                error_log('certifications tüm kolonlarla eklenemedi: ' . $e->getMessage());
                try {
                    $stmt = $pdo->prepare("\n                        INSERT INTO certifications \n                        (company_id, document_type_id, document_number, scope, issue_date, expiry_date) \n                        VALUES (?, ?, ?, ?, ?, ?)\n                    ");
                    $stmt->execute([
                        $assignment['company_id'],
                        $documentTypeId,
                        $certificateNumber,
                        $scope,
                        $issueDate,
                        $expiryDate
                    ]);
                } catch (Exception $e2) {
                    error_log('certifications minimal kolonla ekleniyor: ' . $e2->getMessage());
                    $stmt = $pdo->prepare("\n                        INSERT INTO certifications \n                        (company_id, document_type_id, document_number, issue_date, expiry_date) \n                        VALUES (?, ?, ?, ?, ?)\n                    ");
                    $stmt->execute([
                        $assignment['company_id'],
                        $documentTypeId,
                        $certificateNumber,
                        $issueDate,
                        $expiryDate
                    ]);
                }
            }
            $certificationId = $pdo->lastInsertId();
            
            
            try {
                $stmt = $pdo->prepare("\n                    UPDATE completed_inspections \n                    SET certification_id = ? \n                    WHERE id = ?\n                ");
                $stmt->execute([$certificationId, $completedInspectionId]);
            } catch (Exception $e) {
                error_log('completed_inspections.certification_id güncellenemedi: ' . $e->getMessage());
            }

            try {
                if (!empty($assignment['non_certified_inspection_id'])) {
                    $stmt = $pdo->prepare("UPDATE non_certified_inspections SET status = 'completed', updated_by = ? WHERE id = ?");
                    $stmt->execute([$auditorId, $assignment['non_certified_inspection_id']]);
                }
            } catch (Exception $e) {
                error_log('non_certified_inspections tamamlandı olarak işaretlenemedi: ' . $e->getMessage());
            }
        }
        
        
        try {
            $stmt = $pdo->prepare(
                "
                UPDATE auditor_assignments 
                SET assignment_status = 'completed', completed_inspection_id = ?, updated_by = ? 
                WHERE id = ?
            ");
            $stmt->execute([$completedInspectionId, $auditorId, $assignmentId]);
        } catch (Exception $e) {
            error_log('auditor_assignments updated_by yok: ' . $e->getMessage());
            $stmt = $pdo->prepare(
                "
                UPDATE auditor_assignments 
                SET assignment_status = 'completed', completed_inspection_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$completedInspectionId, $assignmentId]);
        }
        
        
        try {
            $stmt = $pdo->prepare(
                "
                UPDATE plans 
                SET completion_status = 'completed', updated_by = ? 
                WHERE id = ?
            ");
            $stmt->execute([$auditorId, $assignment['plan_id']]);
        } catch (Exception $e1) {
            error_log('plans.completion_status veya updated_by yok: ' . $e1->getMessage());
            try {
                $stmt = $pdo->prepare(
                    "
                    UPDATE plans 
                    SET completion_status = 'completed'
                    WHERE id = ?
                ");
                $stmt->execute([$assignment['plan_id']]);
            } catch (Exception $e2) {
                
                error_log('plans.completion_status yok, status kolonuna düşülüyor: ' . $e2->getMessage());
                try {
                    $stmt = $pdo->prepare(
                        "
                        UPDATE plans 
                        SET status = 'completed'
                        WHERE id = ?
                    ");
                    $stmt->execute([$assignment['plan_id']]);
                } catch (Exception $e3) {
                    
                    error_log('Plan durumu güncellenemedi ancak süreç devam ediyor: ' . $e3->getMessage());
                }
            }
        }
        
        if ($assignment['inspection_type'] === 'certified' && $assignment['certification_id']) {
            try {
                $stmt = $pdo->prepare("SELECT inspection_1_status, inspection_2_status FROM certifications WHERE id = ?");
                $stmt->execute([$assignment['certification_id']]);
                $certStatus = $stmt->fetch();
                if ($certStatus && array_key_exists('inspection_1_status', $certStatus)) {
                    if ($certStatus['inspection_1_status'] !== 'tamamlandi') {
                        $stmt = $pdo->prepare("UPDATE certifications SET inspection_1_status = 'tamamlandi' WHERE id = ?");
                        $stmt->execute([$assignment['certification_id']]);
                    } else if (array_key_exists('inspection_2_status', $certStatus)) {
                        $stmt = $pdo->prepare("UPDATE certifications SET inspection_2_status = 'tamamlandi' WHERE id = ?");
                        $stmt->execute([$assignment['certification_id']]);
                    }
                }
            } catch (Exception $e) { }
        }
        
        if ($pdo->inTransaction()) { $pdo->commit(); }
        
        jsonResponse(true, 'Tetkik başarıyla tamamlandı', [
            'completed_inspection_id' => $completedInspectionId
        ]);
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Tetkik tamamlama hatası: ' . $e->getMessage());
        
        if ($e->getCode() == 23000) {
            if (strpos($e->getMessage(), 'document_number') !== false) {
                jsonResponse(false, 'Kayıt mevcut. Farklı bir değer deneyin.');
            } elseif (strpos($e->getMessage(), 'company_id') !== false && strpos($e->getMessage(), 'document_type_id') !== false) {
                jsonResponse(false, 'Kayıt mevcut.');
            } else {
                jsonResponse(false, 'İşlem gerçekleştirilemedi.');
            }
        } else {
            jsonResponse(false, 'Bir hata oluştu');
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Tetkik tamamlama error: ' . $e->getMessage());
        jsonResponse(false, 'Bir hata oluştu');
    }
}

function updateInspection($data) {
    try {
        $pdo = getConnection();
        $pdo->beginTransaction();
        
        $completedInspectionId = $data['completed_inspection_id'];
        $inspectionResult = $data['inspection_result'];
        $inspectionNotes = $data['inspection_notes'] ?? '';
        $auditorId = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("
            SELECT ci.*, p.inspection_type, cert.id as certification_id
            FROM completed_inspections ci
            JOIN plans p ON ci.plan_id = p.id
            LEFT JOIN certifications cert ON ci.certification_id = cert.id
            WHERE ci.id = ? AND ci.auditor_id = ?
        ");
        $stmt->execute([$completedInspectionId, $auditorId]);
        $inspection = $stmt->fetch();
        
        if (!$inspection) {
            throw new Exception('Tetkik bulunamadı veya düzenleme yetkiniz yok');
        }
        
        $stmt = $pdo->prepare("
            UPDATE completed_inspections 
            SET inspection_result = ?, inspection_notes = ?, updated_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$inspectionResult, $inspectionNotes, $auditorId, $completedInspectionId]);
        
        if ($inspection['inspection_type'] === 'non_certified' && $inspection['certification_id']) {
            $documentTypeId = $data['document_type_id'];
            $accreditationType = trim($data['accreditation_type'] ?? '');
            if ($accreditationType !== '') {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS accreditation_types (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL UNIQUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $ins = $pdo->prepare("INSERT INTO accreditation_types (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
                    $ins->execute([$accreditationType]);
                } catch (Exception $e) {
                    error_log('Akreditasyon türü ekleme hatası: ' . $e->getMessage());
                }
            }
            $certificateNumber = $data['certificate_number'];
            $level = $data['level'] ?? null;
            $issueDate = $data['issue_date'];
            $scope = $data['scope'] ?? '';
            
            $stmt = $pdo->prepare("SELECT validity_period FROM document_types WHERE id = ?");
            $stmt->execute([$documentTypeId]);
            $docType = $stmt->fetch();
            
            $expiryDate = date('Y-m-d', strtotime($issueDate . ' + ' . $docType['validity_period'] . ' years'));
            
            $stmt = $pdo->prepare("
                UPDATE certifications 
                SET document_type_id = ?, accreditation_type = ?, document_number = ?, 
                    level = ?, scope = ?, issue_date = ?, expiry_date = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $documentTypeId,
                $accreditationType,
                $certificateNumber,
                $level,
                $scope,
                $issueDate,
                $expiryDate,
                $inspection['certification_id']
            ]);
        }
        
        if ($pdo->inTransaction()) { $pdo->commit(); }
        jsonResponse(true, 'Tetkik başarıyla güncellendi');
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        jsonResponse(false, 'Hata: ' . $e->getMessage());
    }
}

function deleteInspectionFile($fileId) {
    try {
        $pdo = getConnection();
        $auditorId = $_SESSION['user_id'];
        
        $stmt = $pdo->prepare("
            SELECT inf.*, ci.auditor_id
            FROM inspection_files inf
            JOIN completed_inspections ci ON inf.completed_inspection_id = ci.id
            WHERE inf.id = ? AND ci.auditor_id = ?
        ");
        $stmt->execute([$fileId, $auditorId]);
        $file = $stmt->fetch();
        
        if (!$file) {
            throw new Exception('Dosya bulunamadı veya silme yetkiniz yok');
        }
         
        $stmt = $pdo->prepare("DELETE FROM inspection_files WHERE id = ?");
        $stmt->execute([$fileId]);
        
        jsonResponse(true, 'Dosya başarıyla silindi');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Hata: ' . $e->getMessage());
    }
}

function uploadInspectionFile($data, $files) {
    try {
        $completedInspectionId = $data['completed_inspection_id'];
        $description = $data['description'] ?? '';
        $category = $data['category'] ?? 'document';
        
        if (!isset($files['file']) || $files['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Dosya yükleme hatası');
        }
        
        $file = $files['file'];
         
        $allowedCategories = ['document','image','other'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'document';
        }
 
        if (!isValidUpload($file['tmp_name'], $file['name'], $file['size'])) {
            throw new Exception('Geçersiz dosya: tür, boyut veya uzantı izin verilmiyor');
        }
 
        $fileContent = file_get_contents($file['tmp_name']);
        if ($fileContent === false) {
            throw new Exception('Dosya okunamadı');
        }
        
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : 'application/octet-stream';
        if ($finfo) { finfo_close($finfo); }
        
         
        if (!isAllowedFileType($file['tmp_name'])) {
            throw new Exception('İzin verilmeyen dosya türü');
        }
        
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
        $fileName = uniqid('', true) . '_' . $safeBase;
        
        $pdo = getConnection();
        
        
        $stmt = $pdo->prepare("
            INSERT INTO inspection_files 
            (completed_inspection_id, file_name, original_file_name, file_path, 
             file_size, file_type, file_category, mime_type, file_content, description, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $completedInspectionId,
            $fileName,
            $safeBase,
            null,  
            $file['size'],
            $file['type'],
            $category,
            $mimeType,
            $fileContent,
            $description,
            $_SESSION['user_id']
        ]);
        
        if (!$result) {
            throw new Exception('Veritabanına kayıt hatası: ' . print_r($stmt->errorInfo(), true));
        }
        
        jsonResponse(true, 'Dosya başarıyla yüklendi');
        
    } catch (Exception $e) {
        error_log('Dosya yükleme hatası: ' . $e->getMessage());
        jsonResponse(false, 'Hata: ' . $e->getMessage());
    }
}

$auditorId = $_SESSION['user_id'];
$quickStats = getQuickStats($auditorId);
$inspections = getAssignedInspections($auditorId);

$companies = [];
$documentTypes = [];

try {
    $pdo = getConnection();
    
    $stmt = $pdo->query("SELECT id, COALESCE(trade_name, short_name) AS name FROM companies WHERE status='active' ORDER BY name ASC");
    $companies = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, name, standard, validity_period FROM document_types ORDER BY name ASC");
    $documentTypes = $stmt->fetchAll();
    
} catch (Exception $e) {
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Denetçi Dashboard - Belgelendirme</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';
        async function fetchJson(url, options) {
            const res = await fetch(url, options);
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                return { success: false, message: text || ('HTTP ' + res.status) };
            }
        }
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            height: 50px;
        }

        .logo img {
            height: 300%;
            width: auto;
            max-height: 500px;
            object-fit: contain;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .main-content {
            padding: 30px;
        }

        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .filters-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #34495e;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(243, 156, 18, 0.3);
        }

        .inspections-section {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .inspections-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .inspections-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .inspection-item {
            padding: 25px;
            border-bottom: 1px solid #f8f9fa;
            transition: all 0.3s ease;
            position: relative;
        }

        .inspection-item:hover {
            background: #f8f9fa;
        }

        .inspection-item:last-child {
            border-bottom: none;
        }

        .inspection-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .inspection-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .inspection-company {
            color: #7f8c8d;
            font-size: 14px;
        }

        .inspection-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }

        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            min-width: 200px;
        }

        .meta-item i {
            width: 16px;
            color: #667eea;
        }

        .days-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .days-urgent {
            background: #ff6b6b;
            color: white;
        }

        .days-warning {
            background: #feca57;
            color: #2c3e50;
        }

        .days-normal {
            background: #48dbfb;
            color: white;
        }

        .days-overdue {
            background: #ff3838;
            color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .inspection-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-complete {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(46, 204, 113, 0.3);
        }
        .btn-complete.disabled, .btn-complete:disabled {
            background: #bdc3c7;
            color: #f7f7f7;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
            opacity: 0.8;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 500;
        }

        .close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
        }

        .form-section {
            margin-bottom: 25px;
        }

        .form-section h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #667eea;
            background: #f0f2ff;
        }

        .file-upload-area.dragover {
            border-color: #667eea;
            background: #e8ecff;
        }

        .uploaded-files {
            margin-top: 15px;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-remove {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            cursor: pointer;
        }

        .modal-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(46, 204, 113, 0.3);
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .no-inspections {
            text-align: center;
            padding: 50px;
            color: #7f8c8d;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #667eea;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .textarea {
            min-height: 180px;
            resize: vertical;
            font-family: inherit;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.4;
            background: white;
            width: 100%;
        }

        .form-group[style*="grid-column: 1 / -1"] {
            grid-column: 1 / -1;
        }

        .form-group[style*="grid-column: 1 / -1"] .textarea {
            width: 100%;
            max-width: 100%;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-assigned {
            background: #fff3cd;
            color: #856404;
        }

        .status-in_progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .inspection-type-badge {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
        }

        .type-certified {
            background: #e7f3ff;
            color: #0066cc;
        }

        .type-non_certified {
            background: #fff0f5;
            color: #cc0066;
        }

        .existing-files {
            margin-bottom: 20px;
        }

        .existing-file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: #e8f5e8;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .file-date {
            font-size: 11px;
            color: #666;
        }

        .searchable-select {
            position: relative;
        }

        .searchable-select .select-wrapper {
            position: relative;
        }

        .searchable-select input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            cursor: pointer;
        }

        .searchable-select input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .searchable-select .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e1e5e9;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .searchable-select .dropdown-list.show {
            display: block;
        }

        .searchable-select .dropdown-item {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
        }

        .searchable-select .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .searchable-select .dropdown-item.selected {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .searchable-select .dropdown-item.no-results {
            color: #666;
            font-style: italic;
            cursor: default;
        }

        .searchable-select .dropdown-item.no-results:hover {
            background-color: white;
        }

        .searchable-select .dropdown-arrow {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #666;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
                padding: 20px;
                gap: 15px;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .inspection-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .meta-row {
                flex-direction: column;
                gap: 8px;
            }
            
            .meta-item {
                min-width: auto;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <h2 style="margin:0;">Belgelendirme</h2>
            </div>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($userData['full_name']); ?></span>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Çıkış
                </button>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $quickStats['total_inspections']; ?></div>
                <div class="stat-label">Toplam Tetkik</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $quickStats['today_ending']; ?></div>
                <div class="stat-label">Bugün Bitenler</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $quickStats['upcoming']; ?></div>
                <div class="stat-label">Yaklaşan (10 Gün)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $quickStats['overdue']; ?></div>
                <div class="stat-label">Süresi Geçenler</div>
            </div>
        </div>

        <div class="main-content">
            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtreler
                </div>
                
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Firma</label>
                        <div class="searchable-select" id="companySearchable">
                            <div class="select-wrapper">
                                <input type="text" id="companySearch" placeholder="Firma arayın veya seçin..." autocomplete="off">
                                <span class="dropdown-arrow">▼</span>
                                <div class="dropdown-list" id="companyDropdown">
                                    <div class="dropdown-item" data-value="" data-text="Tüm Firmalar">
                                        Tüm Firmalar
                                    </div>
                                    <?php foreach ($companies as $company): ?>
                                        <div class="dropdown-item" data-value="<?php echo $company['id']; ?>" data-text="<?php echo htmlspecialchars($company['name']); ?>">
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" id="company-filter" name="company_id">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Belge Türü</label>
                        <div class="searchable-select" id="documentTypeSearchable">
                            <div class="select-wrapper">
                                <input type="text" id="documentTypeSearch" placeholder="Belge türü arayın veya seçin..." autocomplete="off">
                                <span class="dropdown-arrow">▼</span>
                                <div class="dropdown-list" id="documentTypeDropdown">
                                    <div class="dropdown-item" data-value="" data-text="Tüm Belge Türleri">
                                        Tüm Belge Türleri
                                    </div>
                                    <?php foreach ($documentTypes as $docType): ?>
                                        <div class="dropdown-item" data-value="<?php echo $docType['id']; ?>" data-text="<?php echo htmlspecialchars($docType['name']); ?> - <?php echo htmlspecialchars($docType['standard']); ?>">
                                            <?php echo htmlspecialchars($docType['name']); ?>
                                            <?php if ($docType['standard']): ?>
                                                - <?php echo htmlspecialchars($docType['standard']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" id="document-filter" name="document_type_id">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Başlangıç Tarihi</label>
                        <input type="date" id="start-date">
                    </div>
                    
                    <div class="form-group">
                        <label>Bitiş Tarihi</label>
                        <input type="date" id="end-date">
                    </div>
                    
                    <div class="form-group">
                        <label>Durum</label>
                        <select id="status-filter">
                            <option value="">Tüm Durumlar</option>
                            <option value="assigned">Atanmış</option>
                            <option value="in_progress">Devam Eden</option>
                            <option value="completed">Tamamlanmış</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <button class="btn btn-secondary" onclick="clearFilters()">
                        <i class="fas fa-eraser"></i> Temizle
                    </button>
                    <button class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-search"></i> Filtrele
                    </button>
                </div>
            </div>

            <!-- Alert Messages -->
            <div id="alert-container">
                <div class="alert alert-success" id="success-alert"></div>
                <div class="alert alert-error" id="error-alert"></div>
            </div>

            <!-- Loading -->
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <div>Yükleniyor...</div>
            </div>

            <!-- Inspections Section -->
            <div class="inspections-section">
                <div class="inspections-header">
                    <i class="fas fa-tasks"></i>
                    <span>Atanmış Tetkikler</span>
                    <span id="inspection-count">(<?php echo count($inspections); ?> tetkik)</span>
                </div>
                
                <div class="inspections-list" id="inspections-list">
                    <?php if (empty($inspections)): ?>
                        <div class="no-inspections">
                            <i class="fas fa-clipboard-list fa-3x"></i>
                            <h3>Henüz atanmış tetkik bulunmuyor</h3>
                            <p>Size atanmış tetkikler burada görünecektir.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($inspections as $inspection): ?>
                            <div class="inspection-item" data-inspection="<?php echo $inspection['assignment_id']; ?>">
                                <div class="inspection-header">
                                    <div>
                                        <div class="inspection-title">
                                            <?php if ($inspection['inspection_type'] === 'certified'): ?>
                                                <?php echo htmlspecialchars($inspection['document_type_name']); ?> - <?php echo htmlspecialchars($inspection['document_number']); ?>
                                            <?php else: ?>
                                                Tetkik
                                            <?php endif; ?>
                                        </div>
                                        <div class="inspection-company">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($inspection['company_name']); ?>
                                            <?php if ($inspection['trade_name'] && $inspection['trade_name'] !== $inspection['company_name']): ?>
                                                (<?php echo htmlspecialchars($inspection['trade_name']); ?>)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="inspection-type-badge type-<?php echo $inspection['inspection_type']; ?>">
                                            <?php echo $inspection['inspection_type'] === 'certified' ? 'Belgelendirilmiş' : 'Tetkik'; ?>
                                        </span>
                                        <span class="status-badge status-<?php echo $inspection['assignment_status']; ?>">
                                            <?php 
                                            $statusLabels = [
                                                'assigned' => 'Atanmış',
                                                'in_progress' => 'Devam Eden',
                                                'completed' => 'Tamamlanmış'
                                            ];
                                            echo $statusLabels[$inspection['assignment_status']] ?? $inspection['assignment_status']; 
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="inspection-meta">
                                    <div class="meta-row">
                                        <div class="meta-item">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>Başlangıç: <?php echo date('d.m.Y', strtotime($inspection['audit_start_date'])); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-calendar-times"></i>
                                            <span>Bitiş: <?php echo date('d.m.Y', strtotime($inspection['audit_end_date'])); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <span>
                                                <?php
                                                $daysRemaining = $inspection['days_remaining'];
                                                $badgeClass = 'days-normal';
                                                if ($daysRemaining < 0) {
                                                    $badgeClass = 'days-overdue';
                                                    echo '<span class="days-badge ' . $badgeClass . '">' . abs($daysRemaining) . ' gün geçti</span>';
                                                } elseif ($daysRemaining == 0) {
                                                    $badgeClass = 'days-urgent';
                                                    echo '<span class="days-badge ' . $badgeClass . '">Bugün bitiyor</span>';
                                                } elseif ($daysRemaining <= 3) {
                                                    $badgeClass = 'days-urgent';
                                                    echo '<span class="days-badge ' . $badgeClass . '">' . $daysRemaining . ' gün kaldı</span>';
                                                } elseif ($daysRemaining <= 7) {
                                                    $badgeClass = 'days-warning';
                                                    echo '<span class="days-badge ' . $badgeClass . '">' . $daysRemaining . ' gün kaldı</span>';
                                                } else {
                                                    echo '<span class="days-badge ' . $badgeClass . '">' . $daysRemaining . ' gün kaldı</span>';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($inspection['standard']): ?>
                                    <div class="meta-row">
                                        <div class="meta-item">
                                            <span>Standart: <?php echo htmlspecialchars($inspection['standard']); ?></span>
                                        </div>
                                        <?php if ($inspection['inspection_result']): ?>
                                        <div class="meta-item">
                                            <span>Sonuç: 
                                                <?php 
                                                $resultLabels = ['passed' => 'Başarılı', 'failed' => 'Başarısız', 'conditional' => 'Şartlı'];
                                                echo $resultLabels[$inspection['inspection_result']] ?? $inspection['inspection_result'];
                                                ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (isset($inspection['certification_organization']) && $inspection['certification_organization']): ?>
                                    <div class="meta-row">
                                        <div class="meta-item">
                                            <span>Belgelendiren Kuruluş: <?php echo htmlspecialchars($inspection['certification_organization']); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="inspection-actions">
                                    <?php if ($inspection['assignment_status'] !== 'completed'):
                                        $isOverdue = ($inspection['days_remaining'] < 0);
                                        $btnClass = $isOverdue ? 'btn-complete disabled' : 'btn-complete';
                                        $btnAttr = $isOverdue ? 'disabled' : '';
                                    ?>
                                        <button class="<?php echo $btnClass; ?>" <?php echo $btnAttr; ?> onclick="<?php echo $isOverdue ? 'return false;' : 'openCompleteModal(' . htmlspecialchars(json_encode($inspection)) . ')'; ?>">
                                            <i class="fas fa-check"></i> Tamamla
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-edit" onclick="openEditModal(<?php echo $inspection['completed_inspection_id']; ?>)">
                                            <i class="fas fa-edit"></i> Düzenle
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Complete Inspection Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Tetkik Tamamla</h3>
                <button class="close" onclick="closeModal('completeModal')">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="complete-form">
                    <input type="hidden" id="assignment-id">
                    <input type="hidden" id="inspection-type">
                    
                    
                    <div id="non-certified-fields" style="display: none;">
                        <div class="form-section">
                            <h4>Sertifika Bilgileri</h4>
                            
                            <div class="filters-grid">
                                <div class="form-group">
                                    <label>Belge Türü *</label>
                                    <div class="searchable-select" id="docTypeSearchable">
                                        <div class="select-wrapper">
                                            <input type="text" id="docTypeSearch" placeholder="Belge türü arayın veya seçin..." autocomplete="off">
                                            <span class="dropdown-arrow">▼</span>
                                            <div class="dropdown-list" id="docTypeDropdown">
                                                <div class="dropdown-item" data-value="" data-text="Belge türü seçiniz">
                                                    Belge türü seçiniz
                                                </div>
                                                <?php foreach ($documentTypes as $docType): ?>
                                                    <div class="dropdown-item" data-value="<?php echo $docType['id']; ?>" data-text="<?php echo htmlspecialchars($docType['name']); ?> - <?php echo htmlspecialchars($docType['standard']); ?>" data-validity="<?php echo $docType['validity_period']; ?>">
                                                        <?php echo htmlspecialchars($docType['name']); ?>
                                                        <?php if ($docType['standard']): ?>
                                                            - <?php echo htmlspecialchars($docType['standard']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <input type="hidden" id="doc-type-select" name="document_type_id" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Akreditasyon Türü *</label>
                                    <div class="searchable-select" id="accTypeSearchable">
                                        <div class="select-wrapper">
                                            <input type="text" id="accTypeSearch" placeholder="Akreditasyon türü arayın veya yazın..." autocomplete="off">
                                            <span class="dropdown-arrow">▼</span>
                                            <div class="dropdown-list" id="accTypeDropdown">
                                                <div class="dropdown-item" data-value="" data-text="Akreditasyon türü seçiniz">Akreditasyon türü seçiniz</div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="accreditation-type" required>
                                    </div>
                                    <div style="margin-top:8px; display:flex; gap:8px;">
                                        <input type="text" id="newAccTypeInputModal" placeholder="Yeni akreditasyon türü" style="flex:1; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
                                        <button type="button" class="btn btn-secondary" onclick="addAccType('newAccTypeInputModal','accTypeDropdown','accTypeSearch')">Ekle</button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Belgelendiren Kuruluş</label>
                                    <div class="searchable-select" id="certOrgSearchable">
                                        <div class="select-wrapper">
                                            <input type="text" id="certOrgSearch" placeholder="Belgelendiren kuruluş arayın veya yazın..." autocomplete="off">
                                            <span class="dropdown-arrow">▼</span>
                                            <div class="dropdown-list" id="certOrgDropdown">
                                                <div class="dropdown-item" data-value="" data-text="Belgelendiren kuruluş seçiniz">Belgelendiren kuruluş seçiniz</div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="certification-organization">
                                    </div>
                                    <div style="margin-top:8px; display:flex; gap:8px;">
                                        <input type="text" id="newCertOrgInputModal" placeholder="Yeni belgelendiren kuruluş" style="flex:1; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
                                        <button type="button" class="btn btn-secondary" onclick="addCertOrg('newCertOrgInputModal','certOrgDropdown','certOrgSearch')">Ekle</button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Sertifika Numarası *</label>
                                    <input type="text" id="certificate-number" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Seviye</label>
                                    <select id="level">
                                        <option value="">Seviye Yok</option>
                                        <option value="1">Seviye 1</option>
                                        <option value="2">Seviye 2</option>
                                        <option value="3">Seviye 3</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Düzenleme Tarihi *</label>
                                    <input type="date" id="issue-date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Bitiş Tarihi</label>
                                    <input type="date" id="expiry-date" readonly style="background: #f8f9fa;">
                                </div>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Kapsam *</label>
                                <textarea id="scope" class="textarea" placeholder="Sertifika kapsamını yazınız..." style="min-height:140px;" required></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ortak Alanlar -->
                    <div class="form-section">
                        <h4><i class="fas fa-clipboard-list"></i> Tetkik Sonucu</h4>
                        
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Tetkik Sonucu *</label>
                                <select id="inspection-result" required>
                                    <option value="passed">Başarılı</option>
                                    <option value="failed">Başarısız</option>
                                    <option value="conditional">Şartlı</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Tetkik Notları</label>
                            <textarea id="inspection-notes" class="textarea" placeholder="Tetkik notlarınızı yazınız..." style="min-height:160px;"></textarea>
                        </div>
                    </div>
                    
                    <!-- Dosya Yükleme -->
                    <div class="form-section">
                        <h4><i class="fas fa-file-upload"></i> Tetkik Dosyaları</h4>
                        
                        <div class="file-upload-area" id="file-upload-area">
                            <i class="fas fa-cloud-upload-alt fa-2x"></i>
                            <p>Dosyaları buraya sürükleyin veya <strong>tıklayarak seçin</strong></p>
                            <p><small>PDF, Word, Excel, Resim dosyaları desteklenir</small></p>
                            <input type="file" id="file-input" multiple style="display: none;" 
                                   accept=".pdf,.doc,.docx,.txt,.odt,.jpg,.jpeg,.png,.gif">
                        </div>
                        
                        <div class="uploaded-files" id="uploaded-files"></div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-cancel" onclick="closeModal('completeModal')">
                    <i class="fas fa-times"></i> İptal
                </button>
                <button class="btn btn-success" onclick="submitCompletion()">
                    <i class="fas fa-check"></i> Tamamla
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Inspection Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="edit-modal-title">Tetkik Düzenle</h3>
                <button class="close" onclick="closeModal('editModal')">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="edit-form">
                    <input type="hidden" id="edit-completed-inspection-id">
                    <input type="hidden" id="edit-inspection-type">
                    
                    
                    <div id="edit-non-certified-fields" style="display: none;">
                        <div class="form-section">
                            <h4>Sertifika Bilgileri</h4>
                            
                            <div class="filters-grid">
                                <div class="form-group">
                                    <label>Belge Türü *</label>
                                    <div class="searchable-select" id="editDocTypeSearchable">
                                        <div class="select-wrapper">
                                            <input type="text" id="editDocTypeSearch" placeholder="Belge türü arayın veya seçin..." autocomplete="off">
                                            <span class="dropdown-arrow">▼</span>
                                            <div class="dropdown-list" id="editDocTypeDropdown">
                                                <div class="dropdown-item" data-value="" data-text="Belge türü seçiniz">
                                                    Belge türü seçiniz
                                                </div>
                                                <?php foreach ($documentTypes as $docType): ?>
                                                    <div class="dropdown-item" data-value="<?php echo $docType['id']; ?>" data-text="<?php echo htmlspecialchars($docType['name']); ?> - <?php echo htmlspecialchars($docType['standard']); ?>" data-validity="<?php echo $docType['validity_period']; ?>">
                                                        <?php echo htmlspecialchars($docType['name']); ?>
                                                        <?php if ($docType['standard']): ?>
                                                            - <?php echo htmlspecialchars($docType['standard']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <input type="hidden" id="edit-doc-type-select" name="document_type_id" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Akreditasyon Türü *</label>
                                    <div class="searchable-select" id="editAccTypeSearchable">
                                        <div class="select-wrapper">
                                            <input type="text" id="editAccTypeSearch" placeholder="Akreditasyon türü arayın veya yazın..." autocomplete="off">
                                            <span class="dropdown-arrow">▼</span>
                                            <div class="dropdown-list" id="editAccTypeDropdown">
                                                <div class="dropdown-item" data-value="" data-text="Akreditasyon türü seçiniz">Akreditasyon türü seçiniz</div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="edit-accreditation-type" required>
                                    </div>
                                    <div style="margin-top:8px; display:flex; gap:8px;">
                                        <input type="text" id="newAccTypeInputEdit" placeholder="Yeni akreditasyon türü" style="flex:1; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
                                        <button type="button" class="btn btn-secondary" onclick="addAccType('newAccTypeInputEdit','editAccTypeDropdown','editAccTypeSearch')">Ekle</button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Belgelendiren Kuruluş</label>
                                    <div class="searchable-select" id="editCertOrgSearchable">
                                        <div class="select-wrapper">
                                            <input type="text" id="editCertOrgSearch" placeholder="Belgelendiren kuruluş arayın veya yazın..." autocomplete="off">
                                            <span class="dropdown-arrow">▼</span>
                                            <div class="dropdown-list" id="editCertOrgDropdown">
                                                <div class="dropdown-item" data-value="" data-text="Belgelendiren kuruluş seçiniz">Belgelendiren kuruluş seçiniz</div>
                                            </div>
                                        </div>
                                        <input type="hidden" id="edit-certification-organization">
                                    </div>
                                    <div style="margin-top:8px; display:flex; gap:8px;">
                                        <input type="text" id="newCertOrgInputEdit" placeholder="Yeni belgelendiren kuruluş" style="flex:1; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
                                        <button type="button" class="btn btn-secondary" onclick="addCertOrg('newCertOrgInputEdit','editCertOrgDropdown','editCertOrgSearch')">Ekle</button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Sertifika Numarası *</label>
                                    <input type="text" id="edit-certificate-number" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Seviye</label>
                                    <select id="edit-level">
                                        <option value="">Seviye Yok</option>
                                        <option value="1">Seviye 1</option>
                                        <option value="2">Seviye 2</option>
                                        <option value="3">Seviye 3</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Düzenleme Tarihi *</label>
                                    <input type="date" id="edit-issue-date" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Bitiş Tarihi</label>
                                    <input type="date" id="edit-expiry-date" readonly style="background: #f8f9fa;">
                                </div>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Kapsam *</label>
                                <textarea id="edit-scope" class="textarea" placeholder="Sertifika kapsamını yazınız..." required></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ortak Alanlar -->
                    <div class="form-section">
                        <h4><i class="fas fa-clipboard-list"></i> Tetkik Sonucu</h4>
                        
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Tetkik Sonucu *</label>
                                <select id="edit-inspection-result" required>
                                    <option value="passed">Başarılı</option>
                                    <option value="failed">Başarısız</option>
                                    <option value="conditional">Şartlı</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Tetkik Notları</label>
                            <textarea id="edit-inspection-notes" class="textarea" placeholder="Tetkik notlarınızı yazınız..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Mevcut Dosyalar -->
                    <div class="form-section">
                        <h4><i class="fas fa-files"></i> Mevcut Dosyalar</h4>
                        <div class="existing-files" id="existing-files"></div>
                    </div>
                    
                    <!-- Yeni Dosya Yükleme -->
                    <div class="form-section">
                        <h4><i class="fas fa-file-upload"></i> Yeni Dosya Ekle</h4>
                        
                        <div class="file-upload-area" id="edit-file-upload-area">
                            <i class="fas fa-cloud-upload-alt fa-2x"></i>
                            <p>Dosyaları buraya sürükleyin veya <strong>tıklayarak seçin</strong></p>
                            <p><small>PDF, Word, Excel, Resim dosyaları desteklenir</small></p>
                            <input type="file" id="edit-file-input" multiple style="display: none;" 
                                   accept=".pdf,.doc,.docx,.txt,.odt,.jpg,.jpeg,.png,.gif">
                        </div>
                        
                        <div class="uploaded-files" id="edit-uploaded-files"></div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-cancel" onclick="closeModal('editModal')">
                    <i class="fas fa-times"></i> İptal
                </button>
                <button class="btn btn-success" onclick="submitUpdate()">
                    <i class="fas fa-save"></i> Güncelle
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedFiles = [];
        let editSelectedFiles = [];
        let currentInspection = null;
        let currentEditInspection = null;

        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            initSearchableSelects();
            
            refreshAccreditationTypes('accTypeDropdown');
            refreshAccreditationTypes('editAccTypeDropdown');
            refreshCertificationOrganizations('certOrgDropdown');
            refreshCertificationOrganizations('editCertOrgDropdown');
            bindAccDropdownSelection('accTypeDropdown');
            bindAccDropdownSelection('editAccTypeDropdown');
            bindCertOrgDropdownSelection('certOrgDropdown');
            bindCertOrgDropdownSelection('editCertOrgDropdown');
        });

        function setupEventListeners() {
            setupFileUpload();
            setupEditFileUpload();

            window.onclick = function(event) {
                const completeModal = document.getElementById('completeModal');
                const editModal = document.getElementById('editModal');
                if (event.target == completeModal) {
                    closeModal('completeModal');
                }
                if (event.target == editModal) {
                    closeModal('editModal');
                }
            }

            document.getElementById('doc-type-select').addEventListener('change', function() {
                calculateExpiryDate();
            });

            document.getElementById('issue-date').addEventListener('change', function() {
                calculateExpiryDate();
            });

            document.getElementById('edit-doc-type-select').addEventListener('change', function() {
                calculateEditExpiryDate();
            });

            document.getElementById('edit-issue-date').addEventListener('change', function() {
                calculateEditExpiryDate();
            });
        }

        function initSearchableSelect(searchInputId, dropdownId, hiddenInputId, onSelectCallback = null) {
            const searchInput = document.getElementById(searchInputId);
            const dropdown = document.getElementById(dropdownId);
            const hiddenInput = document.getElementById(hiddenInputId);
            const allItems = dropdown.querySelectorAll('.dropdown-item');
            
            searchInput.addEventListener('click', function() {
                dropdown.classList.add('show');
                filterItems('');
            });
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filterItems(searchTerm);
                dropdown.classList.add('show');
            });
            
            document.addEventListener('click', function(e) {
                if (!searchInput.parentElement.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
            
            allItems.forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.dataset.value;
                    const text = this.dataset.text;
                    
                    searchInput.value = text;
                    hiddenInput.value = value;
                    dropdown.classList.remove('show');
                    
                    allItems.forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    const changeEvent = new Event('change', { bubbles: true });
                    hiddenInput.dispatchEvent(changeEvent);
                    
                    if (onSelectCallback) {
                        onSelectCallback(this);
                    }
                });
            });
            
            function filterItems(searchTerm) {
                let hasResults = false;
                
                allItems.forEach(item => {
                    const text = item.dataset.text.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = 'block';
                        hasResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                let noResultsItem = dropdown.querySelector('.no-results');
                if (!hasResults && searchTerm) {
                    if (!noResultsItem) {
                        noResultsItem = document.createElement('div');
                        noResultsItem.className = 'dropdown-item no-results';
                        noResultsItem.textContent = 'Sonuç bulunamadı';
                        dropdown.appendChild(noResultsItem);
                    }
                    noResultsItem.style.display = 'block';
                } else if (noResultsItem) {
                    noResultsItem.style.display = 'none';
                }
            }
            
            return {
                clear: function() {
                    searchInput.value = '';
                    hiddenInput.value = '';
                    allItems.forEach(i => i.classList.remove('selected'));
                },
                setValue: function(value, text) {
                    searchInput.value = text;
                    hiddenInput.value = value;
                    allItems.forEach(item => {
                        if (item.dataset.value === value) {
                            item.classList.add('selected');
                        } else {
                            item.classList.remove('selected');
                        }
                    });
                }
            };
        }

        let companySelect, documentTypeSelect, docTypeSelect, editDocTypeSelect, accTypeSelect, editAccTypeSelect, certOrgSelect, editCertOrgSelect;

        function initSearchableSelects() {
            companySelect = initSearchableSelect('companySearch', 'companyDropdown', 'company-filter');
            documentTypeSelect = initSearchableSelect('documentTypeSearch', 'documentTypeDropdown', 'document-filter');
            
            docTypeSelect = initSearchableSelect('docTypeSearch', 'docTypeDropdown', 'doc-type-select', function(selectedItem) {
                calculateExpiryDate();
            });
            editDocTypeSelect = initSearchableSelect('editDocTypeSearch', 'editDocTypeDropdown', 'edit-doc-type-select', function(selectedItem) {
                calculateEditExpiryDate();
            });
            accTypeSelect = initSearchableSelect('accTypeSearch', 'accTypeDropdown', 'accreditation-type');
            editAccTypeSelect = initSearchableSelect('editAccTypeSearch', 'editAccTypeDropdown', 'edit-accreditation-type');
            certOrgSelect = initSearchableSelect('certOrgSearch', 'certOrgDropdown', 'certification-organization');
            editCertOrgSelect = initSearchableSelect('editCertOrgSearch', 'editCertOrgDropdown', 'edit-certification-organization');
            
            document.getElementById('accTypeSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                if (searchTerm.length > 0) {
                    searchAccreditationTypes(searchTerm, 'accTypeDropdown');
                } else {
                    refreshAccreditationTypes('accTypeDropdown');
                }
            });
            
            document.getElementById('editAccTypeSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                if (searchTerm.length > 0) {
                    searchAccreditationTypes(searchTerm, 'editAccTypeDropdown');
                } else {
                    refreshAccreditationTypes('editAccTypeDropdown');
                }
            });
            
            document.getElementById('certOrgSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                if (searchTerm.length > 0) {
                    searchCertificationOrganizations(searchTerm, 'certOrgDropdown');
                } else {
                    refreshCertificationOrganizations('certOrgDropdown');
                }
            });
            
            document.getElementById('editCertOrgSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                if (searchTerm.length > 0) {
                    searchCertificationOrganizations(searchTerm, 'editCertOrgDropdown');
                } else {
                    refreshCertificationOrganizations('editCertOrgDropdown');
                }
            });
        }

        function refreshAccreditationTypes(dropdownId) {
            fetch('process_accreditation_types.php', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const dropdown = document.getElementById(dropdownId);
                    dropdown.innerHTML = '<div class="dropdown-item" data-value="" data-text="Akreditasyon türü seçiniz">Akreditasyon türü seçiniz</div>';
                    data.types.forEach(t => {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.dataset.value = t;
                        div.dataset.text = t;
                        div.innerHTML = `<span>${t}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteAccType('${t.replace(/'/g, "\'")}','${dropdownId}')">🗑</button>`;
                        dropdown.appendChild(div);
                    });
                    bindAccDropdownSelection(dropdownId);
                })
                .catch(() => {});
        }

        function searchAccreditationTypes(searchTerm, dropdownId) {
            fetch('get_accreditation_types.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const dropdown = document.getElementById(dropdownId);
                    dropdown.innerHTML = '<div class="dropdown-item" data-value="" data-text="Akreditasyon türü seçiniz">Akreditasyon türü seçiniz</div>';
                    
                    const filteredTypes = data.types.filter(type => 
                        type.toLowerCase().includes(searchTerm)
                    );
                    
                    filteredTypes.forEach(type => {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.dataset.value = type;
                        div.dataset.text = type;
                        div.innerHTML = `<span>${type}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteAccType('${type.replace(/'/g, "\\'")}','${dropdownId}')">🗑</button>`;
                        dropdown.appendChild(div);
                    });
                    
                    bindAccDropdownSelection(dropdownId);
                })
                .catch(() => {});
        }

        function searchCertificationOrganizations(searchTerm, dropdownId) {
            fetch('get_certification_organizations.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const dropdown = document.getElementById(dropdownId);
                    dropdown.innerHTML = '<div class="dropdown-item" data-value="" data-text="Belgelendiren kuruluş seçiniz">Belgelendiren kuruluş seçiniz</div>';
                    
                    const filteredOrgs = data.organizations.filter(org => 
                        org.toLowerCase().includes(searchTerm)
                    );
                    
                    filteredOrgs.forEach(org => {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.dataset.value = org;
                        div.dataset.text = org;
                        div.innerHTML = `<span>${org}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteCertOrg('${org.replace(/'/g, "\\'")}','${dropdownId}')">🗑</button>`;
                        dropdown.appendChild(div);
                    });
                    
                    fetch('process_certification_organizations.php', { method: 'POST' })
                        .then(r => r.json())
                        .then(newData => {
                            if (newData.success && newData.organizations) {
                                const filteredNewOrgs = newData.organizations.filter(org => 
                                    org.toLowerCase().includes(searchTerm)
                                );
                                filteredNewOrgs.forEach(org => {
                                    const div = document.createElement('div');
                                    div.className = 'dropdown-item';
                                    div.dataset.value = org;
                                    div.dataset.text = org;
                                    div.innerHTML = `<span>${org}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteCertOrg('${org.replace(/'/g, "\\'")}','${dropdownId}')">🗑</button>`;
                                    dropdown.appendChild(div);
                                });
                            }
                            bindCertOrgDropdownSelection(dropdownId);
                        })
                        .catch(() => bindCertOrgDropdownSelection(dropdownId));
                })
                .catch(() => {});
        }

        function refreshCertificationOrganizations(dropdownId) {
            fetch('get_certification_organizations.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const dropdown = document.getElementById(dropdownId);
                    dropdown.innerHTML = '<div class="dropdown-item" data-value="" data-text="Belgelendiren kuruluş seçiniz">Belgelendiren kuruluş seçiniz</div>';
                    
                    data.organizations.forEach(org => {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.dataset.value = org;
                        div.dataset.text = org;
                        div.innerHTML = `<span>${org}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteCertOrg('${org.replace(/'/g, "\\'")}','${dropdownId}')">🗑</button>`;
                        dropdown.appendChild(div);
                    });
                    
                    fetch('process_certification_organizations.php', { method: 'POST' })
                        .then(r => r.json())
                        .then(newData => {
                            if (newData.success && newData.organizations) {
                                newData.organizations.forEach(org => {
                                    const div = document.createElement('div');
                                    div.className = 'dropdown-item';
                                    div.dataset.value = org;
                                    div.dataset.text = org;
                                    div.innerHTML = `<span>${org}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteCertOrg('${org.replace(/'/g, "\\'")}','${dropdownId}')">🗑</button>`;
                                    dropdown.appendChild(div);
                                });
                            }
                            bindCertOrgDropdownSelection(dropdownId);
                        })
                        .catch(() => bindCertOrgDropdownSelection(dropdownId));
                })
                .catch(() => {});
        }

        function addAccType(inputId, dropdownId, searchInputId) {
            const val = (document.getElementById(inputId).value || '').trim();
            if (!val) return;
            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('name', val);
            fd.append('csrf_token', CSRF_TOKEN);
            fetch('process_accreditation_types.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(inputId).value = '';
                        refreshAccreditationTypes(dropdownId);
                        
                        const s = document.getElementById(searchInputId);
                        if (s) s.value = val;
                    } else {
                        showAlert('error', data.message || 'Ekleme başarısız');
                    }
                })
                .catch(() => {
                    showAlert('error', 'Ekleme sırasında hata');
                });
        }

        function addCertOrg(inputId, dropdownId, searchInputId) {
            const val = (document.getElementById(inputId).value || '').trim();
            if (!val) return;
            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('name', val);
            fetch('process_certification_organizations.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(inputId).value = '';
                        refreshCertificationOrganizations(dropdownId);
                        const searchInput = document.getElementById(searchInputId);
                        if (searchInput) searchInput.value = val;
                    } else {
                        showAlert('error', data.message || 'Ekleme başarısız');
                    }
                })
                .catch(() => {
                    showAlert('error', 'Ekleme sırasında hata');
                });
        }

        function deleteAccType(name, dropdownId) {
            if (!confirm(`"${name}" türünü silmek istediğinize emin misiniz?`)) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('name', name);
            fd.append('csrf_token', CSRF_TOKEN);
            fetch('process_accreditation_types.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        refreshAccreditationTypes(dropdownId);
                        showAlert('success', 'Akreditasyon türü silindi');
                    } else {
                        showAlert('error', data.message || 'Silme başarısız');
                    }
                })
                .catch(() => showAlert('error', 'Silme sırasında hata'));
        }

        function deleteCertOrg(name, dropdownId) {
            if (!confirm(`"${name}" kuruluşunu silmek istediğinize emin misiniz?`)) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('name', name);
            fetch('process_certification_organizations.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        refreshCertificationOrganizations(dropdownId);
                        showAlert('success', 'Belgelendiren kuruluş silindi');
                    } else {
                        showAlert('error', data.message || 'Silme başarısız');
                    }
                })
                .catch(() => showAlert('error', 'Silme sırasında hata'));
        }

        function bindAccDropdownSelection(dropdownId) {
            const mapping = {
                'accTypeDropdown': { searchId: 'accTypeSearch', hiddenId: 'accreditation-type' },
                'editAccTypeDropdown': { searchId: 'editAccTypeSearch', hiddenId: 'edit-accreditation-type' }
            };
            const map = mapping[dropdownId];
            const dropdown = document.getElementById(dropdownId);
            if (!map || !dropdown) return;
            dropdown.onclick = function(e) {
                const removeBtn = e.target && e.target.closest ? e.target.closest('.btn-remove') : null;
                if (removeBtn) return;
                const item = e.target.closest && e.target.closest('.dropdown-item');
                if (!item) return;
                const searchInput = document.getElementById(map.searchId);
                const hiddenInput = document.getElementById(map.hiddenId);
                const value = item.dataset.value || '';
                const text = item.dataset.text || '';
                if (searchInput) searchInput.value = text;
                if (hiddenInput) hiddenInput.value = value;
                dropdown.classList.remove('show');
                dropdown.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
            };
        }

        function bindCertOrgDropdownSelection(dropdownId) {
            const mapping = {
                'certOrgDropdown': { searchId: 'certOrgSearch', hiddenId: 'certification-organization' },
                'editCertOrgDropdown': { searchId: 'editCertOrgSearch', hiddenId: 'edit-certification-organization' }
            };
            const map = mapping[dropdownId];
            const dropdown = document.getElementById(dropdownId);
            if (!map || !dropdown) return;
            dropdown.onclick = function(e) {
                const removeBtn = e.target && e.target.closest ? e.target.closest('.btn-remove') : null;
                if (removeBtn) return;
                const item = e.target.closest && e.target.closest('.dropdown-item');
                if (!item) return;
                const searchInput = document.getElementById(map.searchId);
                const hiddenInput = document.getElementById(map.hiddenId);
                const value = item.dataset.value || '';
                const text = item.dataset.text || '';
                if (searchInput) searchInput.value = text;
                if (hiddenInput) hiddenInput.value = value;
                dropdown.classList.remove('show');
                dropdown.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
            };
        }

        function clearFilters() {
            document.getElementById('start-date').value = '';
            document.getElementById('end-date').value = '';
            document.getElementById('status-filter').value = '';
            
            if (companySelect) companySelect.clear();
            if (documentTypeSelect) documentTypeSelect.clear();
            
            applyFilters();
        }

        function applyFilters() {
            const filters = {
                action: 'filter_inspections',
                company_id: document.getElementById('company-filter').value || '',
                document_type_id: document.getElementById('document-filter').value || '',
                start_date: document.getElementById('start-date').value,
                end_date: document.getElementById('end-date').value,
                status: document.getElementById('status-filter').value
            };

            showLoading(true);

            const formData = new FormData();
            Object.keys(filters).forEach(key => {
                formData.append(key, filters[key]);
            });
            formData.append('csrf_token', CSRF_TOKEN);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateInspectionsList(data.data);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Filtreleme hatası:', error);
                showAlert('error', 'Filtreleme sırasında hata oluştu');
            })
            .finally(() => {
                showLoading(false);
            });
        }

        function updateInspectionsList(inspections) {
            const listContainer = document.getElementById('inspections-list');
            const countElement = document.getElementById('inspection-count');
            
            countElement.textContent = `(${inspections.length} tetkik)`;
            
            if (inspections.length === 0) {
                listContainer.innerHTML = `
                    <div class="no-inspections">
                        <i class="fas fa-search fa-3x"></i>
                        <h3>Filtrelere uygun tetkik bulunamadı</h3>
                        <p>Filtre kriterlerinizi değiştirerek tekrar deneyin.</p>
                    </div>
                `;
                return;
            }

            listContainer.innerHTML = '';
            
            inspections.forEach(inspection => {
                const div = document.createElement('div');
                div.className = 'inspection-item';
                div.setAttribute('data-inspection', inspection.assignment_id);
                
                const daysRemaining = inspection.days_remaining;
                let badgeClass = 'days-normal';
                let badgeText = '';
                
                if (daysRemaining < 0) {
                    badgeClass = 'days-overdue';
                    badgeText = `${Math.abs(daysRemaining)} gün geçti`;
                } else if (daysRemaining == 0) {
                    badgeClass = 'days-urgent';
                    badgeText = 'Bugün bitiyor';
                } else if (daysRemaining <= 3) {
                    badgeClass = 'days-urgent';
                    badgeText = `${daysRemaining} gün kaldı`;
                } else if (daysRemaining <= 7) {
                    badgeClass = 'days-warning';
                    badgeText = `${daysRemaining} gün kaldı`;
                } else {
                    badgeText = `${daysRemaining} gün kaldı`;
                }

                const title = inspection.inspection_type === 'certified' 
                    ? `${inspection.document_type_name} - ${inspection.document_number}`
                    : 'Tetkik';

                const typeLabel = inspection.inspection_type === 'certified' ? 'Belgelendirilmiş' : 'Tetkik';
                const statusLabels = {
                    'assigned': 'Atanmış',
                    'in_progress': 'Devam Eden',
                    'completed': 'Tamamlanmış'
                };

                const standardRow = inspection.standard ? `
                    <div class=\"meta-row\">
                        <div class=\"meta-item\">
                            <span>Standart: ${inspection.standard}</span>
                        </div>
                        ${inspection.inspection_result ? `
                        <div class=\"meta-item\">
                            <span>Sonuç: ${getResultLabel(inspection.inspection_result)}</span>
                        </div>
                        ` : ''}
                    </div>
                ` : '';

                const tradeNameText = inspection.trade_name && inspection.trade_name !== inspection.company_name 
                    ? `(${inspection.trade_name})` 
                    : '';

                div.innerHTML = `
                    <div class="inspection-header">
                        <div>
                            <div class="inspection-title">${title}</div>
                            <div class="inspection-company">
                                <i class="fas fa-building"></i> ${inspection.company_name}
                                ${tradeNameText}
                            </div>
                        </div>
                        <div>
                            <span class="inspection-type-badge type-${inspection.inspection_type}">${typeLabel}</span>
                            <span class="status-badge status-${inspection.assignment_status}">
                                ${statusLabels[inspection.assignment_status] || inspection.assignment_status}
                            </span>
                        </div>
                    </div>
                    
                    <div class="inspection-meta">
                        <div class="meta-row">
                            <div class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Başlangıç: ${new Date(inspection.audit_start_date).toLocaleDateString('tr-TR')}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar-times"></i>
                                <span>Bitiş: ${new Date(inspection.audit_end_date).toLocaleDateString('tr-TR')}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <span class="days-badge ${badgeClass}">${badgeText}</span>
                            </div>
                        </div>
                        ${standardRow}
                    </div>
                    
                    <div class="inspection-actions">
                        ${inspection.assignment_status !== 'completed' ? `
                        <button class="btn-complete ${inspection.days_remaining < 0 ? 'disabled' : ''}" ${inspection.days_remaining < 0 ? 'disabled' : ''} ${inspection.days_remaining < 0 ? '' : `onclick="openCompleteModal(${JSON.stringify(inspection).replace(/"/g, '&quot;')})"`}>
                            <i class="fas fa-check"></i> Tamamla
                        </button>
                        ` : `
                        <button class="btn-edit" onclick="openEditModal(${inspection.completed_inspection_id})">
                            <i class="fas fa-edit"></i> Düzenle
                        </button>
                        `}
                    </div>
                `;
                
                listContainer.appendChild(div);
            });
        }

        function getResultLabel(result) {
            const labels = {
                'passed': 'Başarılı',
                'failed': 'Başarısız', 
                'conditional': 'Şartlı'
            };
            return labels[result] || result;
        }

        function openCompleteModal(inspection) {
            currentInspection = inspection;
            document.getElementById('assignment-id').value = inspection.assignment_id;
            document.getElementById('inspection-type').value = inspection.inspection_type;
            
            const title = inspection.inspection_type === 'certified' 
                ? `${inspection.document_type_name} - ${inspection.document_number}`
                : 'Tetkik';
                
            document.getElementById('modal-title').textContent = `Tetkik Tamamla - ${title}`;
            
            const nonCertifiedFields = document.getElementById('non-certified-fields');
            if (inspection.inspection_type === 'non_certified') {
                nonCertifiedFields.style.display = 'block';
            } else {
                nonCertifiedFields.style.display = 'none';
            }
            
            resetCompleteForm();
            
            document.getElementById('completeModal').style.display = 'block';
        }

        function openEditModal(completedInspectionId) {
    console.log('Opening edit modal for inspection:', completedInspectionId);
    
    showLoading(true);
    
    const formData = new FormData();
    formData.append('action', 'get_inspection_details');
    formData.append('completed_inspection_id', completedInspectionId);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Received inspection data:', data); 
        
        if (data.success) {
            currentEditInspection = data.data;
            populateEditForm(data.data);
            document.getElementById('editModal').style.display = 'block';
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Tetkik detayları alma hatası:', error);
        showAlert('error', 'Tetkik detayları alınırken hata oluştu');
    })
    .finally(() => {
        showLoading(false);
    });
}

        function populateEditForm(inspection) {
    console.log('Populating edit form with:', inspection); 
    
    document.getElementById('edit-completed-inspection-id').value = inspection.id;
    document.getElementById('edit-inspection-type').value = inspection.inspection_type;
    document.getElementById('edit-modal-title').textContent = `Tetkik Düzenle - ${inspection.company_name}`;
    
    document.getElementById('edit-inspection-result').value = inspection.inspection_result || 'passed';
    document.getElementById('edit-inspection-notes').value = inspection.inspection_notes || '';
    
    const nonCertifiedFields = document.getElementById('edit-non-certified-fields');
    if (inspection.inspection_type === 'non_certified') {
        nonCertifiedFields.style.display = 'block';
        
        console.log('Setting non-certified fields:', {
            document_type_id: inspection.document_type_id,
            accreditation_type: inspection.accreditation_type,
            certificate_number: inspection.certificate_number,
            level: inspection.level,
            issue_date: inspection.issue_date,
            scope: inspection.scope
        });
        
        if (inspection.document_type_id) {
            const docTypeDropdown = document.getElementById('editDocTypeDropdown');
            const docTypeItem = docTypeDropdown.querySelector(`[data-value="${inspection.document_type_id}"]`);
            if (docTypeItem && editDocTypeSelect) {
                editDocTypeSelect.setValue(inspection.document_type_id, docTypeItem.dataset.text);
            }
        }
        if (inspection.accreditation_type) {
            document.getElementById('edit-accreditation-type').value = inspection.accreditation_type;
        }
        if (inspection.certification_organization) {
            document.getElementById('edit-certification-organization').value = inspection.certification_organization;
        }
        if (inspection.certificate_number) {
            document.getElementById('edit-certificate-number').value = inspection.certificate_number;
        }
        if (inspection.level) {
            document.getElementById('edit-level').value = inspection.level;
        }
        if (inspection.issue_date) {
            document.getElementById('edit-issue-date').value = inspection.issue_date;
        }
        if (inspection.expiry_date) {
            document.getElementById('edit-expiry-date').value = inspection.expiry_date;
        }
        if (inspection.scope) {
            document.getElementById('edit-scope').value = inspection.scope;
        }
        
        if (!document.getElementById('edit-doc-type-select').value) {
            console.warn('document_type_id bulunamadı, kontrol edin');
        }
        if (!document.getElementById('edit-accreditation-type').value) {
            console.warn('accreditation_type bulunamadı, kontrol edin');
        }
        if (!document.getElementById('edit-certificate-number').value) {
            console.warn('certificate_number bulunamadı, kontrol edin');
        }
        
    } else {
        nonCertifiedFields.style.display = 'none';
    }
    
    displayExistingFiles(inspection.files || []);
}

        function displayExistingFiles(files) {
            const container = document.getElementById('existing-files');
            container.innerHTML = '';
            
            if (files.length === 0) {
                container.innerHTML = '<p><em>Henüz dosya yüklenmemiş</em></p>';
                return;
            }
            
            files.forEach(file => {
                const div = document.createElement('div');
                div.className = 'existing-file-item';
                div.innerHTML = `
                    <div class="file-info">
                        <i class="fas fa-file"></i>
                        <a href="auditor_dashboard.php?download_inspection_file=${file.id}" style="color: inherit; text-decoration: none;">
                            <span>${file.original_file_name}</span>
                        </a>
                        <small>(${(file.file_size / 1024).toFixed(1)} KB)</small>
                        <div class="file-date">${new Date(file.created_at).toLocaleDateString('tr-TR')}</div>
                    </div>
                    <button type="button" class="btn-remove" onclick="deleteExistingFile(${file.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
                container.appendChild(div);
            });
        }

        function deleteExistingFile(fileId) {
            if (!confirm('Bu dosyayı silmek istediğinize emin misiniz?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_inspection_file');
            formData.append('file_id', fileId);
            formData.append('csrf_token', CSRF_TOKEN);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    openEditModal(currentEditInspection.id);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                console.error('Dosya silme hatası:', error);
                showAlert('error', 'Dosya silinirken hata oluştu');
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'completeModal') {
                resetCompleteForm();
                selectedFiles = [];
                currentInspection = null;
            } else if (modalId === 'editModal') {
                resetEditForm();
                editSelectedFiles = [];
                currentEditInspection = null;
            }
        }

        function resetCompleteForm() {
            document.getElementById('complete-form').reset();
            document.getElementById('expiry-date').value = '';
            document.getElementById('uploaded-files').innerHTML = '';
            selectedFiles = [];
            
            if (docTypeSelect) docTypeSelect.clear();
        }

        function resetEditForm() {
            document.getElementById('edit-form').reset();
            document.getElementById('edit-expiry-date').value = '';
            document.getElementById('edit-uploaded-files').innerHTML = '';
            document.getElementById('existing-files').innerHTML = '';
            editSelectedFiles = [];
            
            if (editDocTypeSelect) editDocTypeSelect.clear();
        }

        function calculateExpiryDate() {
            const docTypeSelect = document.getElementById('doc-type-select');
            const issueDate = document.getElementById('issue-date').value;
            
            if (!docTypeSelect.value || !issueDate) {
                document.getElementById('expiry-date').value = '';
                return;
            }
            
            const docTypeDropdown = document.getElementById('docTypeDropdown');
            const selectedItem = docTypeDropdown.querySelector(`[data-value="${docTypeSelect.value}"]`);
            const validityPeriod = selectedItem ? selectedItem.dataset.validity : null;
            
            if (validityPeriod) {
                const issueDateTime = new Date(issueDate);
                const expiryDateTime = new Date(issueDateTime);
                expiryDateTime.setFullYear(expiryDateTime.getFullYear() + parseInt(validityPeriod));
                document.getElementById('expiry-date').value = expiryDateTime.toISOString().split('T')[0];
            }
        }

        function calculateEditExpiryDate() {
            const docTypeSelect = document.getElementById('edit-doc-type-select');
            const issueDate = document.getElementById('edit-issue-date').value;
            
            if (!docTypeSelect.value || !issueDate) {
                document.getElementById('edit-expiry-date').value = '';
                return;
            }
            
            const editDocTypeDropdown = document.getElementById('editDocTypeDropdown');
            const selectedItem = editDocTypeDropdown.querySelector(`[data-value="${docTypeSelect.value}"]`);
            const validityPeriod = selectedItem ? selectedItem.dataset.validity : null;
            
            if (validityPeriod) {
                const issueDateTime = new Date(issueDate);
                const expiryDateTime = new Date(issueDateTime);
                expiryDateTime.setFullYear(expiryDateTime.getFullYear() + parseInt(validityPeriod));
                document.getElementById('edit-expiry-date').value = expiryDateTime.toISOString().split('T')[0];
            }
        }

        function setupFileUpload() {
            const fileInput = document.getElementById('file-input');
            const uploadArea = document.getElementById('file-upload-area');
            
            if (!fileInput || !uploadArea) return;
            
            uploadArea.addEventListener('click', () => fileInput.click());
            
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                const files = Array.from(e.dataTransfer.files);
                addFiles(files);
            });
            
            fileInput.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                addFiles(files);
            });
        }

        function setupEditFileUpload() {
            const fileInput = document.getElementById('edit-file-input');
            const uploadArea = document.getElementById('edit-file-upload-area');
            
            if (!fileInput || !uploadArea) return;
            
            uploadArea.addEventListener('click', () => fileInput.click());
            
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                const files = Array.from(e.dataTransfer.files);
                addEditFiles(files);
            });
            
            fileInput.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                addEditFiles(files);
            });
        }

        function addFiles(files) {
            files.forEach(file => {
                if (selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                    return; 
                }
                selectedFiles.push(file);
            });
            updateFilesList();
        }

        function addEditFiles(files) {
            files.forEach(file => {
                if (editSelectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                    return; 
                }
                editSelectedFiles.push(file);
            });
            updateEditFilesList();
        }

        function updateFilesList() {
            const container = document.getElementById('uploaded-files');
            container.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'file-item';
                div.innerHTML = `
                    <div class="file-info">
                        <i class="fas fa-file"></i>
                        <span>${file.name}</span>
                        <small>(${(file.size / 1024).toFixed(1)} KB)</small>
                    </div>
                    <button type="button" class="btn-remove" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(div);
            });
        }

        function updateEditFilesList() {
            const container = document.getElementById('edit-uploaded-files');
            container.innerHTML = '';
            
            editSelectedFiles.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'file-item';
                div.innerHTML = `
                    <div class="file-info">
                        <i class="fas fa-file"></i>
                        <span>${file.name}</span>
                        <small>(${(file.size / 1024).toFixed(1)} KB)</small>
                    </div>
                    <button type="button" class="btn-remove" onclick="removeEditFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(div);
            });
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFilesList();
        }

        function removeEditFile(index) {
            editSelectedFiles.splice(index, 1);
            updateEditFilesList();
        }

function submitCompletion() {
    const assignmentId = document.getElementById('assignment-id').value;
    const inspectionResult = document.getElementById('inspection-result').value;
    const inspectionNotes = document.getElementById('inspection-notes').value;
    
    if (!assignmentId || !inspectionResult) {
        showAlert('error', 'Lütfen gerekli alanları doldurun');
        return;
    }
    
    if (currentInspection.inspection_type === 'non_certified') {
        const docTypeId = document.getElementById('doc-type-select').value;
        const accreditationType = document.getElementById('accreditation-type').value;
        const certificateNumber = document.getElementById('certificate-number').value;
        const issueDate = document.getElementById('issue-date').value;
        const scopeVal = (document.getElementById('scope').value || '').trim();
        
        
        if (!docTypeId || !certificateNumber || !issueDate || !scopeVal) {
            showAlert('error', 'Tetkik için tüm sertifika bilgileri ve kapsam gereklidir');
            return;
        }
    }
    
    const formData = new FormData();
    formData.append('action', 'complete_inspection');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('assignment_id', assignmentId);
    formData.append('inspection_result', inspectionResult);
    formData.append('inspection_notes', inspectionNotes);
    
    if (currentInspection.inspection_type === 'non_certified') {
        formData.append('document_type_id', document.getElementById('doc-type-select').value);
        formData.append('accreditation_type', document.getElementById('accreditation-type').value);
        formData.append('certification_organization', document.getElementById('certification-organization').value);
        formData.append('certificate_number', document.getElementById('certificate-number').value);
        formData.append('level', document.getElementById('level').value);
        formData.append('issue_date', document.getElementById('issue-date').value);
        formData.append('scope', document.getElementById('scope').value);
    }
    
    showLoading(true);
    
    fetchJson('auditor_dashboard.php', { method: 'POST', body: formData, headers: { 'X-CSRF-Token': CSRF_TOKEN } })
        .then(handleCompleteResponse)
        .catch(showError);
}
function uploadSelectedFiles(completedInspectionId) {
    if (!completedInspectionId) {
        console.error('completed_inspection_id bulunamadı');
        showAlert('error', 'Dosya yükleme için tetkik ID bulunamadı');
        showLoading(false);
        return;
    }
    
    if (selectedFiles.length === 0) {
        showAlert('success', 'Tetkik başarıyla tamamlandı');
        closeModal('completeModal');
        setTimeout(() => {
            location.reload();
        }, 1500);
        showLoading(false);
        return;
    }
    
    let uploadPromises = [];
    
    selectedFiles.forEach(file => {
        const uploadFormData = new FormData();
        uploadFormData.append('action', 'upload_file');
        uploadFormData.append('csrf_token', CSRF_TOKEN);
        uploadFormData.append('completed_inspection_id', completedInspectionId);
        uploadFormData.append('file', file);
        uploadFormData.append('category', 'document');
        uploadFormData.append('description', '');
        
        const uploadPromise = fetchJson('auditor_dashboard.php', { method: 'POST', body: uploadFormData, headers: { 'X-CSRF-Token': CSRF_TOKEN } });
        
        uploadPromises.push(uploadPromise);
    });
    
    Promise.all(uploadPromises)
        .then(results => {
            const failedUploads = results.filter(result => !result.success);
            
            if (failedUploads.length > 0) {
                console.error('Bazı dosyalar yüklenemedi:', failedUploads);
                showAlert('error', `${failedUploads.length} dosya yüklenemedi`);
            } else {
                showAlert('success', 'Tetkik ve dosyalar başarıyla tamamlandı');
            }
            
            closeModal('completeModal');
            setTimeout(() => {
                location.reload();
            }, 1500);
        })
        .catch(error => {
            console.error('Dosya yükleme hatası:', error);
            showAlert('error', 'Dosya yükleme sırasında hata oluştu');
        })
        .finally(() => {
            showLoading(false);
        });
}
       function submitUpdate() {
    const completedInspectionId = document.getElementById('edit-completed-inspection-id').value;
    const inspectionResult = document.getElementById('edit-inspection-result').value;
    const inspectionNotes = document.getElementById('edit-inspection-notes').value;
    
    if (!completedInspectionId || !inspectionResult) {
        showAlert('error', 'Lütfen gerekli alanları doldurun');
        return;
    }
    
    if (currentEditInspection.inspection_type === 'non_certified') {
        const docTypeId = document.getElementById('edit-doc-type-select').value;
        const accreditationType = document.getElementById('edit-accreditation-type').value;
        const certificateNumber = document.getElementById('edit-certificate-number').value;
        const issueDate = document.getElementById('edit-issue-date').value;
        const scopeVal = (document.getElementById('edit-scope').value || '').trim();
        
        if (!docTypeId || !certificateNumber || !issueDate || !scopeVal) {
            showAlert('error', 'Tetkik için tüm sertifika bilgileri ve kapsam gereklidir');
            return;
        }
    }
    
    const formData = new FormData();
    formData.append('action', 'update_inspection');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('completed_inspection_id', completedInspectionId);
    formData.append('inspection_result', inspectionResult);
    formData.append('inspection_notes', inspectionNotes);
    
    if (currentEditInspection.inspection_type === 'non_certified') {
        formData.append('document_type_id', document.getElementById('edit-doc-type-select').value);
        formData.append('accreditation_type', document.getElementById('edit-accreditation-type').value);
        formData.append('certification_organization', document.getElementById('edit-certification-organization').value);
        formData.append('certificate_number', document.getElementById('edit-certificate-number').value);
        formData.append('level', document.getElementById('edit-level').value);
        formData.append('issue_date', document.getElementById('edit-issue-date').value);
        formData.append('scope', document.getElementById('edit-scope').value);
    }
    
    showLoading(true);
    
    fetchJson('auditor_dashboard.php', { method: 'POST', body: formData, headers: { 'X-CSRF-Token': CSRF_TOKEN } })
        .then(handleUpdateResponse)
        .catch(showError);
}
function uploadNewFilesInEdit(completedInspectionId) {
    if (editSelectedFiles.length === 0) {
        showAlert('success', 'Tetkik başarıyla güncellendi');
        closeModal('editModal');
        setTimeout(() => {
            location.reload();
        }, 1500);
        showLoading(false);
        return;
    }
    
    let uploadPromises = [];
    
    editSelectedFiles.forEach(file => {
        const uploadFormData = new FormData();
        uploadFormData.append('action', 'upload_file');
        uploadFormData.append('csrf_token', CSRF_TOKEN);
        uploadFormData.append('completed_inspection_id', completedInspectionId);
        uploadFormData.append('file', file);
        uploadFormData.append('category', 'document');
        uploadFormData.append('description', '');
        
        const uploadPromise = fetchJson('auditor_dashboard.php', { method: 'POST', body: uploadFormData, headers: { 'X-CSRF-Token': CSRF_TOKEN } });
        
        uploadPromises.push(uploadPromise);
    });
    
    Promise.all(uploadPromises)
        .then(results => {
            const failedUploads = results.filter(result => !result.success);
            
            if (failedUploads.length > 0) {
                console.error('Bazı dosyalar yüklenemedi:', failedUploads);
                showAlert('error', `${failedUploads.length} dosya yüklenemedi`);
            } else {
                showAlert('success', 'Tetkik ve dosyalar başarıyla güncellendi');
            }
            
            closeModal('editModal');
            setTimeout(() => {
                location.reload();
            }, 1500);
        })
        .catch(error => {
            console.error('Dosya yükleme hatası:', error);
            showAlert('error', 'Dosya yükleme sırasında hata oluştu');
        })
        .finally(() => {
            showLoading(false);
        });
}
        
        function handleCompleteResponse(data) {
            if (!data || !data.success) {
                showAlert('error', (data && data.message) ? data.message : 'Tetkik tamamlanırken hata oluştu');
                showLoading(false);
                return;
            }
            const completedId = (data.data && (data.data.completed_inspection_id || data.data.id)) ? (data.data.completed_inspection_id || data.data.id) : null;
            uploadSelectedFiles(completedId);
        }

        function handleUpdateResponse(data) {
            if (!data || !data.success) {
                showAlert('error', (data && data.message) ? data.message : 'Tetkik güncellenirken hata oluştu');
                showLoading(false);
                return;
            }
            const completedId = document.getElementById('edit-completed-inspection-id').value;
            uploadNewFilesInEdit(completedId);
        }

        function showError(err) {
            console.error(err);
            showAlert('error', 'İstek sırasında beklenmeyen bir hata oluştu');
            showLoading(false);
        }
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }

        function showAlert(type, message) {
            const alertElement = document.getElementById(type + '-alert');
            alertElement.textContent = message;
            alertElement.style.display = 'block';
            
            setTimeout(() => {
                alertElement.style.display = 'none';
            }, 5000);
        }

        function logout() {
            if (confirm('Çıkış yapmak istediğinize emin misiniz?')) {
                const formData = new FormData();
                formData.append('action', 'logout');
                formData.append('csrf_token', CSRF_TOKEN);
                fetch('auditor_dashboard.php', { method: 'POST', body: formData, headers: { 'X-CSRF-Token': CSRF_TOKEN } })
                    .then(r => r.json())
                    .then(data => {
                        window.location.href = 'index.html';
                    })
                    .catch(() => {
                        window.location.href = 'index.html';
                    });
            }
        }
    </script>
</body>
</html>