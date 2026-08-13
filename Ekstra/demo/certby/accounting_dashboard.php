<?php

require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}

// Sadece muhasebeci erişebilir
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

function getAccountingStats() {
    try {
        $pdo = getConnection();
        $stats = [];
        
        // Toplam belge masrafı
        $stmt = $pdo->query("SELECT SUM(belge_masraf + danisman_masraf + egitim_masraf) as total_expense FROM certifications WHERE status = 'active'");
        $stats['total_expense'] = $stmt->fetch()['total_expense'] ?? 0;
        
        // Toplam ödenen
        $stmt = $pdo->query("SELECT SUM(belge_odenen + danisman_odenen + egitim_odenen) as total_paid FROM certifications WHERE status = 'active'");
        $stats['total_paid'] = $stmt->fetch()['total_paid'] ?? 0;
        
        // Toplam kalan
        $stats['total_remaining'] = $stats['total_expense'] - $stats['total_paid'];
        
        // Aktif belgeler
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certifications WHERE status = 'active'");
        $stats['active_certifications'] = $stmt->fetch()['count'];
        
        // Borcu olan belgeler
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
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgelendirme - Muhasebe Paneli</title>
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
            font-size: 1.1em;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }

        .main-content {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-title {
            font-size: 2em;
            margin-bottom: 30px;
            color: #333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
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
            font-size: 1.1em;
            color: #666;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .action-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
        }

        .action-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        .action-card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s ease;
            font-size: 1em;
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .logo img {
                max-height: 40px;
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
                <span>Hoşgeldiniz, <?php echo htmlspecialchars($userData['full_name']); ?></span>
                <span style="background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px;">Muhasebeci</span>
                <a href="?logout=1" class="logout-btn">Çıkış Yap</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <h1 class="page-title">💰 Muhasebe Paneli</h1>

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
                <div class="label">Borcu Olan Belge</div>
            </div>
        </div>

        <div class="actions-grid">
            <div class="action-card">
                <h3>📝 Masraf Yönetimi</h3>
                <p>Belgelere masraf ekleyin, ödemeleri takip edin ve borç durumunu görüntüleyin.</p>
                <button class="action-btn" onclick="window.location.href='expense_management.php'">Masraf Yönet</button>
            </div>

            <div class="action-card">
                <h3>📊 Excel'e Aktar</h3>
                <p>Tüm belge ve masraf bilgilerini Excel formatında indirin.</p>
                <button class="action-btn" onclick="window.location.href='export_expenses.php'">Excel İndir</button>
            </div>

            <div class="action-card">
                <h3>📋 Belge Takibi</h3>
                <p>Tüm belgeleri görüntüleyin ve masraf detaylarına erişin.</p>
                <button class="action-btn" onclick="window.location.href='document_tracking.php'">Belgeleri Görüntüle</button>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.logout-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Çıkış yapmak istediğinizden emin misiniz?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
