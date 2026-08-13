<?php

require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}


if (isset($_POST['ajax']) && $_POST['ajax'] == 'update_dates') {
    header('Content-Type: application/json');
    
    $documentId = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;
    $issueDate = isset($_POST['issue_date']) ? $_POST['issue_date'] : '';
    $expiryDate = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : '';
    
    if ($documentId > 0 && !empty($issueDate) && !empty($expiryDate)) {
        try {
            $pdo = getConnection();
            $sql = "UPDATE certifications SET issue_date = ?, expiry_date = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$issueDate, $expiryDate, $documentId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'İşlem başarılı']);
            } else {
                echo json_encode(['success' => false, 'message' => 'İşlem gerçekleştirilemedi']);
            }
            
        } catch (Exception $e) {
            error_log('Date update error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
    }
    
    exit();
}

$documentId = isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0;

if ($documentId == 0) {
    header('Location: document_tracking.php');
    exit();
}

function getDocumentDetails($documentId) {
    try {
        $pdo = getConnection();
        
        $sql = "SELECT 
                    c.id,
                    COALESCE(comp.trade_name, comp.short_name) as company_name,
                    comp.contact_email,
                    comp.phone,
                    comp.address,
                    dt.name as cert_type,
                    dt.standard,
                    dt.interim_audit_count as inspection_count,
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

function calculateRemainingDays($document) {
    $now = new DateTime();
    $expiry = new DateTime($document['expiry_date']);
    return $now->diff($expiry)->days * ($expiry < $now ? -1 : 1);
}


function getInspectionPlans($documentId) {
    try {
        $pdo = getConnection();

        $sql = "SELECT 
                    p.id AS plan_id,
                    p.audit_start_date,
                    p.audit_end_date,
                    p.created_at,
                    p.completion_status,
                    aa.assignment_status,
                    aa.assignment_date,
                    COALESCE(u.full_name, TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))) AS auditor_name,
                    u.email AS auditor_email
                FROM plans p
                LEFT JOIN auditor_assignments aa ON p.id = aa.plan_id
                LEFT JOIN users u ON aa.auditor_id = u.id
                WHERE p.certification_id = ?
                ORDER BY p.audit_start_date DESC, aa.assignment_date DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$documentId]);
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Tetkik plan hatası: " . $e->getMessage());
        return [];
    }
}

$document = getDocumentDetails($documentId);
$inspectionPlans = getInspectionPlans($documentId);

if (!$document) {
    header('Location: document_tracking.php');
    exit();
}

$daysRemaining = calculateRemainingDays($document);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belge Detayları - Belgelendirme</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .examination-container {
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
        .status-warning { background: #fff3cd; color: #856404; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-suspended { background: #e2e3e5; color: #383d41; }
        
        .document-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
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
        
        .inspection-section {
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
        }
        
        .tetkik-management {
            text-align: center;
            padding: 40px;
        }
        
        .tetkik-manage-btn {
            background: linear-gradient(135deg, #6c5ce7, #a29bfe);
            color: white;
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(108, 92, 231, 0.3);
        }
        
        .tetkik-manage-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(108, 92, 231, 0.4);
            background: linear-gradient(135deg, #5a4fcf, #9f95fb);
        }
        
        .tetkik-manage-btn:active {
            transform: translateY(-1px);
        }
        
        .tetkik-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
        }
        
        .tetkik-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #6c5ce7;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #6c5ce7;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a4fcf;
        }
        
        .date-edit-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 16px;
            display: none;
        }
        
        .date-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .date-field {
            display: flex;
            flex-direction: column;
        }
        
        .date-field label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .date-field input {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
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
        
        .recent-plans {
            margin-top: 20px;
        }
        
        .plan-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .plan-date {
            font-weight: 600;
            color: #2d3436;
        }
        
        .plan-auditor {
            font-size: 13px;
            color: #6c757d;
        }
        
        .plan-status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-assigned { background: #d1ecf1; color: #0c5460; }
        .status-in_progress { background: #fff3cd; color: #856404; }
        .status-completed { background: #d5edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-planned { background: #e2e3e5; color: #383d41; }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        @media (max-width: 768px) {
            .examination-container {
                padding: 16px;
            }
            
            .document-info {
                grid-template-columns: 1fr;
            }
            
            .date-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .tetkik-stats {
                grid-template-columns: 1fr;
            }
            
            .tetkik-manage-btn {
                font-size: 16px;
                padding: 14px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="examination-container">
        <a href="document_tracking.php" class="back-button">
            ← Belge Listesine Dön
        </a>
        
        <div class="document-header">
            <div class="document-title">
                <?php echo htmlspecialchars($document['company_name']); ?>
                <?php
                $statusClass = 'status-active';
                $statusText = 'Aktif';
                
                if ($daysRemaining < 0) {
                    $statusClass = 'status-expired';
                    $statusText = 'Süresi Geçmiş';
                } elseif ($daysRemaining <= 30) {
                    $statusClass = 'status-warning';
                    $statusText = 'Süresi Yaklaşıyor';
                } elseif ($document['cert_status'] == 'suspended') {
                    $statusClass = 'status-suspended';
                    $statusText = 'Askıda';
                }
                ?>
                <span class="status-badge <?php echo $statusClass; ?>">
                    <?php echo $statusText; ?>
                </span>
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
                    <div class="info-label">Başlangıç Tarihi</div>
                    <div class="info-value"><?php echo date('d.m.Y', strtotime($document['issue_date'])); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Bitiş Tarihi</div>
                    <div class="info-value"><?php echo date('d.m.Y', strtotime($document['expiry_date'])); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Kalan Süre</div>
                    <div class="info-value">
                        <?php 
                        if ($daysRemaining < 0) {
                            echo abs($daysRemaining) . ' gün geçmiş';
                        } else {
                            echo $daysRemaining . ' gün';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($document['scope'])): ?>
            <div class="info-card">
                <div class="info-label">Kapsam</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($document['scope'])); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="toggleDateEdit()">
                    📅 Tarihleri Düzenle
                </button>
            </div>
            
            <div class="date-edit-form" id="dateEditForm">
                <div class="alert alert-success" id="dateSuccessAlert"></div>
                <div class="alert alert-error" id="dateErrorAlert"></div>
                
                <div class="date-grid">
                    <div class="date-field">
                        <label>Başlangıç Tarihi</label>
                        <input type="date" id="issueDate" value="<?php echo $document['issue_date']; ?>">
                    </div>
                    <div class="date-field">
                        <label>Bitiş Tarihi</label>
                        <input type="date" id="expiryDate" value="<?php echo $document['expiry_date']; ?>">
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="updateDates()" style="background: #28a745;">💾 Tarihleri Kaydet</button>
                    <button class="btn btn-primary" onclick="toggleDateEdit()">❌ İptal</button>
                </div>
            </div>
        </div>
        
        <div class="inspection-section">
            <div class="section-title">
                🔍 Tetkik Yönetimi
            </div>
            
            <div class="tetkik-info">
                <h4 style="color: #0c5460; margin-bottom: 12px;">📋 Tetkik Bilgileri</h4>
                 
                
                <div class="tetkik-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($inspectionPlans); ?></div>
                        <div class="stat-label">Toplam Plan</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php echo count(array_filter($inspectionPlans, function($p) { return $p['assignment_status'] == 'assigned'; })); ?>
                        </div>
                        <div class="stat-label">Atanmış</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php echo count(array_filter($inspectionPlans, function($p) { return ($p['assignment_status'] ?? null) == 'completed' || ($p['completion_status'] ?? null) == 'completed'; })); ?>
                        </div>
                        <div class="stat-label">Tamamlanan</div>
                    </div>
                </div>
                
                <?php if (!empty($inspectionPlans)): ?>
                <div class="recent-plans">
                    <h5 style="color: #495057; margin-bottom: 12px;">Son Tetkik Planları:</h5>
                    <?php foreach (array_slice($inspectionPlans, 0, 3) as $plan): ?>
                    <div class="plan-item">
                        <div>
                            <div class="plan-date">
                                📅 <?php echo date('d.m.Y', strtotime($plan['audit_start_date'])); ?> - 
                                <?php echo date('d.m.Y', strtotime($plan['audit_end_date'])); ?>
                            </div>
                            <div class="plan-auditor">
                                👨‍💼 <?php echo htmlspecialchars($plan['auditor_name'] ?: 'Atanmamış'); ?>
                            </div>
                        </div>
                        <?php 
                        $rawStatus = $plan['assignment_status'] ?? null;
                        if (!$rawStatus) {
                            $rawStatus = ($plan['completion_status'] ?? '') === 'completed' ? 'completed' : 'planned';
                        }
                        ?>
                        <span class="plan-status status-<?php echo htmlspecialchars($rawStatus); ?>">
                            <?php 
                            $statusLabels = [
                                'assigned' => 'Atandı',
                                'in_progress' => 'Devam Ediyor',
                                'completed' => 'Tamamlandı',
                                'cancelled' => 'İptal',
                                'planned' => 'Planlandı'
                            ];
                            echo $statusLabels[$rawStatus] ?? ucfirst($rawStatus);
                            ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="tetkik-management">
                <a href="planning.php?doc_id=<?php echo $documentId; ?>" class="tetkik-manage-btn">
                    🔍 Tetkikleri Yönet
                </a>
                 
            </div>
        </div>
    </div>

    <script>
        function toggleDateEdit() {
            const form = document.getElementById('dateEditForm');
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                hideAlerts('date');
            } else {
                form.style.display = 'none';
                hideAlerts('date');
            }
        }
        
        function updateDates() {
            const issueDate = document.getElementById('issueDate').value;
            const expiryDate = document.getElementById('expiryDate').value;
            
            if (!issueDate || !expiryDate) {
                showAlert('Lütfen tüm tarihleri girin.', 'error', 'date');
                return;
            }
            
            if (new Date(issueDate) >= new Date(expiryDate)) {
                showAlert('Bitiş tarihi, başlangıç tarihinden sonra olmalıdır.', 'error', 'date');
                return;
            }
            
            const formData = new FormData();
            formData.append('ajax', 'update_dates');
            formData.append('document_id', '<?php echo $documentId; ?>');
            formData.append('issue_date', issueDate);
            formData.append('expiry_date', expiryDate);
            
            document.body.classList.add('loading');
            
            fetch('examination.php?doc_id=<?php echo $documentId; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.classList.remove('loading');
                
                if (data.success) {
                    showAlert(data.message, 'success', 'date');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error', 'date');
                }
            })
            .catch(error => {
                document.body.classList.remove('loading');
                showAlert('Bir hata oluştu. Lütfen tekrar deneyin.', 'error', 'date');
                console.error('Error:', error);
            });
        }
        
        function showAlert(message, type, prefix = '') {
            hideAlerts(prefix);
            
            const alertId = prefix ? `${prefix}${type.charAt(0).toUpperCase() + type.slice(1)}Alert` : `${type}Alert`;
            const alertElement = document.getElementById(alertId);
            
            if (alertElement) {
                alertElement.textContent = message;
                alertElement.style.display = 'block';
                
                setTimeout(() => {
                    alertElement.style.display = 'none';
                }, 5000);
            }
        }
        
        function hideAlerts(prefix = '') {
            const alertIds = prefix ? 
                [`${prefix}SuccessAlert`, `${prefix}ErrorAlert`] : 
                ['successAlert', 'errorAlert'];
                
            alertIds.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>