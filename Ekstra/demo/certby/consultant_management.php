<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getConnection();
        
        switch ($action) {
            case 'add_consultant':
                $company_short_name = sanitizeInput($_POST['company_short_name']);
                $company_full_name = sanitizeInput($_POST['company_full_name']);
                $company_address = sanitizeInput($_POST['company_address']);
                $company_email = sanitizeInput($_POST['company_email']);
                $company_phone = sanitizeInput($_POST['company_phone'] ?? '');
                $consultant_name = sanitizeInput($_POST['consultant_name']);
                $consultant_email = sanitizeInput($_POST['consultant_email']);
                $consultant_phone = sanitizeInput($_POST['consultant_phone'] ?? '');
                
                if (!validateEmail($company_email)) { throw new Exception('İstek geçersiz'); }
                
                if (!empty($consultant_email) && !validateEmail($consultant_email)) { throw new Exception('İstek geçersiz'); }
                
                $stmt = $pdo->prepare("SELECT id FROM consultants WHERE company_email = ?");
                $stmt->execute([$company_email]);
                if ($stmt->fetch()) { throw new Exception('Kayıt mevcut'); }
                
                $stmt = $pdo->prepare("INSERT INTO consultants (company_short_name, company_full_name, company_address, company_email, company_phone, consultant_name, consultant_email, consultant_phone, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$company_short_name, $company_full_name, $company_address, $company_email, $company_phone ?: NULL, $consultant_name, $consultant_email, $consultant_phone ?: NULL, $_SESSION['user_id']]);
                
                jsonResponse(true, 'İşlem başarılı');
                break;
                
            case 'update_consultant':
                $id = (int)$_POST['id'];
                $company_short_name = sanitizeInput($_POST['company_short_name']);
                $company_full_name = sanitizeInput($_POST['company_full_name']);
                $company_address = sanitizeInput($_POST['company_address']);
                $company_email = sanitizeInput($_POST['company_email']);
                $company_phone = sanitizeInput($_POST['company_phone'] ?? '');
                $consultant_name = sanitizeInput($_POST['consultant_name']);
                $consultant_email = sanitizeInput($_POST['consultant_email']);
                $consultant_phone = sanitizeInput($_POST['consultant_phone'] ?? '');
                $status = sanitizeInput($_POST['status']);
                
                if (!validateEmail($company_email)) { throw new Exception('İstek geçersiz'); }
                
                if (!empty($consultant_email) && !validateEmail($consultant_email)) { throw new Exception('İstek geçersiz'); }
                
                $stmt = $pdo->prepare("SELECT id FROM consultants WHERE company_email = ? AND id != ?");
                $stmt->execute([$company_email, $id]);
                if ($stmt->fetch()) { throw new Exception('Kayıt mevcut'); }
                
                $stmt = $pdo->prepare("UPDATE consultants SET company_short_name = ?, company_full_name = ?, company_address = ?, company_email = ?, company_phone = ?, consultant_name = ?, consultant_email = ?, consultant_phone = ?, status = ? WHERE id = ?");
                $stmt->execute([$company_short_name, $company_full_name, $company_address, $company_email, $company_phone ?: NULL, $consultant_name, $consultant_email, $consultant_phone ?: NULL, $status, $id]);
                
                jsonResponse(true, 'İşlem başarılı');
                break;
                
            case 'delete_consultant':
                $id = (int)$_POST['id'];
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM certifications WHERE consultant_id = ?");
                $stmt->execute([$id]);
                $cert_count = $stmt->fetchColumn();
                
                if ($cert_count > 0) { throw new Exception('İşlem gerçekleştirilemedi'); }
                
                $stmt = $pdo->prepare("DELETE FROM consultants WHERE id = ?");
                $stmt->execute([$id]);
                
                jsonResponse(true, 'İşlem başarılı');
                break;
                
            case 'get_consultant_details':
                $id = (int)$_POST['id'];
                
                $stmt = $pdo->prepare("SELECT * FROM consultants WHERE id = ?");
                $stmt->execute([$id]);
                $consultant = $stmt->fetch();
                
                if (!$consultant) { throw new Exception('Kayıt bulunamadı'); }
                
                $stmt = $pdo->prepare("
                    SELECT DISTINCT c.id, c.short_name, c.trade_name, c.contact_person, c.contact_email, c.phone
                    FROM companies c
                    INNER JOIN certifications cert ON c.id = cert.company_id
                    WHERE cert.consultant_id = ?
                    ORDER BY c.short_name
                ");
                $stmt->execute([$id]);
                $companies = $stmt->fetchAll();
                
                $stmt = $pdo->prepare("
                    SELECT cert.id, cert.document_number, cert.issue_date, cert.expiry_date, cert.status,
                           c.short_name as company_name, dt.name as document_type
                    FROM certifications cert
                    INNER JOIN companies c ON cert.company_id = c.id
                    INNER JOIN document_types dt ON cert.document_type_id = dt.id
                    WHERE cert.consultant_id = ?
                    ORDER BY cert.issue_date DESC
                ");
                $stmt->execute([$id]);
                $certifications = $stmt->fetchAll();
                
                jsonResponse(true, '', [
                    'consultant' => $consultant,
                    'companies' => $companies,
                    'certifications' => $certifications
                ]);
                break;
        }
        
    } catch (Exception $e) {
        jsonResponse(false, 'Bir hata oluştu');
    }
}

try {
    $pdo = getConnection();
    $stmt = $pdo->query("
        SELECT c.*, 
               COUNT(cert.id) as certification_count,
               COUNT(DISTINCT cert.company_id) as company_count
        FROM consultants c
        LEFT JOIN certifications cert ON c.id = cert.consultant_id
        GROUP BY c.id
        ORDER BY c.company_short_name
    ");
    $consultants = $stmt->fetchAll();
} catch (Exception $e) {
    $consultants = [];
    $error_message = "Danışmanlar yüklenirken hata oluştu.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danışman Firma Yönetimi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .main-container {
            margin-top: 2rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 15px;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        
        .card-title {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 500;
            border-radius: 10px;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #667eea;
            font-weight: 600;
            color: #495057;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .consultant-row {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .consultant-row:hover {
            background-color: #f8f9fa;
        }
        
        .detail-card {
            margin-bottom: 1rem;
        }
        
        .detail-header {
            background-color: #e9ecef;
            padding: 0.75rem 1rem;
            font-weight: 600;
            border-radius: 10px 10px 0 0;
        }
        
        .action-buttons {
            gap: 0.5rem;
        }
        
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1100;
        }
        
        .toast {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .toast-success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .toast-error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .toast-header {
            background-color: transparent;
            border-bottom: none;
        }
        
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                Danışman Firma Yönetimi
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </div>
        </div>
    </nav>

    <div class="toast-container" id="toastContainer"></div>

    <div class="container-fluid main-container">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-building"></i> Danışman Firma Yönetimi
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Kayıtlı Danışman Firmaları</h6>
                            <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addConsultantModal">
                                <i class="fas fa-plus"></i> Yeni Danışman Firma
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Firma Kısa Adı</th>
                                        <th>Firma Uzun Adı</th>
                                        <th>Firma E-posta</th>
                                        <th>Danışman Adı</th>
                                        <th>Belge Sayısı</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($consultants as $consultant): ?>
                                    <tr class="consultant-row" onclick="showConsultantDetails(<?php echo $consultant['id']; ?>)">
                                        <td><?php echo htmlspecialchars($consultant['company_short_name']); ?></td>
                                        <td><?php echo htmlspecialchars($consultant['company_full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($consultant['company_email']); ?></td>
                                        <td><?php echo htmlspecialchars($consultant['consultant_name'] ?? '-'); ?></td>
                                        <td><span class="badge bg-success"><?php echo $consultant['certification_count']; ?></span></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $consultant['status']; ?>">
                                                <?php echo $consultant['status'] === 'active' ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons d-flex">
                                                <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); editConsultant(<?php echo htmlspecialchars(json_encode($consultant)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteConsultant(<?php echo $consultant['id']; ?>, '<?php echo htmlspecialchars($consultant['company_short_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div id="consultantDetails" class="card" style="display: none;">
                    <div class="card-header">
                        <h6 class="card-title">
                            <i class="fas fa-info-circle"></i> Danışman Firma Detayları
                        </h6>
                    </div>
                    <div class="card-body" id="detailsContent">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($consultants); ?></div>
                            <div class="stats-label">Toplam Danışman Firma</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addConsultantModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-building"></i> Yeni Danışman Firma Ekle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addConsultantForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="company_short_name" class="form-label">Firma Kısa Adı <span class="required">*</span></label>
                            <input type="text" class="form-control" id="company_short_name" name="company_short_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_full_name" class="form-label">Firma Uzun Adı <span class="required">*</span></label>
                            <input type="text" class="form-control" id="company_full_name" name="company_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_address" class="form-label">Firma Adresi</label>
                            <textarea class="form-control" id="company_address" name="company_address" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="company_email" class="form-label">Firma E-posta Adresi <span class="required">*</span></label>
                            <input type="email" class="form-control" id="company_email" name="company_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_phone" class="form-label">Firma Telefonu</label>
                            <input type="text" class="form-control" id="company_phone" name="company_phone"  >
                        </div>
                        <div class="mb-3">
                            <label for="consultant_name" class="form-label">Danışman Adı ve Soyadı</label>
                            <input type="text" class="form-control" id="consultant_name" name="consultant_name">
                        </div>
                        <div class="mb-3">
                            <label for="consultant_email" class="form-label">Danışman E-posta Adresi</label>
                            <input type="email" class="form-control" id="consultant_email" name="consultant_email">
                        </div>
                        <div class="mb-3">
                            <label for="consultant_phone" class="form-label">Danışman Telefonu</label>
                            <input type="text" class="form-control" id="consultant_phone" name="consultant_phone">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-gradient">
                            <i class="fas fa-save"></i> Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editConsultantModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Danışman Firma Düzenle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editConsultantForm">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_company_short_name" class="form-label">Firma Kısa Adı <span class="required">*</span></label>
                            <input type="text" class="form-control" id="edit_company_short_name" name="company_short_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_full_name" class="form-label">Firma Uzun Adı <span class="required">*</span></label>
                            <input type="text" class="form-control" id="edit_company_full_name" name="company_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_address" class="form-label">Firma Adresi</label>
                            <textarea class="form-control" id="edit_company_address" name="company_address" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_email" class="form-label">Firma E-posta Adresi <span class="required">*</span></label>
                            <input type="email" class="form-control" id="edit_company_email" name="company_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_phone" class="form-label">Firma Telefonu</label>
                            <input type="text" class="form-control" id="edit_company_phone" name="company_phone" placeholder="Örn: +90 555 555 55 55">
                        </div>
                        <div class="mb-3">
                            <label for="edit_consultant_name" class="form-label">Danışman Adı ve Soyadı</label>
                            <input type="text" class="form-control" id="edit_consultant_name" name="consultant_name">
                        </div>
                        <div class="mb-3">
                            <label for="edit_consultant_email" class="form-label">Danışman E-posta Adresi</label>
                            <input type="email" class="form-control" id="edit_consultant_email" name="consultant_email">
                        </div>
                        <div class="mb-3">
                            <label for="edit_consultant_phone" class="form-label">Danışman Telefonu</label>
                            <input type="text" class="form-control" id="edit_consultant_phone" name="consultant_phone" placeholder="Örn: +90 555 555 55 55">
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Durum</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Aktif</option>
                                <option value="inactive">Pasif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-gradient">
                            <i class="fas fa-save"></i> Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const toastHtml = `
                <div class="toast toast-${type}" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <i class="fas fa-${type === 'success' ? 'check-circle text-success' : 'exclamation-circle text-danger'} me-2"></i>
                        <strong class="me-auto">${type === 'success' ? 'Başarılı' : 'Hata'}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: 4000 });
            toast.show();
            
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }

        document.getElementById('addConsultantForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_consultant');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addConsultantModal')).hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Bir hata oluştu.', 'error');
            });
        });

        document.getElementById('editConsultantForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_consultant');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editConsultantModal')).hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Bir hata oluştu.', 'error');
            });
        });

        function editConsultant(consultant) {
            document.getElementById('edit_id').value = consultant.id;
            document.getElementById('edit_company_short_name').value = consultant.company_short_name;
            document.getElementById('edit_company_full_name').value = consultant.company_full_name;
            document.getElementById('edit_company_address').value = consultant.company_address || '';
            document.getElementById('edit_company_email').value = consultant.company_email;
            document.getElementById('edit_company_phone').value = consultant.company_phone || '';
            document.getElementById('edit_consultant_name').value = consultant.consultant_name || '';
            document.getElementById('edit_consultant_email').value = consultant.consultant_email || '';
            document.getElementById('edit_consultant_phone').value = consultant.consultant_phone || '';
            document.getElementById('edit_status').value = consultant.status;
            
            new bootstrap.Modal(document.getElementById('editConsultantModal')).show();
        }

        function deleteConsultant(id, name) {
            if (confirm(`"${name}" danışman firmasını silmek istediğinizden emin misiniz?`)) {
                const formData = new FormData();
                formData.append('action', 'delete_consultant');
                formData.append('id', id);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Bir hata oluştu.', 'error');
                });
            }
        }

        function showConsultantDetails(consultantId) {
            const formData = new FormData();
            formData.append('action', 'get_consultant_details');
            formData.append('id', consultantId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayConsultantDetails(data.data);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Detaylar yüklenirken hata oluştu.', 'error');
            });
        }

        function displayConsultantDetails(data) {
            const consultant = data.consultant;
            const companies = data.companies;
            const certifications = data.certifications;
            
            let html = `
                <div class="mb-4">
                    <h6>${consultant.company_short_name}</h6>
                    <p class="text-muted mb-1"><strong>Uzun Adı:</strong> ${consultant.company_full_name}</p>
                    <p class="text-muted mb-1"><i class="fas fa-envelope"></i> ${consultant.company_email}</p>
                    ${consultant.company_phone ? `<p class="text-muted mb-1"><i class="fas fa-phone"></i> ${consultant.company_phone}</p>` : ''}
                    ${consultant.company_address ? `<p class="text-muted mb-1"><i class="fas fa-map-marker-alt"></i> ${consultant.company_address}</p>` : ''}
                    ${consultant.consultant_name ? `<p class="text-muted mb-1"><i class="fas fa-user"></i> ${consultant.consultant_name}</p>` : ''}
                    ${consultant.consultant_email ? `<p class="text-muted mb-1"><i class="fas fa-envelope"></i> ${consultant.consultant_email}</p>` : ''}
                    ${consultant.consultant_phone ? `<p class="text-muted mb-1"><i class="fas fa-phone"></i> ${consultant.consultant_phone}</p>` : ''}
                    <span class="status-badge status-${consultant.status}">
                        ${consultant.status === 'active' ? 'Aktif' : 'Pasif'}
                    </span>
                </div>
                
                <div class="detail-card">
                    <div class="detail-header">
                        Firmalar (${companies.length})
                    </div>
                    <div class="p-2">
            `;
            
            if (companies.length > 0) {
                companies.forEach(company => {
                    html += `
                        <div class="border-bottom pb-2 mb-2">
                            <strong>${company.short_name}</strong><br>
                            <small class="text-muted">${company.trade_name}</small><br>
                            <small><i class="fas fa-user"></i> ${company.contact_person}</small><br>
                            <small><i class="fas fa-envelope"></i> ${company.contact_email}</small>
                        </div>
                    `;
                });
            } else {
                html += '<p class="text-muted">Bu danışman firmasına ait firma bulunmamaktadır.</p>';
            }
            
            html += `
                    </div>
                </div>
                
                <div class="detail-card">
                    <div class="detail-header">
                        Belgelendirmeler (${certifications.length})
                    </div>
                    <div class="p-2">
            `;
            
            if (certifications.length > 0) {
                certifications.forEach(cert => {
                    const statusText = cert.status === 'active' ? 'Aktif' : 'Pasif';
                    const statusColor = cert.status === 'active' ? 'success' : 'secondary';
                    html += `
                        <div class="border-bottom pb-2 mb-2">
                            <strong>${cert.document_number}</strong>
                            <span class="badge bg-${statusColor} float-end">${statusText}</span><br>
                            <small class="text-muted">${cert.company_name} - ${cert.document_type}</small><br>
                            <small><i class="fas fa-calendar"></i> ${cert.issue_date} / ${cert.expiry_date}</small>
                        </div>
                    `;
                });
            } else {
                html += '<p class="text-muted">Bu danışman firmasına ait belgelendirme bulunmamaktadır.</p>';
            }
            
            html += `
                    </div>
                </div>
            `;
            
            document.getElementById('detailsContent').innerHTML = html;
            document.getElementById('consultantDetails').style.display = 'block';
        }
    </script>
</body>
</html>