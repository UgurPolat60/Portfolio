<?php

require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}

if (isset($_GET['logout'])) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, content, ip_address) VALUES (?, 'logout', 'Kullanıcı çıkış yaptı', ?)");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'Unknown']);
    } catch (Exception $e) {
        error_log("Logout log hatası: " . $e->getMessage());
    }
    
    session_destroy();
    header('Location: index.html');
    exit();
}

$stats = getStats();

if (isset($userData['role']) && in_array(strtolower($userData['role']), ['auditor','denetci'])) {
    header('Location: auditor_dashboard.php');
    exit();
}

if (isset($userData['role']) && strtolower($userData['role']) === 'muhasebeci') {
    header('Location: expense_management.php');
    exit();
}

function getStats() {
    try {
        $pdo = getConnection();
        $stats = [];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM companies WHERE status = 'active'");
        $stats['companies'] = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certifications WHERE status = 'active'");
        $stats['certifications'] = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM certifications WHERE status = 'active' AND expiry_date > NOW() AND expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)");
        $stats['expiring_soon'] = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM mail_history WHERE DATE(sent_at) = CURDATE() AND status = 'sent'");
        $stats['mails_today'] = $stmt->fetch()['count'];
        
        return $stats;
    } catch (Exception $e) {
        error_log("İstatistik hatası: " . $e->getMessage());
        return [
            'companies' => 0,
            'certifications' => 0,
            'expiring_soon' => 0,
            'mails_today' => 0
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgelendirme - Ana Sayfa</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
            font-size: 3em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-card .label {
            font-size: 1.1em;
            color: #666;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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

        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-top: 50px;
        }

        @media (max-width: 968px) {
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
                <span class="badge"><?php echo $userData['role'] === 'operator' ? 'Operatör' : 'Kullanıcı'; ?></span>
                <a href="?logout=1" class="logout-btn">Çıkış Yap</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php $isOperator = ($userData['role'] === 'operator'); ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['companies']); ?></div>
                <div class="label">Aktif Firma</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['certifications']); ?></div>
                <div class="label">Toplam Belge</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['expiring_soon']); ?></div>
                <div class="label">Süresi Yaklaşan</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['mails_today']); ?></div>
                <div class="label">Bugün Gönderilen Mail</div>
            </div>
        </div>

        <div class="actions-grid">
            <?php if ($isOperator || $userData['role'] === 'user'): ?>
                <div class="action-card">
                    <h3>📋 Belge Takibi</h3>
                    <p>Belgelendirme süreçlerini takip edin. Süresi yaklaşan belgeleri görüntüleyin.</p>
                    <button class="action-btn" onclick="window.location.href='document_tracking.php'">Belgeleri Görüntüle</button>
                </div>
            <?php endif; ?>

            <?php if ($isOperator || $userData['role'] === 'user'): ?>
                <div class="action-card">
                    <h3>🏢 Firma Yönetimi</h3>
                    <p>Firmalarınızı ekleyin, düzenleyin ve yönetin. Firma bilgilerini güncel tutun.</p>
                    <button class="action-btn" onclick="window.location.href='company_management.php'">Firma Yönet</button>
                </div>
            <?php endif; ?>

            <?php if ($isOperator || $userData['role'] === 'user'): ?>
                <div class="action-card">
                    <h3>📊 Raporlar</h3>
                    <p>Detaylı raporlar oluşturun ve sistem istatistiklerini inceleyin.</p>
                    <button class="action-btn" onclick="window.location.href='reports.php'">Raporları Görüntüle</button>
                </div>
            <?php endif; ?>

            <?php if ($isOperator || $userData['role'] === 'user'): ?>
                <div class="action-card">
                    <h3>📝 Sistem Logları</h3>
                    <p>Sistem aktivitelerini takip edin ve güvenlik loglarını inceleyin.</p>
                    <button class="action-btn" onclick="window.location.href='logs.php'">Logları Görüntüle</button>
                </div>
            <?php endif; ?>

            <?php if ($userData['role'] === 'operator'): ?>
                <div class="action-card">
                    <h3>⚙️ Sistem Ayarları</h3>
                    <p>Sistem ayarlarını yapılandırın ve kullanıcı yönetimi yapın.</p>
                    <button class="action-btn" onclick="window.location.href='system_settings.php'">Ayarlar</button>
                </div>
            <?php endif; ?>

            <?php if ($isOperator || $userData['role'] === 'user'): ?>
                <div class="action-card">
                    <h3>📅 Planlamalar</h3>
                    <p>Denetim planlarını oluşturun, düzenleyin ve takip edin.</p>
                    <button class="action-btn" onclick="window.location.href='planning.php'">Planları Yönet</button>
                </div>
            <?php endif; ?>

            <?php if ($userData['role'] === 'user'): ?>
                <div class="action-card">
                    <h3>📃 Belge Yönetimi</h3>
                    <p>Belge türlerini yönetin ve belgelendirme işlemlerine erişin.</p>
                    <button class="action-btn" onclick="window.location.href='document_management.php'">Belge Yönet</button>
                </div>
            <?php endif; ?>

            <?php if ($isOperator): ?>
                <div class="action-card">
                    <h3>👨‍💼 Denetçileri Yönet</h3>
                    <p>Mevcut denetçi bilgilerini görüntüleyin ve yönetin</p>
                    <button class="action-btn" onclick="window.location.href='auditor_management.php'">Denetçiler</button>
                </div>
            <?php endif; ?>

            <?php if ($isOperator): ?>
                <div class="action-card">
                    <h3>🤝 Danışman Yönetimi</h3>
                    <p>Danışman bilgilerini yönetin ve atama işlemlerini gerçekleştirin.</p>
                    <button class="action-btn" onclick="window.location.href='consultant_management.php'">Danışmanlar</button>
                </div>
            <?php endif; ?>

            <?php if ($isOperator): ?>
                <div class="action-card">
                    <h3>📃 Belgeleri Yönet</h3>
                    <p>Belge türü ekleyin ve belgelerinizi düzenli bir şekilde biçimlendirin</p>
                    <button class="action-btn" onclick="window.location.href='document_management.php'">Belge Oluştur</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    

    <script>
        window.addEventListener('load', function() {
            console.log('Belgelendirme Sistemi başarıyla yüklendi');
        });
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