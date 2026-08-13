<?php
require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}

$documentId = isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0;
$planningMode = $documentId > 0 ? 'document' : 'general';
 
if (isset($_GET['download_inspection_file']) && is_numeric($_GET['download_inspection_file'])) {
    try {
        $pdo = getConnection();
        $fileId = intval($_GET['download_inspection_file']);
        
        $stmt = $pdo->prepare('SELECT original_file_name, mime_type, file_content FROM inspection_files WHERE id = ?');
        $stmt->execute([$fileId]);
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
            die('Dosya bulunamadı');
        }
    } catch (Exception $e) {
        error_log('Dosya indirme hatası: ' . $e->getMessage());
        die('Bir hata oluştu');
    }
}

if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax'] == 'search_companies') {
        $searchTerm = isset($_POST['search_term']) ? sanitizeInput($_POST['search_term']) : '';
        
        try {
            $pdo = getConnection();
            
            if (empty($searchTerm)) {
                $sql = "SELECT id, COALESCE(trade_name, short_name) as company_name, 
                              contact_email, phone, address 
                       FROM companies 
                       WHERE status = 'active' 
                       ORDER BY created_at DESC 
                       LIMIT 20";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
            } else {
                $searchPattern = '%' . $searchTerm . '%';
                $sql = "SELECT id, COALESCE(trade_name, short_name) as company_name, 
                              contact_email, phone, address 
                       FROM companies 
                       WHERE status = 'active' 
                       AND (trade_name LIKE ? OR short_name LIKE ? OR contact_email LIKE ?)
                       ORDER BY 
                           CASE 
                               WHEN trade_name LIKE ? THEN 1
                               WHEN short_name LIKE ? THEN 2
                               ELSE 3
                           END,
                           company_name
                       LIMIT 20";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern]);
            }
            
            $companies = $stmt->fetchAll();
            echo json_encode(['success' => true, 'companies' => $companies]);
            
        } catch (Exception $e) {
            error_log("Firma arama hatası: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
        }
        exit();
    }
    
    if ($_POST['ajax'] == 'search_document_types') {
        $searchTerm = isset($_POST['search_term']) ? sanitizeInput($_POST['search_term']) : '';
        
        try {
            $pdo = getConnection();
            
            if (empty($searchTerm)) {
                $sql = "SELECT id, name, standard FROM document_types ORDER BY name LIMIT 20";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
            } else {
                $searchPattern = '%' . $searchTerm . '%';
                $sql = "SELECT id, name, standard FROM document_types 
                       WHERE name LIKE ? OR standard LIKE ?
                       ORDER BY 
                           CASE 
                               WHEN name LIKE ? THEN 1
                               WHEN standard LIKE ? THEN 2
                               ELSE 3
                           END,
                           name
                       LIMIT 20";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$searchPattern, $searchPattern, $searchPattern, $searchPattern]);
            }
            
            $documentTypes = $stmt->fetchAll();
            echo json_encode(['success' => true, 'document_types' => $documentTypes]);
            
        } catch (Exception $e) {
            error_log("Belge türü arama hatası: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
        }
        exit();
    }
    
    if ($_POST['ajax'] == 'get_company_documents') {
        $companyId = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
        
        if ($companyId > 0) {
            try {
                $pdo = getConnection();
                
                $sql = "SELECT 
                            c.id,
                            dt.name as cert_type,
                            dt.standard,
                            c.document_number,
                            c.issue_date,
                            c.expiry_date,
                            c.status,
                            c.scope
                        FROM certifications c
                        JOIN document_types dt ON c.document_type_id = dt.id
                        WHERE c.company_id = ? AND c.status = 'active'
                        ORDER BY c.created_at DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$companyId]);
                $documents = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'documents' => $documents]);
                
            } catch (Exception $e) {
                error_log("Belge getirme hatası: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }
    
    if ($_POST['ajax'] == 'get_company_plans') {
        $companyId = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
        $certificationId = isset($_POST['certification_id']) ? intval($_POST['certification_id']) : null;
        
        if ($companyId > 0) {
            try {
                $pdo = getConnection();
                
                $whereClause = "WHERE p.company_id = ?";
                $params = [$companyId];
                
                if ($certificationId) {
                    $whereClause .= " AND p.certification_id = ?";
                    $params[] = $certificationId;
                }
                
                $sql = "SELECT 
                            p.id as plan_id,
                            p.company_id,
                            p.certification_id,
                            p.non_certified_inspection_id,
                            p.inspection_type,
                            p.audit_start_date,
                            p.completion_status,
                            p.created_at,
                            COALESCE(comp.trade_name, comp.short_name) as company_name,
                            dt.name as cert_type,
                            c.document_number,
                            a.first_name,
                            a.last_name,
                            a.email,
                            aa.id as assignment_id,
                            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(a.first_name,''),' ',COALESCE(a.last_name,''))),''), NULLIF(a.full_name,''), a.username, a.email) AS auditor_name,
                            aa.assignment_status,
                            aa.assignment_notes,
                            ci.id as completed_inspection_id
                        FROM plans p
                        JOIN auditor_assignments aa ON p.id = aa.plan_id
                        JOIN users a ON aa.auditor_id = a.id
                        JOIN companies comp ON p.company_id = comp.id
                        LEFT JOIN certifications c ON p.certification_id = c.id
                        LEFT JOIN document_types dt ON c.document_type_id = dt.id
                        LEFT JOIN completed_inspections ci ON p.id = ci.plan_id
                        $whereClause
                        ORDER BY p.audit_start_date DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $plans = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'plans' => $plans]);
                
            } catch (Exception $e) {
                error_log("Plan getirme hatası: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }

    if ($_POST['ajax'] == 'get_inspection_details') {
        $completedInspectionId = isset($_POST['completed_inspection_id']) ? intval($_POST['completed_inspection_id']) : 0;
        
        if ($completedInspectionId > 0) {
            try {
                $pdo = getConnection();
                $sql = "SELECT 
                            ci.*,
                            COALESCE(comp.trade_name, comp.short_name) as company_name,
                            dt.name as cert_type,
                            c.document_number,
                            a.first_name,
                            a.last_name,
                            a.email as auditor_email,
                            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(a.first_name,''),' ',COALESCE(a.last_name,''))),''), NULLIF(a.full_name,''), a.username, a.email) AS auditor_name
                        FROM completed_inspections ci
                        JOIN companies comp ON ci.company_id = comp.id
                        JOIN users a ON ci.auditor_id = a.id
                        LEFT JOIN certifications c ON ci.certification_id = c.id
                        LEFT JOIN document_types dt ON c.document_type_id = dt.id
                        WHERE ci.id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$completedInspectionId]);
                $inspection = $stmt->fetch();
                
                if (!$inspection) {
                    echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı']);
                    exit();
                }
                
                $filesSql = "SELECT id, file_name, original_file_name, file_path, file_size, file_type, created_at 
                            FROM inspection_files 
                            WHERE completed_inspection_id = ? 
                            ORDER BY created_at DESC";
                $filesStmt = $pdo->prepare($filesSql);
                $filesStmt->execute([$completedInspectionId]);
                $files = $filesStmt->fetchAll();
                
                echo json_encode([
                    'success' => true, 
                    'inspection' => $inspection,
                    'files' => $files
                ]);
                
            } catch (Exception $e) {
                error_log("Tetkik detay hatası: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }

    if ($_POST['ajax'] == 'update_inspection') {
        $completedInspectionId = isset($_POST['completed_inspection_id']) ? intval($_POST['completed_inspection_id']) : 0;
        $inspectionNotes = isset($_POST['inspection_notes']) ? sanitizeInput($_POST['inspection_notes']) : '';
        $inspectionResult = isset($_POST['inspection_result']) ? $_POST['inspection_result'] : '';
        
        if ($completedInspectionId > 0 && !empty($inspectionNotes) && !empty($inspectionResult)) {
            try {
                $pdo = getConnection();
                
                $sql = "UPDATE completed_inspections 
                       SET inspection_notes = ?, inspection_result = ?, updated_at = NOW() 
                       WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$inspectionNotes, $inspectionResult, $completedInspectionId]);
                
                if ($result) {
                    $logContent = "Tetkik bilgileri güncellendi - ID: $completedInspectionId";
                    $logSql = "INSERT INTO system_logs (user_id, log_type, level, content, ip_address, created_at) 
                              VALUES (?, 'inspection', 'INFO', ?, ?, NOW())";
                    $logStmt = $pdo->prepare($logSql);
                    $logStmt->execute([$_SESSION['user_id'], $logContent, $_SERVER['REMOTE_ADDR'] ?? '']);
                    
                    echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                }
                
            } catch (Exception $e) {
                error_log("Tetkik güncelleme hatası: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }

    if ($_POST['ajax'] == 'add_inspection_file') {
        $completedInspectionId = isset($_POST['completed_inspection_id']) ? intval($_POST['completed_inspection_id']) : 0;
        
        if ($completedInspectionId > 0 && isset($_FILES['inspection_file'])) {
            $uploadResult = handleSingleFileUpload($completedInspectionId, $_FILES['inspection_file']);
            echo json_encode($uploadResult);
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }

    if ($_POST['ajax'] == 'remove_inspection_file') {
        $fileId = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
        
        if ($fileId > 0) {
            try {
                $pdo = getConnection();
                 
                $deleteSql = "DELETE FROM inspection_files WHERE id = ?";
                $deleteStmt = $pdo->prepare($deleteSql);
                $deleteResult = $deleteStmt->execute([$fileId]);
                
                if ($deleteResult) {
                    echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                }
                
            } catch (Exception $e) {
                error_log("Dosya silme hatası: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }
    
    if ($_POST['ajax'] == 'add_plan') {
        $auditorIds = isset($_POST['auditor_ids']) ? (array)$_POST['auditor_ids'] : [];
        $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : '';
        $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';
        $companyId = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
        $certificationId = isset($_POST['certification_id']) && $_POST['certification_id'] !== '' ? intval($_POST['certification_id']) : null;
        
        $auditorIds = array_values(array_filter(array_map('intval', $auditorIds), function($v){ return $v > 0; }));
        if (!empty($auditorIds) && !empty($startDate) && !empty($endDate) && $companyId > 0) {
            try {
                $pdo = getConnection();
                $pdo->beginTransaction();
                
                $companySql = "SELECT COALESCE(trade_name, short_name) as company_name FROM companies WHERE id = ?";
                $companyStmt = $pdo->prepare($companySql);
                $companyStmt->execute([$companyId]);
                $companyData = $companyStmt->fetch();
                
                if (!$companyData) {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı']);
                    exit();
                }
                
                if (strtotime($startDate) < strtotime('today') || strtotime($endDate) < strtotime('today')) {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
                    exit();
                }
                if (strtotime($endDate) < strtotime($startDate)) {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
                    exit();
                }
                
                $documentTypeId = null;
                $nonCertifiedInspectionId = null;
                $inspectionType = 'certified';
                
                if ($certificationId) {
                    $docTypeSql = "SELECT document_type_id FROM certifications WHERE id = ?";
                    $docTypeStmt = $pdo->prepare($docTypeSql);
                    $docTypeStmt->execute([$certificationId]);
                    $docTypeResult = $docTypeStmt->fetch();
                    $documentTypeId = $docTypeResult ? $docTypeResult['document_type_id'] : null;
                } else {
                    $inspectionType = 'non_certified';
                    
                    $nciSql = "INSERT INTO non_certified_inspections (
                                  company_id, inspection_title, inspection_description, 
                                  status, created_by, created_at
                              ) VALUES (?, ?, ?, 'active', ?, NOW())";
                    $nciStmt = $pdo->prepare($nciSql);
                    $nciResult = $nciStmt->execute([
                        $companyId,
                        'Belgesiz Tetkik - ' . date('d.m.Y', strtotime($startDate)),
                        'Genel tetkik planlaması',
                        $_SESSION['user_id']
                    ]);
                    
                    if (!$nciResult) {
                        $pdo->rollback();
                        echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                        exit();
                    }
                    
                    $nonCertifiedInspectionId = $pdo->lastInsertId();
                }
                
                $checkSql = "SELECT COUNT(*) FROM plans p
                            JOIN auditor_assignments aa ON p.id = aa.plan_id 
                            WHERE p.company_id = ? AND aa.auditor_id = ? AND p.audit_start_date = ?";
                
                $planSql = "INSERT INTO plans (
                              company_id, document_type_id, certification_id, non_certified_inspection_id,
                              inspection_type, audit_start_date, audit_end_date, 
                              completion_status, created_by, created_at
                           ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";
                $planStmt = $pdo->prepare($planSql);
                $planResult = $planStmt->execute([
                    $companyId,
                    $documentTypeId,
                    $certificationId,
                    $nonCertifiedInspectionId,
                    $inspectionType,
                    $startDate,
                    $endDate,
                    $_SESSION['user_id']
                ]);
                
                if ($planResult) {
                    $planId = $pdo->lastInsertId();
                    $assignSql = "INSERT INTO auditor_assignments (
                                     plan_id, auditor_id, assignment_date, assignment_status, 
                                     assignment_notes, created_by, created_at
                                 ) VALUES (?, ?, ?, 'assigned', ?, ?, NOW())";
                    $assignStmt = $pdo->prepare($assignSql);
                    foreach ($auditorIds as $aid) {
                        $checkParams = [$companyId, $aid, $startDate];
                        $checkAdd = '';
                        if ($certificationId) { $checkAdd = " AND p.certification_id = ?"; $checkParams[] = $certificationId; }
                        else { $checkAdd = " AND p.inspection_type = 'non_certified'"; }
                        $stmtCheck = $pdo->prepare($checkSql.$checkAdd);
                        $stmtCheck->execute($checkParams);
                        if ($stmtCheck->fetchColumn() > 0) { continue; }

                        $assignStmt->execute([
                        $planId,
                            $aid,
                            $startDate,
                        $notes,
                        $_SESSION['user_id']
                    ]);
                    }
                    
                        $planType = $certificationId ? "Belge ID: $certificationId" : "Belgesiz Tetkik";
                        $logContent = "Tetkik planı oluşturuldu - $planType, Firma: " . $companyData['company_name'] . ", Tarihler: $startDate - $endDate";
                        $logSql = "INSERT INTO system_logs (user_id, log_type, level, content, ip_address, created_at) VALUES (?, 'inspection', 'INFO', ?, ?, NOW())";
                        $logStmt = $pdo->prepare($logSql);
                        $logStmt->execute([$_SESSION['user_id'], $logContent, $_SERVER['REMOTE_ADDR'] ?? '']);
                        
                        $pdo->commit();
                        echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
                } else {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                }
                
            } catch (PDOException $e) {
                $pdo->rollback();
                error_log("Plan oluşturma hatası: " . $e->getMessage());
                
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
                $pdo->rollback();
                error_log("Plan oluşturma hatası: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }
    
    if ($_POST['ajax'] == 'complete_inspection') {
        $planId = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $inspectionNotes = isset($_POST['inspection_notes']) ? sanitizeInput($_POST['inspection_notes']) : '';
        $inspectionResult = isset($_POST['inspection_result']) ? $_POST['inspection_result'] : 'passed';
        
        if ($planId > 0) {
            try {
                $pdo = getConnection();
                $pdo->beginTransaction();
                
                $planSql = "SELECT p.*, aa.id AS assignment_id, aa.auditor_id, aa.assignment_date, 
                                  COALESCE(comp.trade_name, comp.short_name) as company_name
                           FROM plans p
                           JOIN auditor_assignments aa ON p.id = aa.plan_id
                           JOIN companies comp ON p.company_id = comp.id
                           WHERE p.id = ? AND p.completion_status = 'pending' AND p.inspection_type = 'certified'";
                $planStmt = $pdo->prepare($planSql);
                $planStmt->execute([$planId]);
                $planData = $planStmt->fetch();
                
                if (!$planData) {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı']);
                    exit();
                }
                
                $completedSql = "INSERT INTO completed_inspections (
                                    plan_id, certification_id, 
                                    inspection_type, auditor_id, company_id, 
                                    inspection_date, completion_date, inspection_notes, 
                                    inspection_result, created_by, created_at
                                ) VALUES (?, ?, 'certified', ?, ?, ?, NOW(), ?, ?, ?, NOW())";
                
                $completedStmt = $pdo->prepare($completedSql);
                $completedResult = $completedStmt->execute([
                    $planId,
                    $planData['certification_id'],
                    $planData['auditor_id'],
                    $planData['company_id'],
                    $planData['assignment_date'],
                    $inspectionNotes,
                    $inspectionResult,
                    $_SESSION['user_id']
                ]);
                
                if ($completedResult) {
                    $completedInspectionId = $pdo->lastInsertId();
                    
                    if (isset($_FILES['inspection_files']) && !empty($_FILES['inspection_files']['name'][0])) {
                        $uploadResult = handleFileUploads($completedInspectionId, $_FILES['inspection_files'], $pdo);
                        if (!$uploadResult['success']) {
                            $pdo->rollback();
                            echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                            exit();
                        }
                    }
                    
                    if ($planData['certification_id']) {
                        $certificationId = $planData['certification_id'];
                        
                        $completedCountSql = "SELECT COUNT(*) FROM completed_inspections 
                                             WHERE certification_id = ? AND inspection_result = 'passed'";
                        $completedCountStmt = $pdo->prepare($completedCountSql);
                        $completedCountStmt->execute([$certificationId]);
                        $completedCount = $completedCountStmt->fetchColumn();
                        
                        if ($completedCount == 1) {
                            $updateCertSql = "UPDATE certifications SET inspection_1_status = 'tamamlandi' WHERE id = ?";
                        } elseif ($completedCount == 2) {
                            $updateCertSql = "UPDATE certifications SET inspection_2_status = 'tamamlandi' WHERE id = ?";
                        }
                        
                        if (isset($updateCertSql)) {
                            $updateCertStmt = $pdo->prepare($updateCertSql);
                            $updateCertStmt->execute([$certificationId]);
                        }
                    }
                    
                    $updatePlanSql = "UPDATE plans SET completion_status = 'completed', updated_at = NOW() WHERE id = ?";
                    $updatePlanStmt = $pdo->prepare($updatePlanSql);
                    $updatePlanStmt->execute([$planId]);
                    
                    $updateAssignSql = "UPDATE auditor_assignments SET assignment_status = 'completed', updated_at = NOW() WHERE plan_id = ?";
                    $updateAssignStmt = $pdo->prepare($updateAssignSql);
                    $updateAssignStmt->execute([$planId]);
                    
                    $logContent = "Tetkik tamamlandı - Plan ID: $planId, Firma: " . $planData['company_name'] . ", Sonuç: $inspectionResult";
                    $logSql = "INSERT INTO system_logs (user_id, log_type, level, content, ip_address, created_at) VALUES (?, 'inspection', 'INFO', ?, ?, NOW())";
                    $logStmt = $pdo->prepare($logSql);
                    $logStmt->execute([$_SESSION['user_id'], $logContent, $_SERVER['REMOTE_ADDR'] ?? '']);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
                } else {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                }
                
            } catch (Exception $e) {
                $pdo->rollback();
                error_log("Tetkik tamamlama hatası: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }
    
    if ($_POST['ajax'] == 'complete_non_certified_inspection') {
        $planId = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $inspectionNotes = isset($_POST['inspection_notes']) ? sanitizeInput($_POST['inspection_notes']) : '';
        $inspectionResult = isset($_POST['inspection_result']) ? $_POST['inspection_result'] : 'passed';
        $createCertificate = isset($_POST['create_certificate']) && $_POST['create_certificate'] === 'true';
        
        if ($planId > 0) {
            try {
                $pdo = getConnection();
                $pdo->beginTransaction();
                
                $planSql = "SELECT p.*, aa.id AS assignment_id, aa.auditor_id, aa.assignment_date, 
                                  COALESCE(comp.trade_name, comp.short_name) as company_name
                           FROM plans p
                           JOIN auditor_assignments aa ON p.id = aa.plan_id
                           JOIN companies comp ON p.company_id = comp.id
                           WHERE p.id = ? AND p.completion_status = 'pending' AND p.inspection_type = 'non_certified'";
                $planStmt = $pdo->prepare($planSql);
                $planStmt->execute([$planId]);
                $planData = $planStmt->fetch();
                
                if (!$planData) {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'Plan bulunamadı, zaten tamamlanmış veya sertifikalı tetkik']);
                    exit();
                }
                
                $certificationId = null;
                
                if ($createCertificate) {
                    $documentTypeId = isset($_POST['document_type_id']) ? intval($_POST['document_type_id']) : 0;
                    $certificateNumber = isset($_POST['certificate_number']) ? sanitizeInput($_POST['certificate_number']) : '';
                    $accreditationType = isset($_POST['accreditation_type']) ? sanitizeInput($_POST['accreditation_type']) : null;
                    $issueDate = isset($_POST['issue_date']) ? $_POST['issue_date'] : '';
                    $expiryDate = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : '';
                    $certificateScope = isset($_POST['certificate_scope']) ? sanitizeInput($_POST['certificate_scope']) : '';
                    $certLevel = isset($_POST['cert_level']) && $_POST['cert_level'] !== '' ? intval($_POST['cert_level']) : null;
                    
                    if (!$documentTypeId || !$certificateNumber || !$issueDate || !$expiryDate) {
                        $pdo->rollback();
                        echo json_encode(['success' => false, 'message' => 'Sertifika oluşturmak için gerekli alanlar eksik']);
                        exit();
                    }
                    
                    $checkDocNumStmt = $pdo->prepare("SELECT id, status FROM certifications WHERE document_number = ?");
                    $checkDocNumStmt->execute([$certificateNumber]);
                    $existingCert = $checkDocNumStmt->fetch();
                    if ($existingCert && $existingCert['status'] !== 'cancelled') {
                        $pdo->rollback();
                        echo json_encode(['success' => false, 'message' => 'Bu belge numarası zaten kullanılıyor. Lütfen farklı bir belge numarası giriniz.']);
                        exit();
                    }
                    
                    $checkComboStmt = $pdo->prepare("SELECT id FROM certifications WHERE company_id = ? AND document_type_id = ? AND status IN ('active', 'inactive', 'suspended')");
                    $checkComboStmt->execute([$planData['company_id'], $documentTypeId]);
                    if ($checkComboStmt->fetch()) {
                        $pdo->rollback();
                        echo json_encode(['success' => false, 'message' => 'Bu firma için bu belge türünde belgelendirme zaten mevcut. Önce mevcut belgelendirmeyi iptal edin veya güncelleyin.']);
                        exit();
                    }
                    
                    $certSql = "INSERT INTO certifications (
                                   company_id, document_type_id, accreditation_type,
                                   document_number, scope, issue_date, expiry_date, 
                                   level, status, inspection_1_status, inspection_2_status, 
                                   created_by, created_at
                               ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 'tamamlandi', 'bekliyor', ?, NOW())";
                    $certStmt = $pdo->prepare($certSql);
                    $certResult = $certStmt->execute([
                        $planData['company_id'],
                        $documentTypeId,
                        $accreditationType,
                        $certificateNumber,
                        $certificateScope,
                        $issueDate,
                        $expiryDate,
                        $certLevel,
                        $_SESSION['user_id']
                    ]);
                    
                    if (!$certResult) {
                        $pdo->rollback();
                        echo json_encode(['success' => false, 'message' => 'Sertifika oluşturulamadı']);
                        exit();
                    }
                    
                    $certificationId = $pdo->lastInsertId();
                    
                    $updateDocTypeSql = "UPDATE document_types SET used_count = used_count + 1 WHERE id = ?";
                    $updateDocTypeStmt = $pdo->prepare($updateDocTypeSql);
                    $updateDocTypeStmt->execute([$documentTypeId]);
                }
                
                $completedSql = "INSERT INTO completed_inspections (
                                    plan_id, non_certified_inspection_id, certification_id,
                                    inspection_type, auditor_id, company_id, 
                                    inspection_date, completion_date, inspection_notes, 
                                    inspection_result, created_by, created_at
                                ) VALUES (?, ?, ?, 'non_certified', ?, ?, ?, NOW(), ?, ?, ?, NOW())";
                
                $completedStmt = $pdo->prepare($completedSql);
                $completedResult = $completedStmt->execute([
                    $planId,
                    $planData['non_certified_inspection_id'],
                    $certificationId,
                    $planData['auditor_id'],
                    $planData['company_id'],
                    $planData['assignment_date'],
                    $inspectionNotes,
                    $inspectionResult,
                    $_SESSION['user_id']
                ]);
                
                if ($completedResult) {
                    $completedInspectionId = $pdo->lastInsertId();
                    
                    if (isset($_FILES['non_certified_files']) && !empty($_FILES['non_certified_files']['name'][0])) {
                        $uploadResult = handleFileUploads($completedInspectionId, $_FILES['non_certified_files'], $pdo);
                        if (!$uploadResult['success']) {
                            $pdo->rollback();
                            echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
                            exit();
                        }
                    }
                    
                    $updatePlanSql = "UPDATE plans SET completion_status = 'completed', updated_at = NOW() WHERE id = ?";
                    $updatePlanStmt = $pdo->prepare($updatePlanSql);
                    $updatePlanStmt->execute([$planId]);
                    
                    $updateAssignSql = "UPDATE auditor_assignments SET assignment_status = 'completed', updated_at = NOW() WHERE plan_id = ?";
                    $updateAssignStmt = $pdo->prepare($updateAssignSql);
                    $updateAssignStmt->execute([$planId]);
                    
                    $successMessage = 'Belgesiz tetkik başarıyla tamamlandı';
                    if ($createCertificate) {
                        $successMessage .= ' ve yeni sertifika oluşturuldu';
                    }
                    
                    $logContent = "Belgesiz tetkik tamamlandı - Plan ID: $planId, Firma: " . $planData['company_name'] . ", Sonuç: $inspectionResult";
                    if ($createCertificate) {
                        $logContent .= ", Yeni Sertifika: $certificateNumber";
                    }
                    
                    $logSql = "INSERT INTO system_logs (user_id, log_type, level, content, ip_address, created_at) VALUES (?, 'inspection', 'INFO', ?, ?, NOW())";
                    $logStmt = $pdo->prepare($logSql);
                    $logStmt->execute([$_SESSION['user_id'], $logContent, $_SERVER['REMOTE_ADDR'] ?? '']);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => $successMessage]);
                } else {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'Tetkik tamamlanma kaydı oluşturulamadı']);
                }
                
            } catch (Exception $e) {
                $pdo->rollback();
                error_log("Belgesiz tetkik tamamlama hatası: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Geçersiz plan ID']);
        }
        exit();
    }
    
    if ($_POST['ajax'] == 'delete_plan') {
        $planId = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $assignmentId = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
        
        if ($planId > 0) {
            try {
                $pdo = getConnection();
                $pdo->beginTransaction();
                
                $checkSql = "SELECT completion_status, non_certified_inspection_id FROM plans WHERE id = ?";
                $checkStmt = $pdo->prepare($checkSql);
                $checkStmt->execute([$planId]);
                $planInfo = $checkStmt->fetch();
                
                if ($planInfo['completion_status'] === 'completed') {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                    exit();
                }
                
                $countAssignSql = "SELECT COUNT(*) AS cnt FROM auditor_assignments WHERE plan_id = ?";
                $countAssignStmt = $pdo->prepare($countAssignSql);
                $countAssignStmt->execute([$planId]);
                $assignCount = (int)($countAssignStmt->fetch()['cnt'] ?? 0);

                if ($assignCount > 1) {
                    if ($assignmentId > 0) {
                        $delByIdSql = "DELETE FROM auditor_assignments WHERE id = ? AND plan_id = ?";
                        $delByIdStmt = $pdo->prepare($delByIdSql);
                        $delByIdStmt->execute([$assignmentId, $planId]);
                    } else {
                        $currentUserId = $_SESSION['user_id'] ?? 0;
                        $delOneSql = "DELETE FROM auditor_assignments WHERE plan_id = ? AND auditor_id = ?";
                        $delOneStmt = $pdo->prepare($delOneSql);
                        $delOneStmt->execute([$planId, $currentUserId]);
                        if ($delOneStmt->rowCount() === 0) {
                            $pickSql = "SELECT id FROM auditor_assignments WHERE plan_id = ? ORDER BY assignment_date DESC, id DESC LIMIT 1";
                            $pickStmt = $pdo->prepare($pickSql);
                            $pickStmt->execute([$planId]);
                            $assignRow = $pickStmt->fetch();
                            if ($assignRow && isset($assignRow['id'])) {
                                $delByIdSql = "DELETE FROM auditor_assignments WHERE id = ?";
                                $delByIdStmt = $pdo->prepare($delByIdSql);
                                $delByIdStmt->execute([$assignRow['id']]);
                            }
                        }
                    }
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
                } else {
                    $deleteAssignSql = "DELETE FROM auditor_assignments WHERE plan_id = ?";
                    $deleteAssignStmt = $pdo->prepare($deleteAssignSql);
                    $deleteAssignStmt->execute([$planId]);
                    
                    $deletePlanSql = "DELETE FROM plans WHERE id = ?";
                    $deletePlanStmt = $pdo->prepare($deletePlanSql);
                    $deletePlanResult = $deletePlanStmt->execute([$planId]);
                    
                    
                    if ($deletePlanResult) {
                        $pdo->commit();
                        echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
                    } else {
                        $pdo->rollback();
                        echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                    }
                }
                
            } catch (Exception $e) {
                $pdo->rollback();
                error_log("Plan silme hatası: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }
}

function handleSingleFileUpload($completedInspectionId, $file) {
 
    $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
    $maxFileSize = 10 * 1024 * 1024; 
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Dosya yükleme hatası'];
    }
    
    $originalFileName = $file['name'];
    $fileSize = $file['size'];
    $tmpName = $file['tmp_name'];
    
    if ($fileSize > $maxFileSize) {
        return ['success' => false, 'message' => "Dosya boyutu çok büyük (Max: 10MB)"];
    }
    
    $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedTypes)) {
        return ['success' => false, 'message' => "Geçersiz dosya türü"];
    }
     
    $fileContent = file_get_contents($tmpName);
    if ($fileContent === false) {
        return ['success' => false, 'message' => 'Dosya okunamadı'];
    }
     
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpName) : 'application/octet-stream';
    if ($finfo) { finfo_close($finfo); }
    
    try {
        $pdo = getConnection();
        
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
         
        $fileSql = "INSERT INTO inspection_files (
                       completed_inspection_id, file_name, original_file_name, 
                       file_path, file_size, file_type, file_category, mime_type, file_content,
                       uploaded_by, created_at
                   ) VALUES (?, ?, ?, ?, ?, ?, 'document', ?, ?, ?, NOW())";
        $fileStmt = $pdo->prepare($fileSql);
        $fileResult = $fileStmt->execute([
            $completedInspectionId,
            $fileName,
            $originalFileName,
            null,  
            $fileSize,
            $fileExtension,
            $mimeType,
            $fileContent,
            $_SESSION['user_id']
        ]);
        
        if ($fileResult) {
            $fileId = $pdo->lastInsertId();
            return [
                'success' => true, 
                'message' => 'Dosya başarıyla yüklendi',
                'file' => [
                    'id' => $fileId,
                    'original_file_name' => $originalFileName,
                    'file_size' => $fileSize,
                    'file_type' => $fileExtension,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
        } else {
            return ['success' => false, 'message' => "Dosya veritabanına kaydedilemedi"];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Veritabanı hatası: " . $e->getMessage()];
    }
}

function handleFileUploads($completedInspectionId, $files, $pdo) {
    $uploadDir = 'uploads/inspections/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
    $maxFileSize = 10 * 1024 * 1024; 
    
    $uploadedFiles = [];
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $originalFileName = $files['name'][$i];
        $fileSize = $files['size'][$i];
        $tmpName = $files['tmp_name'][$i];
        
        if ($fileSize > $maxFileSize) {
            return ['success' => false, 'message' => "Dosya boyutu çok büyük: $originalFileName (Max: 10MB)"];
        }
        
        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedTypes)) {
            return ['success' => false, 'message' => "Geçersiz dosya türü: $originalFileName"];
        }
        
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($tmpName, $filePath)) {
            $fileSql = "INSERT INTO inspection_files (
                           completed_inspection_id, file_name, original_file_name, 
                           file_path, file_size, file_type, file_category, 
                           uploaded_by, created_at
                       ) VALUES (?, ?, ?, ?, ?, ?, 'document', ?, NOW())";
            $fileStmt = $pdo->prepare($fileSql);
            $fileResult = $fileStmt->execute([
                $completedInspectionId,
                $fileName,
                $originalFileName,
                $filePath,
                $fileSize,
                $fileExtension,
                $_SESSION['user_id']
            ]);
            
            if (!$fileResult) {
                return ['success' => false, 'message' => "Dosya veritabanına kaydedilemedi: $originalFileName"];
            }
            
            $uploadedFiles[] = $originalFileName;
        } else {
            return ['success' => false, 'message' => "Dosya yüklenemedi: $originalFileName"];
        }
    }
    
    return ['success' => true, 'uploaded_files' => $uploadedFiles];
}

function getDocumentDetails($documentId) {
    try {
        $pdo = getConnection();
        
        $sql = "SELECT 
                    c.id,
                    c.company_id,
                    COALESCE(comp.trade_name, comp.short_name) as company_name,
                    comp.contact_email,
                    comp.phone,
                    comp.address,
                    dt.name as cert_type,
                    dt.standard,
                    c.document_number as cert_number,
                    c.issue_date,
                    c.expiry_date,
                    c.status as cert_status,
                    c.scope
                FROM certifications c
                JOIN companies comp ON c.company_id = comp.id
                JOIN document_types dt ON c.document_type_id = dt.id
                WHERE c.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$documentId]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Belge detay hatası: " . $e->getMessage());
        return null;
    }
}

function getAuditors() {
    try {
        $pdo = getConnection();
        
        $sql = "SELECT id, first_name, last_name, email, full_name, username,
                       TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) AS name_surname,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))),''), NULLIF(full_name,''), username, email) AS auditor_name
                FROM users 
                WHERE (role = 'auditor' OR role = 'denetci') AND status = 'active'
                ORDER BY first_name, last_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Denetçi getirme hatası: " . $e->getMessage());
        return [];
    }
}

function getExistingPlans($documentId) {
    try {
        $pdo = getConnection();
        
        $sql = "SELECT 
                    p.id as plan_id,
                    p.company_id,
                    p.certification_id,
                    p.non_certified_inspection_id,
                    p.inspection_type,
                    p.audit_start_date,
                    p.completion_status,
                    p.created_at,
                    COALESCE(comp.trade_name, comp.short_name) as company_name,
                    dt.name as cert_type,
                    c.document_number,
                    a.first_name,
                    a.last_name,
                    a.email,
                    aa.id as assignment_id,
                    aa.assignment_status,
                    aa.assignment_notes,
                    ci.id as completed_inspection_id
                FROM plans p
                JOIN auditor_assignments aa ON p.id = aa.plan_id
                JOIN users a ON aa.auditor_id = a.id
                JOIN companies comp ON p.company_id = comp.id
                LEFT JOIN certifications c ON p.certification_id = c.id
                LEFT JOIN document_types dt ON c.document_type_id = dt.id
                LEFT JOIN completed_inspections ci ON p.id = ci.plan_id
                WHERE p.certification_id = ?
                ORDER BY p.audit_start_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Mevcut plan hatası: " . $e->getMessage());
        return [];
    }
}

if ($planningMode === 'document') {
    $document = getDocumentDetails($documentId);
    if (!$document) {
        header('Location: document_tracking.php');
        exit();
    }
    $existingPlans = getExistingPlans($documentId);
} else {
    $document = null;
    $existingPlans = [];
}

$auditors = getAuditors();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $planningMode === 'document' ? 'Tetkik Planlaması' : 'Genel Tetkik Planlaması'; ?> - Belgelendirme</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .planning-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #6c5ce7;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .back-button:hover {
            background: #5a4fcf;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 32px;
            border-radius: 16px;
            margin-bottom: 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
        }
        
        .page-header-content {
            position: relative;
            z-index: 1;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .document-header {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        
        .document-title {
            font-size: 24px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-active { background: #d5edda; color: #155724; }
        
        .document-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #6c5ce7;
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 500;
            color: #2d3436;
        }
        
        .company-selection, .documents-section, .planning-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        .selection-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            align-items: start;
        }
        
        .documents-section {
            display: none;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .searchable-select {
            position: relative;
            max-width: 400px;
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
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            max-width: 100%;
            box-sizing: border-box;
        }

        .searchable-select .dropdown-list.show {
            display: block;
        }

        .searchable-select .dropdown-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
            word-wrap: break-word;
            white-space: normal;
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
        
        .option-name {
            font-weight: 600;
            margin-bottom: 6px;
            color: #2d3436;
            font-size: 15px;
            line-height: 1.3;
        }
        
        .option-details {
            font-size: 13px;
            color: #6c757d;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            line-height: 1.4;
        }
        
        .modal-content .searchable-select {
            max-width: 100%;
        }

        .modal-content .searchable-select .dropdown-list {
            max-width: calc(100% - 4px);
            left: 2px;
            right: 2px;
        }
        
        .option-detail-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .documents-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .document-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 24px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .document-item .document-type {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
            text-align: center;
        }

        .document-item .document-number {
            font-size: 16px;
            margin-bottom: 8px;
            opacity: 0.9;
            text-align: center;
        }

        .document-item .document-dates {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 4px;
            text-align: center;
        }

        .document-item.no-document {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            text-align: center;
            font-weight: 600;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .document-item.no-document .document-type {
            font-size: 20px;
            font-weight: 600;
            color: white;
            margin-bottom: 12px;
        }
        
        .document-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.15);
            border-color: #6c5ce7;
        }
        
        .document-item.selected {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-color: #1e7e34;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }
        
        .document-item.no-document {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            text-align: center;
            font-weight: 600;
        }
        
        .document-item.no-document:hover {
            background: linear-gradient(135deg, #e0a800 0%, #e8590c 100%);
        }
        
        .document-item.no-document.selected {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border-color: #bd2130;
        }
        
        .document-type {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .document-number {
            font-size: 15px;
            margin-bottom: 8px;
            opacity: 0.9;
        }
        
        .document-dates {
            font-size: 13px;
            opacity: 0.8;
            margin-bottom: 4px;
        }
        
        .plan-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #e9ecef;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group-full {
            display: flex;
            flex-direction: column;
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .form-input, .form-select {
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #6c5ce7;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.15);
        }
        
        .form-textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            font-family: inherit;
            background: white;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: #6c5ce7;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.15);
        }
        
        .file-upload-area {
            border: 2px dashed #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .file-upload-area:hover {
            border-color: #6c5ce7;
            background: #f0f2ff;
        }
        
        .file-upload-area.dragover {
            border-color: #28a745;
            background: #e8f5e8;
        }
        
        .file-upload-icon {
            font-size: 24px;
            margin-bottom: 8px;
            color: #6c757d;
        }
        
        .file-upload-text {
            font-size: 14px;
            color: #495057;
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .file-upload-subtext {
            font-size: 12px;
            color: #6c757d;
        }
        
        .file-upload-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .file-list {
            margin-top: 16px;
            padding: 0;
            list-style: none;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .file-icon {
            font-size: 16px;
            color: #6c757d;
        }
        
        .file-name {
            font-size: 14px;
            color: #2d3436;
            font-weight: 500;
        }
        
        .file-size {
            font-size: 12px;
            color: #6c757d;
        }
        
        .remove-file {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.3s ease;
            z-index: 10;
            position: relative;
        }
        
        .remove-file:hover {
            background: #c82333;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 8px;
            margin-bottom: 8px;
            position: relative;
            z-index: 10;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-complete {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }
        
        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #6f42c1 0%, #5e57c6 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(111, 66, 193, 0.3);
        }
        
        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(111, 66, 193, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        .plan-item {
            background: white;
            border: 2px solid #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .plan-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, #6c5ce7, #a55eea);
        }
        
        .plan-item:hover {
            border-color: #6c5ce7;
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.15);
            transform: translateY(-2px);
        }
        
        .plan-item.completed {
            border-color: #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f8ea 100%);
        }
        
        .plan-item.completed::before {
            background: linear-gradient(to bottom, #28a745, #20c997);
        }
        
        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .plan-info {
            flex: 1;
        }
        
        .plan-date {
            font-size: 18px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 8px;
        }
        
        .plan-company {
            font-size: 16px;
            color: #495057;
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .plan-document {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 4px;
        }
        
        .plan-auditor {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 4px;
        }
        
        .plan-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .status-assigned { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d5edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-pending { background: #e2e3e5; color: #383d41; }
        
        .plan-notes {
            font-size: 14px;
            color: #495057;
            font-style: italic;
            margin-top: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #6c5ce7;
        }
        
        .plan-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            position: relative;
            z-index: 10;
        }
        
        .completion-modal, .non-certified-modal, .inspection-details-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            overflow-y: auto;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 32px;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            overflow: visible;
        }

        .inspection-details-modal .modal-content {
            max-width: 900px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f8f9fa;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            color: #2d3436;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #6c757d;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            position: relative;
            z-index: 20;
        }

        .close-modal:hover {
            background: #f8f9fa;
            color: #2d3436;
        }

        .inspection-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .detail-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #6c5ce7;
        }

        .detail-card h4 {
            font-size: 16px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }

        .detail-value {
            color: #2d3436;
        }

        .inspection-files-section {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .files-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .files-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3436;
        }

        .add-file-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.3s ease;
            position: relative;
            z-index: 10;
        }

        .add-file-btn:hover {
            background: #218838;
        }

        .existing-files-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .existing-file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 8px;
            border-left: 3px solid #17a2b8;
        }

        .file-details {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .file-actions {
            display: flex;
            gap: 8px;
        }

        .download-file-btn {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s ease;
            position: relative;
            z-index: 10;
        }

        .download-file-btn:hover {
            background: #138496;
        }

        .delete-file-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.3s ease;
            position: relative;
            z-index: 10;
        }

        .delete-file-btn:hover {
            background: #c82333;
        }

        .edit-section {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .edit-section h4 {
            font-size: 18px;
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 16px;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 32px;
            height: 32px;
            margin: -16px 0 0 -16px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #6c5ce7;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 1001;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #6c757d;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border: 2px dashed #dee2e6;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: 12px;
            color: #495057;
        }
        
        .hidden {
            display: none !important;
        }
        
        .form-group-half {
            grid-column: span 1;
        }
        
        .certificate-form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
            overflow: visible;
        }

        .modal-content .btn {
            position: relative;
            z-index: 20;
            pointer-events: all;
        }

        .modal-content .file-upload-area {
            pointer-events: none;
        }

        .modal-content .file-upload-input {
            pointer-events: all;
        }

        .modal-content .file-upload-area * {
            pointer-events: none;
        }

        .modal-content .file-upload-input {
            pointer-events: all;
        }
        
        @media (max-width: 768px) {
            .planning-container {
                padding: 16px;
            }
            
            .document-info {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .plan-header {
                flex-direction: column;
                gap: 12px;
            }
            
            .modal-content {
                margin: 10% auto;
                width: 95%;
                padding: 20px;
            }
            
            .searchable-select {
                max-width: 100%;
            }
            
            .documents-grid {
                max-width: 100%;
            }
            
            .document-item {
                max-width: 100%;
            }

            .inspection-details-grid {
                grid-template-columns: 1fr;
            }

            .file-upload-area {
                padding: 16px;
                min-height: 60px;
            }

            .file-upload-icon {
                font-size: 20px;
                margin-bottom: 6px;
            }

            .file-upload-text {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="planning-container">
        <?php if ($planningMode === 'document'): ?>
        <a href="examination.php?doc_id=<?php echo $documentId; ?>" class="back-button">
            ← Belge Detayına Dön
        </a>
        <?php else: ?>
        <a href="dashboard.php" class="back-button">
            ← Ana Sayfaya Dön
        </a>
        <?php endif; ?>
        
        <?php if ($planningMode === 'general'): ?>
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">Genel Tetkik Planlaması</h1>
                <p class="page-subtitle">Firma ve belge seçerek tetkik planı oluşturun</p>
            </div>
        </div>
        
        <div class="company-selection">
            <div class="section-title">
                Firma Seçimi
            </div>
            
            <div class="selection-row">
            <div class="searchable-select" id="companySearchable">
                <div class="select-wrapper">
                    <input type="text" id="companySearch" placeholder="Firma arayın veya seçin..." autocomplete="off">
                    <span class="dropdown-arrow">▼</span>
                    <div class="dropdown-list" id="companyDropdown">
                    </div>
                </div>
                <input type="hidden" id="companyId" name="company_id">
            </div>
                <div class="searchable-select" id="certSearchable" style="display: none;">
                    <div class="select-wrapper">
                        <input type="text" id="certSearch" placeholder="Firmanın belgelerini arayın..." autocomplete="off">
                        <span class="dropdown-arrow">▼</span>
                        <div class="dropdown-list" id="certDropdown"></div>
        </div>
                    <input type="hidden" id="certIdHidden">
            </div>
            </div>
        </div>
        
        
        <?php else: ?>
        <div class="document-header">
            <div class="document-title">
                <?php echo htmlspecialchars($document['company_name']); ?> - Tetkik Planlaması
                <span class="status-badge status-active">Aktif</span>
            </div>
            
            <div class="document-info">
                <div class="info-card">
                    <div class="info-label">Belge Türü</div>
                    <div class="info-value"><?php echo htmlspecialchars($document['cert_type']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Standart</div>
                    <div class="info-value"><?php echo htmlspecialchars($document['standard']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Belge No</div>
                    <div class="info-value"><?php echo htmlspecialchars($document['cert_number']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Geçerlilik</div>
                    <div class="info-value">
                        <?php echo date('d.m.Y', strtotime($document['issue_date'])); ?> - 
                        <?php echo date('d.m.Y', strtotime($document['expiry_date'])); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="planning-section" id="planningFormSection" <?php echo $planningMode === 'general' ? 'style="display: none;"' : ''; ?>>
            <div class="section-title">
                Yeni Tetkik Planı Oluştur
            </div>
            
            <div class="alert alert-success" id="successAlert"></div>
            <div class="alert alert-error" id="errorAlert"></div>
            
            <div class="plan-form">
                <form id="planForm">
                    <input type="hidden" id="selectedCompanyId" value="<?php echo $planningMode === 'document' ? $document['company_id'] : ''; ?>">
                    <input type="hidden" id="selectedCertificationId" value="<?php echo $planningMode === 'document' ? $documentId : ''; ?>">
                    
                    <?php if ($planningMode === 'general'): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Plan Türü</label>
                            <div id="planTypeInfo" class="info-value">Önce firma ve belge seçimi yapın</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Denetçi(ler)i Seçin *</label>
                            <div class="searchable-select" id="auditorSearchable">
                                <div class="select-wrapper">
                                    <input type="text" id="auditorSearch" placeholder="Denetçi arayın ve tıklayarak ekleyin..." autocomplete="off">
                                    <span class="dropdown-arrow">▼</span>
                                    <div class="dropdown-list" id="auditorDropdown">
                                        <?php foreach ($auditors as $auditor): ?>
                                        <?php 
                                            $label = trim(($auditor['name_surname'] ?? '')) !== '' 
                                                ? ($auditor['name_surname']) 
                                                : ($auditor['auditor_name']);
                                        ?>
                                        <div class="dropdown-item" data-value="<?php echo $auditor['id']; ?>" data-text="<?php echo htmlspecialchars($label); ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input type="hidden" id="auditorSelect" required>
                            </div>
                            <div id="selectedAuditors" style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tetkik Başlangıç Tarihi *</label>
                            <input type="date" class="form-input" id="startDate" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tetkik Bitiş Tarihi *</label>
                            <input type="date" class="form-input" id="endDate" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group-full">
                        <label class="form-label">Plan Notları</label>
                        <textarea class="form-textarea" id="planNotes" 
                                  placeholder="Tetkik planı ile ilgili özel notlar, dikkat edilecek hususlar..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        Planı Oluştur
                    </button>
                </form>
            </div>
        </div>
        
        <div class="planning-section" id="existingPlansSection">
            <div class="section-title">
                <?php echo $planningMode === 'document' ? 'Bu Belgeye Ait' : ''; ?> Tetkik Planları
            </div>
            
            <div class="existing-plans" id="existingPlansContainer">
                <?php if (empty($existingPlans) && $planningMode === 'document'): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <h3>Henüz tetkik planı bulunmuyor</h3>
                    <p>Bu belge için henüz bir tetkik planı oluşturulmamış. Yukarıdaki formu kullanarak ilk tetkik planınızı oluşturun.</p>
                </div>
                <?php elseif ($planningMode === 'general'): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏢</div>
                    <h3>Firma seçimi yapın</h3>
                    <p>Tetkik planlarını görüntülemek için önce bir firma seçin.</p>
                </div>
                <?php else: ?>
                <?php foreach ($existingPlans as $plan): ?>
                <div class="plan-item <?php echo $plan['completion_status'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="plan-header">
                        <div class="plan-info">
                            <div class="plan-date">
                                <?php echo date('d.m.Y', strtotime($plan['audit_start_date'])); ?>
                            </div>
                            <?php if ($planningMode === 'general'): ?>
                            <div class="plan-company">
                                <?php echo htmlspecialchars($plan['company_name']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($plan['cert_type']): ?>
                            <div class="plan-document">
                                <?php echo htmlspecialchars($plan['cert_type'] . ' - ' . $plan['document_number']); ?>
                            </div>
                            <?php else: ?>
                            <div class="plan-document" style="color: #fd7e14; font-weight: 600;">
                                Belgesiz Tetkik
                            </div>
                            <?php endif; ?>
                            <div class="plan-auditor">
                                Denetçi: <?php echo htmlspecialchars(($plan['first_name'] && $plan['last_name']) ? ($plan['first_name'].' '.$plan['last_name']) : ($plan['auditor_name'] ?? '')); ?>
                            </div>
                        </div>
                        <div>
                            <span class="plan-status status-<?php echo $plan['assignment_status']; ?>">
                                <?php 
                                $statusLabels = [
                                    'assigned' => 'Atandı',
                                    'completed' => 'Tamamlandı',
                                    'cancelled' => 'İptal'
                                ];
                                echo $statusLabels[$plan['assignment_status']] ?? ucfirst($plan['assignment_status']);
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($plan['assignment_notes'])): ?>
                    <div class="plan-notes">
                        Not: <?php echo nl2br(htmlspecialchars($plan['assignment_notes'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="plan-actions">
                        <?php if ($plan['completion_status'] === 'completed' && $plan['completed_inspection_id']): ?>
                        <button class="btn btn-info" onclick="openInspectionDetailsModal(<?php echo $plan['completed_inspection_id']; ?>)">
                            📄 Tetkik Bilgileri
                        </button>
                        <?php endif; ?>
                        <?php if ($plan['completion_status'] === 'pending'): ?>
                        <button class="btn btn-danger" onclick="deletePlan(<?php echo $plan['plan_id']; ?>, <?php echo (int)($plan['assignment_id'] ?? 0); ?>)">
                            🗑️ Sil
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <small style="color: #6c757d; font-style: italic; display: block; margin-top: 12px;">
                        Oluşturulma: <?php echo date('d.m.Y H:i', strtotime($plan['created_at'])); ?>
                    </small>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    

    <div id="inspectionDetailsModal" class="inspection-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">📋 Tetkik Detayları</h3>
                <button class="close-modal" onclick="closeInspectionDetailsModal()">×</button>
            </div>
            
            <div class="alert alert-success" id="detailsSuccessAlert"></div>
            <div class="alert alert-error" id="detailsErrorAlert"></div>
            
            <div id="inspectionDetailsContent">
            </div>
            
            <div class="edit-section" id="editInspectionSection" style="display: none;">
                <h4>✏️ Tetkik Bilgilerini Düzenle</h4>
                
                <form id="editInspectionForm">
                    <input type="hidden" id="editCompletedInspectionId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tetkik Sonucu *</label>
                            <select class="form-select" id="editInspectionResult" required>
                                <option value="passed">Başarılı</option>
                                <option value="cancelled">İptal</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group-full">
                        <label class="form-label">Tetkik Notları *</label>
                        <textarea class="form-textarea" id="editInspectionNotes" required
                                  placeholder="Tetkik sırasında yapılan gözlemler, tespit edilen durumlar..."></textarea>
                    </div>
                    
                    <div style="margin-top: 16px; display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-success">
                            💾 Değişiklikleri Kaydet
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                            ❌ İptal
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="inspection-files-section">
                <div class="files-header">
                    <h4 class="files-title">📁 Tetkik Dosyaları</h4>
                    <label class="add-file-btn" for="addInspectionFile">
                        ➕ Dosya Ekle
                    </label>
                    <input type="file" id="addInspectionFile" style="display: none;" 
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.zip,.rar">
                </div>
                
                <ul id="existingFilesList" class="existing-files-list">
                </ul>
            </div>
            
            <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-info" id="editInspectionBtn" onclick="toggleEdit()">
                    ✏️ Düzenle
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeInspectionDetailsModal()">
                    Kapat
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedCompanyId = null;
        let selectedCertificationId = null;
        let planningMode = '<?php echo $planningMode; ?>';
        let companies = [];
        let documentTypes = [];
        let companySelect, auditorSelect, documentTypeSelect, certSelect;
        let companyDocuments = [];
        let selectedAuditorIds = [];
        let inspectionFiles = [];
        let nonCertifiedFiles = [];
        let currentInspectionId = null;
        let isEditMode = false;
        
        document.addEventListener('DOMContentLoaded', function() {
            const planForm = document.getElementById('planForm');
            const completionForm = document.getElementById('completionForm');
            const nonCertifiedForm = document.getElementById('nonCertifiedForm');
            const editInspectionForm = document.getElementById('editInspectionForm');
            
            if (planningMode === 'general') {
                initializeGeneralMode();
            } else {
                selectedCompanyId = <?php echo $document['company_id'] ?? 'null'; ?>;
                selectedCertificationId = <?php echo $documentId ?? 'null'; ?>;
                initializeDocumentMode();
            }
            
            if (planForm) {
            planForm.addEventListener('submit', function(e) {
                e.preventDefault();
                handlePlanSubmit();
            });
            }
            if (completionForm) {
            completionForm.addEventListener('submit', function(e) {
                e.preventDefault();
            });
            }
            if (nonCertifiedForm) {
            nonCertifiedForm.addEventListener('submit', function(e) {
                e.preventDefault();
            });
            }
            if (editInspectionForm) {
            editInspectionForm.addEventListener('submit', function(e) {
                e.preventDefault();
                handleEditInspectionSubmit();
            });
            }
            
            initializeDocumentTypeSearchable();
            initializeFileUploads();
            initializeInspectionFileUpload();
            initializeAuditorMultiSelect();
        });

        function initializeInspectionFileUpload() {
            const addFileInput = document.getElementById('addInspectionFile');
            if (addFileInput) {
                addFileInput.addEventListener('change', function(e) {
                    if (e.target.files.length > 0 && currentInspectionId) {
                        uploadInspectionFile(e.target.files[0]);
                    }
                });
            }
        }

        function uploadInspectionFile(file) {
            const formData = new FormData();
            formData.append('ajax', 'add_inspection_file');
            formData.append('completed_inspection_id', currentInspectionId);
            formData.append('inspection_file', file);
            
            const filesList = document.getElementById('existingFilesList');
            filesList.classList.add('loading');
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                filesList.classList.remove('loading');
                
                if (data.success) {
                    showDetailsAlert(data.message, 'success');
                    loadInspectionDetails(currentInspectionId); 
                } else {
                    showDetailsAlert(data.message, 'error');
                }
                
                document.getElementById('addInspectionFile').value = '';
            })
            .catch(error => {
                filesList.classList.remove('loading');
                showDetailsAlert('Dosya yükleme sırasında hata oluştu.', 'error');
                document.getElementById('addInspectionFile').value = '';
            });
        }

        function removeInspectionFile(fileId) {
            if (!confirm('Bu dosyayı silmek istediğinize emin misiniz?')) {
                return;
            }

            const formData = new FormData();
            formData.append('ajax', 'remove_inspection_file');
            formData.append('file_id', fileId);
            
            const filesList = document.getElementById('existingFilesList');
            filesList.classList.add('loading');
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                filesList.classList.remove('loading');
                
                if (data.success) {
                    showDetailsAlert(data.message, 'success');
                    loadInspectionDetails(currentInspectionId); 
                } else {
                    showDetailsAlert(data.message, 'error');
                }
            })
            .catch(error => {
                filesList.classList.remove('loading');
                showDetailsAlert('Dosya silme sırasında hata oluştu.', 'error');
            });
        }

        function openInspectionDetailsModal(completedInspectionId) {
            currentInspectionId = completedInspectionId;
            loadInspectionDetails(completedInspectionId);
            
            const modal = document.getElementById('inspectionDetailsModal');
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function loadInspectionDetails(completedInspectionId) {
            const formData = new FormData();
            formData.append('ajax', 'get_inspection_details');
            formData.append('completed_inspection_id', completedInspectionId);
            
            const content = document.getElementById('inspectionDetailsContent');
            content.classList.add('loading');
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                content.classList.remove('loading');
                
                if (data.success) {
                    displayInspectionDetails(data.inspection, data.files);
                } else {
                    showDetailsAlert(data.message, 'error');
                }
            })
            .catch(error => {
                content.classList.remove('loading');
                showDetailsAlert('Tetkik detayları yüklenirken hata oluştu.', 'error');
            });
        }

        function displayInspectionDetails(inspection, files) {
            const content = document.getElementById('inspectionDetailsContent');
            
            const inspectionTypeLabel = inspection.inspection_type === 'certified' ? 'Belgeye Özel Tetkik' : 'Belgesiz Tetkik';
            const resultLabels = {
                'passed': '✅ Başarılı',
                'cancelled': '❌ İptal'
            };
            
            content.innerHTML = `
                <div class="inspection-details-grid">
                    <div class="detail-card">
                        <h4>🏢 Firma Bilgileri</h4>
                        <div class="detail-item">
                            <span class="detail-label">Firma:</span>
                            <span class="detail-value">${inspection.company_name}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tetkik Türü:</span>
                            <span class="detail-value">${inspectionTypeLabel}</span>
                        </div>
                        ${inspection.cert_type ? `
                            <div class="detail-item">
                                <span class="detail-label">Belge Türü:</span>
                                <span class="detail-value">${inspection.cert_type}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Belge No:</span>
                                <span class="detail-value">${inspection.document_number}</span>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="detail-card">
                        <h4>👨‍💼 Denetçi Bilgileri</h4>
                        <div class="detail-item">
                            <span class="detail-label">Ad Soyad:</span>
                            <span class="detail-value">${inspection.auditor_name}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tetkik Tarihi:</span>
                            <span class="detail-value">${formatDate(inspection.inspection_date)}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tamamlanma:</span>
                            <span class="detail-value">${formatDateTime(inspection.completion_date)}</span>
                        </div>
                    </div>
                    
                    <div class="detail-card" style="grid-column: 1 / -1;">
                        <h4>📝 Tetkik Sonuçları</h4>
                        <div class="detail-item">
                            <span class="detail-label">Sonuç:</span>
                            <span class="detail-value">${resultLabels[inspection.inspection_result] || inspection.inspection_result}</span>
                        </div>
                        <div class="detail-item" style="flex-direction: column; align-items: flex-start; gap: 8px;">
                            <span class="detail-label">Tetkik Notları:</span>
                            <div class="detail-value" style="background: white; padding: 12px; border-radius: 6px; width: 100%; box-sizing: border-box; border: 1px solid #e9ecef;">
                                ${inspection.inspection_notes.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            displayInspectionFiles(files);
            
            document.getElementById('editCompletedInspectionId').value = inspection.id;
            document.getElementById('editInspectionResult').value = inspection.inspection_result;
            document.getElementById('editInspectionNotes').value = inspection.inspection_notes;
        }

        function displayInspectionFiles(files) {
            const filesList = document.getElementById('existingFilesList');
            filesList.innerHTML = '';
            
            if (files.length === 0) {
                filesList.innerHTML = `
                    <li style="padding: 20px; text-align: center; color: #6c757d; font-style: italic;">
                        Henüz dosya yüklenmemiş
                    </li>
                `;
                return;
            }
            
            files.forEach(file => {
                const li = document.createElement('li');
                li.className = 'existing-file-item';
                li.innerHTML = `
                    <div class="file-details">
                        <span class="file-icon">📎</span>
                        <div>
                            <div class="file-name">${file.original_file_name}</div>
                            <div class="file-size">${formatFileSize(file.file_size)} • ${formatDateTime(file.created_at)}</div>
                        </div>
                    </div>
                    <div class="file-actions">
                        <a href="planning.php?download_inspection_file=${file.id}" class="download-file-btn">📥 İndir</a>
                        <button class="delete-file-btn" onclick="removeInspectionFile(${file.id})">🗑️ Sil</button>
                    </div>
                `;
                filesList.appendChild(li);
            });
        }

        function toggleEdit() {
            const editSection = document.getElementById('editInspectionSection');
            const editBtn = document.getElementById('editInspectionBtn');
            
            isEditMode = !isEditMode;
            
            if (isEditMode) {
                editSection.style.display = 'block';
                editBtn.innerHTML = '❌ Düzenlemeyi İptal Et';
                editBtn.className = 'btn btn-secondary';
            } else {
                editSection.style.display = 'none';
                editBtn.innerHTML = '✏️ Düzenle';
                editBtn.className = 'btn btn-info';
            }
        }

        function cancelEdit() {
            toggleEdit();
        }

        function handleEditInspectionSubmit() {
            const completedInspectionId = document.getElementById('editCompletedInspectionId').value;
            const inspectionResult = document.getElementById('editInspectionResult').value;
            const inspectionNotes = document.getElementById('editInspectionNotes').value;
            
            if (!completedInspectionId || !inspectionResult || !inspectionNotes) {
                showDetailsAlert('Lütfen tüm gerekli alanları doldurun.', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax', 'update_inspection');
            formData.append('completed_inspection_id', completedInspectionId);
            formData.append('inspection_result', inspectionResult);
            formData.append('inspection_notes', inspectionNotes);
            
            const editSection = document.getElementById('editInspectionSection');
            editSection.classList.add('loading');
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                editSection.classList.remove('loading');
                
                if (data.success) {
                    showDetailsAlert(data.message, 'success');
                    toggleEdit(); 
                    loadInspectionDetails(currentInspectionId); 
                } else {
                    showDetailsAlert(data.message, 'error');
                }
            })
            .catch(error => {
                editSection.classList.remove('loading');
                showDetailsAlert('Güncelleme sırasında hata oluştu.', 'error');
            });
        }

        function closeInspectionDetailsModal() {
            document.getElementById('inspectionDetailsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            currentInspectionId = null;
            isEditMode = false;
            
            document.getElementById('editInspectionSection').style.display = 'none';
            document.getElementById('editInspectionBtn').innerHTML = '✏️ Düzenle';
            document.getElementById('editInspectionBtn').className = 'btn btn-info';
            
            hideDetailsAlerts();
        }

        function showDetailsAlert(message, type) {
            hideDetailsAlerts();
            
            const alertId = type === 'success' ? 'detailsSuccessAlert' : 'detailsErrorAlert';
            const alertElement = document.getElementById(alertId);
            
            if (alertElement) {
                alertElement.textContent = message;
                alertElement.style.display = 'block';
                
                setTimeout(() => {
                    alertElement.style.display = 'none';
                }, 5000);
            }
        }
        
        function hideDetailsAlerts() {
            document.getElementById('detailsSuccessAlert').style.display = 'none';
            document.getElementById('detailsErrorAlert').style.display = 'none';
        }
        
        function initializeFileUploads() {
            const inspectionFilesInput = document.getElementById('inspectionFiles');
            const inspectionFileList = document.getElementById('inspectionFileList');
            
            if (inspectionFilesInput) {
                inspectionFilesInput.addEventListener('change', function(e) {
                    handleFileSelection(e.target.files, 'inspection');
                });
            }
            
            const nonCertifiedFilesInput = document.getElementById('nonCertifiedFiles');
            const nonCertifiedFileList = document.getElementById('nonCertifiedFileList');
            
            if (nonCertifiedFilesInput) {
                nonCertifiedFilesInput.addEventListener('change', function(e) {
                    handleFileSelection(e.target.files, 'nonCertified');
                });
            }
            
            setupDragAndDrop();
        }
        
        function handleFileSelection(files, type) {
            const maxFiles = 10;
            const maxFileSize = 10 * 1024 * 1024; 
            const allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
            
            const currentFiles = type === 'inspection' ? inspectionFiles : nonCertifiedFiles;
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (currentFiles.length >= maxFiles) {
                    showAlert(`En fazla ${maxFiles} dosya yükleyebilirsiniz.`, 'error');
                    break;
                }
                
                if (file.size > maxFileSize) {
                    showAlert(`${file.name} dosyası çok büyük. Maksimum 10MB olabilir.`, 'error');
                    continue;
                }
                
                const fileExtension = file.name.split('.').pop().toLowerCase();
                if (!allowedTypes.includes(fileExtension)) {
                    showAlert(`${file.name} dosya türü desteklenmiyor.`, 'error');
                    continue;
                }
                
                const fileId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                currentFiles.push({
                    id: fileId,
                    file: file,
                    name: file.name,
                    size: file.size
                });
            }
            
            updateFileList(type);
        }
        
        function updateFileList(type) {
            const currentFiles = type === 'inspection' ? inspectionFiles : nonCertifiedFiles;
            const fileListElement = type === 'inspection' ? 
                document.getElementById('inspectionFileList') : 
                document.getElementById('nonCertifiedFileList');
            
            if (currentFiles.length === 0) {
                fileListElement.style.display = 'none';
                return;
            }
            
            fileListElement.style.display = 'block';
            fileListElement.innerHTML = '';
            
            currentFiles.forEach(fileObj => {
                const li = document.createElement('li');
                li.className = 'file-item';
                
                li.innerHTML = `
                    <div class="file-info">
                        <span class="file-icon">📎</span>
                        <span class="file-name">${fileObj.name}</span>
                        <span class="file-size">(${formatFileSize(fileObj.size)})</span>
                    </div>
                    <button type="button" class="remove-file" onclick="removeFile('${fileObj.id}', '${type}')">
                        ×
                    </button>
                `;
                
                fileListElement.appendChild(li);
            });
        }
        
        function removeFile(fileId, type) {
            if (type === 'inspection') {
                inspectionFiles = inspectionFiles.filter(f => f.id !== fileId);
            } else {
                nonCertifiedFiles = nonCertifiedFiles.filter(f => f.id !== fileId);
            }
            updateFileList(type);
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function setupDragAndDrop() {
            const uploadAreas = document.querySelectorAll('.file-upload-area');
            
            uploadAreas.forEach(area => {
                area.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });
                
                area.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                });
                
                area.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    const type = this.querySelector('input').id === 'inspectionFiles' ? 'inspection' : 'nonCertified';
                    handleFileSelection(files, type);
                });
            });
        }
        
        function initSearchableSelect(searchInputId, dropdownId, hiddenInputId, onSelectCallback = null) {
            const searchInput = document.getElementById(searchInputId);
            const dropdown = document.getElementById(dropdownId);
            const hiddenInput = document.getElementById(hiddenInputId);
            
            if (!searchInput || !dropdown || !hiddenInput) {
                return null;
            }
            
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
            
            function filterItems(searchTerm) {
                const allItems = dropdown.querySelectorAll('.dropdown-item');
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
            
            function addItemClickListeners() {
                const allItems = dropdown.querySelectorAll('.dropdown-item');
                
                allItems.forEach((item) => {
                    if (item.clickHandler) {
                        item.removeEventListener('click', item.clickHandler);
                    }
                    
                    item.clickHandler = function() {
                        const value = this.dataset.value;
                        const text = this.dataset.text;
                        
                        searchInput.value = text;
                        hiddenInput.value = value;
                        dropdown.classList.remove('show');
                        
                        if (onSelectCallback) {
                            onSelectCallback(this);
                        }
                        
                        allItems.forEach(i => i.classList.remove('selected'));
                        this.classList.add('selected');
                    };
                    
                    item.addEventListener('click', item.clickHandler);
                });
            }
            
            addItemClickListeners();
            
            return {
                clear: function() {
                    searchInput.value = '';
                    hiddenInput.value = '';
                    const allItems = dropdown.querySelectorAll('.dropdown-item');
                    allItems.forEach(i => i.classList.remove('selected'));
                },
                setValue: function(value, text) {
                    searchInput.value = text;
                    hiddenInput.value = value;
                    const allItems = dropdown.querySelectorAll('.dropdown-item');
                    allItems.forEach(item => {
                        if (item.dataset.value === value) {
                            item.classList.add('selected');
                        } else {
                            item.classList.remove('selected');
                        }
                    });
                },
                refreshItems: function() {
                    addItemClickListeners();
                }
            };
        }
        
        function initializeDocumentTypeSearchable() {
            documentTypeSelect = initSearchableSelect('documentTypeSearch', 'documentTypeDropdown', 'documentType');
            
            const documentTypeSearchInput = document.getElementById('documentTypeSearch');
            if (documentTypeSearchInput) {
                documentTypeSearchInput.addEventListener('input', function() {
                    const searchTerm = this.value.trim();
                    if (searchTerm.length >= 2) {
                        loadDocumentTypes(searchTerm);
                    } else if (searchTerm.length === 0) {
                        loadDocumentTypes('');
                    }
                });
            }
            
            loadDocumentTypes('');
        }
        
        function loadDocumentTypes(searchTerm) {
            const formData = new FormData();
            formData.append('ajax', 'search_document_types');
            formData.append('search_term', searchTerm);
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    documentTypes = data.document_types;
                    displayDocumentTypeOptions(data.document_types);
                    
                    if (documentTypeSelect && documentTypeSelect.refreshItems) {
                        documentTypeSelect.refreshItems();
                    }
                }
            })
            .catch(error => {
                console.error('Document types search error:', error);
            });
        }
        
        function displayDocumentTypeOptions(docTypes) {
            const dropdown = document.getElementById('documentTypeDropdown');
            if (!dropdown) return;
            
            dropdown.innerHTML = '';
            
            if (docTypes.length === 0) {
                dropdown.innerHTML = '<div class="dropdown-item no-results">Belge türü bulunamadı</div>';
                return;
            }
            
            docTypes.forEach(docType => {
                const option = document.createElement('div');
                option.className = 'dropdown-item';
                option.setAttribute('data-value', docType.id);
                option.setAttribute('data-text', docType.name + ' - ' + docType.standard);
                
                option.innerHTML = `
                    <div class="option-name">${docType.name}</div>
                    <div class="option-details">
                        <div class="option-detail-item">
                            <span>Standart: ${docType.standard}</span>
                        </div>
                    </div>
                `;
                
                dropdown.appendChild(option);
            });
            
            if (documentTypeSelect && documentTypeSelect.refreshItems) {
                documentTypeSelect.refreshItems();
            }
        }
        
        function initializeGeneralMode() {
            loadCompanies('');
            
            companySelect = initSearchableSelect('companySearch', 'companyDropdown', 'companyId', function(selectedItem) {
                const companyId = selectedItem.dataset.value;
                const company = companies.find(c => c.id == companyId);
                if (company) {
                    selectCompany(company);
                }
            });
            auditorSelect = initSearchableSelect('auditorSearch', 'auditorDropdown', 'auditorSelect');
            
            const companySearchInput = document.getElementById('companySearch');
            if (companySearchInput) {
                companySearchInput.addEventListener('input', function() {
                    const searchTerm = this.value.trim();
                    if (searchTerm.length >= 2) {
                        loadCompanies(searchTerm);
                    } else if (searchTerm.length === 0) {
                        loadCompanies('');
                    }
                });
            }
            certSelect = initSearchableSelect('certSearch', 'certDropdown', 'certIdHidden', function(selectedItem){
                const certId = selectedItem.dataset.value;
                const doc = companyDocuments.find(d => (d.id+"") === (certId+""));
                if (certId === '' || !doc) {
                    selectDocument(null);
                } else {
                    selectDocument(doc);
                }
            });
        }
        
        function initializeDocumentMode() {
            auditorSelect = initSearchableSelect('auditorSearch', 'auditorDropdown', 'auditorSelect');
        }
        
        function loadCompanies(searchTerm) {
            const formData = new FormData();
            formData.append('ajax', 'search_companies');
            formData.append('search_term', searchTerm);
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    companies = data.companies;
                    displayCompanyOptions(data.companies);
                    
                    if (companySelect && companySelect.refreshItems) {
                        companySelect.refreshItems(data.companies);
                    }
                }
            })
            .catch(error => {
                console.error('Search error:', error);
            });
        }
        
        function displayCompanyOptions(companies) {
            const companyDropdown = document.getElementById('companyDropdown');
            if (!companyDropdown) return;
            
            companyDropdown.innerHTML = '';
            
            if (companies.length === 0) {
                companyDropdown.innerHTML = '<div class="dropdown-item no-results">Firma bulunamadı</div>';
                return;
            }
            
            companies.forEach(company => {
                const option = document.createElement('div');
                option.className = 'dropdown-item';
                option.setAttribute('data-value', company.id);
                option.setAttribute('data-text', company.company_name);
                
                option.innerHTML = `
                    <div class="option-name">${company.company_name}</div>
                    <div class="option-details">
                        <div class="option-detail-item">
                            <span>E-posta:</span>
                            <span>${company.contact_email || 'Email yok'}</span>
                        </div>
                        <div class="option-detail-item">
                            <span>Telefon:</span>
                            <span>${company.phone || 'Telefon yok'}</span>
                        </div>
                    </div>
                `;
                
                companyDropdown.appendChild(option);
            });
            
            if (companySelect && companySelect.refreshItems) {
                companySelect.refreshItems();
            }
        }
        
        function selectCompany(company) {
            selectedCompanyId = company.id;
            
            if (companySelect) {
                companySelect.setValue(company.id, company.company_name);
            }
            
            document.getElementById('selectedCompanyId').value = company.id;
            
            loadCompanyDocuments(company.id);
            const certBox = document.getElementById('certSearchable');
            if (certBox) certBox.style.display = 'block';
            
            selectedCertificationId = null;
            document.getElementById('selectedCertificationId').value = '';
            document.getElementById('planTypeInfo').textContent = 'Belge seçimi yapın';
            document.getElementById('planningFormSection').style.display = 'none';
        }
        
        function loadCompanyDocuments(companyId) {
            const formData = new FormData();
            formData.append('ajax', 'get_company_documents');
            formData.append('company_id', companyId);
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    companyDocuments = data.documents || [];
                    displayCertOptions(companyDocuments);
                }
            })
            .catch(error => {
                console.error('Documents load error:', error);
            });
        }
        
        function displayCertOptions(documents) {
            const dropdown = document.getElementById('certDropdown');
            if (!dropdown) return;
            dropdown.innerHTML = '';
            const none = document.createElement('div');
            none.className = 'dropdown-item';
            none.setAttribute('data-value', '');
            none.setAttribute('data-text', 'Belge Seçilmeden');
            none.innerHTML = `<div class="option-name">Belge Seçilmeden</div><div class="option-details"><div class="option-detail-item">Belge bağlantısı olmadan tetkik</div></div>`;
            dropdown.appendChild(none);
            documents.forEach(doc => {
                const option = document.createElement('div');
                option.className = 'dropdown-item';
                option.setAttribute('data-value', doc.id);
                option.setAttribute('data-text', `${doc.cert_type} - ${doc.document_number}`);
                option.innerHTML = `
                    <div class="option-name">${doc.cert_type} - ${doc.document_number}</div>
                    <div class="option-details">
                        <div class="option-detail-item">${doc.standard}</div>
                        <div class="option-detail-item">${formatDate(doc.issue_date)} - ${formatDate(doc.expiry_date)}</div>
                    </div>
                `;
                dropdown.appendChild(option);
            });
            if (certSelect && certSelect.refreshItems) certSelect.refreshItems();
        }
        
        function selectDocument(doc) {
            if (doc) {
                selectedCertificationId = doc.id;
                document.getElementById('selectedCertificationId').value = doc.id;
                document.getElementById('planTypeInfo').textContent = `Tetkik - ${doc.cert_type}`;
                if (certSelect && certSelect.setValue) {
                    certSelect.setValue(doc.id+"", `${doc.cert_type} - ${doc.document_number}`);
                }
            } else {
                selectedCertificationId = null;
                document.getElementById('selectedCertificationId').value = '';
                document.getElementById('planTypeInfo').textContent = 'Tetkik Ekle';
                if (certSelect && certSelect.setValue) {
                    certSelect.setValue('', 'Belge Seçilmeden');
            }
            }
            document.getElementById('planningFormSection').style.display = 'block';
            loadCompanyPlans(selectedCompanyId, selectedCertificationId);
        }
        
        function loadCompanyPlans(companyId, certificationId) {
            const formData = new FormData();
            formData.append('ajax', 'get_company_plans');
            formData.append('company_id', companyId);
            if (certificationId) {
                formData.append('certification_id', certificationId);
            }
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPlans(data.plans);
                } else {
                    displayPlans([]);
                }
            })
            .catch(error => {
                console.error('Plans load error:', error);
                displayPlans([]);
            });
        }
        
        function displayPlans(plans) {
            const container = document.getElementById('existingPlansContainer');
            
            if (plans.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <h3>Henüz tetkik planı bulunmuyor</h3>
                        <p>Bu seçim için henüz bir tetkik planı oluşturulmamış.</p>
                    </div>
                `;
                return;
            }
            
            let planHtml = '';
            plans.forEach(plan => {
                const completedClass = plan.completion_status === 'completed' ? 'completed' : '';
                const statusLabels = {
                    'assigned': 'Atandı',
                    'completed': 'Tamamlandı',
                    'cancelled': 'İptal'
                };
                
                const showDetailsButton = plan.completion_status === 'completed' && plan.completed_inspection_id;
                
                planHtml += `
                    <div class="plan-item ${completedClass}">
                        <div class="plan-header">
                            <div class="plan-info">
                                <div class="plan-date">
                                    ${formatDate(plan.audit_start_date)}
                                </div>
                                <div class="plan-company">
                                    ${plan.company_name}
                                </div>
                                ${plan.cert_type ? `
                                    <div class="plan-document">
                                        ${plan.cert_type} - ${plan.document_number}
                                    </div>
                                ` : ``}
                                <div class="plan-auditor">
                                    Denetçi: ${plan.auditor_name}
                                </div>
                            </div>
                            <div>
                                <span class="plan-status status-${plan.assignment_status}">
                                    ${statusLabels[plan.assignment_status] || plan.assignment_status}
                                </span>
                            </div>
                        </div>
                        
                        ${plan.assignment_notes ? `
                            <div class="plan-notes">
                                Not: ${plan.assignment_notes.replace(/\n/g, '<br>')}
                            </div>
                        ` : ''}
                        
                        <div class="plan-actions">
                            ${showDetailsButton ? `
                                <button class="btn btn-info" onclick="openInspectionDetailsModal(${plan.completed_inspection_id})">
                                    📄 Tetkik Bilgileri
                                </button>
                            ` : ''}
                            ${plan.completion_status === 'pending' ? `
                                <button class="btn btn-danger" onclick="deletePlan(${plan.plan_id}, ${plan.assignment_id || 0})">
                                    🗑️ Sil
                                </button>
                            ` : ''}
                        </div>
                        
                        <small style="color: #6c757d; font-style: italic; display: block; margin-top: 12px;">
                            Oluşturulma: ${formatDateTime(plan.created_at)}
                        </small>
                    </div>
                `;
            });
            
            container.innerHTML = planHtml;
        }
        
        function handlePlanSubmit() {
            const planStart = document.getElementById('startDate').value;
            const planEnd = document.getElementById('endDate').value;
            const notes = document.getElementById('planNotes').value;
            
            if (selectedAuditorIds.length === 0 || !planStart || !planEnd) {
                showAlert('Lütfen tüm gerekli alanları doldurun.', 'error');
                return;
            }
            
            if (planningMode === 'general' && !selectedCompanyId) {
                showAlert('Lütfen bir firma seçin.', 'error');
                return;
            }
            
            const today = new Date();
            const startDateObj = new Date(planStart);
            const endDateObj = new Date(planEnd);
            today.setHours(0, 0, 0, 0);
            startDateObj.setHours(0, 0, 0, 0);
            endDateObj.setHours(0, 0, 0, 0);
            
            if (startDateObj < today || endDateObj < today) {
                showAlert('Geçmiş tarih seçilemez.', 'error');
                return;
            }
            if (endDateObj < startDateObj) {
                showAlert('Bitiş tarihi başlangıç tarihinden önce olamaz.', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax', 'add_plan');
            selectedAuditorIds.forEach(id => formData.append('auditor_ids[]', id));
            formData.append('start_date', planStart);
            formData.append('end_date', planEnd);
            formData.append('notes', notes);
            formData.append('company_id', selectedCompanyId);
            formData.append('certification_id', selectedCertificationId || '');
            
            document.body.classList.add('loading');
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.classList.remove('loading');
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('planForm').reset();
                    setTimeout(() => {
                        if (planningMode === 'document') {
                            location.reload();
                        } else {
                            loadCompanyPlans(selectedCompanyId, selectedCertificationId);
                        }
                    }, 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                document.body.classList.remove('loading');
                showAlert('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
            });
        }
        

        function initializeAuditorMultiSelect() {
            const dropdown = document.getElementById('auditorDropdown');
            const searchInput = document.getElementById('auditorSearch');
            const hiddenInput = document.getElementById('auditorSelect');
            const selectedBox = document.getElementById('selectedAuditors');
            if (!dropdown || !searchInput || !hiddenInput || !selectedBox) return;
            dropdown.addEventListener('click', function(e){
                const item = e.target.closest('.dropdown-item');
                if (!item) return;
                const id = item.dataset.value;
                const text = item.dataset.text;
                if (!id) return;
                if (!selectedAuditorIds.includes(id)) {
                    selectedAuditorIds.push(id);
                    const tag = document.createElement('span');
                    tag.setAttribute('data-id', id);
                    tag.style.cssText = 'background:#e9ecef;border-radius:16px;padding:6px 10px;display:inline-flex;gap:6px;align-items:center;';
                    tag.innerHTML = `<span>${text}</span><button type="button" aria-label="remove" style="background:none;border:none;cursor:pointer;font-weight:bold;">×</button>`;
                    tag.querySelector('button').addEventListener('click', function(){
                        selectedAuditorIds = selectedAuditorIds.filter(a => a !== id);
                        tag.remove();
                    });
                    selectedBox.appendChild(tag);
                }
                searchInput.value = '';
                hiddenInput.value = selectedAuditorIds.join(',');
                dropdown.classList.remove('show');
            });
        }
        
        function deletePlan(planId, assignmentId = 0) {
            if (!confirm('Bu tetkik planını silmek istediğinize emin misiniz?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax', 'delete_plan');
            formData.append('plan_id', planId);
            if (assignmentId) {
                formData.append('assignment_id', assignmentId);
            }
            
            document.body.classList.add('loading');
            
            fetch('planning.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.classList.remove('loading');
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        if (planningMode === 'document') {
                            location.reload();
                        } else {
                            loadCompanyPlans(selectedCompanyId, selectedCertificationId);
                        }
                    }, 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                document.body.classList.remove('loading');
                showAlert('Silme işlemi sırasında hata oluştu.', 'error');
            });
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('tr-TR');
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('tr-TR') + ' ' + date.toLocaleTimeString('tr-TR', {hour: '2-digit', minute: '2-digit'});
        }
        
        function showAlert(message, type) {
            hideAlerts();
            
            const alertId = type === 'success' ? 'successAlert' : 'errorAlert';
            const alertElement = document.getElementById(alertId);
            
            if (alertElement) {
                alertElement.textContent = message;
                alertElement.style.display = 'block';
                
                setTimeout(() => {
                    alertElement.style.display = 'none';
                }, 5000);
            }
        }
        
        function hideAlerts() {
            document.getElementById('successAlert').style.display = 'none';
            document.getElementById('errorAlert').style.display = 'none';
        }
        
        window.addEventListener('click', function(event) {
            const inspectionDetailsModal = document.getElementById('inspectionDetailsModal');
            if (event.target === inspectionDetailsModal) {
                closeInspectionDetailsModal();
            }
        });
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeInspectionDetailsModal();
            }
        });
    </script>
</body>
</html>