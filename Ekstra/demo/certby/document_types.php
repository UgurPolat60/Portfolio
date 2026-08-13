<?php

require_once 'config.php';
requireLogin();

$currentUser = getUserData($_SESSION['user_id']);

function getDocumentTypes() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT dt.*, 
                   COUNT(c.id) as usage_count,
                   COUNT(CASE WHEN c.status = 'active' THEN 1 END) as active_usage_count
            FROM document_types dt
            LEFT JOIN certifications c ON dt.id = c.document_type_id
            GROUP BY dt.id
            ORDER BY dt.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Belge türleri getirme hatası: " . $e->getMessage());
        return [];
    }
}

$documentTypes = getDocumentTypes();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belge Türleri - Belgelendirme</title>
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
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
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
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

        .document-type-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .document-type-card:hover {
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

        .card-standard {
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

        .usage-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .usage-badge.inactive {
            background: #f3e5f5;
            color: #7b1fa2;
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

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(2px);
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            min-width: 400px;
            max-width: 500px;
            z-index: 1001;
        }

        .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-icon {
            width: 50px;
            height: 50px;
            background: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 24px;
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
        }

        .modal-body {
            margin-bottom: 25px;
            line-height: 1.6;
            color: #666;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .modal-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .modal-btn-cancel:hover {
            background: #5a6268;
        }

        .modal-btn-delete {
            background: #dc3545;
            color: white;
        }

        .modal-btn-delete:hover {
            background: #c82333;
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

            .modal {
                min-width: 90%;
                margin: 0 5%;
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
            <strong>Belge Türleri</strong>
        </div>

        <div class="page-title">
            <h1>📄 Belge Türleri</h1>
        </div>

        <div class="alert" id="alert"></div>

        <div class="content-grid">
            <div class="form-section">
                <h2 id="formTitle">Yeni Belge Türü Ekle</h2>
                <form id="documentTypeForm">
                    <input type="hidden" id="editId" value="">
                    
                    <div class="form-group">
                        <label for="documentName">Belge Adı *</label>
                        <input type="text" id="documentName" name="name" required placeholder="Örn: BGYS, KYS, ÇYS">
                    </div>

                    <div class="form-group">
                        <label for="documentStandard">Belge Standartı *</label>
                        <input type="text" id="documentStandard" name="standard" required placeholder="Örn: ISO 13485:2016">
                    </div>

                    <div class="form-group">
                        <label for="validityPeriod">Belgelendirme Periyodu (Yıl) *</label>
                        <input type="number" id="validityPeriod" name="validity_period" required min="1" max="10" placeholder="3">
                    </div>

                    <div class="form-group">
                        <label for="interimAuditCount">Ara Tetkik Sayısı *</label>
                        <input type="number" id="interimAuditCount" name="interim_audit_count" required min="0" max="10" placeholder="2">
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" id="submitBtn">Belge Türü Ekle</button>
                        <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;">İptal</button>
                    </div>
                </form>
            </div>

            <div class="list-section">
                <h2>Mevcut Belge Türleri</h2>
                <div id="documentTypesList">
                    <?php if (empty($documentTypes)): ?>
                        <div class="empty-state">
                            <h3>Henüz belge türü eklenmemiş</h3>
                            <p>Sol taraftaki formu kullanarak ilk belge türünüzü ekleyin.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($documentTypes as $docType): ?>
                            <div class="document-type-card">
                                <div class="card-header">
                                    <div>
                                        <div class="card-title"><?= htmlspecialchars($docType['name']) ?></div>
                                        <div class="card-standard"><?= htmlspecialchars($docType['standard']) ?></div>
                                    </div>
                                    <div>
                                        <?php if ($docType['active_usage_count'] > 0): ?>
                                            <div class="usage-badge">
                                                <?= $docType['active_usage_count'] ?> aktif belge
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($docType['usage_count'] > $docType['active_usage_count']): ?>
                                            <div class="usage-badge inactive" style="margin-top: 5px;">
                                                <?= ($docType['usage_count'] - $docType['active_usage_count']) ?> iptal edilmiş
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Geçerlilik Süresi</div>
                                        <div class="detail-value"><?= $docType['validity_period'] ?> Yıl</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Ara Tetkik Sayısı</div>
                                        <div class="detail-value"><?= $docType['interim_audit_count'] ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Oluşturulma</div>
                                        <div class="detail-value"><?= date('d.m.Y', strtotime($docType['created_at'])) ?></div>
                                    </div>
                                </div>
                                
                                <div class="card-actions">
                                    <button class="btn btn-edit" onclick="editDocumentType(<?= $docType['id'] ?>)">
                                        Düzenle
                                    </button>
                                    <button class="btn btn-danger" onclick="showDeleteModal(<?= $docType['id'] ?>, '<?= htmlspecialchars($docType['name'], ENT_QUOTES) ?>', <?= $docType['usage_count'] ?>, <?= $docType['active_usage_count'] ?>)">
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
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon">⚠️</div>
                <div class="modal-title">Belge Türünü Sil</div>
            </div>
            <div class="modal-body" id="deleteModalBody">
            </div>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="hideDeleteModal()">İptal</button>
                <button class="modal-btn modal-btn-delete" id="confirmDeleteBtn">Sil</button>
            </div>
        </div>
    </div>

    <script>
        let deleteDocumentTypeId = null;

        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert ${type}`;
            alert.style.display = 'block';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        function showDeleteModal(id, name, totalUsage, activeUsage) {
            deleteDocumentTypeId = id;
            const modal = document.getElementById('deleteModal');
            const modalBody = document.getElementById('deleteModalBody');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            if (totalUsage > 0) {
                let message = `<strong>"${name}"</strong> belge türü `;
                if (activeUsage > 0) {
                    message += `${activeUsage} aktif belge`;
                    if (totalUsage > activeUsage) {
                        message += ` ve ${totalUsage - activeUsage} iptal edilmiş belge`;
                    }
                    message += ` tarafından kullanılıyor.<br><br>
                              <span style="color: #dc3545; font-weight: bold;">
                              Bu belge türünü silemezsiniz! Önce tüm ilişkili belgelendirmeleri tamamen kaldırın.
                              </span>`;
                    confirmBtn.style.display = 'none';
                } else {
                    message += `${totalUsage} iptal edilmiş belge tarafından kullanılıyor.<br><br>
                              Bu belge türünü silmek istediğinizden emin misiniz? 
                              <br><span style="color: #dc3545;">Bu işlem geri alınamaz!</span>`;
                    confirmBtn.style.display = 'inline-block';
                }
                modalBody.innerHTML = message;
            } else {
                modalBody.innerHTML = `<strong>"${name}"</strong> belge türünü silmek istediğinizden emin misiniz?
                                     <br><span style="color: #dc3545;">Bu işlem geri alınamaz!</span>`;
                confirmBtn.style.display = 'inline-block';
            }
            
            modal.style.display = 'block';
        }
        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteDocumentTypeId = null;
        }
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (deleteDocumentTypeId) {
                deleteDocumentType(deleteDocumentTypeId);
            }
        });
        document.getElementById('documentTypeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const editId = document.getElementById('editId').value;
            
            formData.append('action', editId ? 'update' : 'add');
            if (editId) formData.append('id', editId);
            formData.append('name', document.getElementById('documentName').value);
            formData.append('standard', document.getElementById('documentStandard').value);
            formData.append('validity_period', document.getElementById('validityPeriod').value);
            formData.append('interim_audit_count', document.getElementById('interimAuditCount').value);
            
            fetch('process_document_types.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('documentTypeForm').reset();
                    document.getElementById('editId').value = '';
                    document.getElementById('formTitle').textContent = 'Yeni Belge Türü Ekle';
                    document.getElementById('submitBtn').textContent = 'Belge Türü Ekle';
                    document.getElementById('cancelBtn').style.display = 'none';
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Bir hata oluştu! Lütfen tekrar deneyin.', 'error');
            });
        });

        function editDocumentType(id) {
            fetch('get_document_type.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const docType = data.document_type;
                        document.getElementById('editId').value = docType.id;
                        document.getElementById('documentName').value = docType.name;
                        document.getElementById('documentStandard').value = docType.standard;
                        document.getElementById('validityPeriod').value = docType.validity_period;
                        document.getElementById('interimAuditCount').value = docType.interim_audit_count;
                        
                        document.getElementById('formTitle').textContent = 'Belge Türü Düzenle';
                        document.getElementById('submitBtn').textContent = 'Güncelle';
                        document.getElementById('cancelBtn').style.display = 'inline-block';
                        
                        document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Belge türü bilgileri alınırken hata oluştu.', 'error');
                });
        }

        function deleteDocumentType(id) {
            fetch('process_document_types.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                hideDeleteModal();
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
                hideDeleteModal();
                showAlert('Silme işlemi sırasında hata oluştu.', 'error');
            });
        }
        document.getElementById('cancelBtn').addEventListener('click', function() {
            document.getElementById('documentTypeForm').reset();
            document.getElementById('editId').value = '';
            document.getElementById('formTitle').textContent = 'Yeni Belge Türü Ekle';
            document.getElementById('submitBtn').textContent = 'Belge Türü Ekle';
            document.getElementById('cancelBtn').style.display = 'none';
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideDeleteModal();
            }
        });
    </script>
</body>
</html>