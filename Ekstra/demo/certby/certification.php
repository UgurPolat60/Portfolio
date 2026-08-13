<?php

require_once 'config.php';
requireLogin();

$currentUser = getUserData($_SESSION['user_id']);

function getCompanies() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, short_name, trade_name FROM companies WHERE status = 'active' ORDER BY short_name");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Firmalar getirme hatası: " . $e->getMessage());
        return [];
    }
}

function getDocumentTypes() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, name, standard, validity_period FROM document_types ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Belge türleri getirme hatası: " . $e->getMessage());
        return [];
    }
}

function getConsultants() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT id, company_short_name, company_full_name, consultant_name, consultant_email, consultant_phone, company_email, company_phone FROM consultants WHERE status = 'active' ORDER BY company_short_name");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Danışmanlar getirme hatası: " . $e->getMessage());
        return [];
    }
}

function getCertificationOrganizations() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT DISTINCT certification_organization FROM certifications WHERE certification_organization IS NOT NULL AND certification_organization != '' ORDER BY certification_organization");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Belgelendiren kuruluşlar getirme hatası: " . $e->getMessage());
        return [];
    }
}

function getCertifications() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   comp.short_name as company_name,
                   comp.trade_name as company_trade_name,
                   dt.name as document_type_name,
                   dt.standard as document_standard,
                   dt.validity_period,
                   con.company_short_name as consultant_company_name,
                   con.consultant_name as consultant_name,
                   COALESCE(con.consultant_email, con.company_email) as consultant_email,
                   COALESCE(con.consultant_phone, con.company_phone) as consultant_phone
            FROM certifications c
            JOIN companies comp ON c.company_id = comp.id
            JOIN document_types dt ON c.document_type_id = dt.id
            LEFT JOIN consultants con ON c.consultant_id = con.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Belgelendirmeler getirme hatası: " . $e->getMessage());
        return [];
    }
}

$companies = getCompanies();
$documentTypes = getDocumentTypes();
$consultants = getConsultants();
$certificationOrganizations = getCertificationOrganizations();
$certifications = getCertifications();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgelendirme</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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
            gap: 20px;
        }

        .user-info span {
            font-size: 14px;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .navigation {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .nav-btn {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            padding: 12px 24px;
            border-radius: 8px;
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-btn:hover {
            background: #e9ecef;
            border-color: #dee2e6;
            transform: translateY(-2px);
        }

        .nav-btn.active {
            background: #e3f2fd;
            border-color: #2196f3;
            color: #1976d2;
        }

        .breadcrumb {
            margin-bottom: 20px;
            color: #666;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .page-title {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .page-title h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .page-title p {
            color: #666;
            font-size: 1.1em;
        }

        .consultant-action {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .form-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: #ffc107;
            color: #212529;
        }

        .btn-edit:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .btn-consultant {
            background: #17a2b8;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-consultant:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .list-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .list-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .certification-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .certification-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .card-subtitle {
            color: #667eea;
            font-weight: 500;
        }

        .card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid #667eea;
        }

        .detail-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 2px;
        }

        .detail-value {
            font-weight: 500;
            color: #333;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #fff3cd;
            color: #856404;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: none;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #999;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
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

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border: none;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            margin: 0;
            color: #333;
            font-size: 1.5em;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .card-details {
                grid-template-columns: 1fr;
            }

            .card-actions {
                flex-direction: column;
            }

            .nav-buttons {
                flex-wrap: wrap;
            }

            .consultant-action {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h2 style="margin:0;">Belgelendirme</h2>
            </div>
            <div class="user-info">
                <span>Hoşgeldiniz, <?= htmlspecialchars($currentUser['full_name']) ?></span>
                 
            </div>
        </div>
    </div>

    <div class="container">
        <div class="navigation">
            <div class="nav-buttons">
                <a href="dashboard.php" class="nav-btn">
                    <span>🏠</span> Ana Sayfa
                </a>
                <a href="javascript:history.back()" class="nav-btn">
                    <span>←</span> Geri Git
                </a>
            </div>
        </div>

        <div class="breadcrumb">
            <a href="dashboard.php">Ana Sayfa</a> > 
            <strong>Belgelendirme</strong>
        </div>

        <div class="page-title">
            <h1>✅ Belgelendirme</h1>
        </div>


        <div class="alert" id="alert"></div>

        <div class="content-grid">
            <div class="form-section">
                <h2 id="formTitle">Yeni Belgelendirme Ekle</h2>
                <form id="certificationForm">
                    <input type="hidden" id="editId" value="">
                    
                    <div class="form-group">
                        <label for="companyId">Firma *</label>
                        <div class="searchable-select" id="companySearchable">
                            <div class="select-wrapper">
                                <input type="text" id="companySearch" placeholder="Firma arayın veya seçin..." autocomplete="off">
                                <span class="dropdown-arrow">▼</span>
                                <div class="dropdown-list" id="companyDropdown">
                                    <?php foreach ($companies as $company): ?>
                                        <div class="dropdown-item" data-value="<?= $company['id'] ?>" data-text="<?= htmlspecialchars($company['short_name']) ?> ">
                                            <?= htmlspecialchars($company['short_name']) ?> 
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" id="companyId" name="company_id" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="documentTypeId">Belge Türü *</label>
                        <div class="searchable-select" id="documentTypeSearchable">
                            <div class="select-wrapper">
                                <input type="text" id="documentTypeSearch" placeholder="Belge türü arayın veya seçin..." autocomplete="off">
                                <span class="dropdown-arrow">▼</span>
                                <div class="dropdown-list" id="documentTypeDropdown">
                                    <?php foreach ($documentTypes as $docType): ?>
                                        <div class="dropdown-item" data-value="<?= $docType['id'] ?>" data-text="<?= htmlspecialchars($docType['name']) ?> - <?= htmlspecialchars($docType['standard']) ?>" data-validity="<?= $docType['validity_period'] ?>">
                                            <?= htmlspecialchars($docType['name']) ?> - <?= htmlspecialchars($docType['standard']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" id="documentTypeId" name="document_type_id" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="consultantId">Danışman Firma</label>
                        <div class="searchable-select" id="consultantSearchable">
                            <div class="select-wrapper">
                                <input type="text" id="consultantSearch" placeholder="Danışman firma arayın veya seçin..." autocomplete="off">
                                <span class="dropdown-arrow">▼</span>
                                <div class="dropdown-list" id="consultantDropdown">
                                    <div class="dropdown-item" data-value="" data-text="Danışman Firma Seçiniz">
                                        Danışman Firma Seçiniz
                                    </div>
                                    <?php foreach ($consultants as $consultant): ?>
                                        <div class="dropdown-item" data-value="<?= $consultant['id'] ?>" data-text="<?= htmlspecialchars($consultant['company_short_name']) ?> - <?= htmlspecialchars($consultant['company_full_name']) ?>">
                                            <?= htmlspecialchars($consultant['company_short_name']) ?> - <?= htmlspecialchars($consultant['company_full_name']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" id="consultantId" name="consultant_id">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="accreditationType">Akreditasyon Türü</label>
                        <div class="searchable-select" id="accreditationTypeSearchable">
                            <div class="select-wrapper">
                                <input type="text" id="accreditationTypeSearch" placeholder="Akreditasyon türü arayın veya yazın..." autocomplete="off">
                                <span class="dropdown-arrow">▼</span>
                                <div class="dropdown-list" id="accreditationTypeDropdown">
                                    <div class="dropdown-item" data-value="" data-text="Akreditasyon türü seçiniz">Akreditasyon türü seçiniz</div>
                                </div>
                            </div>
                            <input type="hidden" id="accreditationType" name="accreditation_type">
                        </div>
                        <div style="margin-top:8px; display:flex; gap:8px;">
                            <input type="text" id="newAccTypeInput" placeholder="Yeni akreditasyon türü" style="flex:1; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
                            <button type="button" class="btn btn-secondary" id="addAccTypeBtn">Ekle</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="certificationOrganization">Belgelendiren Kuruluş</label>
                        <div class="searchable-select" id="certificationOrganizationSearchable">
                            <div class="select-wrapper">
                                <input type="text" id="certificationOrganizationSearch" placeholder="Belgelendiren kuruluş arayın veya yazın..." autocomplete="off">
                                <span class="dropdown-arrow">▼</span>
                                <div class="dropdown-list" id="certificationOrganizationDropdown">
                                    <div class="dropdown-item" data-value="" data-text="Belgelendiren kuruluş seçiniz">Belgelendiren kuruluş seçiniz</div>
                                    <?php foreach ($certificationOrganizations as $org): ?>
                                        <div class="dropdown-item" data-value="<?= htmlspecialchars($org) ?>" data-text="<?= htmlspecialchars($org) ?>">
                                            <?= htmlspecialchars($org) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" id="certificationOrganization" name="certification_organization">
                        </div>
                        <div style="margin-top:8px; display:flex; gap:8px;">
                            <input type="text" id="newCertOrgInput" placeholder="Yeni belgelendiren kuruluş" style="flex:1; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
                            <button type="button" class="btn btn-secondary" id="addCertOrgBtn">Ekle</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="documentNumber">Belge Numarası *</label>
                        <input type="text" id="documentNumber" name="document_number" required placeholder="Örn: CERT-2024-001">
                    </div>

                    <div class="form-group">
                        <label for="scope">Kapsam *</label>
                        <textarea id="scope" name="scope" placeholder="Belgelendirme kapsamını giriniz..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="issueDate">Belge Basım Tarihi *</label>
                        <input type="date" id="issueDate" name="issue_date" required>
                    </div>

                    <div class="form-group">
                        <label for="expiryDate">Belge Bitiş Tarihi *</label>
                        <input type="date" id="expiryDate" name="expiry_date" required>
                    </div>

                    <div class="form-group">
                        <label for="level">Seviye</label>
                        <input type="number" id="level" name="level" min="1" placeholder="Seviye numarası">
                    </div>

                    <div class="form-group">
                        <label for="status">Durum *</label>
                        <select id="status" name="status" required>
                            <option value="active">Aktif - Sertifika Geçerli</option>
                            <option value="inactive">Pasif - Ara Tetkik Tarihi Geçmiş</option>
                            <option value="suspended">Askıda - Ara Tetkik Yaptırılmamış</option>
                            <option value="cancelled">İptal - Uygunsuzluk Nedeniyle İptal/Müşteri İsteği İle iptal</option>
                            <option value="updated">Güncelleme - Sertifika Güncellenmiş</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" id="submitBtn">Belgelendirme Ekle</button>
                        <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;">İptal</button>
                    </div>
                </form>
            </div>

            <div class="list-section">
                <h2>Mevcut Belgelendirmeler</h2>
                
                <div class="search-box">
                    <input type="text" id="searchBox" placeholder="Firma adı, belge numarası, belge türü veya danışman ile ara...">
                </div>

                <div id="certificationsList">
                    <?php if (empty($certifications)): ?>
                        <div class="empty-state">
                            <h3>Henüz belgelendirme eklenmemiş</h3>
                            <p>Sol taraftaki formu kullanarak ilk belgelendirmenizi ekleyin.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($certifications as $cert): ?>
                            <div class="certification-card" data-search="<?= strtolower($cert['company_name'] . ' ' . $cert['document_number'] . ' ' . $cert['document_type_name'] . ' ' . ($cert['consultant_company_name'] ?? '')) ?>">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title"><?= htmlspecialchars($cert['company_name']) ?></div>
                                        <div class="card-subtitle"><?= htmlspecialchars($cert['document_type_name']) ?> - <?= htmlspecialchars($cert['document_standard']) ?></div>
                                    </div>
                                    <div class="status-badge status-<?= $cert['status'] ?>">
                                        <?php
                                        $statusText = [
                                            'active' => 'Aktif',
                                            'inactive' => 'Pasif',
                                            'suspended' => 'Askıda',
                                            'cancelled' => 'İptal',
                                            'updated' => 'Güncelleme'
                                        ];
                                        echo $statusText[$cert['status']] ?? 'Bilinmiyor';
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="card-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Belge Numarası</div>
                                        <div class="detail-value"><?= htmlspecialchars($cert['document_number']) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Basım Tarihi</div>
                                        <div class="detail-value"><?= date('d.m.Y', strtotime($cert['issue_date'])) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Bitiş Tarihi</div>
                                        <div class="detail-value"><?= date('d.m.Y', strtotime($cert['expiry_date'])) ?></div>
                                    </div>
                                    <?php if ($cert['consultant_company_name']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Danışman Firma</div>
                                        <div class="detail-value"><?= htmlspecialchars($cert['consultant_company_name']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($cert['level']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Seviye</div>
                                        <div class="detail-value"><?= $cert['level'] ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (isset($cert['accreditation_type']) && $cert['accreditation_type']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Akreditasyon Türü</div>
                                        <div class="detail-value"><?= htmlspecialchars($cert['accreditation_type']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (isset($cert['certification_organization']) && $cert['certification_organization']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Belgelendiren Kuruluş</div>
                                        <div class="detail-value"><?= htmlspecialchars($cert['certification_organization']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($cert['scope']): ?>
                                <div class="detail-item" style="margin-bottom: 15px;">
                                    <div class="detail-label">Kapsam</div>
                                    <div class="detail-value"><?= htmlspecialchars($cert['scope']) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="card-actions">
                                    <button class="btn btn-edit" onclick="editCertification(<?= $cert['id'] ?>)">
                                        Düzenle
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteCertification(<?= $cert['id'] ?>, '<?= htmlspecialchars($cert['document_number']) ?>')">
                                        Sil
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


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
                },
                refresh: function() {
                    fetch('get_consultants.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                dropdown.innerHTML = '<div class="dropdown-item" data-value="" data-text="Danışman Firma Seçiniz">Danışman Firma Seçiniz</div>';
                                data.consultants.forEach(consultant => {
                                    const item = document.createElement('div');
                                    item.className = 'dropdown-item';
                                    item.dataset.value = consultant.id;
                                    item.dataset.text = consultant.company_short_name + ' - ' + consultant.company_full_name;
                                    item.textContent = consultant.company_short_name + ' - ' + consultant.company_full_name;
                                    item.addEventListener('click', function() {
                                        const value = this.dataset.value;
                                        const text = this.dataset.text;
                                        
                                        searchInput.value = text;
                                        hiddenInput.value = value;
                                        dropdown.classList.remove('show');
                                        
                                        if (onSelectCallback) {
                                            onSelectCallback(this);
                                        }
                                        
                                        dropdown.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('selected'));
                                        this.classList.add('selected');
                                    });
                                    dropdown.appendChild(item);
                                });
                            }
                        });
                }
            };
        }
        let companySelect, documentTypeSelect, consultantSelect, certificationOrganizationSelect;
        
        document.addEventListener('DOMContentLoaded', function() {
            companySelect = initSearchableSelect('companySearch', 'companyDropdown', 'companyId');
            documentTypeSelect = initSearchableSelect('documentTypeSearch', 'documentTypeDropdown', 'documentTypeId', function(selectedItem) {
                const issueDateInput = document.getElementById('issueDate');
                const expiryDateInput = document.getElementById('expiryDate');
                
                if (selectedItem.dataset.validity && issueDateInput.value) {
                    const validityPeriod = selectedItem.dataset.validity;
                    const issueDate = new Date(issueDateInput.value);
                    const expiryDate = new Date(issueDate);
                    expiryDate.setFullYear(expiryDate.getFullYear() + parseInt(validityPeriod));
                    
                    expiryDateInput.value = expiryDate.toISOString().split('T')[0];
                }
            });
            consultantSelect = initSearchableSelect('consultantSearch', 'consultantDropdown', 'consultantId');
            initSearchableSelect('accreditationTypeSearch', 'accreditationTypeDropdown', 'accreditationType');
            certificationOrganizationSelect = initSearchableSelect('certificationOrganizationSearch', 'certificationOrganizationDropdown', 'certificationOrganization');
            
            document.getElementById('accreditationTypeSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                if (searchTerm.length > 0) {
                    searchAccreditationTypes(searchTerm);
                } else {
                    refreshAccreditationTypes();
                }
            });
            
            document.getElementById('certificationOrganizationSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                if (searchTerm.length > 0) {
                    searchCertificationOrganizations(searchTerm);
                } else {
                    refreshCertificationOrganizations();
                }
            });
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('issueDate').value = today;
        });

        function searchAccreditationTypes(searchTerm) {
            fetch('get_accreditation_types.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const dropdown = document.getElementById('accreditationTypeDropdown');
                    dropdown.innerHTML = '<div class="dropdown-item" data-value="" data-text="Akreditasyon türü seçiniz">Akreditasyon türü seçiniz</div>';
                    
                    const filteredTypes = data.types.filter(type => 
                        type.toLowerCase().includes(searchTerm)
                    );
                    
                    filteredTypes.forEach(type => {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.dataset.value = type;
                        div.dataset.text = type;
                        div.innerHTML = `<span>${type}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteAccreditationType('${type.replace(/'/g, "\\'")}')\">🗑</button>`;
                        dropdown.appendChild(div);
                    });
                    
                    bindAccreditationTypeDropdownSelection();
                })
                .catch(() => {});
        }

        function bindAccreditationTypeDropdownSelection() {
            const dropdown = document.getElementById('accreditationTypeDropdown');
            dropdown.onclick = function(e) {
                const removeBtn = e.target && e.target.closest ? e.target.closest('.btn-remove') : null;
                if (removeBtn) return;
                const item = e.target.closest && e.target.closest('.dropdown-item');
                if (!item) return;
                const searchInput = document.getElementById('accreditationTypeSearch');
                const hiddenInput = document.getElementById('accreditationType');
                const value = item.dataset.value || '';
                const text = item.dataset.text || '';
                if (searchInput) searchInput.value = text;
                if (hiddenInput) hiddenInput.value = value;
                dropdown.classList.remove('show');
                dropdown.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
            };
        }

        function deleteAccreditationType(name) {
            if (!confirm(`"${name}" akreditasyon türünü silmek istediğinize emin misiniz?`)) return;
            
            const fd = new FormData();
            fd.append('action', 'delete_from_db');
            fd.append('name', name);
            fetch('get_accreditation_types.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        refreshAccreditationTypes();
                        showAlert('Akreditasyon türü silindi', 'success');
                    } else {
                        showAlert(data.message || 'Silme başarısız', 'error');
                    }
                })
                .catch(() => showAlert('Silme sırasında hata', 'error'));
        }

        function searchCertificationOrganizations(searchTerm) {
            fetch('get_certification_organizations.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const dropdown = document.getElementById('certificationOrganizationDropdown');
                    dropdown.innerHTML = '<div class="dropdown-item" data-value="" data-text="Belgelendiren kuruluş seçiniz">Belgelendiren kuruluş seçiniz</div>';
                    
                    const filteredOrgs = data.organizations.filter(org => 
                        org.toLowerCase().includes(searchTerm)
                    );
                    
                    filteredOrgs.forEach(org => {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.dataset.value = org;
                        div.dataset.text = org;
                        div.innerHTML = `<span>${org}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteCertOrg('${org.replace(/'/g, "\\'")}')\">🗑</button>`;
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
                                    div.innerHTML = `<span>${org}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteCertOrg('${org.replace(/'/g, "\\'")}')\">🗑</button>`;
                                    dropdown.appendChild(div);
                                });
                            }
                            bindCertOrgDropdownSelection();
                        })
                        .catch(() => bindCertOrgDropdownSelection());
                })
                .catch(() => {});
        }

        function refreshAccreditationTypes() {
            fetch('process_accreditation_types.php', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const dropdown = document.getElementById('accreditationTypeDropdown');
                    dropdown.innerHTML = '<div class="dropdown-item" data-value="" data-text="Akreditasyon türü seçiniz">Akreditasyon türü seçiniz</div>';
                    data.types.forEach(t => {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.dataset.value = t;
                        div.dataset.text = t;
                        div.innerHTML = `<span>${t}</span> <button type=\"button\" class=\"btn-remove\" style=\"float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;\" onclick=\"deleteAccType('${t.replace(/'/g, "\\'")}')\">🗑</button>`;
                        dropdown.appendChild(div);
                    });
                    bindAccreditationTypeDropdownSelection();
                })
                .catch(() => {});
        }

        document.addEventListener('DOMContentLoaded', function() {
            refreshAccreditationTypes();
            refreshCertificationOrganizations();
            
            const addAccBtn = document.getElementById('addAccTypeBtn');
            if (addAccBtn) {
                addAccBtn.addEventListener('click', function() {
                    const input = document.getElementById('newAccTypeInput');
                    const val = (input.value || '').trim();
                    if (!val) return;
                    const fd = new FormData();
                    fd.append('action', 'add');
                    fd.append('name', val);
                    fetch('process_accreditation_types.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                input.value = '';
                                refreshAccreditationTypes();
                                const searchInput = document.getElementById('accreditationTypeSearch');
                                if (searchInput) searchInput.value = val;
                            } else {
                                alert(data.message || 'Ekleme başarısız');
                            }
                        })
                        .catch(() => alert('Ekleme sırasında hata'));
                });
            }
            
            const addCertOrgBtn = document.getElementById('addCertOrgBtn');
            if (addCertOrgBtn) {
                addCertOrgBtn.addEventListener('click', function() {
                    const input = document.getElementById('newCertOrgInput');
                    const val = (input.value || '').trim();
                    if (!val) return;
                    const fd = new FormData();
                    fd.append('action', 'add');
                    fd.append('name', val);
                    fetch('process_certification_organizations.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                input.value = '';
                                refreshCertificationOrganizations();
                                const searchInput = document.getElementById('certificationOrganizationSearch');
                                if (searchInput) searchInput.value = val;
                            } else {
                                alert(data.message || 'Ekleme başarısız');
                            }
                        })
                        .catch(() => alert('Ekleme sırasında hata'));
                });
            }
        });

        function bindAccDropdownSelection() {
            const dropdown = document.getElementById('accreditationTypeDropdown');
            if (!dropdown) return;
            dropdown.onclick = function(e) {
                const removeBtn = e.target && e.target.closest ? e.target.closest('.btn-remove') : null;
                if (removeBtn) return;
                const item = e.target.closest && e.target.closest('.dropdown-item');
                if (!item) return;
                const searchInput = document.getElementById('accreditationTypeSearch');
                const hiddenInput = document.getElementById('accreditationType');
                const value = item.dataset.value || '';
                const text = item.dataset.text || '';
                if (searchInput) searchInput.value = text;
                if (hiddenInput) hiddenInput.value = value;
                dropdown.classList.remove('show');
                dropdown.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
            };
        }

        function deleteAccType(name) {
            if (!confirm(`"${name}" türünü silmek istediğinize emin misiniz?`)) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('name', name);
            fetch('process_accreditation_types.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        refreshAccreditationTypes();
                        showAlert('Akreditasyon türü silindi', 'success');
                    } else {
                        showAlert(data.message || 'Silme başarısız', 'error');
                    }
                })
                .catch(() => showAlert('Silme sırasında hata', 'error'));
        }

        function refreshCertificationOrganizations() {
            fetch('get_certification_organizations.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const dropdown = document.getElementById('certificationOrganizationDropdown');
                    dropdown.innerHTML = '<div class="dropdown-item" data-value="" data-text="Belgelendiren kuruluş seçiniz">Belgelendiren kuruluş seçiniz</div>';
                    
                    data.organizations.forEach(org => {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.dataset.value = org;
                        div.dataset.text = org;
                        div.innerHTML = `<span>${org}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteCertOrg('${org.replace(/'/g, "\\'")}')\">🗑</button>`;
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
                                    div.innerHTML = `<span>${org}</span> <button type="button" class="btn-remove" style="float:right;color:#dc3545;border:none;background:transparent;cursor:pointer;" onclick="deleteCertOrg('${org.replace(/'/g, "\\'")}')\">🗑</button>`;
                                    dropdown.appendChild(div);
                                });
                            }
                            bindCertOrgDropdownSelection();
                        })
                        .catch(() => bindCertOrgDropdownSelection());
                })
                .catch(() => {});
        }

        function bindCertOrgDropdownSelection() {
            const dropdown = document.getElementById('certificationOrganizationDropdown');
            if (!dropdown) return;
            dropdown.onclick = function(e) {
                const removeBtn = e.target && e.target.closest ? e.target.closest('.btn-remove') : null;
                if (removeBtn) return;
                const item = e.target.closest && e.target.closest('.dropdown-item');
                if (!item) return;
                const searchInput = document.getElementById('certificationOrganizationSearch');
                const hiddenInput = document.getElementById('certificationOrganization');
                const value = item.dataset.value || '';
                const text = item.dataset.text || '';
                if (searchInput) searchInput.value = text;
                if (hiddenInput) hiddenInput.value = value;
                dropdown.classList.remove('show');
                dropdown.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
            };
        }

        function deleteCertOrg(name) {
            if (!confirm(`"${name}" kuruluşunu silmek istediğinize emin misiniz?`)) return;
            
            // Önce process_certification_organizations.php'den silmeyi dene
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('name', name);
            fetch('process_certification_organizations.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        refreshCertificationOrganizations();
                        showAlert('Belgelendiren kuruluş silindi', 'success');
                    } else {
                        // Eğer process_certification_organizations.php'de yoksa, doğrudan veritabanından sil
                        deleteCertOrgFromDB(name);
                    }
                })
                .catch(() => {
                    // Hata durumunda da doğrudan veritabanından silmeyi dene
                    deleteCertOrgFromDB(name);
                });
        }

        function deleteCertOrgFromDB(name) {
            const fd = new FormData();
            fd.append('action', 'delete_from_db');
            fd.append('name', name);
            fetch('get_certification_organizations.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        refreshCertificationOrganizations();
                        showAlert('Belgelendiren kuruluş silindi', 'success');
                    } else {
                        showAlert(data.message || 'Silme başarısız', 'error');
                    }
                })
                .catch(() => showAlert('Silme sırasında hata', 'error'));
        }

        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert ${type}`;
            alert.style.display = 'block';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }
        document.getElementById('issueDate').addEventListener('change', function() {
            const expiryDateInput = document.getElementById('expiryDate');
            const documentTypeValue = document.getElementById('documentTypeId').value;
            
            if (documentTypeValue && this.value) {
                const selectedItem = document.querySelector(`#documentTypeDropdown .dropdown-item[data-value="${documentTypeValue}"]`);
                
                if (selectedItem && selectedItem.dataset.validity) {
                    const validityPeriod = selectedItem.dataset.validity;
                    const issueDate = new Date(this.value);
                    const expiryDate = new Date(issueDate);
                    expiryDate.setFullYear(expiryDate.getFullYear() + parseInt(validityPeriod));
                    
                    expiryDateInput.value = expiryDate.toISOString().split('T')[0];
                }
            }
        });
        document.getElementById('searchBox').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.certification-card');
            
            cards.forEach(card => {
                const searchData = card.dataset.search;
                if (searchData.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        document.getElementById('certificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const editId = document.getElementById('editId').value;
            let accreditationType = document.getElementById('accreditationType').value;
            
            formData.append('action', editId ? 'update' : 'add');
            if (editId) formData.append('id', editId);
            formData.append('company_id', document.getElementById('companyId').value);
            formData.append('document_type_id', document.getElementById('documentTypeId').value);
            formData.append('consultant_id', document.getElementById('consultantId').value);
            formData.append('accreditation_type', accreditationType);
            formData.append('certification_organization', document.getElementById('certificationOrganization').value);
            formData.append('document_number', document.getElementById('documentNumber').value);
            formData.append('scope', document.getElementById('scope').value);
            formData.append('issue_date', document.getElementById('issueDate').value);
            formData.append('expiry_date', document.getElementById('expiryDate').value);
            formData.append('level', document.getElementById('level').value);
            formData.append('status', document.getElementById('status').value);
            
            fetch('process_certification.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('certificationForm').reset();
                    document.getElementById('editId').value = '';
                    document.getElementById('formTitle').textContent = 'Yeni Belgelendirme Ekle';
                    document.getElementById('submitBtn').textContent = 'Belgelendirme Ekle';
                    document.getElementById('cancelBtn').style.display = 'none';
                    if (companySelect) companySelect.clear();
                    if (documentTypeSelect) documentTypeSelect.clear();
                    if (consultantSelect) consultantSelect.clear();
                    if (certificationOrganizationSelect) certificationOrganizationSelect.clear();
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Fetch hatası:', error);
                showAlert('Bir hata oluştu! Lütfen tekrar deneyin.', 'error');
            });
        });
        
        window.editCertification = function(id) {
            fetch('get_certification.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cert = data.certification;
                        document.getElementById('editId').value = cert.id;
                        if (cert.company_id && companySelect) {
                            const companyItem = document.querySelector(`#companyDropdown .dropdown-item[data-value="${cert.company_id}"]`);
                            if (companyItem) {
                                companySelect.setValue(cert.company_id, companyItem.dataset.text);
                            }
                        }
                        
                        if (cert.document_type_id && documentTypeSelect) {
                            const docTypeItem = document.querySelector(`#documentTypeDropdown .dropdown-item[data-value="${cert.document_type_id}"]`);
                            if (docTypeItem) {
                                documentTypeSelect.setValue(cert.document_type_id, docTypeItem.dataset.text);
                            }
                        }
                        
                        if (cert.consultant_id && consultantSelect) {
                            const consultantItem = document.querySelector(`#consultantDropdown .dropdown-item[data-value="${cert.consultant_id}"]`);
                            if (consultantItem) {
                                consultantSelect.setValue(cert.consultant_id, consultantItem.dataset.text);
                            }
                        }
                        if (cert.accreditation_type) {
                            document.getElementById('accreditationType').value = cert.accreditation_type;
                        }
                        
                        if (cert.certification_organization) {
                            if (certificationOrganizationSelect) {
                                certificationOrganizationSelect.setValue(cert.certification_organization, cert.certification_organization);
                            } else {
                                document.getElementById('certificationOrganization').value = cert.certification_organization;
                                document.getElementById('certificationOrganizationSearch').value = cert.certification_organization;
                            }
                        } else {
                            if (certificationOrganizationSelect) {
                                certificationOrganizationSelect.setValue('', 'Belgelendiren kuruluş seçiniz');
                            } else {
                                document.getElementById('certificationOrganization').value = '';
                                document.getElementById('certificationOrganizationSearch').value = '';
                            }
                        }
                        
                        document.getElementById('documentNumber').value = cert.document_number;
                        document.getElementById('scope').value = cert.scope || '';
                        document.getElementById('issueDate').value = cert.issue_date;
                        document.getElementById('expiryDate').value = cert.expiry_date;
                        document.getElementById('level').value = cert.level || '';
                        document.getElementById('status').value = cert.status;
                        
                        document.getElementById('formTitle').textContent = 'Belgelendirme Düzenle';
                        document.getElementById('submitBtn').textContent = 'Güncelle';
                        document.getElementById('cancelBtn').style.display = 'inline-block';
                        
                        document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Belgelendirme bilgileri alınırken hata oluştu.', 'error');
                });
        }
        function deleteCertification(id, documentNumber) {
            if (confirm(`"${documentNumber}" numaralı belgelendirmeyi silmek istediğinizden emin misiniz?`)) {
                fetch('process_certification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Belgelendirme silinirken hata oluştu.', 'error');
                });
            }
        }
        document.getElementById('cancelBtn').addEventListener('click', function() {
            document.getElementById('certificationForm').reset();
            document.getElementById('editId').value = '';
            document.getElementById('formTitle').textContent = 'Yeni Belgelendirme Ekle';
            document.getElementById('submitBtn').textContent = 'Belgelendirme Ekle';
            document.getElementById('cancelBtn').style.display = 'none';
            if (companySelect) companySelect.clear();
            if (documentTypeSelect) documentTypeSelect.clear();
            if (consultantSelect) consultantSelect.clear();
            if (certificationOrganizationSelect) certificationOrganizationSelect.clear();
        });
        document.getElementById('certificationForm').addEventListener('reset', function() {
            setTimeout(() => {
                if (companySelect) companySelect.clear();
                if (documentTypeSelect) documentTypeSelect.clear();
                if (consultantSelect) consultantSelect.clear();
                if (certificationOrganizationSelect) certificationOrganizationSelect.clear();
            }, 10);
        });
    </script>
</body>
</html>