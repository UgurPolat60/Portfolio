<?php
require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}

$completedId = isset($_GET['completed_id']) ? intval($_GET['completed_id']) : 0;

if (!$completedId) {
    echo "<script>alert('İstek geçersiz'); window.location.href = 'dashboard.php';</script>";
    exit();
}

if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax'] == 'update_inspection') {
        $completedId = isset($_POST['completed_id']) ? intval($_POST['completed_id']) : 0;
        $inspectionNotes = isset($_POST['inspection_notes']) ? sanitizeInput($_POST['inspection_notes']) : '';
        $inspectionResult = isset($_POST['inspection_result']) ? $_POST['inspection_result'] : '';
        
        if ($completedId > 0 && !empty($inspectionNotes) && !empty($inspectionResult)) {
            try {
                $pdo = getConnection();
                
                $updateSql = "UPDATE completed_inspections 
                             SET inspection_notes = ?, inspection_result = ?, updated_at = NOW() 
                             WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateResult = $updateStmt->execute([$inspectionNotes, $inspectionResult, $completedId]);
                
                if ($updateResult) {
                    $logContent = "Tetkik bilgileri güncellendi - ID: $completedId";
                    $logSql = "INSERT INTO system_logs (user_id, log_type, level, content, ip_address, created_at) VALUES (?, 'inspection', 'INFO', ?, ?, NOW())";
                    $logStmt = $pdo->prepare($logSql);
                    $logStmt->execute([$_SESSION['user_id'], $logContent, $_SERVER['REMOTE_ADDR'] ?? '']);
                    
                    echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                }
                
            } catch (Exception $e) {
                error_log('Inspection update error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }
    
    if ($_POST['ajax'] == 'upload_files') {
        $completedId = isset($_POST['completed_id']) ? intval($_POST['completed_id']) : 0;
        
        if ($completedId > 0 && isset($_FILES['new_files'])) {
            try {
                $pdo = getConnection();
                $uploadResult = handleFileUploads($completedId, $_FILES['new_files'], $pdo);
                
                if ($uploadResult['success']) {
                    echo json_encode(['success' => true, 'message' => 'İşlem başarılı', 'uploaded_files' => $uploadResult['uploaded_files']]);
                } else {
                    echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
                }
                
            } catch (Exception $e) {
                error_log('File upload error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }
    
    if ($_POST['ajax'] == 'delete_file') {
        $fileId = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
        
        if ($fileId > 0) {
            try {
                $pdo = getConnection();
                
                $fileSql = "SELECT file_path, original_file_name FROM inspection_files WHERE id = ?";
                $fileStmt = $pdo->prepare($fileSql);
                $fileStmt->execute([$fileId]);
                $fileData = $fileStmt->fetch();
                
                if ($fileData) {
                    $deleteSql = "DELETE FROM inspection_files WHERE id = ?";
                    $deleteStmt = $pdo->prepare($deleteSql);
                    $deleteResult = $deleteStmt->execute([$fileId]);
                    
                    if ($deleteResult) {
                        if (file_exists($fileData['file_path'])) {
                            unlink($fileData['file_path']);
                        }
                        
                        echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı']);
                }
                
            } catch (Exception $e) {
                error_log('File delete error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
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

function getInspectionSummary($completedId) {
    try {
        $pdo = getConnection();
        
        $sql = "SELECT 
                    ci.id,
                    ci.plan_id,
                    ci.certification_id,
                    ci.non_certified_inspection_id,
                    ci.inspection_type,
                    ci.inspection_date,
                    ci.completion_date,
                    ci.inspection_notes,
                    ci.inspection_result,
                    ci.created_at,
                    ci.updated_at,
                    COALESCE(comp.trade_name, comp.short_name) as company_name,
                    comp.contact_email,
                    comp.phone,
                    comp.address,
                    a.first_name as auditor_first_name,
                    a.last_name as auditor_last_name,
                    a.email as auditor_email,
                    a.phone as auditor_phone,
                    dt.name as cert_type,
                    dt.standard,
                    c.document_number,
                    c.scope as cert_scope,
                    c.issue_date as cert_issue_date,
                    c.expiry_date as cert_expiry_date,
                    nci.inspection_title,
                    nci.inspection_description
                FROM completed_inspections ci
                JOIN companies comp ON ci.company_id = comp.id
                JOIN auditors a ON ci.auditor_id = a.id
                LEFT JOIN certifications c ON ci.certification_id = c.id
                LEFT JOIN document_types dt ON c.document_type_id = dt.id
                LEFT JOIN non_certified_inspections nci ON ci.non_certified_inspection_id = nci.id
                WHERE ci.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$completedId]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Tetkik özeti hatası: " . $e->getMessage());
        return null;
    }
}

function getInspectionFiles($completedId) {
    try {
        $pdo = getConnection();
        
        $sql = "SELECT 
                    id,
                    file_name,
                    original_file_name,
                    file_path,
                    file_size,
                    file_type,
                    file_category,
                    created_at
                FROM inspection_files 
                WHERE completed_inspection_id = ?
                ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$completedId]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Dosya listesi hatası: " . $e->getMessage());
        return [];
    }
}

$inspectionData = getInspectionSummary($completedId);
$inspectionFiles = getInspectionFiles($completedId);

if (!$inspectionData) {
    echo "<script>alert('Tetkik bilgileri bulunamadı. Ana sayfaya yönlendiriliyorsunuz.'); window.location.href = 'dashboard.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tetkik Özeti - Belgelendirme</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .summary-container {
            max-width: 1000px;
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
        
        .summary-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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
            word-wrap: break-word;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-passed { background: #d5edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }
        
        .inspection-type-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .type-certified {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .type-non-certified {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        
        .editable-section {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            position: relative;
        }
        
        .edit-mode {
            border-color: #6c5ce7;
            background: #f0f2ff;
        }
        
        .edit-toggle {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #6c5ce7;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .edit-toggle:hover {
            background: #5a4fcf;
        }
        
        .edit-toggle.save-mode {
            background: #28a745;
        }
        
        .edit-toggle.save-mode:hover {
            background: #20c997;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #6c5ce7;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.15);
        }
        
        .view-mode {
            background: white;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            min-height: 120px;
            font-size: 14px;
            line-height: 1.6;
            color: #2d3436;
            white-space: pre-wrap;
        }
        
        .files-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .file-upload-area {
            border: 2px dashed #e1e5e9;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            margin-bottom: 20px;
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
            font-size: 48px;
            margin-bottom: 12px;
            color: #6c757d;
        }
        
        .file-upload-text {
            font-size: 16px;
            color: #495057;
            margin-bottom: 8px;
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
        }
        
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        
        .file-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .file-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-color: #6c5ce7;
        }
        
        .file-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .file-icon {
            font-size: 24px;
            color: #6c5ce7;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-size: 15px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 4px;
            word-wrap: break-word;
        }
        
        .file-size {
            font-size: 12px;
            color: #6c757d;
        }
        
        .file-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #6c5ce7 0%, #a55eea 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a4fcf 0%, #9c4dd8 100%);
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #dc2626 100%);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 12px 24px;
            font-size: 14px;
            margin-right: 12px;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            font-size: 14px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
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
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .empty-files {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }
        
        .empty-files-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .summary-container {
                padding: 16px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .files-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="summary-container">
        <a href="javascript:history.back()" class="back-button">
            ← Geri Dön
        </a>
        
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">Tetkik Özeti</h1>
                <p class="page-subtitle"><?php echo htmlspecialchars($inspectionData['company_name']); ?></p>
            </div>
        </div>
        
        <div class="summary-section">
            <div class="section-title">
                📊 Genel Bilgiler
            </div>
            
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Firma</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['company_name']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Tetkik Türü</div>
                    <div class="info-value">
                        <span class="inspection-type-badge type-<?php echo $inspectionData['inspection_type']; ?>">
                            <?php echo $inspectionData['inspection_type'] === 'certified' ? 'Belgeye Özel' : 'Belgesiz'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Tetkik Tarihi</div>
                    <div class="info-value"><?php echo date('d.m.Y', strtotime($inspectionData['inspection_date'])); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Tamamlanma Tarihi</div>
                    <div class="info-value"><?php echo date('d.m.Y H:i', strtotime($inspectionData['completion_date'])); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Denetçi</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($inspectionData['auditor_first_name'] . ' ' . $inspectionData['auditor_last_name']); ?>
                        <br><small><?php echo htmlspecialchars($inspectionData['auditor_email']); ?></small>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Tetkik Sonucu</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $inspectionData['inspection_result']; ?>">
                            <?php 
                            $resultLabels = [
                                'passed' => 'Başarılı',
                                'failed' => 'Başarısız',
                                'cancelled' => 'İptal'
                            ];
                            echo $resultLabels[$inspectionData['inspection_result']] ?? ucfirst($inspectionData['inspection_result']);
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <?php if ($inspectionData['inspection_type'] === 'certified' && $inspectionData['cert_type']): ?>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Belge Türü</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['cert_type']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Standart</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['standard']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Belge Numarası</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['document_number']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Belge Geçerlilik</div>
                    <div class="info-value">
                        <?php echo date('d.m.Y', strtotime($inspectionData['cert_issue_date'])); ?> - 
                        <?php echo date('d.m.Y', strtotime($inspectionData['cert_expiry_date'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($inspectionData['inspection_type'] === 'non_certified'): ?>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Tetkik Başlığı</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['inspection_title']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Tetkik Açıklaması</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['inspection_description']); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="summary-section">
            <div class="section-title">
                🏢 Firma Bilgileri
            </div>
            
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Firma Adı</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['company_name']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">E-posta</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['contact_email'] ?: 'Belirtilmemiş'); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Telefon</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['phone'] ?: 'Belirtilmemiş'); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Adres</div>
                    <div class="info-value"><?php echo htmlspecialchars($inspectionData['address'] ?: 'Belirtilmemiş'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="editable-section" id="inspectionDetailsSection">
            <button class="edit-toggle" id="editToggle" onclick="toggleEditMode()">
                ✏️ Düzenle
            </button>
            
            <div class="section-title">
                📝 Tetkik Detayları
            </div>
            
            <div class="alert alert-success" id="updateSuccessAlert"></div>
            <div class="alert alert-error" id="updateErrorAlert"></div>
            
            <form id="updateForm">
                <input type="hidden" id="completedInspectionId" value="<?php echo $completedId; ?>">
                
                <div class="form-group">
                    <label class="form-label">Tetkik Sonucu</label>
                    <select class="form-input" id="inspectionResult" disabled>
                        <option value="passed" <?php echo $inspectionData['inspection_result'] === 'passed' ? 'selected' : ''; ?>>Başarılı</option>
                        <option value="failed" <?php echo $inspectionData['inspection_result'] === 'failed' ? 'selected' : ''; ?>>Başarısız</option>
                        <option value="cancelled" <?php echo $inspectionData['inspection_result'] === 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tetkik Notları</label>
                    <div class="view-mode" id="notesViewMode"><?php echo nl2br(htmlspecialchars($inspectionData['inspection_notes'])); ?></div>
                    <textarea class="form-textarea" id="inspectionNotes" style="display: none;" disabled><?php echo htmlspecialchars($inspectionData['inspection_notes']); ?></textarea>
                </div>
                
                <div class="action-buttons" id="editActions" style="display: none;">
                    <button type="submit" class="btn btn-success">
                        💾 Değişiklikleri Kaydet
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                        ❌ İptal
                    </button>
                </div>
            </form>
        </div>
        
        <div class="files-section">
            <div class="section-title">
                📁 Tetkik Dosyaları
            </div>
            
            <div class="alert alert-success" id="fileSuccessAlert"></div>
            <div class="alert alert-error" id="fileErrorAlert"></div>
            
            <div class="file-upload-area" onclick="document.getElementById('newFiles').click()">
                <div class="file-upload-icon">📁</div>
                <div class="file-upload-text">Yeni dosya yüklemek için tıklayın</div>
                <div class="file-upload-subtext">PDF, DOC, resim ve arşiv dosyaları desteklenir (Max: 10MB)</div>
                <input type="file" id="newFiles" multiple 
                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.zip,.rar" 
                       class="file-upload-input" onchange="handleNewFileUpload(this.files)">
            </div>
            
            <?php if (empty($inspectionFiles)): ?>
            <div class="empty-files">
                <div class="empty-files-icon">📄</div>
                <h3>Henüz dosya yüklenmemiş</h3>
                <p>Bu tetkik için henüz dosya yüklenmemiş. Yukarıdaki alanı kullanarak dosya ekleyebilirsiniz.</p>
            </div>
            <?php else: ?>
            <div class="files-grid" id="filesGrid">
                <?php foreach ($inspectionFiles as $file): ?>
                <div class="file-item" id="file-<?php echo $file['id']; ?>">
                    <div class="file-header">
                        <div class="file-icon">
                            <?php
                            $icons = [
                                'pdf' => '📄',
                                'doc' => '📄', 'docx' => '📄',
                                'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
                                'zip' => '📦', 'rar' => '📦'
                            ];
                            echo $icons[$file['file_type']] ?? '📎';
                            ?>
                        </div>
                        <div class="file-info">
                            <div class="file-name"><?php echo htmlspecialchars($file['original_file_name']); ?></div>
                            <div class="file-size"><?php echo formatFileSize($file['file_size']); ?></div>
                        </div>
                    </div>
                    
                    <div class="file-actions">
                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-primary">
                            👁️ Görüntüle
                        </a>
                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" download="<?php echo htmlspecialchars($file['original_file_name']); ?>" class="btn btn-primary">
                            ⬇️ İndir
                        </a>
                        <button class="btn btn-danger" onclick="deleteFile(<?php echo $file['id']; ?>)">
                            🗑️ Sil
                        </button>
                    </div>
                    
                    <small style="color: #6c757d; font-style: italic; display: block; margin-top: 8px;">
                        Yüklenme: <?php echo date('d.m.Y H:i', strtotime($file['created_at'])); ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let isEditMode = false;
        let originalNotes = '';
        let originalResult = '';
        
        document.addEventListener('DOMContentLoaded', function() {
            const updateForm = document.getElementById('updateForm');
            
            updateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                handleUpdateSubmit();
            });
            
            setupDragAndDrop();
            
            originalNotes = document.getElementById('inspectionNotes').value;
            originalResult = document.getElementById('inspectionResult').value;
        });
        
        function toggleEditMode() {
            const section = document.getElementById('inspectionDetailsSection');
            const toggleBtn = document.getElementById('editToggle');
            const notesView = document.getElementById('notesViewMode');
            const notesEdit = document.getElementById('inspectionNotes');
            const resultSelect = document.getElementById('inspectionResult');
            const editActions = document.getElementById('editActions');
            
            if (!isEditMode) {
                section.classList.add('edit-mode');
                toggleBtn.innerHTML = '💾 Kaydet';
                toggleBtn.classList.add('save-mode');
                
                notesView.style.display = 'none';
                notesEdit.style.display = 'block';
                notesEdit.disabled = false;
                resultSelect.disabled = false;
                editActions.style.display = 'flex';
                
                isEditMode = true;
            } else {
                handleUpdateSubmit();
            }
        }
        
        function cancelEdit() {
            const section = document.getElementById('inspectionDetailsSection');
            const toggleBtn = document.getElementById('editToggle');
            const notesView = document.getElementById('notesViewMode');
            const notesEdit = document.getElementById('inspectionNotes');
            const resultSelect = document.getElementById('inspectionResult');
            const editActions = document.getElementById('editActions');
            
            notesEdit.value = originalNotes;
            resultSelect.value = originalResult;
            
            section.classList.remove('edit-mode');
            toggleBtn.innerHTML = '✏️ Düzenle';
            toggleBtn.classList.remove('save-mode');
            
            notesView.style.display = 'block';
            notesEdit.style.display = 'none';
            notesEdit.disabled = true;
            resultSelect.disabled = true;
            editActions.style.display = 'none';
            
            isEditMode = false;
            hideUpdateAlerts();
        }
        
        function handleUpdateSubmit() {
            const completedId = document.getElementById('completedInspectionId').value;
            const inspectionNotes = document.getElementById('inspectionNotes').value;
            const inspectionResult = document.getElementById('inspectionResult').value;
            
            if (!inspectionNotes.trim()) {
                showUpdateAlert('Tetkik notları boş olamaz.', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax', 'update_inspection');
            formData.append('completed_id', completedId);
            formData.append('inspection_notes', inspectionNotes);
            formData.append('inspection_result', inspectionResult);
            
            document.body.classList.add('loading');
            
            fetch('inspection_summary.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.classList.remove('loading');
                
                if (data.success) {
                    showUpdateAlert(data.message, 'success');
                    
                    originalNotes = inspectionNotes;
                    originalResult = inspectionResult;
                    
                    const notesView = document.getElementById('notesViewMode');
                    notesView.innerHTML = inspectionNotes.replace(/\n/g, '<br>');
                    
                    setTimeout(() => {
                        cancelEdit();
                        location.reload(); 
                    }, 1500);
                } else {
                    showUpdateAlert(data.message, 'error');
                }
            })
            .catch(error => {
                document.body.classList.remove('loading');
                showUpdateAlert('Güncelleme sırasında hata oluştu.', 'error');
            });
        }
        
        function handleNewFileUpload(files) {
            if (files.length === 0) return;
            
            const maxFiles = 10;
            const maxFileSize = 10 * 1024 * 1024; 
            const allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
            
            const formData = new FormData();
            formData.append('ajax', 'upload_files');
            formData.append('completed_id', document.getElementById('completedInspectionId').value);
            
            let validFiles = 0;
            
            for (let i = 0; i < files.length && validFiles < maxFiles; i++) {
                const file = files[i];
                
                if (file.size > maxFileSize) {
                    showFileAlert(`${file.name} dosyası çok büyük. Maksimum 10MB olabilir.`, 'error');
                    continue;
                }
                
                const fileExtension = file.name.split('.').pop().toLowerCase();
                if (!allowedTypes.includes(fileExtension)) {
                    showFileAlert(`${file.name} dosya türü desteklenmiyor.`, 'error');
                    continue;
                }
                
                formData.append('new_files[]', file);
                validFiles++;
            }
            
            if (validFiles === 0) {
                showFileAlert('Yüklenecek geçerli dosya bulunamadı.', 'error');
                return;
            }
            
            document.body.classList.add('loading');
            
            fetch('inspection_summary.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.classList.remove('loading');
                
                if (data.success) {
                    showFileAlert(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showFileAlert(data.message, 'error');
                }
            })
            .catch(error => {
                document.body.classList.remove('loading');
                showFileAlert('Dosya yükleme sırasında hata oluştu.', 'error');
            });
            
            document.getElementById('newFiles').value = '';
        }
        
        function deleteFile(fileId) {
            if (!confirm('Bu dosyayı silmek istediğinize emin misiniz?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax', 'delete_file');
            formData.append('file_id', fileId);
            
            document.body.classList.add('loading');
            
            fetch('inspection_summary.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.classList.remove('loading');
                
                if (data.success) {
                    showFileAlert(data.message, 'success');
                    
                    const fileElement = document.getElementById(`file-${fileId}`);
                    if (fileElement) {
                        fileElement.style.transition = 'all 0.3s ease';
                        fileElement.style.opacity = '0';
                        fileElement.style.transform = 'scale(0.8)';
                        
                        setTimeout(() => {
                            fileElement.remove();
                            
                            const filesGrid = document.getElementById('filesGrid');
                            if (filesGrid && filesGrid.children.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                } else {
                    showFileAlert(data.message, 'error');
                }
            })
            .catch(error => {
                document.body.classList.remove('loading');
                showFileAlert('Dosya silme sırasında hata oluştu.', 'error');
            });
        }
        
        function setupDragAndDrop() {
            const uploadArea = document.querySelector('.file-upload-area');
            
            if (uploadArea) {
                uploadArea.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });
                
                uploadArea.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                });
                
                uploadArea.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    handleNewFileUpload(files);
                });
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function showUpdateAlert(message, type) {
            hideUpdateAlerts();
            
            const alertId = type === 'success' ? 'updateSuccessAlert' : 'updateErrorAlert';
            const alertElement = document.getElementById(alertId);
            
            if (alertElement) {
                alertElement.textContent = message;
                alertElement.style.display = 'block';
                
                setTimeout(() => {
                    alertElement.style.display = 'none';
                }, 5000);
            }
        }
        
        function hideUpdateAlerts() {
            document.getElementById('updateSuccessAlert').style.display = 'none';
            document.getElementById('updateErrorAlert').style.display = 'none';
        }
        
        function showFileAlert(message, type) {
            hideFileAlerts();
            
            const alertId = type === 'success' ? 'fileSuccessAlert' : 'fileErrorAlert';
            const alertElement = document.getElementById(alertId);
            
            if (alertElement) {
                alertElement.textContent = message;
                alertElement.style.display = 'block';
                
                setTimeout(() => {
                    alertElement.style.display = 'none';
                }, 5000);
            }
        }
        
        function hideFileAlerts() {
            document.getElementById('fileSuccessAlert').style.display = 'none';
            document.getElementById('fileErrorAlert').style.display = 'none';
        }
    </script>
</body>
</html>

<?php
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes) / log($k));
    
    return round(($bytes / pow($k, $i)), 2) . ' ' . $sizes[$i];
}
?>