<?php

require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}


if (!isset($userData['role']) || strtolower($userData['role']) !== 'muhasebeci') {
    header('Location: dashboard.php');
    exit();
}


if (isset($_GET['logout'])) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, content, ip_address) VALUES (?, 'logout', 'Muhasebeci çıkış yaptı', ?)");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'Unknown']);
    } catch (Exception $e) {
        error_log("Logout log hatası: " . $e->getMessage());
    }
    
    session_destroy();
    header('Location: index.html');
    exit();
}


$stats = getAccountingStats();


$certifications = getCertificationsWithExpenses();

function getAccountingStats() {
    try {
        $pdo = getConnection();
        $stats = [];
        

        $stmt = $pdo->query("SELECT SUM(belge_masraf + danisman_masraf + egitim_masraf) as total_expense FROM certifications WHERE status = 'active'");
        $stats['total_expense'] = $stmt->fetch()['total_expense'] ?? 0;
        

        $stmt = $pdo->query("SELECT SUM(belge_odenen + danisman_odenen + egitim_odenen) as total_paid FROM certifications WHERE status = 'active'");
        $stats['total_paid'] = $stmt->fetch()['total_paid'] ?? 0;
        

        $stats['total_remaining'] = $stats['total_expense'] - $stats['total_paid'];
        

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certifications WHERE status = 'active'");
        $stats['active_certifications'] = $stmt->fetch()['count'];
        

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certifications WHERE status = 'active' AND (belge_masraf - belge_odenen + danisman_masraf - danisman_odenen + egitim_masraf - egitim_odenen) > 0");
        $stats['debt_count'] = $stmt->fetch()['count'];
        
        return $stats;
    } catch (Exception $e) {
        error_log("İstatistik hatası: " . $e->getMessage());
        return [
            'total_expense' => 0,
            'total_paid' => 0,
            'total_remaining' => 0,
            'active_certifications' => 0,
            'debt_count' => 0
        ];
    }
}

function getCertificationsWithExpenses() {
    try {
        $pdo = getConnection();
        
        $sql = "SELECT 
                    c.id,
                    c.document_number,
                    c.scope,
                    c.issue_date,
                    c.expiry_date,
                    c.belge_masraf,
                    c.belge_odenen,
                    c.danisman_masraf,
                    c.danisman_odenen,
                    c.egitim_masraf,
                    c.egitim_odenen,
                    c.expense_updated_at,
                    comp.short_name as company_name,
                    comp.trade_name,
                    dt.name as document_type,
                    dt.standard
                FROM certifications c
                INNER JOIN companies comp ON c.company_id = comp.id
                INNER JOIN document_types dt ON c.document_type_id = dt.id
                WHERE c.status = 'active'
                ORDER BY c.created_at DESC";
                
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Belge getirme hatası: " . $e->getMessage());
        return [];
    }
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgelendirme - Masraf Yönetimi</title>
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
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h2 {
            font-size: 1.5em;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }

        .main-content {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-title {
            font-size: 2em;
            margin-bottom: 30px;
            color: #333;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        thead th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid #e1e5e9;
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        tbody td {
            padding: 12px 15px;
            white-space: nowrap;
        }

        .expense-section {
            background: #f8f9fa;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }

        .expense-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .expense-item {
            padding: 8px;
            background: white;
            border-radius: 3px;
        }

        .expense-item label {
            display: block;
            font-size: 0.85em;
            color: #666;
            margin-bottom: 3px;
        }

        .expense-item input {
            width: 100%;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
        }

        .expense-item.remaining {
            background: #fff3cd;
        }

        .expense-item.remaining input {
            font-weight: bold;
            color: #f39c12;
            background: transparent;
            border: none;
        }

        .save-btn {
            background: #27ae60;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .save-btn:hover {
            background: #229954;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: none;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card.expense .number {
            color: #e74c3c;
        }

        .stat-card.paid .number {
            color: #27ae60;
        }

        .stat-card.remaining .number {
            color: #f39c12;
        }

        .stat-card.count .number {
            color: #3498db;
        }

        .stat-card .label {
            font-size: 0.9em;
            color: #666;
        }

        @media (max-width: 768px) {
            .expense-row {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
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
            <div class="nav-links">
                <span style="font-size: 1.1em;">Hoşgeldiniz, <?php echo htmlspecialchars($userData['full_name']); ?></span>
                <a href="?logout=1">Çıkış</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div id="alert" class="alert"></div>


        <div class="stats-grid">
            <div class="stat-card expense">
                <div class="number"><?php echo number_format($stats['total_expense'], 2); ?> ₺</div>
                <div class="label">Toplam Masraf</div>
            </div>
            <div class="stat-card paid">
                <div class="number"><?php echo number_format($stats['total_paid'], 2); ?> ₺</div>
                <div class="label">Tahsil Edilen</div>
            </div>
            <div class="stat-card remaining">
                <div class="number"><?php echo number_format($stats['total_remaining'], 2); ?> ₺</div>
                <div class="label">Kalan Borç</div>
            </div>
            <div class="stat-card count">
                <div class="number"><?php echo number_format($stats['active_certifications']); ?></div>
                <div class="label">Aktif Belge</div>
            </div>
            <div class="stat-card count">
                <div class="number"><?php echo number_format($stats['debt_count']); ?></div>
                <div class="label">Borcu Olan</div>
            </div>
        </div>

        <div class="actions-bar">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 Belge ara (firma adı, belge no, standart...)">
            </div>
        </div>

        <div class="table-container">
            <table id="expenseTable">
                <thead>
                    <tr>
                        <th>Firma</th>
                        <th>Belge Türü</th>
                        <th>Belge No</th>
                        <th>Standart</th>
                        <th>Masraf ve Ödeme Bilgileri</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($certifications)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            Henüz belge bulunmuyor.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($certifications as $cert): 
                        $belge_kalan = $cert['belge_masraf'] - $cert['belge_odenen'];
                        $danisman_kalan = $cert['danisman_masraf'] - $cert['danisman_odenen'];
                        $egitim_kalan = $cert['egitim_masraf'] - $cert['egitim_odenen'];
                    ?>
                    <tr data-id="<?php echo $cert['id']; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($cert['company_name']); ?></strong><br>
                            <small style="color: #666;"><?php echo htmlspecialchars($cert['trade_name']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($cert['document_type']); ?></td>
                        <td><?php echo htmlspecialchars($cert['document_number']); ?></td>
                        <td><?php echo htmlspecialchars($cert['standard']); ?></td>
                        <td>
                            <div class="expense-section">

                                <div class="expense-row">
                                    <div class="expense-item">
                                        <label>📄 Belge Masrafı</label>
                                        <input type="number" step="0.01" class="belge-masraf" 
                                               value="<?php echo number_format($cert['belge_masraf'], 2, '.', ''); ?>" 
                                               data-cert-id="<?php echo $cert['id']; ?>">
                                    </div>
                                    <div class="expense-item">
                                        <label>💳 Ödenen</label>
                                        <input type="number" step="0.01" class="belge-odenen" 
                                               value="<?php echo number_format($cert['belge_odenen'], 2, '.', ''); ?>">
                                    </div>
                                    <div class="expense-item remaining">
                                        <label>📊 Kalan</label>
                                        <input type="text" class="belge-kalan" 
                                               value="<?php echo number_format($belge_kalan, 2); ?> ₺" readonly>
                                    </div>
                                </div>


                                <div class="expense-row">
                                    <div class="expense-item">
                                        <label>👨‍💼 Danışman Masrafı</label>
                                        <input type="number" step="0.01" class="danisman-masraf" 
                                               value="<?php echo number_format($cert['danisman_masraf'], 2, '.', ''); ?>">
                                    </div>
                                    <div class="expense-item">
                                        <label>💳 Ödenen</label>
                                        <input type="number" step="0.01" class="danisman-odenen" 
                                               value="<?php echo number_format($cert['danisman_odenen'], 2, '.', ''); ?>">
                                    </div>
                                    <div class="expense-item remaining">
                                        <label>📊 Kalan</label>
                                        <input type="text" class="danisman-kalan" 
                                               value="<?php echo number_format($danisman_kalan, 2); ?> ₺" readonly>
                                    </div>
                                </div>


                                <div class="expense-row">
                                    <div class="expense-item">
                                        <label>🎓 Eğitim Masrafı</label>
                                        <input type="number" step="0.01" class="egitim-masraf" 
                                               value="<?php echo number_format($cert['egitim_masraf'], 2, '.', ''); ?>">
                                    </div>
                                    <div class="expense-item">
                                        <label>💳 Ödenen</label>
                                        <input type="number" step="0.01" class="egitim-odenen" 
                                               value="<?php echo number_format($cert['egitim_odenen'], 2, '.', ''); ?>">
                                    </div>
                                    <div class="expense-item remaining">
                                        <label>📊 Kalan</label>
                                        <input type="text" class="egitim-kalan" 
                                               value="<?php echo number_format($egitim_kalan, 2); ?> ₺" readonly>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <button class="save-btn" onclick="saveExpense(<?php echo $cert['id']; ?>)">
                                💾 Kaydet
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>

        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#expenseTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });


        document.querySelectorAll('input[type="number"]').forEach(input => {

            input.addEventListener('focus', function() {
                const value = parseFloat(this.value);
                if (value === 0 || this.value === '0.00' || this.value === '0') {
                    this.value = '';
                }
            });


            input.addEventListener('blur', function() {
                if (this.value === '' || this.value === null) {
                    this.value = '0.00';
                }
                // Kalan hesapla
                updateRemaining(this);
            });

            input.addEventListener('input', function() {
                updateRemaining(this);
            });
        });

        // Kalan hesaplama fonksiyonu
        function updateRemaining(input) {
            const row = input.closest('tr');
            if (!row) return;

            // Belge kalanı hesapla
            const belgeMasraf = parseFloat(row.querySelector('.belge-masraf').value) || 0;
            const belgeOdenen = parseFloat(row.querySelector('.belge-odenen').value) || 0;
            row.querySelector('.belge-kalan').value = (belgeMasraf - belgeOdenen).toFixed(2) + ' ₺';

            // Danışman kalanı hesapla
            const danismanMasraf = parseFloat(row.querySelector('.danisman-masraf').value) || 0;
            const danismanOdenen = parseFloat(row.querySelector('.danisman-odenen').value) || 0;
            row.querySelector('.danisman-kalan').value = (danismanMasraf - danismanOdenen).toFixed(2) + ' ₺';

            // Eğitim kalanı hesapla
            const egitimMasraf = parseFloat(row.querySelector('.egitim-masraf').value) || 0;
            const egitimOdenen = parseFloat(row.querySelector('.egitim-odenen').value) || 0;
            row.querySelector('.egitim-kalan').value = (egitimMasraf - egitimOdenen).toFixed(2) + ' ₺';
        }

        // Kaydetme fonksiyonu
        async function saveExpense(certId) {
            const row = document.querySelector(`tr[data-id="${certId}"]`);
            if (!row) return;

            const data = {
                certification_id: certId,
                belge_masraf: parseFloat(row.querySelector('.belge-masraf').value) || 0,
                belge_odenen: parseFloat(row.querySelector('.belge-odenen').value) || 0,
                danisman_masraf: parseFloat(row.querySelector('.danisman-masraf').value) || 0,
                danisman_odenen: parseFloat(row.querySelector('.danisman-odenen').value) || 0,
                egitim_masraf: parseFloat(row.querySelector('.egitim-masraf').value) || 0,
                egitim_odenen: parseFloat(row.querySelector('.egitim-odenen').value) || 0
            };

            try {
                const response = await fetch('process_expense.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?php echo getCsrfToken(); ?>'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Masraf bilgileri başarıyla kaydedildi!', 'success');
                } else {
                    showAlert(result.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Bir hata oluştu!', 'error');
            }
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
    </script>
</body>
</html>
