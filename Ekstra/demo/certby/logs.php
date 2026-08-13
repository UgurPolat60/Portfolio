<?php
require_once 'config.php';
require_once 'MonologConfig.php';

requireLogin();

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'system';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$search_email = isset($_GET['search_email']) ? trim($_GET['search_email']) : '';
$log_type_filter = isset($_GET['log_type']) ? trim($_GET['log_type']) : '';

$limit = 50;
$offset = ($page - 1) * $limit;

try {
    $pdo = getConnection();
    
    if ($tab === 'system') {
        $whereClause = "WHERE l.log_type NOT IN ('logs_page_accessed', 'mail_sent', 'mail_failed', 'mail_template_added', 'mail_template_deleted', 'mail_template_updated', 'email_sent', 'email_failed', 'mail_log', 'email_log', 'mail', 'email', 'template_mail', 'mail_queue', 'mail_delivery', 'smtp_log') AND l.log_type NOT LIKE '%mail%' AND l.log_type NOT LIKE '%email%' AND l.log_type NOT LIKE '%template%' AND l.content NOT LIKE '%mail%' AND l.content NOT LIKE '%email%' AND l.content NOT LIKE '%şablon%' AND l.content NOT LIKE '%template%'";
        $params = [];
        
        if (!empty($date_from)) {
            $whereClause .= " AND DATE(l.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $whereClause .= " AND DATE(l.created_at) <= ?";
            $params[] = $date_to;
        }
        
        if (!empty($search_name)) {
            $whereClause .= " AND u.full_name LIKE ?";
            $params[] = '%' . $search_name . '%';
        }
        
        if (!empty($log_type_filter)) {
            $whereClause .= " AND l.log_type = ?";
            $params[] = $log_type_filter;
        }
        
        $countSql = "SELECT COUNT(*) FROM system_logs l LEFT JOIN users u ON l.user_id = u.id $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        $sql = "SELECT 
                    l.id,
                    u.full_name AS user_name,
                    u.role AS user_role,
                    l.log_type,
                    l.content,
                    l.context,
                    l.created_at
                FROM system_logs l
                LEFT JOIN users u ON l.user_id = u.id
                $whereClause
                ORDER BY l.created_at DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
    } else { 
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($date_from)) {
            $whereClause .= " AND DATE(m.sent_at) >= ?";
            $params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $whereClause .= " AND DATE(m.sent_at) <= ?";
            $params[] = $date_to;
        }
        
        if (!empty($search_email)) {
            $whereClause .= " AND (c.short_name LIKE ? OR m.recipient_email LIKE ?)";
            $params[] = '%' . $search_email . '%';
            $params[] = '%' . $search_email . '%';
        }
        
        $countSql = "SELECT COUNT(*) FROM mail_history m LEFT JOIN companies c ON m.company_id = c.id $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        $sql = "SELECT 
                    m.id,
                    m.recipient_email,
                    COALESCE(c.short_name, 'Bilinmiyor') as company_name,
                    m.subject,
                    m.content,
                    m.sent_at,
                    m.status,
                    m.error_message
                FROM mail_history m
                LEFT JOIN companies c ON m.company_id = c.id
                $whereClause
                ORDER BY m.sent_at DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
    }
    
    $totalPages = ceil($totalRecords / $limit);
    
} catch (Exception $e) {
    logSystemEvent('Error loading logs', 'error', ['error' => $e->getMessage()]);
    $data = [];
    $totalRecords = 0;
    $totalPages = 0;
    $error_message = "Log kayıtları yüklenirken bir hata oluştu.";
}

function buildUrl($newParams = []) {
    $params = array_merge($_GET, $newParams);
    return 'logs.php?' . http_build_query($params);
}


function formatLogContent($content, $context = '') {
    if (stripos($content, 'firma') !== false || stripos($content, 'company') !== false) {
        if (stripos($content, 'eklendi') !== false || stripos($content, 'added') !== false) {
            $contextData = json_decode($context, true);
            if ($contextData && isset($contextData['company_name'])) {
                return "Firma Eklendi: " . $contextData['company_name'];
            } elseif ($contextData && isset($contextData['short_name'])) {
                return "Firma Eklendi: " . $contextData['short_name'];
            } else {
                return "Firma Eklendi";
            }
        } elseif (stripos($content, 'silindi') !== false || stripos($content, 'deleted') !== false) {
            $contextData = json_decode($context, true);
            if ($contextData && isset($contextData['company_name'])) {
                return "Firma Silindi: " . $contextData['company_name'];
            } elseif ($contextData && isset($contextData['short_name'])) {
                return "Firma Silindi: " . $contextData['short_name'];
            } else {
                return "Firma Silindi";
            }
        }
    }
    
    return $content;
}

function getLogTypeIcon($logType) {
    switch($logType) {
        case 'login':
            return '🔐';
        case 'logout':
            return '🚪';
        case 'company_added':
            return '🏢';
        case 'company_deleted':
            return '🗑️';
        default:
            return '📝';
    }
}

function getStatusColor($status) {
    switch(strtolower($status)) {
        case 'sent':
        case 'completed':
            return 'success';
        case 'failed':
        case 'error':
            return 'failed';
        case 'pending':
            return 'warning';
        default:
            return 'default';
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Kayıtları</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo {
            width: 180px;
            height: auto;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 20px;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid transparent;
        }
        
        .back-link:hover {
            background: rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }
        
        h1 {
            color: #333;
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
        }
        
        .tabs {
            display: flex;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 16px;
            padding: 6px;
            margin-bottom: 30px;
            gap: 4px;
        }
        
        .tab {
            flex: 1;
            padding: 16px 24px;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 600;
            color: #6c757d;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
        }
        
        .tab:hover {
            color: #495057;
            background: rgba(255, 255, 255, 0.6);
        }
        
        .tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }
        
        .filters {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 50px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 100;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: 700;
            color: #495057;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .filter-group input {
            padding: 14px 18px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }
        
        .custom-select {
            position: relative;
            display: block;
            width: 100%;
            z-index: 1000;
        }
        
        .custom-select.active {
            z-index: 10000;
        }
        
        .select-selected {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 14px 18px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            position: relative;
            transition: all 0.3s ease;
            user-select: none;
            z-index: 1001;
        }
        
        .select-selected:hover {
            border-color: #667eea;
            background: white;
        }
        
        .select-selected.select-arrow-active {
            border-color: #667eea;
            background: white;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 -2px 8px rgba(102, 126, 234, 0.1);
        }
        
        .select-selected:after {
            position: absolute;
            content: "";
            top: 50%;
            right: 18px;
            width: 0;
            height: 0;
            border: 7px solid transparent;
            border-color: #999 transparent transparent transparent;
            transform: translateY(-50%);
            transition: all 0.3s ease;
        }
        
        .select-selected.select-arrow-active:after {
            border-color: transparent transparent #667eea transparent;
            top: 40%;
        }
        
        .select-items {
            position: absolute;
            background: white;
            top: calc(100% - 2px);
            left: 0;
            right: 0;
            z-index: 10001;
            border: 2px solid #667eea;
            border-top: none;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            max-height: 300px;
            overflow-y: auto;
            backdrop-filter: blur(10px);
            margin-top: -1px;
        }
        
        .select-hide {
            display: none;
        }
        
        .select-items div {
            color: #333;
            padding: 12px 18px;
            border-bottom: 1px solid #f8f9fa;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .select-items div:hover, .same-as-selected {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            color: #667eea;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 18px;
            border: none;
            border-bottom: 2px solid #e9ecef;
            outline: none;
            font-size: 15px;
            font-weight: 500;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-bottom-color: #667eea;
            background: white;
        }
        
        .content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }
        
        .content-header {
            padding: 30px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-bottom: 2px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .record-count {
            color: #495057;
            font-size: 16px;
            font-weight: 600;
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 70vh;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 18px 24px;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        th {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            font-weight: 800;
            color: #495057;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 12px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tbody tr {
            transition: all 0.2s ease;
        }
        
        tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            transform: scale(1.002);
        }
        
        .log-type {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        .status {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .status.success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .status.failed {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            color: white;
        }
        
        .status.warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #333;
        }
        
        .status.default {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .role {
            font-weight: 800;
            text-transform: uppercase;
            font-size: 12px;
            color: #667eea;
            letter-spacing: 1px;
            padding: 4px 12px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 20px;
            border: 2px solid rgba(102, 126, 234, 0.2);
        }
        
        .content-cell {
            max-width: 300px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.5;
        }
        
        .email-cell {
            max-width: 200px;
            word-wrap: break-word;
            font-weight: 600;
            color: #667eea;
        }
        
        .subject-cell {
            max-width: 250px;
            word-wrap: break-word;
            font-weight: 600;
        }
        
        .user-cell {
            font-weight: 600;
            color: #495057;
        }
        
        .date-cell {
            font-weight: 600;
            color: #6c757d;
            white-space: nowrap;
        }
        
        .no-data {
            text-align: center;
            padding: 80px;
            color: #6c757d;
            font-style: italic;
            font-size: 18px;
            font-weight: 500;
        }
        
        .pagination {
            padding: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            border-top: 2px solid rgba(0,0,0,0.05);
        }
        
        .pagination a, .pagination span {
            padding: 12px 18px;
            border: 2px solid transparent;
            text-decoration: none;
            color: #6c757d;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 600;
            min-width: 45px;
            text-align: center;
        }
        
        .pagination a:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            transform: translateY(-2px);
            border-color: rgba(102, 126, 234, 0.3);
        }
        
        .pagination .current {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .pagination .disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .error-message {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            color: white;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 30px;
            border-left: 6px solid #c82333;
            font-weight: 600;
            font-size: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 1200px) {
            .container {
                padding: 15px;
            }
            
            .filter-row {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 20px;
            }
            
            .header-top {
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }
            
            .logo-section {
                justify-content: center;
            }
            
            .logo {
                width: 150px;
            }
            
            h1 {
                font-size: 28px;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
                gap: 8px;
            }
            
            .table-container {
                font-size: 14px;
                max-height: 60vh;
            }
            
            th, td {
                padding: 14px 16px;
            }
            
            .content-cell,
            .email-cell,
            .subject-cell {
                max-width: 150px;
            }
            
            .content-header {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .pagination {
                padding: 20px;
                flex-wrap: wrap;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <div class="logo-section">
                    <h2 class="logo" style="margin:0;">Belgelendirme</h2>
                </div>
                <a href="dashboard.php" class="back-link">← Ana Ekrana Dön</a>
            </div>
            <h1>Log Kayıtları</h1>
            
            <div class="tabs">
                <a href="<?= buildUrl(['tab' => 'system', 'page' => 1]) ?>" class="tab <?= $tab === 'system' ? 'active' : '' ?>">
                    🔐 Sistem Logları
                </a>
                <a href="<?= buildUrl(['tab' => 'mail', 'page' => 1]) ?>" class="tab <?= $tab === 'mail' ? 'active' : '' ?>">
                    📧 E-posta Geçmişi
                </a>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="date_from">Başlangıç Tarihi</label>
                    <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>" onchange="filterLogs()">
                </div>
                <div class="filter-group">
                    <label for="date_to">Bitiş Tarihi</label>
                    <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>" onchange="filterLogs()">
                </div>
                <?php if ($tab === 'system'): ?>
                    <div class="filter-group">
                        <label for="search_name">Kullanıcı</label>
                        <div class="custom-select" id="user-select">
                            <div class="select-selected"><?= !empty($search_name) ? htmlspecialchars($search_name) : 'Kullanıcı seçin...' ?></div>
                            <div class="select-items select-hide">
                                <input type="text" class="search-input" placeholder="Kullanıcı ara..." onkeyup="filterUsers(this.value)">
                                <div onclick="selectUser('')">Tümü</div>
                                <?php
                                try {
                                    $usersStmt = $pdo->prepare("SELECT DISTINCT u.full_name FROM system_logs l LEFT JOIN users u ON l.user_id = u.id WHERE u.full_name IS NOT NULL AND l.log_type NOT IN ('logs_page_accessed', 'mail_sent', 'mail_failed', 'mail_template_added', 'mail_template_deleted', 'mail_template_updated', 'email_sent', 'email_failed', 'mail_log', 'email_log', 'mail', 'email', 'template_mail', 'mail_queue', 'mail_delivery', 'smtp_log') AND l.log_type NOT LIKE '%mail%' AND l.log_type NOT LIKE '%email%' AND l.log_type NOT LIKE '%template%' AND l.content NOT LIKE '%mail%' AND l.content NOT LIKE '%email%' AND l.content NOT LIKE '%şablon%' AND l.content NOT LIKE '%template%' ORDER BY u.full_name");
                                    $usersStmt->execute();
                                    $users = $usersStmt->fetchAll();
                                    foreach($users as $user) {
                                        $selected = ($search_name === $user['full_name']) ? 'same-as-selected' : '';
                                        echo '<div class="user-option ' . $selected . '" onclick="selectUser(\'' . htmlspecialchars($user['full_name']) . '\')">' . htmlspecialchars($user['full_name']) . '</div>';
                                    }
                                } catch(Exception $e) {}
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label for="log_type">İşlem Türü</label>
                        <div class="custom-select" id="logtype-select">
                            <div class="select-selected"><?= !empty($log_type_filter) ? htmlspecialchars($log_type_filter) : 'İşlem türü seçin...' ?></div>
                            <div class="select-items select-hide">
                                <div onclick="selectLogType('')">Tümü</div>
                                <?php
                                try {
                                    $logTypesStmt = $pdo->prepare("SELECT DISTINCT l.log_type FROM system_logs l WHERE l.log_type NOT IN ('logs_page_accessed', 'mail_sent', 'mail_failed', 'mail_template_added', 'mail_template_deleted', 'mail_template_updated', 'email_sent', 'email_failed', 'mail_log', 'email_log', 'mail', 'email', 'template_mail', 'mail_queue', 'mail_delivery', 'smtp_log') AND l.log_type NOT LIKE '%mail%' AND l.log_type NOT LIKE '%email%' AND l.log_type NOT LIKE '%template%' ORDER BY l.log_type");
                                    $logTypesStmt->execute();
                                    $logTypes = $logTypesStmt->fetchAll();
                                    foreach($logTypes as $logTypeRow) {
                                        $logType = $logTypeRow['log_type'];
                                        $selected = ($log_type_filter === $logType) ? 'same-as-selected' : '';
                                        $icon = getLogTypeIcon($logType);
                                        echo '<div class="logtype-option ' . $selected . '" onclick="selectLogType(\'' . htmlspecialchars($logType) . '\')">' . $icon . ' ' . htmlspecialchars($logType) . '</div>';
                                    }
                                } catch(Exception $e) {
                                    echo '<div onclick="selectLogType(\'login\')">🔐 login</div>';
                                    echo '<div onclick="selectLogType(\'logout\')">🚪 logout</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="filter-group">
                        <label for="search_email">Firma</label>
                        <div class="custom-select" id="company-select">
                            <div class="select-selected"><?= !empty($search_email) ? htmlspecialchars($search_email) : 'Firma seçin...' ?></div>
                            <div class="select-items select-hide">
                                <input type="text" class="search-input" placeholder="Firma ara..." onkeyup="filterCompanies(this.value)">
                                <div onclick="selectCompany('')">Tümü</div>
                                <?php
                                try {
                                    $companiesStmt = $pdo->prepare("SELECT DISTINCT short_name FROM companies WHERE short_name IS NOT NULL ORDER BY short_name");
                                    $companiesStmt->execute();
                                    $companies = $companiesStmt->fetchAll();
                                    foreach($companies as $company) {
                                        $selected = ($search_email === $company['short_name']) ? 'same-as-selected' : '';
                                        echo '<div class="company-option ' . $selected . '" onclick="selectCompany(\'' . htmlspecialchars($company['short_name']) . '\')">' . htmlspecialchars($company['short_name']) . '</div>';
                                    }
                                } catch(Exception $e) {}
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                ⚠️ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <div class="content">
            <div class="content-header">
                <div class="record-count">
                    📊 <strong>Toplam:</strong> <?= number_format($totalRecords) ?> kayıt
                    <?php if ($totalPages > 1): ?>
                        | <strong>Sayfa:</strong> <?= $page ?> / <?= $totalPages ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <?php if ($tab === 'system'): ?>
                                <th>ID</th>
                                <th>Kullanıcı</th>
                                <th>Yetki</th>
                                <th>İşlem Türü</th>
                                <th>Açıklama</th>
                                <th>Tarih</th>
                            <?php else: ?>
                                <th>ID</th>
                                <th>Firma</th>
                                <th>Alıcı E-posta</th>
                                <th>Konu</th>
                                <th>İçerik Özeti</th>
                                <th>Gönderim Tarihi</th>
                                <th>Durum</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data)): ?>
                            <?php foreach($data as $row): ?>
                                <tr>
                                    <?php if ($tab === 'system'): ?>
                                        <td><strong>#<?= htmlspecialchars($row['id']) ?></strong></td>
                                        <td class="user-cell"><?= htmlspecialchars($row['user_name'] ?? 'Sistem') ?></td>
                                        <td>
                                            <?php if ($row['user_role']): ?>
                                                <span class="role"><?= htmlspecialchars($row['user_role']) ?></span>
                                            <?php else: ?>
                                                <span class="role" style="opacity: 0.5;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="log-type">
                                                <?= getLogTypeIcon($row['log_type']) ?> <?= htmlspecialchars($row['log_type']) ?>
                                            </span>
                                        </td>
                                        <td class="content-cell"><?= htmlspecialchars(formatLogContent($row['content'], $row['context'])) ?></td>
                                        <td class="date-cell"><?= date('d.m.Y H:i', strtotime($row['created_at'])) ?></td>
                                    <?php else: ?>
                                        <td><strong>#<?= htmlspecialchars($row['id']) ?></strong></td>
                                        <td class="user-cell"><?= htmlspecialchars($row['company_name'] ?? 'Bilinmiyor') ?></td>
                                        <td class="email-cell"><?= htmlspecialchars($row['recipient_email']) ?></td>
                                        <td class="subject-cell"><?= htmlspecialchars($row['subject']) ?></td>
                                        <td class="content-cell"><?= htmlspecialchars(substr($row['content'], 0, 120)) ?><?= strlen($row['content']) > 120 ? '...' : '' ?></td>
                                        <td class="date-cell"><?= date('d.m.Y H:i', strtotime($row['sent_at'])) ?></td>
                                        <td>
                                            <span class="status <?= getStatusColor($row['status']) ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= ($tab === 'mail') ? '7' : '6' ?>" class="no-data">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">🔍</div>
                                        <div>
                                            <?php if (empty($date_from) && empty($date_to) && empty($search_name) && empty($search_email)): ?>
                                                Henüz kayıt bulunamadı.
                                            <?php else: ?>
                                                Seçilen filtrelere uygun kayıt bulunamadı.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= buildUrl(['page' => 1]) ?>">&laquo; İlk</a>
                        <a href="<?= buildUrl(['page' => $page - 1]) ?>">&lsaquo; Önceki</a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <a href="<?= buildUrl(['page' => 1]) ?>">1</a>
                        <?php if ($startPage > 2): ?>
                            <span>...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= buildUrl(['page' => $i]) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span>...</span>
                        <?php endif; ?>
                        <a href="<?= buildUrl(['page' => $totalPages]) ?>"><?= $totalPages ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= buildUrl(['page' => $page + 1]) ?>">Sonraki &rsaquo;</a>
                        <a href="<?= buildUrl(['page' => $totalPages]) ?>">Son &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        
        document.addEventListener('DOMContentLoaded', function() {
            const userSelect = document.getElementById('user-select');
            if (userSelect) {
                userSelect.querySelector('.select-selected').addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleDropdown('user-select');
                });
            }
            
            
            const companySelect = document.getElementById('company-select');
            if (companySelect) {
                companySelect.querySelector('.select-selected').addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleDropdown('company-select');
                });
            }
            

            const logTypeSelect = document.getElementById('logtype-select');
            if (logTypeSelect) {
                logTypeSelect.querySelector('.select-selected').addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleDropdown('logtype-select');
                });
            }
            
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.custom-select')) {
                    closeAllDropdowns();
                }
            });
            
            
            document.querySelectorAll('.select-items').forEach(items => {
                items.addEventListener('click', function(e) {

                    if (!e.target.classList.contains('search-input')) {
                        e.stopPropagation();
                    }
                });
            });
        });
        
        function toggleDropdown(selectId) {
            const currentSelect = document.getElementById(selectId);
            const isCurrentlyOpen = !currentSelect.querySelector('.select-items').classList.contains('select-hide');
            
            
            closeAllDropdowns();
            
            
            if (!isCurrentlyOpen) {
                const select = document.getElementById(selectId);
                const items = select.querySelector('.select-items');
                const selected = select.querySelector('.select-selected');
                
                select.classList.add('active');
                items.classList.remove('select-hide');
                selected.classList.add('select-arrow-active');
                
                const searchInput = items.querySelector('.search-input');
                if (searchInput) {
                    setTimeout(() => {
                        searchInput.focus();
                        searchInput.select();
                    }, 100);
                }
            }
        }
        
        function closeAllDropdowns() {
            document.querySelectorAll('.custom-select').forEach(select => {
                select.classList.remove('active');
            });
            document.querySelectorAll('.select-items').forEach(item => {
                item.classList.add('select-hide');
            });
            document.querySelectorAll('.select-selected').forEach(selected => {
                selected.classList.remove('select-arrow-active');
            });
        }
        
        function selectUser(userName) {
            const userSelect = document.getElementById('user-select');
            const selected = userSelect.querySelector('.select-selected');
            selected.textContent = userName || 'Kullanıcı seçin...';
            closeAllDropdowns();
            
            const url = new URL(window.location);
            if (userName) {
                url.searchParams.set('search_name', userName);
            } else {
                url.searchParams.delete('search_name');
            }
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
        
        function selectCompany(companyName) {
            const companySelect = document.getElementById('company-select');
            const selected = companySelect.querySelector('.select-selected');
            selected.textContent = companyName || 'Firma seçin...';
            closeAllDropdowns();
            
            const url = new URL(window.location);
            if (companyName) {
                url.searchParams.set('search_email', companyName);
            } else {
                url.searchParams.delete('search_email');
            }
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
        
        function selectLogType(logType) {
            const logTypeSelect = document.getElementById('logtype-select');
            if (logTypeSelect) {
                const selected = logTypeSelect.querySelector('.select-selected');
                if (logType) {
                    const selectedOption = document.querySelector('.logtype-option.same-as-selected') || 
                                         document.querySelector(`[onclick="selectLogType('${logType}')"]`);
                    if (selectedOption) {
                        selected.innerHTML = selectedOption.innerHTML;
                    } else {
                        selected.textContent = logType;
                    }
                } else {
                    selected.textContent = 'İşlem türü seçin...';
                }
                closeAllDropdowns();
                
                const url = new URL(window.location);
                if (logType) {
                    url.searchParams.set('log_type', logType);
                } else {
                    url.searchParams.delete('log_type');
                }
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            }
        }
        
        function filterUsers(searchText) {
            const userOptions = document.querySelectorAll('.user-option');
            userOptions.forEach(option => {
                const text = option.textContent.toLowerCase();
                if (text.includes(searchText.toLowerCase())) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        }
        
        function filterCompanies(searchText) {
            const companyOptions = document.querySelectorAll('.company-option');
            companyOptions.forEach(option => {
                const text = option.textContent.toLowerCase();
                if (text.includes(searchText.toLowerCase())) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        }
        
        function filterLogs() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            
            const url = new URL(window.location);
            if (dateFrom) {
                url.searchParams.set('date_from', dateFrom);
            } else {
                url.searchParams.delete('date_from');
            }
            if (dateTo) {
                url.searchParams.set('date_to', dateTo);
            } else {
                url.searchParams.delete('date_to');
            }
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });
        
        let dropdownTimeout;
        function resetDropdownTimeout() {
            clearTimeout(dropdownTimeout);
            dropdownTimeout = setTimeout(() => {
                closeAllDropdowns();
            }, 30000);
        }
        
        document.querySelectorAll('.custom-select').forEach(select => {
            select.addEventListener('mouseenter', resetDropdownTimeout);
            select.addEventListener('mouseleave', resetDropdownTimeout);
        });
    </script>
</body>
</html>