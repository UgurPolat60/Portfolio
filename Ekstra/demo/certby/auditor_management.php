<?php
require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}

if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax'] == 'get_auditor_details') {
        $auditorId = isset($_POST['auditor_id']) ? intval($_POST['auditor_id']) : 0;
        
        if ($auditorId > 0) {
            try {
                $pdo = getConnection();
                
                $auditorSql = "SELECT * FROM users WHERE id = ? AND role = 'auditor'";
                $auditorStmt = $pdo->prepare($auditorSql);
                $auditorStmt->execute([$auditorId]);
                $auditor = $auditorStmt->fetch();
                
                if (!$auditor) {
                    echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı']);
                    exit();
                }
                
                $assignedSql = "SELECT 
                    aa.id,
                    aa.assignment_date,
                    aa.assignment_status,
                    aa.assignment_notes,
                    p.audit_start_date,
                    p.audit_end_date,
                    p.inspection_type,
                    comp.trade_name,
                    comp.short_name,
                    dt.name as cert_type,
                    CASE 
                        WHEN p.inspection_type = 'certified' THEN dt.name
                        ELSE 'Belgesiz Tetkik'
                    END as inspection_title
                FROM auditor_assignments aa
                JOIN plans p ON aa.plan_id = p.id
                JOIN companies comp ON p.company_id = comp.id
                LEFT JOIN certifications c ON p.certification_id = c.id
                LEFT JOIN document_types dt ON c.document_type_id = dt.id
                WHERE aa.auditor_id = ? AND aa.assignment_status != 'completed'
                ORDER BY p.audit_start_date ASC";
                
                $assignedStmt = $pdo->prepare($assignedSql);
                $assignedStmt->execute([$auditorId]);
                $assignments = $assignedStmt->fetchAll();
                
                $completedSql = "SELECT 
                    ci.id,
                    ci.inspection_date,
                    ci.completion_date,
                    ci.inspection_result,
                    ci.inspection_notes,
                    ci.recommendations,
                    p.inspection_type,
                    comp.trade_name,
                    comp.short_name,
                    dt.name as cert_type,
                    CASE 
                        WHEN p.inspection_type = 'certified' THEN dt.name
                        ELSE 'Belgesiz Tetkik'
                    END as inspection_title
                FROM completed_inspections ci
                JOIN plans p ON ci.plan_id = p.id
                JOIN companies comp ON ci.company_id = comp.id
                LEFT JOIN certifications c ON p.certification_id = c.id
                LEFT JOIN document_types dt ON c.document_type_id = dt.id
                WHERE ci.auditor_id = ?
                ORDER BY ci.completion_date DESC";
                
                $completedStmt = $pdo->prepare($completedSql);
                $completedStmt->execute([$auditorId]);
                $completed = $completedStmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'auditor' => $auditor,
                    'assignments' => $assignments,
                    'completed' => $completed
                ]);
                
            } catch (Exception $e) {
                error_log('Auditor details error: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'İstek geçersiz']);
        }
        exit();
    }
}

function getAuditors() {
    try {
        $pdo = getConnection();
        
        $sql = "SELECT u.id, 
                       u.username,
                       u.full_name,
                       u.first_name,
                       u.last_name,
                       u.email,
                       u.role,
                       u.status,
                       u.created_at,
                       COUNT(aa.id) as assignment_count,
                       COUNT(CASE WHEN aa.assignment_status = 'assigned' THEN 1 END) as active_assignments,
                       COUNT(CASE WHEN aa.assignment_status = 'completed' THEN 1 END) as completed_assignments
                FROM users u
                LEFT JOIN auditor_assignments aa ON u.id = aa.auditor_id
                WHERE u.role = 'auditor' AND u.status = 'active'
                GROUP BY u.id
                ORDER BY COALESCE(u.first_name, u.full_name), COALESCE(u.last_name, '')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Denetçi getirme hatası: " . $e->getMessage());
        return [];
    }
}

$auditors = getAuditors();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Denetçi Yönetimi - Belgelendirme</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .auditor-container {
            max-width: 1400px;
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
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-description {
            color: #6c757d;
            font-size: 16px;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
        }
        
        .section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3436;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-content {
            padding: 24px;
        }
        
        .search-container {
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 90%;
            padding: 12px 16px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 14px;
            background: #f8f9fa;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #6c5ce7;
            box-shadow: 0 0 0 2px rgba(108, 92, 231, 0.25);
            background: white;
        }
        
        .auditor-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .auditor-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s;
            cursor: pointer;
            display: block;
        }
        
        .auditor-item:hover {
            background: #e9ecef;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .auditor-item.selected {
            background: #e8f4ff;
            border-color: #6c5ce7;
        }
        
        .auditor-item.hidden {
            display: none;
        }
        
        .auditor-name {
            font-size: 16px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 4px;
        }
        
        .auditor-email {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 8px;
        }
        
        .auditor-stats {
            display: flex;
            gap: 16px;
            font-size: 12px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #495057;
        }
        
        .detail-section {
            padding: 24px;
            min-height: 400px;
        }
        
        .detail-tabs {
            display: flex;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .tab-button {
            padding: 12px 24px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #6c757d;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-button.active {
            color: #6c5ce7;
            border-bottom-color: #6c5ce7;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .inspection-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
        }
        
        .inspection-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .inspection-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3436;
        }
        
        .inspection-date {
            font-size: 14px;
            color: #6c757d;
        }
        
        .inspection-company {
            font-size: 14px;
            color: #495057;
            margin-bottom: 4px;
        }
        
        .inspection-type {
            font-size: 13px;
            color: #6c757d;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-assigned {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-in_progress {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .result-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .result-passed {
            background: #d4edda;
            color: #155724;
        }
        
        .result-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .result-conditional {
            background: #fff3cd;
            color: #856404;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .no-results {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .auditor-container {
                padding: 16px;
            }
            
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .detail-tabs {
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .tab-button {
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="auditor-container">
        <a href="javascript:history.back()" class="back-button">
            ← Geri Dön
        </a>
        
        <div class="page-header">
            <div class="page-title">
                👨‍💼 Denetçi Yönetimi
            </div>
            <div class="page-description">
                Mevcut denetçileri görüntüleyin ve detaylarını inceleyin
            </div>
        </div>
        
        <div class="main-content">
            <div class="section">
                <div class="section-header">
                    <div class="section-title">
                        📋 Mevcut Denetçiler
                    </div>
                </div>
                
                <div class="section-content">
                    <div class="search-container">
                        <input 
                            type="text" 
                            class="search-input" 
                            id="auditorSearch"
                            placeholder="🔍 Denetçi adı ile ara..."
                            autocomplete="off"
                        >
                    </div>
                    
                    <div class="auditor-list">
                        <?php if (empty($auditors)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">👨‍💼</div>
                            <h4>Henüz denetçi bulunmuyor</h4>
                            <p>Sistemde kayıtlı denetçi bulunmamaktadır.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($auditors as $auditor): ?>
                        <?php 
                            $auditorName = '';
                            if (!empty($auditor['first_name']) && !empty($auditor['last_name'])) {
                                $auditorName = $auditor['first_name'] . ' ' . $auditor['last_name'];
                            } else {
                                $auditorName = $auditor['full_name'];
                            }
                            $searchText = strtolower(htmlspecialchars($auditorName));
                        ?>
                        <div class="auditor-item" onclick="selectAuditor(<?php echo $auditor['id']; ?>)" data-auditor-id="<?php echo $auditor['id']; ?>" data-search-text="<?php echo $searchText; ?>">
                            <div class="auditor-name">
                                <?php echo htmlspecialchars($auditorName); ?>
                            </div>
                            <div class="auditor-email">
                                📧 <?php echo htmlspecialchars($auditor['email']); ?>
                            </div>
                            <div class="auditor-stats">
                                <div class="stat-item">
                                    <span>📋</span>
                                    <span><?php echo $auditor['assignment_count']; ?> Toplam</span>
                                </div>
                                <div class="stat-item">
                                    <span>🔄</span>
                                    <span><?php echo $auditor['active_assignments']; ?> Aktif</span>
                                </div>
                                <div class="stat-item">
                                    <span>✅</span>
                                    <span><?php echo $auditor['completed_assignments']; ?> Tamamlandı</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="no-results" id="noResults" style="display: none;">
                            <div style="font-size: 24px; margin-bottom: 8px;">🔍</div>
                            <h4>Arama sonucu bulunamadı</h4>
                            <p>Aradığınız kriterlere uygun denetçi bulunamadı.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="detail-section">
                    <div id="auditorDetails" style="display: none;">
                        <div id="auditorHeader">
                            <h3 style="color: #2d3436; margin-bottom: 8px;" id="selectedAuditorName"></h3>
                            <p style="color: #6c757d; margin-bottom: 20px;" id="selectedAuditorEmail"></p>
                        </div>
                        
                        <div class="detail-tabs">
                            <button class="tab-button active" onclick="switchTab('assigned')">
                                📋 Atanan Tetkikler
                            </button>
                            <button class="tab-button" onclick="switchTab('completed')">
                                ✅ Tamamlanan Tetkikler
                            </button>
                        </div>
                        
                        <div class="tab-content active" id="assignedTab">
                            <div id="assignedInspections"></div>
                        </div>
                        
                        <div class="tab-content" id="completedTab">
                            <div id="completedInspections"></div>
                        </div>
                    </div>
                    
                    <div id="defaultMessage" class="empty-state">
                        <div class="empty-state-icon">👨‍💼</div>
                        <h4>Denetçi Seçin</h4>
                        <p>Detaylarını görmek için bir denetçi seçin.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedAuditorId = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('auditorSearch');
            
            searchInput.addEventListener('input', function() {
                filterAuditors();
            });
        });
        
        function filterAuditors() {
            const searchTerm = document.getElementById('auditorSearch').value.toLowerCase().trim();
            const auditorItems = document.querySelectorAll('.auditor-item');
            const noResults = document.getElementById('noResults');
            let visibleCount = 0;
            
            auditorItems.forEach(item => {
                if (item.id === 'noResults') return;
                
                const searchText = item.getAttribute('data-search-text');
                
                if (searchTerm === '' || searchText.includes(searchTerm)) {
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                }
            });
            
            if (visibleCount === 0 && searchTerm !== '' && auditorItems.length > 1) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }
        
        function selectAuditor(auditorId) {
            if (selectedAuditorId === auditorId) return;
            
            selectedAuditorId = auditorId;
            
            document.querySelectorAll('.auditor-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            document.querySelector(`[data-auditor-id="${auditorId}"]`).classList.add('selected');
            
            loadAuditorDetails(auditorId);
        }
        
        function loadAuditorDetails(auditorId) {
            const formData = new FormData();
            formData.append('ajax', 'get_auditor_details');
            formData.append('auditor_id', auditorId);
            
            document.body.classList.add('loading');
            
            fetch('auditor_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.classList.remove('loading');
                
                if (data.success) {
                    displayAuditorDetails(data);
                } else {
                    alert('Denetçi detayları yüklenemedi: ' + data.message);
                }
            })
            .catch(error => {
                document.body.classList.remove('loading');
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                console.error('Error:', error);
            });
        }
        
        function displayAuditorDetails(data) {
            const auditorDetails = document.getElementById('auditorDetails');
            const defaultMessage = document.getElementById('defaultMessage');
            
            auditorDetails.style.display = 'block';
            defaultMessage.style.display = 'none';
            
            let auditorName = '';
            if (data.auditor.first_name && data.auditor.last_name) {
                auditorName = data.auditor.first_name + ' ' + data.auditor.last_name;
            } else {
                auditorName = data.auditor.full_name;
            }
            
            document.getElementById('selectedAuditorName').textContent = auditorName;
            document.getElementById('selectedAuditorEmail').textContent = 
                '📧 ' + data.auditor.email;
            
            displayAssignedInspections(data.assignments);
            displayCompletedInspections(data.completed);
            
            switchTab('assigned');
        }
        
        function displayAssignedInspections(assignments) {
            const container = document.getElementById('assignedInspections');
            
            if (assignments.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📋</div>
                        <h4>Atanan tetkik bulunmuyor</h4>
                        <p>Bu denetçiye henüz tetkik atanmamış.</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            assignments.forEach(assignment => {
                const statusLabels = {
                    'assigned': 'Atandı',
                    'in_progress': 'Devam Ediyor',
                    'completed': 'Tamamlandı',
                    'cancelled': 'İptal'
                };
                
                html += `
                    <div class="inspection-item">
                        <div class="inspection-header">
                            <div>
                                <div class="inspection-title">${assignment.inspection_title}</div>
                                <div class="inspection-company">🏢 ${assignment.trade_name || assignment.short_name}</div>
                                <div class="inspection-type">${assignment.inspection_type === 'certified' ? '📋 Belgeli Tetkik' : '📄 Belgesiz Tetkik'}</div>
                            </div>
                            <div style="text-align: right;">
                                <div class="inspection-date">📅 ${formatDate(assignment.audit_start_date)}</div>
                                <span class="status-badge status-${assignment.assignment_status}">
                                    ${statusLabels[assignment.assignment_status] || assignment.assignment_status}
                                </span>
                            </div>
                        </div>
                        ${assignment.assignment_notes ? `<div style="margin-top: 8px; font-size: 13px; color: #6c757d;">💬 ${assignment.assignment_notes}</div>` : ''}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function displayCompletedInspections(completed) {
            const container = document.getElementById('completedInspections');
            
            if (completed.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <h4>Tamamlanan tetkik bulunmuyor</h4>
                        <p>Bu denetçi henüz tetkik tamamlamamış.</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            completed.forEach(inspection => {
                const resultLabels = {
                    'passed': 'Başarılı',
                    'failed': 'Başarısız',
                    'conditional': 'Şartlı'
                };
                
                html += `
                    <div class="inspection-item">
                        <div class="inspection-header">
                            <div>
                                <div class="inspection-title">${inspection.inspection_title}</div>
                                <div class="inspection-company">🏢 ${inspection.trade_name || inspection.short_name}</div>
                                <div class="inspection-type">${inspection.inspection_type === 'certified' ? '📋 Belgeli Tetkik' : '📄 Belgesiz Tetkik'}</div>
                            </div>
                            <div style="text-align: right;">
                                <div class="inspection-date">📅 ${formatDate(inspection.inspection_date)}</div>
                                <span class="result-badge result-${inspection.inspection_result}">
                                    ${resultLabels[inspection.inspection_result] || inspection.inspection_result}
                                </span>
                            </div>
                        </div>
                        ${inspection.inspection_notes ? `<div style="margin-top: 8px; font-size: 13px; color: #6c757d;">💬 ${inspection.inspection_notes}</div>` : ''}
                        ${inspection.recommendations ? `<div style="margin-top: 4px; font-size: 13px; color: #495057;">📋 Öneriler: ${inspection.recommendations}</div>` : ''}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            if (tabName === 'assigned') {
                document.getElementById('assignedTab').classList.add('active');
            } else if (tabName === 'completed') {
                document.getElementById('completedTab').classList.add('active');
            }
        }
        
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('tr-TR');
        }
    </script>
</body>
</html>