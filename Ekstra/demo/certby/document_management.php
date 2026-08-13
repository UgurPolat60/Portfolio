<?php

require_once 'config.php';
requireLogin();

$currentUser = getUserData($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgeleri Yönet - Belgelendirme</title>
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

        .nav-btn.dashboard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .nav-btn.dashboard:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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

        .management-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .option-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .option-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
        }

        .option-card h3 {
            color: #333;
            font-size: 1.5em;
            margin-bottom: 15px;
        }

        .option-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .option-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .option-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .quick-stats {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
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

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .nav-buttons {
                flex-direction: column;
                width: 100%;
            }

            .nav-btn {
                width: 100%;
                justify-content: center;
            }

            .management-options {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <a href="dashboard.php" class="nav-btn dashboard">
                    <span>🏠</span> Ana Sayfa
                </a>
            </div>
        </div>

        <div class="breadcrumb">
            <a href="dashboard.php">Ana Sayfa</a> > <strong>Belgeleri Yönet</strong>
        </div>

        <div class="page-title">
            <h1>📋 Belgeleri Yönet</h1>
        </div>

        <div class="management-options">
            <div class="option-card">
                <div class="option-icon">
                    📄
                </div>
                <h3>Belge Türü Ekle</h3>
                <p>Yeni belge türleri ekleyin, mevcut belge türlerini düzenleyin veya silin. Belge standartlarını, geçerlilik sürelerini ve ara tetkik sayılarını yönetin.</p>
                <a href="document_types.php" class="option-btn">Belge Türlerini Yönet</a>
            </div>

            <div class="option-card">
                <div class="option-icon">
                    ✅
                </div>
                <h3>Belgelendirme</h3>
                <p>Firmalara belge türlerine göre belgelendirme işlemi yapın. Belge numaralarını, geçerlilik sürelerini, danışman bilgilerini ve kapsamları belirleyin.</p>
                <a href="certification.php" class="option-btn">Belgelendirme İşlemleri</a>
            </div>
        </div>

        <div class="quick-stats">
            <h3 style="margin-bottom: 20px; color: #333;">📊 Hızlı İstatistikler</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number" id="totalDocTypes">-</div>
                    <div class="stat-label">Toplam Belge Türü</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="activeCertifications">-</div>
                    <div class="stat-label">Aktif Belgelendirme</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="expiringSoon">-</div>
                    <div class="stat-label">Yakında Sona Erecek</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="totalCompanies">-</div>
                    <div class="stat-label">Belgelendirilen Firma</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
    loadStats();
});

function loadStats() {
    fetch('get_document_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalDocTypes').textContent = data.data.stats.total_doc_types || 0;
                document.getElementById('activeCertifications').textContent = data.data.stats.active_certifications || 0;
                document.getElementById('expiringSoon').textContent = data.data.stats.expiring_soon || 0;
                document.getElementById('totalCompanies').textContent = data.data.stats.total_companies || 0;
            } else {
                console.error('İstatistik hatası:', data.message);
                document.getElementById('totalDocTypes').textContent = '0';
                document.getElementById('activeCertifications').textContent = '0';
                document.getElementById('expiringSoon').textContent = '0';
                document.getElementById('totalCompanies').textContent = '0';
            }
        })
        .catch(error => {
            console.error('İstatistikler yüklenirken hata:', error);
            document.getElementById('totalDocTypes').textContent = '0';
            document.getElementById('activeCertifications').textContent = '0';
            document.getElementById('expiringSoon').textContent = '0';
            document.getElementById('totalCompanies').textContent = '0';
        });
}
    </script>
</body>
</html>