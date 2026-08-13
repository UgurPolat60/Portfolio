<?php
require_once 'config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die('Bir hata oluştu');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_consultant'])) {
    requireCsrfOnPost();
    try {
        $first_name = trim($_POST['consultant_first_name']);
        $last_name = trim($_POST['consultant_last_name']);
        $email = trim($_POST['consultant_email']);
        $phone = trim($_POST['consultant_phone']);        
        $check_stmt = $pdo->prepare("SELECT id FROM consultants WHERE email = ?");
        $check_stmt->execute([$email]);
        
        if ($check_stmt->fetch()) {
            $error_message = "Bu e-mail adresi ile kayıtlı bir danışman zaten mevcut!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO consultants (first_name, last_name, email, phone, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $email, $phone, $user_id]);
        
            $log_stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, level, content, ip_address) VALUES (?, 'data_change', 'INFO', ?, ?)");
            $log_stmt->execute([$user_id, "Yeni danışman eklendi: $first_name $last_name ($email)", $_SERVER['REMOTE_ADDR'] ?? '::1']);
            
            $success_message = "Danışman başarıyla eklendi!";
        }
        
    } catch(PDOException $e) {
        $error_message = "Hata: " . $e->getMessage();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_temp_record'])) {
    requireCsrfOnPost();
    try {
        $temp_record_id = $_POST['temp_record_id'];
        $document_type_id = $_POST['document_type_id'];
        $accreditation_type = $_POST['accreditation_type'] ?? null;
        $document_number = $_POST['document_number'];
        $scope = $_POST['scope'] ?? '';
        $issue_date = $_POST['issue_date'];
        $expiry_date = $_POST['expiry_date'];
        $level = $_POST['level'] ?? null;
        $consultant_id = !empty($_POST['consultant_id']) ? $_POST['consultant_id'] : null;
        
        $temp_stmt = $pdo->prepare("SELECT * FROM inspection_temp_records WHERE id = ?");
        $temp_stmt->execute([$temp_record_id]);
        $temp_record = $temp_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($temp_record) {
            $pdo->beginTransaction();
            
            $inspection_1_status = ($temp_record['completed_inspections'] >= 1) ? 'tamamlandi' : 'bekliyor';
            $inspection_2_status = ($temp_record['completed_inspections'] >= 2) ? 'tamamlandi' : 'bekliyor';
            
            $cert_stmt = $pdo->prepare("INSERT INTO certifications (company_id, document_type_id, accreditation_type, consultant_id, document_number, scope, issue_date, expiry_date, level, status, inspection_1_status, inspection_2_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)");
            $cert_stmt->execute([$temp_record['company_id'], $document_type_id, $accreditation_type, $consultant_id, $document_number, $scope, $issue_date, $expiry_date, $level, $inspection_1_status, $inspection_2_status, $user_id]);
            
            $certification_id = $pdo->lastInsertId();
            
            if ($temp_record['completed_inspections'] > 0) {
                $moved_files = [];
                if ($temp_record['inspection_files']) {
                    $files = json_decode($temp_record['inspection_files'], true);
                    if (is_array($files)) {
                        $upload_dir = "uploads/inspection_reports/";
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        foreach ($files as $index => $file) {
                            if (file_exists($file)) {
                                $new_file_name = time() . "_{$certification_id}_converted_{$index}." . pathinfo($file, PATHINFO_EXTENSION);
                                $new_file_path = $upload_dir . $new_file_name;
                                
                                if (copy($file, $new_file_path)) {
                                    $moved_files[] = $new_file_path;
                                    unlink($file); 
                                }
                            }
                        }
                    }
                }
                
                for ($i = 1; $i <= $temp_record['completed_inspections']; $i++) {
                    $report_files = ($i == 1 && !empty($moved_files)) ? json_encode($moved_files) : null;
                    
                    $inspection_stmt = $pdo->prepare("INSERT INTO inspection_records (document_id, inspection_type, status, completed_date, report_file, note, created_by) VALUES (?, ?, 'completed', CURDATE(), ?, ?, ?)");
                    $inspection_stmt->execute([$certification_id, $i, $report_files, $temp_record['inspection_notes'], $user_id]);
                }
            }
            
            $delete_stmt = $pdo->prepare("DELETE FROM inspection_temp_records WHERE id = ?");
            $delete_stmt->execute([$temp_record_id]);
            
            $pdo->commit();
            
            $log_stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, level, content, ip_address) VALUES (?, 'data_change', 'INFO', ?, ?)");
            $log_stmt->execute([$user_id, "Tetkik kaydı belgelendirmeye dönüştürüldü: " . $document_number, $_SERVER['REMOTE_ADDR'] ?? '::1']);
            
            $success_message = "Tetkik kaydı başarıyla belgelendirmeye dönüştürüldü!";
        }
        
    } catch(PDOException $e) {
        $pdo->rollback();
        $error_message = "Hata: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['convert_temp_record']) && !isset($_POST['add_consultant'])) {
    requireCsrfOnPost();
    try {
        $company_id = $_POST['company_id'];
        $document_type_id = $_POST['document_type_id'];
        $accreditation_type = $_POST['accreditation_type'] ?? null;
        $document_number = $_POST['document_number'];
        $scope = $_POST['scope'] ?? '';
        $issue_date = $_POST['issue_date'];
        $expiry_date = $_POST['expiry_date'];
        $level = $_POST['level'] ?? null;
        $completed_inspections = $_POST['completed_inspections'] ?? 0;
        $consultant_id = !empty($_POST['consultant_id']) ? $_POST['consultant_id'] : null;
        
        $stmt = $pdo->prepare("INSERT INTO certifications (company_id, document_type_id, accreditation_type, consultant_id, document_number, scope, issue_date, expiry_date, level, status, inspection_1_status, inspection_2_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)");
        
        $inspection_1_status = ($completed_inspections >= 1) ? 'tamamlandi' : 'bekliyor';
        $inspection_2_status = ($completed_inspections >= 2) ? 'tamamlandi' : 'bekliyor';
        
        $stmt->execute([$company_id, $document_type_id, $accreditation_type, $consultant_id, $document_number, $scope, $issue_date, $expiry_date, $level, $inspection_1_status, $inspection_2_status, $user_id]);
        
        $certification_id = $pdo->lastInsertId();
        
        if ($completed_inspections > 0) {
            for ($i = 1; $i <= $completed_inspections; $i++) {
                $inspection_note = $_POST["inspection_{$i}_note"] ?? '';
                
                if (isset($_FILES["inspection_{$i}_files"]) && !empty($_FILES["inspection_{$i}_files"]['name'][0])) {
                    $uploaded_files = [];
                    
                    $upload_dir = "uploads/inspection_reports/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_count = count($_FILES["inspection_{$i}_files"]['name']);
                    for ($j = 0; $j < $file_count; $j++) {
                        if ($_FILES["inspection_{$i}_files"]['error'][$j] === UPLOAD_ERR_OK) {
                            $file_name = time() . "_{$certification_id}_{$i}_{$j}." . pathinfo($_FILES["inspection_{$i}_files"]['name'][$j], PATHINFO_EXTENSION);
                            $file_path = $upload_dir . $file_name;
                            
                            $tmp = $_FILES["inspection_{$i}_files"]['tmp_name'][$j];
                            $orig = $_FILES["inspection_{$i}_files"]['name'][$j];
                            $size = $_FILES["inspection_{$i}_files"]['size'][$j];
                            if (!isValidUpload($tmp, $orig, $size)) { throw new Exception('İstek geçersiz'); }
                            if (move_uploaded_file($tmp, $file_path)) {
                                $uploaded_files[] = $file_path;
                            }
                        }
                    }
                    
                    $report_files = json_encode($uploaded_files);
                    $stmt = $pdo->prepare("INSERT INTO inspection_records (document_id, inspection_type, status, completed_date, report_file, note, created_by) VALUES (?, ?, 'completed', CURDATE(), ?, ?, ?)");
                    $stmt->execute([$certification_id, $i, $report_files, $inspection_note, $user_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO inspection_records (document_id, inspection_type, status, completed_date, note, created_by) VALUES (?, ?, 'completed', CURDATE(), ?, ?)");
                    $stmt->execute([$certification_id, $i, $inspection_note, $user_id]);
                }
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, level, content, ip_address) VALUES (?, 'data_change', 'INFO', ?, ?)");
        $stmt->execute([$user_id, "Yeni belgelendirme planı oluşturuldu: $document_number", $_SERVER['REMOTE_ADDR'] ?? '::1']);
        
        $success_message = 'İşlem başarılı';
        
    } catch(PDOException $e) {
        $error_message = 'Bir hata oluştu';
    }
}

$companies_stmt = $pdo->prepare("SELECT id, short_name, trade_name FROM companies WHERE status = 'active' ORDER BY short_name");
$companies_stmt->execute();
$companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);

$document_types_stmt = $pdo->prepare("SELECT id, name, standard, validity_period, interim_audit_count FROM document_types ORDER BY name");
$document_types_stmt->execute();
$document_types = $document_types_stmt->fetchAll(PDO::FETCH_ASSOC);

$consultants_stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone FROM consultants WHERE status = 'active' ORDER BY first_name, last_name");
$consultants_stmt->execute();
$consultants = $consultants_stmt->fetchAll(PDO::FETCH_ASSOC);

$temp_records_stmt = $pdo->prepare("
    SELECT 
        itr.*,
        c.short_name as company_name,
        c.trade_name as company_trade_name
    FROM inspection_temp_records itr
    LEFT JOIN companies c ON itr.company_id = c.id
    ORDER BY itr.created_at DESC
");
$temp_records_stmt->execute();
$temp_records = $temp_records_stmt->fetchAll(PDO::FETCH_ASSOC);

$previous_inspections_stmt = $pdo->prepare("
    SELECT 
        ir.id,
        ir.document_id,
        ir.inspection_type,
        ir.status,
        ir.note,
        ir.inspection_date,
        ir.completed_date,
        ir.report_file,
        c.document_number,
        co.id as company_id,
        co.short_name as company_name,
        co.trade_name as company_trade_name,
        dt.name as document_type_name,
        dt.standard as document_standard
    FROM inspection_records ir
    LEFT JOIN certifications c ON ir.document_id = c.id
    LEFT JOIN companies co ON c.company_id = co.id
    LEFT JOIN document_types dt ON c.document_type_id = dt.id
    WHERE ir.status = 'completed'
AND ir.completed_date IS NOT NULL
    ORDER BY co.short_name, ir.inspection_type, ir.completed_date DESC
");
$previous_inspections_stmt->execute();
$previous_inspections_data = $previous_inspections_stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped_inspections = [];
foreach ($previous_inspections_data as $inspection) {
    $company_key = $inspection['company_id'];
    if (!isset($grouped_inspections[$company_key])) {
        $grouped_inspections[$company_key] = [
            'company_name' => $inspection['company_name'],
            'company_trade_name' => $inspection['company_trade_name'],
            'inspections' => []
        ];
    }
    $grouped_inspections[$company_key]['inspections'][] = $inspection;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgelendirme Planları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background-color: #6c5ce7;
            padding: 1rem 0;
        }

        .navbar .btn {
            margin-left: 10px;
        }

        .main-container {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: white;
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: #6c5ce7;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .btn-primary {
            background-color: #6c5ce7;
            border-color: #6c5ce7;
            border-radius: 10px;
            padding: 0.7rem 1.5rem;
            font-weight: 500;
        }

        .btn-primary:hover {
            background-color: #5a4fcf;
            border-color: #5a4fcf;
        }

        .btn-success {
            background-color: #00b894;
            border-color: #00b894;
            border-radius: 10px;
            padding: 0.7rem 1.5rem;
            font-weight: 500;
        }

        .btn-inspection {
            background-color: #6c5ce7;
            border-color: #6c5ce7;
            color: white;
            border-radius: 10px;
            padding: 0.7rem 1.5rem;
            font-weight: 500;
        }

        .btn-inspection:hover {
            background-color: #5a4fcf;
            border-color: #5a4fcf;
            color: white;
        }

        .btn-consultant {
            background-color: #6c5ce7;
            border-color: #6c5ce7;
            color: white;
            border-radius: 10px;
            padding: 0.7rem 1.5rem;
            font-weight: 500;
        }

        .btn-consultant:hover {
            background-color: #5a4fcf;
            border-color: #5a4fcf;
            color: white;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.7rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #6c5ce7;
            box-shadow: 0 0 0 0.2rem rgba(108, 92, 231, 0.25);
        }

        .inspection-files {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            border: 2px dashed #dee2e6;
            transition: all 0.3s ease;
        }

        .inspection-files:hover {
            border-color: #6c5ce7;
            background: rgba(108, 92, 231, 0.05);
        }

        .alert {
            border: none;
            border-radius: 15px;
            padding: 1rem 1.5rem;
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
            border-left: 4px solid #00b894;
        }

        .alert-danger {
            background: rgba(232, 67, 147, 0.1);
            color: #e84393;
            border-left: 4px solid #e84393;
        }

        .temp-record-card {
            background: linear-gradient(135deg, #6c5ce7, #5a4fcf);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .temp-record-header {
            position: relative;
            z-index: 2;
        }

        .temp-record-details {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
            color: #333;
            display: none;
        }

        .temp-record-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .temp-record-buttons .btn {
            flex: 1;
            min-width: 120px;
        }

        .btn-convert {
            background-color: #6c5ce7;
            border-color: #6c5ce7;
            color: white;
        }

        .btn-convert:hover {
            background-color: #5a4fcf;
            border-color: #5a4fcf;
            color: white;
        }

        .searchable-select {
            position: relative;
        }

        .searchable-select .select-wrapper {
            position: relative;
        }

        .searchable-select input[type="text"] {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .searchable-select input[type="text"]:focus {
            outline: none;
            border-color: #6c5ce7;
            box-shadow: 0 0 0 0.2rem rgba(108, 92, 231, 0.25);
        }

        .searchable-select .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e9ecef;
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .searchable-select .dropdown-list.show {
            display: block;
        }

        .searchable-select .dropdown-item {
            padding: 0.7rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
        }

        .searchable-select .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .searchable-select .dropdown-item.selected {
            background-color: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
            font-weight: 500;
        }

        .searchable-select .dropdown-arrow {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #666;
            font-size: 12px;
        }

        .company-header {
            background: linear-gradient(135deg, #6c5ce7, #5a4fcf);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .company-header:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 92, 231, 0.3);
        }

        .inspection-item {
            background: #f8f9fa;
            border-left: 4px solid #6c5ce7;
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .inspection-item:hover {
            background: #e9ecef;
            border-left-color: #5a4fcf;
        }

        .inspection-details {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 0.5rem 0;
            display: none;
        }

        .inspection-type-badge {
            background: #6c5ce7;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .searchable-select .dropdown-list {
                max-height: 150px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <div class="navbar-nav ms-auto">
                <a href="document_tracking.php" class="btn btn-light me-2">
                    <i class="fas fa-arrow-left me-1"></i>Geri Dön
                </a>
            </div>
        </div>
    </nav>

    <div class="container main-container" style="margin-top: 6rem;">

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-calendar-alt me-2"></i>Belgelendirme Planları</h2>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-consultant" data-bs-toggle="modal" data-bs-target="#addConsultantModal">
                    <i class="fas fa-user-plus me-2"></i>Danışman Ekle
                </button>
                <a href="add_inspection.php" class="btn btn-inspection">
                    <i class="fas fa-clipboard-check me-2"></i>Tetkik ile Ekle
                </a>
            </div>
        </div>

        <?php if (!empty($temp_records)): ?>
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-hourglass-half me-2"></i>
                        Tetkik ile Eklenenler
                    </h4>
                    <p class="mb-0 mt-2 opacity-75">Bu kayıtlar tetkik yapıldıktan sonra eklendi. Belge bilgilerini tamamlayarak gerçek belgelendirmeye dönüştürebilirsiniz.</p>
                </div>
                <div class="card-body">
                    <?php foreach ($temp_records as $temp_record): ?>
                        <div class="temp-record-card">
                            <div class="temp-record-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">
                                            <i class="fas fa-building me-2"></i>
                                            <?php echo htmlspecialchars($temp_record['company_name']); ?>
                                        </h5>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="badge bg-light text-dark">
                                                <?php echo $temp_record['completed_inspections']; ?> Tetkik Tamamlandı
                                            </span>
                                            <small class="opacity-75">
                                                <?php echo date('d.m.Y H:i', strtotime($temp_record['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="temp-record-buttons">
                                        <button class="btn btn-light btn-sm" onclick="toggleTempDetails(<?php echo $temp_record['id']; ?>)">
                                            <i class="fas fa-eye me-1"></i>Detaylar
                                        </button>
                                        <button class="btn btn-convert btn-sm" onclick="showConvertModal(<?php echo $temp_record['id']; ?>)">
                                            <i class="fas fa-arrow-right me-1"></i>Belgeye Dönüştür
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="temp-record-details" id="temp-details-<?php echo $temp_record['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-info-circle me-2 text-primary"></i>Tetkik Bilgileri</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Firma:</strong></td>
                                                <td><?php echo htmlspecialchars($temp_record['company_name'] . ' - ' . $temp_record['company_trade_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tamamlanan Tetkik:</strong></td>
                                                <td><?php echo $temp_record['completed_inspections']; ?> Tetkik</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Kayıt Tarihi:</strong></td>
                                                <td><?php echo date('d.m.Y H:i', strtotime($temp_record['created_at'])); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($temp_record['inspection_notes']): ?>
                                            <h6><i class="fas fa-sticky-note me-2 text-warning"></i>Tetkik Notları</h6>
                                            <div class="alert alert-light">
                                                <?php echo nl2br(htmlspecialchars($temp_record['inspection_notes'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($temp_record['inspection_files']): ?>
                                            <h6><i class="fas fa-download me-2 text-success"></i>Tetkik Dosyaları</h6>
                                            <?php 
                                            $files = json_decode($temp_record['inspection_files'], true);
                                            if (is_array($files) && !empty($files)):
                                            ?>
                                                <div class="list-group">
                                                    <?php foreach ($files as $index => $file): ?>
                                                        <a href="<?php echo htmlspecialchars($file); ?>" 
                                                           target="_blank" 
                                                           class="list-group-item list-group-item-action">
                                                            <i class="fas fa-file-pdf me-2 text-danger"></i>
                                                            Tetkik Dosyası <?php echo $index + 1; ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    Yeni Belgelendirme Planı Ekle
                </h4>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="planForm">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_id" class="form-label">Firma <span class="text-danger">*</span></label>
                                <div class="searchable-select" id="companySearchable">
                                    <div class="select-wrapper">
                                        <input type="text" id="companySearch" placeholder="Firma arayın veya seçin..." autocomplete="off" required>
                                        <span class="dropdown-arrow">▼</span>
                                        <div class="dropdown-list" id="companyDropdown">
                                            <?php foreach ($companies as $company): ?>
                                                <div class="dropdown-item" data-value="<?php echo $company['id']; ?>" data-text="<?php echo htmlspecialchars($company['short_name'] . ' - ' . $company['trade_name']); ?>">
                                                    <?php echo htmlspecialchars($company['short_name'] . ' - ' . $company['trade_name']); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <input type="hidden" id="company_id" name="company_id" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type_id" class="form-label">Belge Türü <span class="text-danger">*</span></label>
                                <div class="searchable-select" id="documentTypeSearchable">
                                    <div class="select-wrapper">
                                        <input type="text" id="documentTypeSearch" placeholder="Belge türü arayın veya seçin..." autocomplete="off" required>
                                        <span class="dropdown-arrow">▼</span>
                                        <div class="dropdown-list" id="documentTypeDropdown">
                                            <?php foreach ($document_types as $type): ?>
                                                <div class="dropdown-item" 
                                                     data-value="<?php echo $type['id']; ?>" 
                                                     data-text="<?php echo htmlspecialchars($type['name'] . ' - ' . $type['standard']); ?>"
                                                     data-audit-count="<?php echo $type['interim_audit_count']; ?>"
                                                     data-validity-period="<?php echo $type['validity_period']; ?>">
                                                    <?php echo htmlspecialchars($type['name'] . ' - ' . $type['standard']); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <input type="hidden" id="document_type_id" name="document_type_id" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="accreditation_type" class="form-label">Akreditasyon Türü</label>
                                <select class="form-select" id="accreditation_type" name="accreditation_type">
                                    <option value="">Akreditasyon Türü Seçiniz</option>
                                    <option value="Türkak">Türkak</option>
                                    <option value="IAS">IAS</option>
                                    <option value="UKAS">UKAS</option>
                                    <option value="DAKKS">DAKKS</option>
                                    <option value="UAF">UAF</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="document_number" class="form-label">Belge Numarası <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="document_number" name="document_number" placeholder="Örn: CERT-2024-001" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="level" class="form-label">Seviye</label>
                                <input type="number" class="form-control" id="level" name="level" placeholder="Seviye numarası">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="consultant_id" class="form-label">Danışman <small class="text-muted"></small></label>
                                <div class="searchable-select" id="consultantSearchable">
                                    <div class="select-wrapper">
                                        <input type="text" id="consultantSearch" placeholder="Danışman arayın veya seçin..." autocomplete="off">
                                        <span class="dropdown-arrow">▼</span>
                                        <div class="dropdown-list" id="consultantDropdown">
                                            <div class="dropdown-item" data-value="" data-text="">Danışman seçilmedi</div>
                                            <?php foreach ($consultants as $consultant): ?>
                                                <div class="dropdown-item" 
                                                     data-value="<?php echo $consultant['id']; ?>" 
                                                     data-text="<?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name'] . ' - ' . $consultant['email']); ?>">
                                                    <?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name'] . ' - ' . $consultant['email']); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <input type="hidden" id="consultant_id" name="consultant_id">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="scope" class="form-label">Kapsam</label>
                        <textarea class="form-control" id="scope" name="scope" rows="3" placeholder="Belgelendirme kapsamını giriniz..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="issue_date" class="form-label">Belge Basım Tarihi <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="issue_date" name="issue_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="expiry_date" class="form-label">Belge Bitiş Tarihi <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="completed_inspections" class="form-label">Tamamlanan Tetkik Sayısı</label>
                                <input type="number" class="form-control" id="completed_inspections" name="completed_inspections" value="0" min="0" max="10" placeholder="Örn: 2">
                            </div>
                        </div>
                    </div>

                    <div id="inspection-files-section" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div id="inspection-1-files" class="inspection-files" style="display: none;">
                                    <h6><i class="fas fa-file-alt me-2"></i>1. Tetkik Raporları</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Tetkik Notu</label>
                                        <textarea class="form-control" name="inspection_1_note" rows="2" placeholder="1. tetkik ile ilgili notlar..."></textarea>
                                    </div>
                                    <input type="file" class="form-control" name="inspection_1_files[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple>
                                    <small class="text-muted">PDF, Word veya resim dosyaları yükleyebilirsiniz. </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div id="inspection-2-files" class="inspection-files" style="display: none;">
                                    <h6><i class="fas fa-file-alt me-2"></i>2. Tetkik Raporları</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Tetkik Notu</label>
                                        <textarea class="form-control" name="inspection_2_note" rows="2" placeholder="2. tetkik ile ilgili notlar..."></textarea>
                                    </div>
                                    <input type="file" class="form-control" name="inspection_2_files[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple>
                                    <small class="text-muted">PDF, Word veya resim dosyaları yükleyebilirsiniz. </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    
                    <div class="text-end">
                        <button type="reset" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-redo me-1"></i>Temizle
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Belgelendirme Planını Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Daha Önceden Verilen Tetkikler
                </h4>
            </div>
            <div class="card-body">
                <?php if (empty($grouped_inspections)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Henüz tamamlanmış tetkik bulunmuyor</h5>
                        <p class="text-muted">Yukarıdaki formdan tamamlanmış tetkiklerle belge oluşturabilirsiniz.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_inspections as $company_id => $company_data): ?>
                        <div class="company-section mb-4">
                            <div class="company-header" onclick="toggleCompanyInspections(<?php echo $company_id; ?>)">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0">
                                            <i class="fas fa-building me-2"></i>
                                            <?php echo htmlspecialchars($company_data['company_name']); ?>
                                        </h5>
                                        <small class="opacity-75"><?php echo htmlspecialchars($company_data['company_trade_name']); ?></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-light text-dark me-2"><?php echo count($company_data['inspections']); ?> Tetkik</span>
                                        <i class="fas fa-chevron-down" id="chevron-<?php echo $company_id; ?>"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="company-inspections" id="inspections-<?php echo $company_id; ?>" style="display: none;">
                                <?php foreach ($company_data['inspections'] as $inspection): ?>
                                    <div class="inspection-item" onclick="toggleInspectionDetails(<?php echo $inspection['id']; ?>)">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="inspection-type-badge me-2">
                                                    <?php echo $inspection['inspection_type']; ?>. Tetkik
                                                </span>
                                                <strong><?php echo htmlspecialchars($inspection['document_number']); ?></strong>
                                                <span class="text-muted ms-2"><?php echo htmlspecialchars($inspection['document_type_name']); ?></span>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">
                                                    <?php echo $inspection['completed_date'] ? date('d.m.Y', strtotime($inspection['completed_date'])) : 'Tarih belirtilmemiş'; ?>
                                                </small>
                                                <i class="fas fa-chevron-down ms-2" id="detail-chevron-<?php echo $inspection['id']; ?>"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="inspection-details" id="details-<?php echo $inspection['id']; ?>">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-info-circle me-2 text-primary"></i>Tetkik Bilgileri</h6>
                                                <table class="table table-sm">
                                                    <tr>
                                                        <td><strong>Belge Numarası:</strong></td>
                                                        <td><?php echo htmlspecialchars($inspection['document_number']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Belge Türü:</strong></td>
                                                        <td><?php echo htmlspecialchars($inspection['document_type_name']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Standard:</strong></td>
                                                        <td><?php echo htmlspecialchars($inspection['document_standard']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Tetkik Türü:</strong></td>
                                                        <td><?php echo $inspection['inspection_type']; ?>. Tetkik</td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Tamamlanma Tarihi:</strong></td>
                                                        <td><?php echo $inspection['completed_date'] ? date('d.m.Y', strtotime($inspection['completed_date'])) : 'Belirtilmemiş'; ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if ($inspection['note']): ?>
                                                    <h6><i class="fas fa-sticky-note me-2 text-warning"></i>Tetkik Notu</h6>
                                                    <div class="alert alert-light">
                                                        <?php echo nl2br(htmlspecialchars($inspection['note'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($inspection['report_file']): ?>
                                                    <h6><i class="fas fa-download me-2 text-success"></i>Tetkik Dosyaları</h6>
                                                    <?php 
                                                    $files = json_decode($inspection['report_file'], true);
                                                    if (is_array($files) && !empty($files)):
                                                    ?>
                                                        <div class="list-group">
                                                            <?php foreach ($files as $index => $file): ?>
                                                                <a href="<?php echo htmlspecialchars($file); ?>" 
                                                                   target="_blank" 
                                                                   class="list-group-item list-group-item-action">
                                                                    <i class="fas fa-file-pdf me-2 text-danger"></i>
                                                                    Dosya <?php echo $index + 1; ?>
                                                                </a>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php elseif (!empty($inspection['report_file'])): ?>
                                                        <div class="list-group">
                                                            <a href="<?php echo htmlspecialchars($inspection['report_file']); ?>" 
                                                               target="_blank" 
                                                               class="list-group-item list-group-item-action">
                                                                <i class="fas fa-file-pdf me-2 text-danger"></i>
                                                                Tetkik Raporu İndir
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addConsultantModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #6c5ce7; color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>
                        Yeni Danışman Ekle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="consultantForm">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <div class="modal-body">
                        <input type="hidden" name="add_consultant" value="1">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="consultant_first_name" class="form-label">Ad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="consultant_first_name" name="consultant_first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="consultant_last_name" class="form-label">Soyad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="consultant_last_name" name="consultant_last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="consultant_email" class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="consultant_email" name="consultant_email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="consultant_phone" class="form-label">Telefon <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="consultant_phone" name="consultant_phone" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>İptal
                        </button>
                        <button type="submit" class="btn btn-consultant">
                            <i class="fas fa-save me-1"></i>Danışman Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="convertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-arrow-right me-2"></i>
                        Belgeye Dönüştür
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="convertForm">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <div class="modal-body">
                        <input type="hidden" name="convert_temp_record" value="1">
                        <input type="hidden" name="temp_record_id" id="convertTempId">
                        
                         
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="convertDocumentNumber" class="form-label">Belge Numarası <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="convertDocumentNumber" name="document_number" placeholder="Örn: CERT-2024-001" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="convertDocumentType" class="form-label">Belge Türü <span class="text-danger">*</span></label>
                                    <div class="searchable-select" id="convertDocumentTypeSearchable">
                                        <div class="select-wrapper">
                                            <input type="text" id="convertDocumentTypeSearch" placeholder="Belge türü arayın..." autocomplete="off" required>
                                            <span class="dropdown-arrow">▼</span>
                                            <div class="dropdown-list" id="convertDocumentTypeDropdown">
                                                <?php foreach ($document_types as $type): ?>
                                                    <div class="dropdown-item" 
                                                         data-value="<?php echo $type['id']; ?>" 
                                                         data-text="<?php echo htmlspecialchars($type['name'] . ' - ' . $type['standard']); ?>"
                                                         data-validity-period="<?php echo $type['validity_period']; ?>">
                                                        <?php echo htmlspecialchars($type['name'] . ' - ' . $type['standard']); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <input type="hidden" id="convertDocumentType" name="document_type_id" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="convertAccreditation" class="form-label">Akreditasyon Türü</label>
                                    <select class="form-select" id="convertAccreditation" name="accreditation_type">
                                        <option value="">Akreditasyon Türü Seçiniz</option>
                                        <option value="Türkak">Türkak</option>
                                        <option value="IAS">IAS</option>
                                        <option value="UKAS">UKAS</option>
                                        <option value="DAKKS">DAKKS</option>
                                        <option value="UAF">UAF</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="convertLevel" class="form-label">Seviye</label>
                                    <input type="number" class="form-control" id="convertLevel" name="level" placeholder="Seviye numarası">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="convertConsultant" class="form-label">Danışman <small class="text-muted"></small></label>
                                    <div class="searchable-select" id="convertConsultantSearchable">
                                        <div class="select-wrapper">
                                            <input type="text" id="convertConsultantSearch" placeholder="Danışman arayın veya seçin..." autocomplete="off">
                                            <span class="dropdown-arrow">▼</span>
                                            <div class="dropdown-list" id="convertConsultantDropdown">
                                                <div class="dropdown-item" data-value="" data-text="">Danışman seçilmedi</div>
                                                <?php foreach ($consultants as $consultant): ?>
                                                    <div class="dropdown-item" 
                                                         data-value="<?php echo $consultant['id']; ?>" 
                                                         data-text="<?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name'] . ' - ' . $consultant['email']); ?>">
                                                        <?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name'] . ' - ' . $consultant['email']); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <input type="hidden" id="convertConsultant" name="consultant_id">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="convertScope" class="form-label">Kapsam</label>
                            <textarea class="form-control" id="convertScope" name="scope" rows="3" placeholder="Belgelendirme kapsamını giriniz..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="convertIssueDate" class="form-label">Belge Basım Tarihi <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="convertIssueDate" name="issue_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="convertExpiryDate" class="form-label">Belge Bitiş Tarihi <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="convertExpiryDate" name="expiry_date" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>İptal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i>Belgeye Dönüştür
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
                if (!searchInput.parentElement.parentElement.contains(e.target)) {
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
                    
                    if (onSelectCallback) {
                        onSelectCallback(this);
                    }
                
                    allItems.forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
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
        
        let companySelect, documentTypeSelect, consultantSelect, convertDocumentTypeSelect, convertConsultantSelect;
        
        document.addEventListener('DOMContentLoaded', function() {
            companySelect = initSearchableSelect('companySearch', 'companyDropdown', 'company_id');
            
            documentTypeSelect = initSearchableSelect('documentTypeSearch', 'documentTypeDropdown', 'document_type_id', function(selectedItem) {
                const issueDateInput = document.getElementById('issue_date');
                const expiryDateInput = document.getElementById('expiry_date');
                
                if (selectedItem.dataset.validityPeriod && issueDateInput.value) {
                    const validityPeriod = selectedItem.dataset.validityPeriod;
                    const issueDate = new Date(issueDateInput.value);
                    const expiryDate = new Date(issueDate);
                    expiryDate.setFullYear(expiryDate.getFullYear() + parseInt(validityPeriod));
                    
                    expiryDateInput.value = expiryDate.toISOString().split('T')[0];
                }
            });

            consultantSelect = initSearchableSelect('consultantSearch', 'consultantDropdown', 'consultant_id');

            convertDocumentTypeSelect = initSearchableSelect('convertDocumentTypeSearch', 'convertDocumentTypeDropdown', 'convertDocumentType', function(selectedItem) {
                const issueDateInput = document.getElementById('convertIssueDate');
                const expiryDateInput = document.getElementById('convertExpiryDate');
                
                if (selectedItem.dataset.validityPeriod && issueDateInput.value) {
                    const validityPeriod = selectedItem.dataset.validityPeriod;
                    const issueDate = new Date(issueDateInput.value);
                    const expiryDate = new Date(issueDate);
                    expiryDate.setFullYear(expiryDate.getFullYear() + parseInt(validityPeriod));
                    
                    expiryDateInput.value = expiryDate.toISOString().split('T')[0];
                }
            });

            convertConsultantSelect = initSearchableSelect('convertConsultantSearch', 'convertConsultantDropdown', 'convertConsultant');
            
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('issue_date').value = today;
            document.getElementById('convertIssueDate').value = today;
            
            document.getElementById('completed_inspections').addEventListener('change', function() {
                const completedCount = parseInt(this.value);
                const inspectionFilesSection = document.getElementById('inspection-files-section');
                const inspection1Files = document.getElementById('inspection-1-files');
                const inspection2Files = document.getElementById('inspection-2-files');
                
                if (completedCount > 0) {
                    inspectionFilesSection.style.display = 'block';
                    inspection1Files.style.display = completedCount >= 1 ? 'block' : 'none';
                    inspection2Files.style.display = completedCount >= 2 ? 'block' : 'none';
                } else {
                    inspectionFilesSection.style.display = 'none';
                }
            });
        });

        document.getElementById('issue_date').addEventListener('change', function() {
            const expiryDateInput = document.getElementById('expiry_date');
            const documentTypeValue = document.getElementById('document_type_id').value;
            
            if (documentTypeValue && this.value) {
                const selectedItem = document.querySelector(`#documentTypeDropdown .dropdown-item[data-value="${documentTypeValue}"]`);
                
                if (selectedItem && selectedItem.dataset.validityPeriod) {
                    const validityPeriod = selectedItem.dataset.validityPeriod;
                    const issueDate = new Date(this.value);
                    const expiryDate = new Date(issueDate);
                    expiryDate.setFullYear(expiryDate.getFullYear() + parseInt(validityPeriod));
                    
                    expiryDateInput.value = expiryDate.toISOString().split('T')[0];
                }
            }
        });

        document.getElementById('convertIssueDate').addEventListener('change', function() {
            const expiryDateInput = document.getElementById('convertExpiryDate');
            const documentTypeValue = document.getElementById('convertDocumentType').value;
            
            if (documentTypeValue && this.value) {
                const selectedItem = document.querySelector(`#convertDocumentTypeDropdown .dropdown-item[data-value="${documentTypeValue}"]`);
                
                if (selectedItem && selectedItem.dataset.validityPeriod) {
                    const validityPeriod = selectedItem.dataset.validityPeriod;
                    const issueDate = new Date(this.value);
                    const expiryDate = new Date(issueDate);
                    expiryDate.setFullYear(expiryDate.getFullYear() + parseInt(validityPeriod));
                    
                    expiryDateInput.value = expiryDate.toISOString().split('T')[0];
                }
            }
        });
        
        document.getElementById('planForm').addEventListener('submit', function(e) {
            const completedCount = parseInt(document.getElementById('completed_inspections').value);
            
            if (!document.getElementById('document_type_id').value) {
                e.preventDefault();
                alert('Lütfen önce belge türünü seçiniz.');
                return;
            }
            
            if (completedCount >= 1) {
                const inspection1Input = document.querySelector('input[name="inspection_1_files[]"]');
                const inspection1Note = document.querySelector('textarea[name="inspection_1_note"]');
                
                if (inspection1Input.files.length === 0 && !inspection1Note.value.trim()) {
                    const confirmSubmit = confirm('1. tetkik için dosya veya not girilmemiş. Devam etmek istiyor musunuz?');
                    if (!confirmSubmit) {
                        e.preventDefault();
                        return;
                    }
                }
            }
            
            if (completedCount >= 2) {
                const inspection2Input = document.querySelector('input[name="inspection_2_files[]"]');
                const inspection2Note = document.querySelector('textarea[name="inspection_2_note"]');
                
                if (inspection2Input.files.length === 0 && !inspection2Note.value.trim()) {
                    const confirmSubmit = confirm('2. tetkik için dosya veya not girilmemiş. Devam etmek istiyor musunuz?');
                    if (!confirmSubmit) {
                        e.preventDefault();
                        return;
                    }
                }
            }
        });

        document.getElementById('consultantForm').addEventListener('submit', function(e) {
            const email = document.getElementById('consultant_email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Lütfen geçerli bir e-mail adresi giriniz.');
                return;
            }
        });

        document.getElementById('addConsultantModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('consultantForm').reset();
        });

        <?php if (isset($success_message) && strpos($success_message, 'Danışman başarıyla eklendi') !== false): ?>
            setTimeout(function() {
                location.reload();
            }, 1500);
        <?php endif; ?>
        
        document.getElementById('planForm').addEventListener('reset', function() {
            setTimeout(() => {
                if (companySelect) companySelect.clear();
                if (documentTypeSelect) documentTypeSelect.clear();
                if (consultantSelect) consultantSelect.clear();
                
                document.getElementById('inspection-files-section').style.display = 'none';
                document.getElementById('inspection-1-files').style.display = 'none';
                document.getElementById('inspection-2-files').style.display = 'none';
                
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('issue_date').value = today;
            }, 10);
        });
        
        function toggleTempDetails(recordId) {
            const detailsDiv = document.getElementById('temp-details-' + recordId);
            
            if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
        }
        
        function showConvertModal(recordId) {
            document.getElementById('convertTempId').value = recordId;
            
            document.getElementById('convertForm').reset();
            if (convertDocumentTypeSelect) convertDocumentTypeSelect.clear();
            if (convertConsultantSelect) convertConsultantSelect.clear();
            
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('convertIssueDate').value = today;
            
            const modal = new bootstrap.Modal(document.getElementById('convertModal'));
            modal.show();
        }
        
        function toggleCompanyInspections(companyId) {
            const inspectionsDiv = document.getElementById('inspections-' + companyId);
            const chevron = document.getElementById('chevron-' + companyId);
            
            if (inspectionsDiv.style.display === 'none') {
                inspectionsDiv.style.display = 'block';
                chevron.classList.remove('fa-chevron-down');
                chevron.classList.add('fa-chevron-up');
            } else {
                inspectionsDiv.style.display = 'none';
                chevron.classList.remove('fa-chevron-up');
                chevron.classList.add('fa-chevron-down');
            }
        }
        
        function toggleInspectionDetails(inspectionId) {
            const detailsDiv = document.getElementById('details-' + inspectionId);
            const chevron = document.getElementById('detail-chevron-' + inspectionId);
            
            if (detailsDiv.style.display === 'none') {
                detailsDiv.style.display = 'block';
                chevron.classList.remove('fa-chevron-down');
                chevron.classList.add('fa-chevron-up');
            } else {
                detailsDiv.style.display = 'none';
                chevron.classList.remove('fa-chevron-up');
                chevron.classList.add('fa-chevron-down');
            }
        }
    </script>
</body>
</html>