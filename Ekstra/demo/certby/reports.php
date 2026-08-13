<?php
require_once 'config.php';
requireLogin();

$userData = getUserData($_SESSION['user_id']);
if (!$userData) {
    header('Location: index.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfOnPost();
    try {
        $pdo = getConnection();
        $action = $_POST['action'] ?? '';

        if ($action === 'upload_report_files') {
            $certificationId = isset($_POST['certification_id']) ? intval($_POST['certification_id']) : 0;
            if ($certificationId < 1) { throw new Exception('İstek geçersiz'); }

            $check = $pdo->prepare('SELECT id FROM certifications WHERE id = ?');
            $check->execute([$certificationId]);
            if (!$check->fetchColumn()) { throw new Exception('Kayıt bulunamadı'); }

           
            if (!isset($_FILES['files'])) { throw new Exception('İstek geçersiz'); }

            $files = $_FILES['files'];
            $total = is_array($files['name']) ? count($files['name']) : 0;
            $saved = 0;

            for ($i = 0; $i < $total; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $tmpPath = $files['tmp_name'][$i];
                $originalName = $files['name'][$i];
                $size = (int)$files['size'][$i];

                if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
                    continue;
                }
                if (!isAllowedFileType($tmpPath)) {
                    continue;
                }
                if (!isAllowedFileExtension($originalName)) {
                    continue;
                }
 
                $fileContent = file_get_contents($tmpPath);
                if ($fileContent === false) {
                    continue;
                }
 
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : 'application/octet-stream';
                if ($finfo) { finfo_close($finfo); }
 
                $stmt = $pdo->prepare('INSERT INTO report_document_files (certification_id, original_name, stored_name, mime_type, file_size, file_content, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                $storedName = time() . '_' . bin2hex(random_bytes(6)) . '_' . $originalName;  
                $stmt->execute([$certificationId, $originalName, $storedName, $mimeType, $size, $fileContent, $_SESSION['user_id'] ?? null]);
                $saved++;
            }

            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                jsonResponse(true, 'İşlem başarılı', ['saved' => $saved]);
            } else {
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            }
        }

        if ($action === 'delete_report_file') {
            $fileId = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
            if ($fileId < 1) { throw new Exception('İstek geçersiz'); }
             
            $stmt = $pdo->prepare('DELETE FROM report_document_files WHERE id = ?');
            $stmt->execute([$fileId]);
            
            if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
                jsonResponse(true, 'İşlem başarılı');
            } else {
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            }
        }
    } catch (Exception $ex) {
        error_log('Rapor belge işlemi hatası: ' . $ex->getMessage());
        if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
            jsonResponse(false, 'Bir hata oluştu');
        } else {
            $upload_error = 'Bir hata oluştu';
        }
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'list_report_files') {
    try {
        $pdo = getConnection();
        $cid = isset($_GET['cert_id']) ? intval($_GET['cert_id']) : 0;
        if ($cid < 1) { throw new Exception('İstek geçersiz'); }
 
        $stmt = $pdo->prepare('SELECT id, original_name, stored_name, mime_type, file_size, uploaded_at FROM report_document_files WHERE certification_id = ? AND deleted_at IS NULL ORDER BY uploaded_at DESC');
        $stmt->execute([$cid]);
        $rows = $stmt->fetchAll();
        jsonResponse(true, '', ['files' => $rows]);
    } catch (Exception $e) {
        jsonResponse(false, 'Bir hata oluştu');
    }
}
 
if (isset($_GET['download_report_file']) && is_numeric($_GET['download_report_file'])) {
    try {
        $pdo = getConnection();
        $fileId = intval($_GET['download_report_file']);
        
        $stmt = $pdo->prepare('SELECT original_name, mime_type, file_content FROM report_document_files WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();
        
        if ($file && !empty($file['file_content'])) {
            $originalName = $file['original_name'] ?: 'download';
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

$company_filter = isset($_GET['company']) ? sanitizeInput($_GET['company']) : '';
$document_type_filter = isset($_GET['document_type']) ? sanitizeInput($_GET['document_type']) : '';
$days_filter = isset($_GET['days']) ? intval($_GET['days']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$predefined_filter = isset($_GET['predefined']) ? sanitizeInput($_GET['predefined']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    $pdo = getConnection();
    $companies_stmt = $pdo->query("SELECT id, short_name, trade_name FROM companies WHERE status = 'active' ORDER BY short_name");
    $companies = $companies_stmt->fetchAll();
    $document_types_stmt = $pdo->query("SELECT id, name, standard FROM document_types ORDER BY name");
    $document_types = $document_types_stmt->fetchAll();
    $where_conditions = [];
    $params = [];
    $base_query = "
        SELECT 
            c.id as company_id,
            c.short_name,
            c.trade_name,
            c.contact_person,
            c.contact_phone,
            c.contact_email,
            cert.id as certification_id,
            cert.document_number,
            cert.scope,
            cert.issue_date,
            cert.expiry_date,
            cert.status,
            dt.name as document_type_name,
            dt.standard,
            dt.validity_period,
            dt.interim_audit_count,
            DATEDIFF(cert.expiry_date, CURDATE()) as days_to_expiry,
            CASE 
                WHEN DATEDIFF(cert.expiry_date, CURDATE()) < 0 THEN 'Süresi Dolmuş'
                WHEN DATEDIFF(cert.expiry_date, CURDATE()) <= 30 THEN 'Kritik'
                WHEN DATEDIFF(cert.expiry_date, CURDATE()) <= 90 THEN 'Yaklaşıyor'
                ELSE 'Normal'
            END as urgency_status
        FROM certifications cert
        JOIN companies c ON cert.company_id = c.id
        JOIN document_types dt ON cert.document_type_id = dt.id
    ";
    
    if ($company_filter) {
        $where_conditions[] = "c.id = ?";
        $params[] = $company_filter;
    }
    
    if ($document_type_filter) {
        $where_conditions[] = "cert.document_type_id = ?";
        $params[] = $document_type_filter;
    }
    
    if ($status_filter == 'expired_by_date') {
        $where_conditions[] = "DATEDIFF(cert.expiry_date, CURDATE()) < 0";
    } elseif ($status_filter) {
        $where_conditions[] = "cert.status = ?";
        $params[] = $status_filter;
    }
    
    if ($predefined_filter) {
        switch ($predefined_filter) {
            case 'expiring_30':
                $where_conditions[] = "DATEDIFF(cert.expiry_date, CURDATE()) BETWEEN 0 AND 30";
                break;
            case 'expiring_90':
                $where_conditions[] = "DATEDIFF(cert.expiry_date, CURDATE()) BETWEEN 0 AND 90";
                break;
            case 'expired':
                $where_conditions[] = "DATEDIFF(cert.expiry_date, CURDATE()) < 0";
                break;
            case 'active':
                $where_conditions[] = "cert.status = 'active'";
                break;
        }
    }
    
    if ($days_filter) {
        $where_conditions[] = "DATEDIFF(cert.expiry_date, CURDATE()) <= ?";
        $params[] = $days_filter;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $count_query = "SELECT COUNT(*) FROM (" . $base_query . $where_clause . ") as total_records";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
    $final_query = $base_query . $where_clause . " ORDER BY 
        CASE 
            WHEN DATEDIFF(cert.expiry_date, CURDATE()) < 0 THEN 1 
            ELSE 0 
        END,
        cert.expiry_date ASC 
        LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($final_query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    $filesByCert = [];
    if (!empty($results)) {
        $certIds = array_column($results, 'certification_id');
        $placeholders = implode(',', array_fill(0, count($certIds), '?'));
        $fsql = "SELECT id, certification_id, original_name, stored_name, mime_type, file_size, uploaded_at FROM report_document_files WHERE certification_id IN ($placeholders) ORDER BY uploaded_at DESC";
        $fstmt = $pdo->prepare($fsql);
        $fstmt->execute($certIds);
        while ($f = $fstmt->fetch()) {
            $cid = $f['certification_id'];
            if (!isset($filesByCert[$cid])) { $filesByCert[$cid] = []; }
            $filesByCert[$cid][] = $f;
        }
    }
    
} catch (Exception $e) {
    error_log("Raporlama hatası: " . $e->getMessage());
    $results = [];
    $total_records = 0;
    $total_pages = 0;
    $filesByCert = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Belgelendirme</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            color: #333;
        }

        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
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

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            color: white;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
        }

        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filters-header h3 {
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .predefined-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .predefined-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .predefined-btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .predefined-btn.active {
            background: #764ba2;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group select {
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .searchable-select {
            position: relative;
        }

        .searchable-select input {
            width: 100%;
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
            padding: 0.8rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .searchable-select .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .searchable-select .dropdown-item:last-child {
            border-bottom: none;
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .results-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .results-info {
            color: #666;
            font-size: 0.9rem;
        }

        .table-container {
            overflow-x: auto;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        .results-table th,
        .results-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .results-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .status-expired-by-date {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-inactive {
            background: #e2e3e5;
            color: #6c757d;
        }

        .urgency-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .urgency-normal {
            background: #d4edda;
            color: #155724;
        }

        .urgency-yaklaşıyor {
            background: #fff3cd;
            color: #856404;
        }

        .urgency-kritik {
            background: #f8d7da;
            color: #721c24;
        }

        .urgency-süresi-dolmuş {
            background: #343a40;
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #667eea;
            background: white;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .no-results i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .predefined-filters {
                flex-direction: column;
                gap: 0.5rem;
            }

            .filter-actions {
                flex-direction: column;
            }

            .results-table {
                font-size: 0.9rem;
            }

            .results-table th,
            .results-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <script>
        const CSRF_TOKEN = '<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <div class="header">
        <div class="logo">
            <h2 style="margin:0;">Belgelendirme</h2>
        </div>
        <div class="nav-links">
            <a href="dashboard.php">Ana Menü</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1>Raporlar</h1>
        </div>
        <div class="filters-card">
            <div class="filters-header">
                <h3><i class="fas fa-filter"></i> Filtreler</h3>
                <span class="results-info">Toplam: <?= $total_records ?> kayıt bulundu</span>
            </div>
            <div class="predefined-filters">
                <a href="?predefined=expiring_30" class="predefined-btn <?= $predefined_filter == 'expiring_30' ? 'active' : '' ?>">
                    <i class="fas fa-exclamation-triangle"></i> 30 Gün İçinde Süresı Dolacaklar
                </a>
                <a href="?predefined=expiring_90" class="predefined-btn <?= $predefined_filter == 'expiring_90' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> 90 Gün İçinde Süresı Dolacaklar
                </a>
                <a href="?predefined=expired" class="predefined-btn <?= $predefined_filter == 'expired' ? 'active' : '' ?>">
                    <i class="fas fa-times-circle"></i> Süresi Dolmuş Belgeler
                </a>
                <a href="?predefined=active" class="predefined-btn <?= $predefined_filter == 'active' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Aktif Belgeler
                </a>
            </div>
            <form method="GET" class="filter-form">
                <?php if ($predefined_filter): ?>
                    <input type="hidden" name="predefined" value="<?= $predefined_filter ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="company_search">Şirket Ara</label>
                    <div class="searchable-select">
                        <input type="text" id="company_search" placeholder="Şirket adı yazın..." autocomplete="off">
                        <input type="hidden" name="company" id="company_value" value="<?= $company_filter ?>">
                        <div class="dropdown-list" id="company_dropdown">
                            <div class="dropdown-item" data-value="">Tüm Şirketler</div>
                            <?php foreach ($companies as $company): ?>
                                <div class="dropdown-item" data-value="<?= $company['id'] ?>">
                                    <?= htmlspecialchars($company['short_name'] . ' - ' . $company['trade_name']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="document_type_search">Belge Türü Ara</label>
                    <div class="searchable-select">
                        <input type="text" id="document_type_search" placeholder="Belge türü yazın..." autocomplete="off">
                        <input type="hidden" name="document_type" id="document_type_value" value="<?= $document_type_filter ?>">
                        <div class="dropdown-list" id="document_type_dropdown">
                            <div class="dropdown-item" data-value="">Tüm Belge Türleri</div>
                            <?php foreach ($document_types as $dt): ?>
                                <div class="dropdown-item" data-value="<?= $dt['id'] ?>">
                                    <?= htmlspecialchars($dt['name'] . ' (' . $dt['standard'] . ')') ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="status">Durum</label>
                    <select id="status" name="status">
                        <option value="">Tüm Durumlar</option>
                        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="expired_by_date" <?= $status_filter == 'expired_by_date' ? 'selected' : '' ?>>Süresi Dolmuş</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Beklemede</option>
                        <option value="suspended" <?= $status_filter == 'suspended' ? 'selected' : '' ?>>Askıda</option>
                        <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Pasif</option>
                        <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>İptal</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="days">Kalan Gün</label>
                    <input type="number" id="days" name="days" value="<?= $days_filter ?>" 
                           placeholder="Örn: 30" min="0">
                </div>

                <div class="filter-actions">
                    <a href="reports.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Temizle
                    </a>
                </div>
            </form>
        </div>
        <div class="results-card">
            <div class="results-header">
                <h3><i class="fas fa-table"></i> Sonuçlar</h3>
                <div class="results-info">
                    Sayfa <?= $page ?> / <?= $total_pages ?> - Toplam <?= $total_records ?> kayıt
                </div>
            </div>

            <?php if (empty($results)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h4>Sonuç bulunamadı</h4>
                    <p>Arama kriterlerinizi değiştirerek tekrar deneyin.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Şirket</th>
                                <th>Belge Türü</th>
                                <th>Belge No</th>
                                <th>Kapsam</th>
                                <th>Bitiş Tarihi</th>
                                <th>Kalan Gün</th>
                                <th>Durum</th>
                                <th>Öncelik</th>
                                <th>İletişim</th>
                                <th>Belgeler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($row['short_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($row['trade_name']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['document_type_name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($row['standard']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($row['document_number']) ?></td>
                                    <td><?= htmlspecialchars($row['scope']) ?></td>
                                    <td><?= date('d.m.Y', strtotime($row['expiry_date'])) ?></td>
                                    <td>
                                        <strong><?= $row['days_to_expiry'] ?></strong> gün
                                    </td>
                                    <td>
                                        <?php
                                        $display_status = $row['status'];
                                        $badge_class = 'status-' . $row['status'];
                                        $status_text = '';
                                        if ($row['urgency_status'] == 'Süresi Dolmuş') {
                                            $display_status = 'expired_by_date';
                                            $badge_class = 'status-expired-by-date';
                                            $status_text = 'Süresi Dolmuş';
                                        } else {
                                            switch ($row['status']) {
                                                case 'active': $status_text = 'Aktif'; break;
                                                case 'pending': $status_text = 'Beklemede'; break;
                                                case 'suspended': $status_text = 'Askıda'; break;
                                                case 'inactive': $status_text = 'Pasif'; break;
                                                case 'cancelled': $status_text = 'İptal'; break;
                                                default: $status_text = ucfirst($row['status']);
                                            }
                                        }
                                        ?>
                                        <span class="status-badge <?= $badge_class ?>">
                                            <?= $status_text ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="urgency-badge urgency-<?= strtolower(str_replace(' ', '-', $row['urgency_status'])) ?>">
                                            <?= $row['urgency_status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['contact_person']) ?></strong>
                                        <br>
                                        <small><?= htmlspecialchars($row['contact_phone']) ?></small>
                                        <br>
                                        <small><?= htmlspecialchars($row['contact_email']) ?></small>
                                    </td>
                                    <td>
                                        <?php $cid = $row['certification_id']; ?>
                                        <button class="btn btn-primary" onclick="openFilesModal(<?= (int)$cid ?>)">Dosyaları Yönet</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $current_params = $_GET;
                        if ($page > 1):
                            $current_params['page'] = $page - 1;
                            $prev_url = '?' . http_build_query($current_params);
                        ?>
                            <a href="<?= $prev_url ?>">&laquo; Önceki</a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                            if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else:
                                $current_params['page'] = $i;
                                $page_url = '?' . http_build_query($current_params);
                            ?>
                                <a href="<?= $page_url ?>"><?= $i ?></a>
                            <?php endif;
                        endfor; ?>

                        <?php
                        if ($page < $total_pages):
                            $current_params['page'] = $page + 1;
                            $next_url = '?' . http_build_query($current_params);
                        ?>
                            <a href="<?= $next_url ?>">Sonraki &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let filesModalCertId = null;
        
        function openFilesModal(certId) {
            filesModalCertId = certId;
            const modal = getOrCreateFilesModal();
            modal.style.display = 'block';
            modal.querySelector('#filesList').innerHTML = '<div>Yükleniyor...</div>';
            loadFiles(certId);
        }

        function closeFilesModal() {
            const modal = document.getElementById('filesModalBox');
            if (modal) modal.style.display = 'none';
            filesModalCertId = null;
        }

        function getOrCreateFilesModal() {
            let modal = document.getElementById('filesModalBox');
            if (modal) return modal;
            modal = document.createElement('div');
            modal.id = 'filesModalBox';
            modal.style.position = 'fixed';
            modal.style.inset = '0';
            modal.style.background = 'rgba(0,0,0,0.4)';
            modal.style.display = 'none';
            modal.style.zIndex = '2000';
            modal.innerHTML = `
                <div id="filesModalBox" style="background:#fff; max-width:750px; width:92%; margin:6vh auto; border-radius:10px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
                    <div style="padding:0.8rem 1.2rem; display:flex; justify-content:space-between; align-items:center; background:#f8f9fa; border-bottom:1px solid #eee;">
                        <h3 style="margin:0; font-size:1.1rem;">Dosyaları Yönet</h3>
                        <button class="btn btn-secondary" onclick="closeFilesModal()">Kapat</button>
                    </div>
                    <div style="padding:1rem 1.2rem;">
                        <form id="uploadForm" onsubmit="return uploadFiles(event)">
                            <label for="filesInput" style="display:block; border:2px dashed #e1e5e9; border-radius:8px; padding:12px; cursor:pointer; background:#f7f9fc; text-align:center; margin-bottom:10px;">
                                <span style="color:#6c757d;">📁 Dosyaları seçmek için tıklayın</span>
                            </label>
                            <input type="file" name="files[]" id="filesInput" multiple style="display:none;" onchange="previewFiles(this)">
                            <div id="filePreview" style="margin:10px 0;"></div>
                            <div style="text-align:right; margin-bottom:14px;">
                                <button type="submit" class="btn btn-primary">Yükle</button>
                            </div>
                        </form>
                        <div id="filesList"></div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            modal.addEventListener('click', function(e){ if (e.target === modal) { closeFilesModal(); } });
            return modal;
        }

        function loadFiles(certId) {
            fetch(`reports.php?ajax=list_report_files&cert_id=${certId}`)
                .then(r => r.json())
                .then(j => {
                    if (!j.success) throw new Error(j.message || 'Hata');
                    const list = document.getElementById('filesList');
                    const files = j.data.files || [];
                    if (files.length === 0) {
                        list.innerHTML = '<small>Dosya yok</small>';
                        return;
                    }
                    const html = ['<div style="display:flex; flex-direction:column; gap:8px;">'];
                    for (const f of files) {
                        const isImg = (f.mime_type || '').startsWith('image/');
                        const size = formatSize(f.file_size);
                        const dateStr = new Date(f.uploaded_at).toLocaleString('tr-TR');
                        html.push(`
                            <div style="display:flex; align-items:center; gap:10px; border:1px solid #eee; border-left:4px solid #2ca3ff; border-radius:6px; padding:8px 10px; background:#fbfdff;">
                                <div style="width:42px; height:42px; flex:0 0 42px; border-radius:4px; overflow:hidden; background:#f1f3f5; display:flex; align-items:center; justify-content:center;">
                                    ${isImg ? '🖼️' : '📎'}
                                </div>
                                <div style="flex:1; min-width:0;">
                                    <div style="font-size:0.95rem;">${escapeHtml(f.original_name)}</div>
                                    <div style="font-size:0.8rem; color:#6c757d;">${size} • ${dateStr}</div>
                                </div>
                                <div style="display:flex; gap:6px;">
                                    <a class="btn btn-primary" style="padding:0.35rem 0.6rem; font-size:0.85rem;" href="reports.php?download_report_file=${f.id}">İndir</a>
                                    <button class="btn btn-secondary" style="padding:0.35rem 0.6rem; font-size:0.85rem;" onclick="deleteFile(${f.id})">Sil</button>
                                </div>
                            </div>
                        `);
                    }
                    html.push('</div>');
                    list.innerHTML = html.join('');
                })
                .catch(err => {
                    document.getElementById('filesList').innerHTML = `<div style="color:#c00;">${escapeHtml(err.message)}</div>`;
                });
        }

        function previewFiles(input) {
            const preview = document.getElementById('filePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                const html = ['<div style="display:flex; flex-direction:column; gap:8px; margin-bottom:10px;">'];
                html.push('<h4 style="margin:0 0 8px 0; font-size:0.9rem; color:#495057;">Seçilen Dosyalar:</h4>');
                
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    const isImg = file.type.startsWith('image/');
                    const size = formatSize(file.size);
                    
                    html.push(`
                        <div style="display:flex; align-items:center; gap:10px; border:1px solid #e9ecef; border-radius:6px; padding:8px 10px; background:#f8f9fa;">
                            <div style="width:32px; height:32px; flex:0 0 32px; border-radius:4px; overflow:hidden; background:#e9ecef; display:flex; align-items:center; justify-content:center;">
                                ${isImg ? '🖼️' : '📎'}
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:0.9rem; font-weight:500;">${escapeHtml(file.name)}</div>
                                <div style="font-size:0.8rem; color:#6c757d;">${size}</div>
                            </div>
                        </div>
                    `);
                }
                
                html.push('</div>');
                preview.innerHTML = html.join('');
            }
        }

        function uploadFiles(e) {
            e.preventDefault();
            const input = document.getElementById('filesInput');
            if (!input.files || input.files.length === 0) return false;
            const fd = new FormData();
            fd.append('action', 'upload_report_files');
            fd.append('certification_id', String(filesModalCertId));
            fd.append('ajax', '1');
            fd.append('csrf_token', CSRF_TOKEN);
            for (const file of input.files) fd.append('files[]', file);
            fetch('reports.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': CSRF_TOKEN } })
                .then(r => r.json())
                .then(j => {
                    if (!j.success) throw new Error(j.message || 'Yükleme hatası');
                    input.value = '';
                    document.getElementById('filePreview').innerHTML = '';
                    loadFiles(filesModalCertId);
                })
                .catch(err => alert(err.message));
            return false;
        }

        function deleteFile(id) {
            if (!confirm('Silmek istiyor musunuz?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_report_file');
            fd.append('file_id', String(id));
            fd.append('ajax', '1');
            fd.append('csrf_token', CSRF_TOKEN);
            fetch('reports.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': CSRF_TOKEN } })
                .then(r => r.json())
                .then(j => {
                    if (!j.success) throw new Error(j.message || 'Hata');
                    loadFiles(filesModalCertId);
                })
                .catch(err => alert(err.message));
        }



        function escapeHtml(s) {
            return String(s).replace(/[&<>\"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;','\'':'&#39;'}[c]));
        }
        function formatSize(bytes) {
            const b = Number(bytes||0);
            if (b < 1024) return b + ' B';
            const kb = b/1024;
            if (kb < 1024) return kb.toFixed(2) + ' KB';
            const mb = kb/1024;
            return mb.toFixed(2) + ' MB';
        }
        function initSearchableDropdown(inputId, dropdownId, valueId) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            const valueInput = document.getElementById(valueId);
            const items = dropdown.querySelectorAll('.dropdown-item');

            
            const currentValue = valueInput.value;
            if (currentValue) {
                const selectedItem = dropdown.querySelector(`[data-value="${currentValue}"]`);
                if (selectedItem) {
                    input.value = selectedItem.textContent.trim();
                }
            }

            input.addEventListener('focus', function() {
                dropdown.classList.add('show');
            });

            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                dropdown.classList.add('show');
                
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });

                
                if (inputId === 'document_type_search') {
                }
            });

            items.forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    const text = this.textContent.trim();
                    
                    input.value = text;
                    valueInput.value = value;
                    dropdown.classList.remove('show');
                });
            });
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
        }

         
        initSearchableDropdown('company_search', 'company_dropdown', 'company_value');
        initSearchableDropdown('document_type_search', 'document_type_dropdown', 'document_type_value');

         
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });

         
        let daysTimeout;
        document.getElementById('days').addEventListener('input', function() {
            clearTimeout(daysTimeout);
            daysTimeout = setTimeout(() => {
                this.form.submit();
            }, 1000);
        });

        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('dropdown-item') && e.target.closest('#company_dropdown')) {
                setTimeout(() => {
                    document.querySelector('form').submit();
                }, 100);
            }
        });
 
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('dropdown-item') && e.target.closest('#document_type_dropdown')) {
                setTimeout(() => {
                    document.querySelector('form').submit();
                }, 100);
            }
        });
    </script>
</body>
</html>