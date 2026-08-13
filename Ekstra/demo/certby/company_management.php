<?php

require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}

if (!in_array($userData['role'], ['operator','user'])) {
    header('Location: dashboard.php');
    exit();
}

if (isset($_POST['toggle_company_status'])) {
    $companyId = $_POST['company_id'];
    $newStatus = $_POST['new_status'];
    
    if ($newStatus === 'inactive') {
        $certCount = getCompanyCertificationCount($companyId);
        if ($certCount > 0) {
            $_SESSION['error_message'] = "Bu firma {$certCount} adet belgelendirmede kullanıldığı için pasif hale getirilemez.";
            header('Location: company_management.php');
            exit();
        }
    }
    
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("UPDATE companies SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $companyId]);
        
        $statusText = ($newStatus === 'active') ? 'aktif' : 'pasif';
        $_SESSION['success_message'] = "Firma durumu başarıyla {$statusText} olarak güncellendi.";
        header('Location: company_management.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Firma durumu güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

function getCompanyCertificationCount($companyId) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cert_count 
            FROM certifications 
            WHERE company_id = ? AND status != 'deleted'
        ");
        $stmt->execute([$companyId]);
        $result = $stmt->fetch();
        return $result['cert_count'];
    } catch (Exception $e) {
        error_log("Firma belgelendirme sayısı alınamadı: " . $e->getMessage());
        return 0;
    }
}

function getCompanies() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("
            SELECT c.id, c.short_name, c.trade_name, c.address, c.contact_person, 
                   c.contact_phone, c.contact_email, c.tax_office, c.tax_number, 
                   c.status, c.created_at, c.updated_at,
                   COUNT(cert.id) as certification_count
            FROM companies c
            LEFT JOIN certifications cert ON c.id = cert.company_id AND cert.status != 'deleted'
            WHERE c.status IN ('active', 'inactive')
            GROUP BY c.id
            ORDER BY 
                CASE WHEN c.status = 'active' THEN 0 ELSE 1 END,
                c.created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Firma listesi alınamadı: " . $e->getMessage());
        return [];
    }
}

$companies = getCompanies();
$activeCount = count(array_filter($companies, function($company) { return $company['status'] === 'active'; }));
$inactiveCount = count(array_filter($companies, function($company) { return $company['status'] === 'inactive'; }));
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgelendirme - Firma Yönetimi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f5f6fa;
            color: #333;
            line-height: 1.6;
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

        .nav-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }

        .main-content {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .add-company-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .add-company-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .companies-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .companies-header {
            background: #f8f9fa;
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
        }

        .companies-header h2 {
            color: #333;
            font-size: 1.5em;
            margin-bottom: 5px;
        }

        .companies-count {
            color: #666;
            font-size: 1em;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .count-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-active {
            background: #28a745;
        }

        .status-inactive {
            background: #dc3545;
        }

        .companies-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .company-item {
            padding: 25px 30px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            position: relative;
        }

        .company-item:hover {
            background: #f8f9fa;
        }

        .company-item:last-child {
            border-bottom: none;
        }

        .company-item.inactive {
            background: #f8f9fa;
            opacity: 0.7;
        }

        .company-item.inactive:hover {
            background: #e9ecef;
        }

        .company-info {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 200px;
            gap: 20px;
            align-items: start;
        }

        .company-main {
            display: flex;
            flex-direction: column;
        }

        .company-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .company-name .status-badge {
            font-size: 0.7em;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: normal;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .company-trade-name {
            color: #666;
            font-size: 0.95em;
            margin-bottom: 8px;
        }

        .company-address {
            color: #777;
            font-size: 0.9em;
            line-height: 1.4;
        }

        .company-contact {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            color: #555;
        }

        .contact-item i {
            width: 16px;
            color: #667eea;
        }

        .company-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.85em;
            color: #888;
        }

        .tax-info {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }

        .certification-info {
            background: #e3f2fd;
            padding: 8px 12px;
            border-radius: 6px;
            border-left: 3px solid #2196f3;
            margin-top: 8px;
            font-weight: bold;
            color: #1976d2;
        }

        .company-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .action-btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: inline-block;
            font-weight: 500;
        }

        .edit-btn {
            background: #ff9800;
            color: white;
        }

        .edit-btn:hover {
            background: #f57c00;
            transform: translateY(-1px);
        }

        .status-btn {
            background: #dc3545;
            color: white;
        }

        .status-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .status-btn.make-active {
            background: #28a745;
        }

        .status-btn.make-active:hover {
            background: #218838;
        }

        .status-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .status-btn:disabled:hover {
            transform: none;
            background: #6c757d;
        }

        .disabled-reason {
            font-size: 0.8em;
            color: #6c757d;
            text-align: center;
            margin-top: 4px;
            font-style: italic;
        }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            color: #666;
        }

        .empty-state i {
            font-size: 4em;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 1.1em;
            margin-bottom: 20px;
        }

        .search-container {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .search-box {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .search-btn:hover {
            background: #5a67d8;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: none;
            border-radius: 10px;
            width: 400px;
            text-align: center;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .modal-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }

        .confirm-btn {
            background: #17a2b8;
            color: white;
        }

        .cancel-btn {
            background: #6c757d;
            color: white;
        }

        .section-divider {
            margin: 20px 30px;
            padding: 10px 0;
            border-bottom: 2px solid #e9ecef;
            font-weight: bold;
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .page-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .company-info {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .search-box {
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }

            .company-actions {
                flex-direction: row;
                justify-content: center;
            }

            .companies-count {
                flex-direction: column;
                gap: 10px;
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
            <div class="nav-info">
                <span>Hoşgeldiniz, <?php echo htmlspecialchars($userData['full_name']); ?></span>
                <a href="dashboard.php" class="back-btn">← Ana Sayfa</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success-message">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="page-header">
            <div class="page-title">
                <h1>🏢 Firma Yönetimi</h1>
                <p>Firmalarınızı görüntüleyin, ekleyin, güncelleyin ve yönetin</p>
            </div>
            <a href="company_add.php" class="add-company-btn">
                ➕ Yeni Firma Ekle
            </a>
        </div>

        <div class="search-container">
            <div class="search-box">
                <input type="text" class="search-input" id="searchInput" placeholder="Firma kısa adı ile arayın...">
                <button class="search-btn" onclick="searchCompanies()">🔍 Ara</button>
            </div>
        </div>

        <div class="companies-container">
            <div class="companies-header">
                <h2>Kayıtlı Firmalar</h2>
                <div class="companies-count">
                    <div class="count-item">
                        <span class="status-indicator status-active"></span>
                        <span>Aktif: <strong><?php echo $activeCount; ?></strong></span>
                    </div>
                    <div class="count-item">
                        <span class="status-indicator status-inactive"></span>
                        <span>Pasif: <strong><?php echo $inactiveCount; ?></strong></span>
                    </div>
                    <div class="count-item">
                        <span>Toplam: <strong><?php echo count($companies); ?></strong></span>
                    </div>
                </div>
            </div>

            <div class="companies-list" id="companiesList">
                <?php if (empty($companies)): ?>
                    <div class="empty-state">
                        <div style="font-size: 4em; color: #ddd; margin-bottom: 20px;">🏢</div>
                        <h3>Henüz firma bulunmuyor</h3>
                        <p>Sistem üzerinde kayıtlı firma bulunmamaktadır.</p>
                        <a href="company_add.php" class="add-company-btn">
                            ➕ İlk Firmayı Ekle
                        </a>
                    </div>
                <?php else: ?>
                    <?php 
                    $currentStatus = '';
                    foreach ($companies as $company): 
                        if ($currentStatus !== $company['status']):
                            $currentStatus = $company['status'];
                            if ($currentStatus === 'active'): ?>
                                <div class="section-divider">✅ Aktif Firmalar</div>
                            <?php elseif ($currentStatus === 'inactive'): ?>
                                <div class="section-divider">⏸️ Pasif Firmalar</div>
                            <?php endif;
                        endif;
                    ?>
                        <div class="company-item <?php echo $company['status']; ?>" 
                             data-company-info="<?php echo htmlspecialchars(strtolower($company['short_name'])); ?>"
                             data-status="<?php echo $company['status']; ?>"
                             data-company-id="<?php echo $company['id']; ?>"
                             data-company-name="<?php echo htmlspecialchars($company['short_name']); ?>">
                            <div class="company-info">
                                <div class="company-main">
                                    <div class="company-name">
                                        <?php echo htmlspecialchars($company['short_name']); ?>
                                        <span class="status-badge <?php echo $company['status']; ?>">
                                            <?php echo $company['status'] === 'active' ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </div>
                                    <div class="company-trade-name"><?php echo htmlspecialchars($company['trade_name']); ?></div>
                                    <div class="company-address"><?php echo htmlspecialchars($company['address']); ?></div>
                                </div>
                                
                                <div class="company-contact">
                                    <div class="contact-item">
                                        <span style="color: #667eea;">👤</span>
                                        <span><?php echo htmlspecialchars($company['contact_person']); ?></span>
                                    </div>
                                    <div class="contact-item">
                                        <span style="color: #667eea;">📞</span>
                                        <span><?php echo htmlspecialchars($company['contact_phone']); ?></span>
                                    </div>
                                    <div class="contact-item">
                                        <span style="color: #667eea;">✉️</span>
                                        <span><?php echo htmlspecialchars($company['contact_email']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="company-meta">
                                    <div class="tax-info">
                                        <strong><?php echo htmlspecialchars($company['tax_office']); ?></strong><br>
                                        <span>VN: <?php echo htmlspecialchars($company['tax_number']); ?></span>
                                    </div>
                                    
                                    <div class="certification-info">
                                        <?php echo $company['certification_count']; ?> Belgelendirme
                                    </div>
                                    
                                    <div style="margin-top: 8px;">
                                        <small>Eklenme: <?php echo date('d.m.Y', strtotime($company['created_at'])); ?></small>
                                        <?php if ($company['updated_at'] && $company['updated_at'] !== $company['created_at']): ?>
                                            <br><small>Güncelleme: <?php echo date('d.m.Y', strtotime($company['updated_at'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="company-actions">
                                    <a href="company_edit.php?id=<?php echo $company['id']; ?>" class="action-btn edit-btn">
                                        Düzenle
                                    </a>
                                    <?php if ($company['status'] === 'active'): ?>
                                        <?php if ($company['certification_count'] > 0): ?>
                                            <button class="action-btn status-btn" disabled title="Bu firma belgelendirmede kullanıldığı için pasif yapılamaz">
                                                Pasif Yap
                                            </button>
                                            <div class="disabled-reason">
                                                <?php echo $company['certification_count']; ?> belgelendirmede kullanılıyor
                                            </div>
                                        <?php else: ?>
                                            <button class="action-btn status-btn" 
                                                    onclick="handleStatusChange(this, 'inactive')">
                                                Pasif Yap
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="action-btn status-btn make-active" 
                                                onclick="handleStatusChange(this, 'active')">
                                            Aktif Yap
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h3>Firma Durumu Değiştirme Onayı</h3>
            <p id="statusMessage"></p>
            <div class="modal-buttons">
                <button class="modal-btn confirm-btn" onclick="changeCompanyStatus()">Evet, Değiştir</button>
                <button class="modal-btn cancel-btn" onclick="closeModal()">İptal</button>
            </div>
        </div>
    </div>

    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="toggle_company_status" value="1">
        <input type="hidden" name="company_id" id="statusCompanyId">
        <input type="hidden" name="new_status" id="newStatus">
    </form>

    <script>
        let statusCompanyId = null;
        let newCompanyStatus = null;

        function handleStatusChange(button, newStatus) {
            const companyItem = button.closest('.company-item');
            const companyId = companyItem.getAttribute('data-company-id');
            const companyName = companyItem.getAttribute('data-company-name');
            
            confirmStatusChange(companyId, companyName, newStatus);
        }

        function searchCompanies() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const companyItems = document.querySelectorAll('.company-item');
            let visibleCount = 0;
            let activeCount = 0;
            let inactiveCount = 0;

            companyItems.forEach(item => {
                const companyInfo = item.getAttribute('data-company-info');
                const status = item.getAttribute('data-status');
                
                if (companyInfo.includes(searchTerm)) {
                    item.style.display = 'block';
                    visibleCount++;
                    if (status === 'active') activeCount++;
                    else inactiveCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            const dividers = document.querySelectorAll('.section-divider');
            dividers.forEach(divider => {
                if (activeCount === 0 && divider.textContent.includes('Aktif')) {
                    divider.style.display = 'none';
                } else if (inactiveCount === 0 && divider.textContent.includes('Pasif')) {
                    divider.style.display = 'none';
                } else {
                    divider.style.display = 'block';
                }
            });

            updateResultCount(visibleCount, activeCount, inactiveCount);
        }

        function updateResultCount(total, active, inactive) {
            const countElement = document.querySelector('.companies-count');
            const totalCount = <?php echo count($companies); ?>;
            
            if (total === totalCount) {
                countElement.innerHTML = `
                    <div class="count-item">
                        <span class="status-indicator status-active"></span>
                        <span>Aktif: <strong>${active}</strong></span>
                    </div>
                    <div class="count-item">
                        <span class="status-indicator status-inactive"></span>
                        <span>Pasif: <strong>${inactive}</strong></span>
                    </div>
                    <div class="count-item">
                        <span>Toplam: <strong>${total}</strong></span>
                    </div>
                `;
            } else {
                countElement.innerHTML = `
                    <div class="count-item">
                        <span>Gösterilen: <strong>${total}</strong> (${totalCount} firma içinden)</span>
                    </div>
                    <div class="count-item">
                        <span class="status-indicator status-active"></span>
                        <span>Aktif: <strong>${active}</strong></span>
                    </div>
                    <div class="count-item">
                        <span class="status-indicator status-inactive"></span>
                        <span>Pasif: <strong>${inactive}</strong></span>
                    </div>
                `;
            }
        }

        function confirmStatusChange(companyId, companyName, newStatus) {
            statusCompanyId = companyId;
            newCompanyStatus = newStatus;
            
            const statusText = newStatus === 'active' ? 'aktif' : 'pasif';
            const actionText = newStatus === 'active' ? 'aktif hale getirmek' : 'pasif hale getirmek';
            
            document.getElementById('statusMessage').textContent = 
                `"${companyName}" firmasını ${actionText} istediğinizden emin misiniz?`;
            document.getElementById('statusModal').style.display = 'block';
        }

        function changeCompanyStatus() {
            if (statusCompanyId && newCompanyStatus) {
                document.getElementById('statusCompanyId').value = statusCompanyId;
                document.getElementById('newStatus').value = newCompanyStatus;
                document.getElementById('statusForm').submit();
            }
        }

        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
            statusCompanyId = null;
            newCompanyStatus = null;
        }
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchCompanies();
            }
        });
        document.getElementById('searchInput').addEventListener('input', function() {
            searchCompanies();
        });
        window.addEventListener('load', function() {
            console.log('Firma yönetimi sayfası yüklendi');
        });
    </script>
</body>
</html>