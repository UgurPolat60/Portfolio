<?php

require_once 'config.php';

requireLogin();
if (($_SESSION['role'] ?? '') !== 'operator') {
    die('Bu sayfaya erişim yetkiniz yok.');
}
 
$max_file_uploads = ini_get('max_file_uploads') ?: 20;

$stay_in_edit_mode = false;
$current_edit_id = null;

if ($_POST) {
    requireCsrfOnPost();
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $pdo = getConnection();
                    $stmt = $pdo->prepare("INSERT INTO mail_templates (name, subject, content) VALUES (?, ?, ?)");
                    $stmt->execute([$_POST['template_name'], $_POST['subject'], $_POST['content']]);
                    $template_id = $pdo->lastInsertId();
                    $uploaded_files = [];
                    
                    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                        $file_count = count($_FILES['attachments']['name']);
                        if ($file_count > $max_file_uploads) {
                            throw new Exception("Aynı anda en fazla " . $max_file_uploads . " dosya yükleyebilirsiniz.");
                        }
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                                $original_filename = $_FILES['attachments']['name'][$i];
                                $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                                $allowed_extensions = ['doc', 'docx', 'pdf', 'txt', 'odt', 'jpg', 'jpeg', 'png', 'gif'];
                                
                                if (!in_array($file_extension, $allowed_extensions)) {
                                    throw new Exception("Sadece Word (.doc, .docx), PDF, metin (.txt), OpenDocument (.odt) ve resim dosyaları yüklenebilir.");
                                }

                                if ($_FILES['attachments']['size'][$i] > 10 * 1024 * 1024) {
                                    throw new Exception("Dosya boyutu 10MB'dan büyük olamaz: " . $original_filename);
                                }
                                
                                if (preg_match('/[<>:"|?*]/', $original_filename)) {
                                    throw new Exception("Dosya adında geçersiz karakterler var: " . $original_filename);
                                }
                                 
                                $file_content = file_get_contents($_FILES['attachments']['tmp_name'][$i]);
                                if ($file_content === false) {
                                    throw new Exception("Dosya okunamadı: " . $original_filename);
                                }
                                 
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $mime_type = finfo_file($finfo, $_FILES['attachments']['tmp_name'][$i]);
                                finfo_close($finfo);
                                
                                $allowed_mimes = [
                                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/pdf', 'text/plain', 'application/vnd.oasis.opendocument.text',
                                    'image/jpeg', 'image/png', 'image/gif'
                                ];
                                
                                if (!in_array($mime_type, $allowed_mimes)) {
                                    throw new Exception("Dosya türü güvenli değil: " . $original_filename);
                                }
                                 
                                $stmt = $pdo->prepare("INSERT INTO mail_template_attachments (template_id, original_filename, file_size, mime_type, file_content) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$template_id, $original_filename, $_FILES['attachments']['size'][$i], $mime_type, $file_content]);
                                
                                $uploaded_files[] = $original_filename;
                            }
                        }
                    }
                    
                    $success_message = "Mail şablonu başarıyla eklendi.";
                    if (!empty($uploaded_files)) {
                        $success_message .= " Yüklenen dosyalar: " . implode(', ', $uploaded_files);
                    }
                    
                    logActivity($_SESSION['user_id'], 'data_change', 'Yeni mail şablonu eklendi: ' . $_POST['template_name'] . (!empty($uploaded_files) ? ' (' . count($uploaded_files) . ' dosya)' : ''));
                } catch(Exception $e) {
                    if (isset($template_id)) {
                        $pdo->prepare("DELETE FROM mail_template_attachments WHERE template_id = ?")->execute([$template_id]);
                        $pdo->prepare("DELETE FROM mail_templates WHERE id = ?")->execute([$template_id]);
                    }
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'update_template_info':
                try {
                    $pdo = getConnection();
                    $template_id = $_POST['template_id'];
                    $current_edit_id = $template_id;
                    $stay_in_edit_mode = true;
                    
                    $stmt = $pdo->prepare("SELECT id FROM mail_templates WHERE id = ?");
                    $stmt->execute([$template_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception("Güncellenecek şablon bulunamadı.");
                    }
                    
                    $stmt = $pdo->prepare("UPDATE mail_templates SET name = ?, subject = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $result = $stmt->execute([$_POST['template_name'], $_POST['subject'], $_POST['content'], $template_id]);
                    
                    if (!$result) {
                        throw new Exception("Şablon bilgileri güncellenirken hata oluştu.");
                    }
                    
                    $success_message = "Şablon bilgileri başarıyla güncellendi: " . htmlspecialchars($_POST['template_name']);
                    logActivity($_SESSION['user_id'], 'data_change', 'Mail şablonu bilgileri güncellendi: ' . $_POST['template_name']);
                    
                } catch(Exception $e) {
                    $error_message = "Güncelleme Hatası: " . $e->getMessage();
                    $stay_in_edit_mode = true;
                    $current_edit_id = $_POST['template_id'];
                }
                break;
                
            case 'add_attachments':
                try {
                    $pdo = getConnection();
                    $template_id = $_POST['template_id'];
                    $current_edit_id = $template_id;
                    $stay_in_edit_mode = true;
                    
                    $stmt = $pdo->prepare("SELECT name FROM mail_templates WHERE id = ?");
                    $stmt->execute([$template_id]);
                    $template = $stmt->fetch();
                    if (!$template) {
                        throw new Exception("Şablon bulunamadı.");
                    }
                    
                    $uploaded_files = [];
                    
                    if (isset($_FILES['new_attachments']) && !empty($_FILES['new_attachments']['name'][0])) {
                        $file_count = count($_FILES['new_attachments']['name']);
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_template_attachments WHERE template_id = ?");
                        $stmt->execute([$template_id]);
                        $existing_count = $stmt->fetchColumn();
                        
                        if (($existing_count + $file_count) > $max_file_uploads) {
                            throw new Exception("Toplam dosya sayısı " . $max_file_uploads . " adetini geçemez. Mevcut: " . $existing_count . ", Eklenecek: " . $file_count);
                        }
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['new_attachments']['error'][$i] === UPLOAD_ERR_OK) {
                                $original_filename = $_FILES['new_attachments']['name'][$i];
                                $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                                $allowed_extensions = ['doc', 'docx', 'pdf', 'txt', 'odt', 'jpg', 'jpeg', 'png', 'gif'];
                                
                                if (!in_array($file_extension, $allowed_extensions)) {
                                    throw new Exception("Sadece Word (.doc, .docx), PDF, metin (.txt), OpenDocument (.odt) ve resim dosyaları yüklenebilir.");
                                }

                                if ($_FILES['new_attachments']['size'][$i] > 10 * 1024 * 1024) {
                                    throw new Exception("Dosya boyutu 10MB'dan büyük olamaz: " . $original_filename);
                                }
                                
                                if (preg_match('/[<>:"|?*]/', $original_filename)) {
                                    throw new Exception("Dosya adında geçersiz karakterler var: " . $original_filename);
                                }
                                 
                                $file_content = file_get_contents($_FILES['new_attachments']['tmp_name'][$i]);
                                if ($file_content === false) {
                                    throw new Exception("Dosya okunamadı: " . $original_filename);
                                }
                                 
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $mime_type = finfo_file($finfo, $_FILES['new_attachments']['tmp_name'][$i]);
                                finfo_close($finfo);
                                
                                $allowed_mimes = [
                                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/pdf', 'text/plain', 'application/vnd.oasis.opendocument.text',
                                    'image/jpeg', 'image/png', 'image/gif'
                                ];
                                
                                if (!in_array($mime_type, $allowed_mimes)) {
                                    throw new Exception("Dosya türü güvenli değil: " . $original_filename);
                                }
                                 
                                $stmt = $pdo->prepare("INSERT INTO mail_template_attachments (template_id, original_filename, file_size, mime_type, file_content) VALUES (?, ?, ?, ?, ?)");
                                $result = $stmt->execute([$template_id, $original_filename, $_FILES['new_attachments']['size'][$i], $mime_type, $file_content]);
                                
                                if (!$result) {
                                    throw new Exception("Ek dosya kaydedilemedi: " . $original_filename);
                                }
                                
                                $uploaded_files[] = $original_filename;
                            }
                        }
                        
                        if (!empty($uploaded_files)) {
                            $success_message = count($uploaded_files) . " yeni dosya başarıyla eklendi: " . implode(', ', $uploaded_files);
                            logActivity($_SESSION['user_id'], 'data_change', 'Mail şablonuna yeni dosyalar eklendi: ' . $template['name'] . ' (' . count($uploaded_files) . ' dosya)');
                        } else {
                            $error_message = "Yüklenecek dosya seçilmedi.";
                        }
                    } else {
                        $error_message = "Yüklenecek dosya seçilmedi.";
                    }
                    
                } catch(Exception $e) {
                    $error_message = "Dosya yükleme hatası: " . $e->getMessage();
                    $stay_in_edit_mode = true;
                    $current_edit_id = $_POST['template_id'];
                }
                break;
                
            case 'delete':
                try {
                    $pdo = getConnection();
                    
                    $stmt = $pdo->prepare("SELECT name FROM mail_templates WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $template_info = $stmt->fetch(PDO::FETCH_ASSOC);
                     
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    
                    $stmt = $pdo->prepare("UPDATE mail_history SET template_id = NULL WHERE template_id = ?");
                    $stmt->execute([$_POST['id']]);
                    
                    $stmt = $pdo->prepare("DELETE FROM mail_template_attachments WHERE template_id = ?");
                    $stmt->execute([$_POST['id']]);
                    
                    $stmt = $pdo->prepare("DELETE FROM mail_templates WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                    
                    $success_message = "Mail şablonu ve tüm ek dosyalar başarıyla silindi.";
                    
                    logActivity($_SESSION['user_id'], 'data_change', 'Mail şablonu silindi: ' . ($template_info['name'] ?: 'ID ' . $_POST['id']));
                } catch(PDOException $e) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                    $error_message = "Hata: " . $e->getMessage();
                }
                break;
                
            case 'delete_attachment':
                try {
                    $pdo = getConnection();
                    
                    $stmt = $pdo->prepare("SELECT original_filename, template_id FROM mail_template_attachments WHERE id = ?");
                    $stmt->execute([$_POST['attachment_id']]);
                    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($attachment) {
                        $stmt = $pdo->prepare("DELETE FROM mail_template_attachments WHERE id = ?");
                        $stmt->execute([$_POST['attachment_id']]);
                        
                        $success_message = "Ek dosya başarıyla silindi: " . $attachment['original_filename'];
                        
                        $stay_in_edit_mode = true;
                        $current_edit_id = $attachment['template_id'];
                        
                        logActivity($_SESSION['user_id'], 'data_change', 'Ek dosya silindi: ' . $attachment['original_filename']);
                    }
                } catch(Exception $e) {
                    $error_message = "Hata: " . $e->getMessage();
                    if (isset($_POST['template_id'])) {
                        $stay_in_edit_mode = true;
                        $current_edit_id = $_POST['template_id'];
                    }
                }
                break;
        }
    }
}

if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT original_filename, file_size, mime_type, file_content FROM mail_template_attachments WHERE id = ?");
    $stmt->execute([$_GET['download']]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($attachment && !empty($attachment['file_content'])) {
        $original_name = $attachment['original_filename'] ?: 'download';
        $mime_type = $attachment['mime_type'] ?: 'application/octet-stream';
         
        $allowed_mimes = [
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/pdf', 'text/plain', 'application/vnd.oasis.opendocument.text',
            'image/jpeg', 'image/png', 'image/gif'
        ];
        
        if (!in_array($mime_type, $allowed_mimes)) {
            $error_message = "Bu dosya türü indirilemez.";
        } else {
            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: attachment; filename="' . $original_name . '"');
            header('Content-Length: ' . strlen($attachment['file_content']));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: must-revalidate');
            echo $attachment['file_content'];
            exit();
        }
    } else {
        $error_message = "Dosya bulunamadı.";
    }
}

$pdo = getConnection();
$stmt = $pdo->query("
    SELECT mt.*, 
           GROUP_CONCAT(mta.id SEPARATOR ',') as attachment_ids,
           GROUP_CONCAT(mta.original_filename SEPARATOR '||') as attachment_names,
           GROUP_CONCAT(mta.file_size SEPARATOR ',') as attachment_sizes
    FROM mail_templates mt 
    LEFT JOIN mail_template_attachments mta ON mt.id = mta.template_id 
    GROUP BY mt.id 
    ORDER BY mt.created_at DESC
");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$edit_template = null;
$edit_id = null;

if ($stay_in_edit_mode && $current_edit_id) {
    $edit_id = $current_edit_id;
} elseif (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = $_GET['edit'];
}

if ($edit_id) {
    $stmt = $pdo->prepare("
        SELECT mt.*, 
               GROUP_CONCAT(mta.id SEPARATOR ',') as attachment_ids,
               GROUP_CONCAT(mta.original_filename SEPARATOR '||') as attachment_names,
               GROUP_CONCAT(mta.file_size SEPARATOR ',') as attachment_sizes
        FROM mail_templates mt 
        LEFT JOIN mail_template_attachments mta ON mt.id = mta.template_id 
        WHERE mt.id = ?
        GROUP BY mt.id
    ");
    $stmt->execute([$edit_id]);
    $edit_template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_template) {
        $stmt = $pdo->prepare("SELECT * FROM mail_template_attachments WHERE template_id = ? ORDER BY created_at");
        $stmt->execute([$edit_id]);
        $edit_template['attachments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function logActivity($userId, $logType, $content) {
    try {
        $pdo = getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, content, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $logType, $content, $ipAddress]);
    } catch (Exception $e) {
        error_log("Log kayıt hatası: " . $e->getMessage());
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Şablonları</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            margin-top: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .header h1 {
            margin: 0;
            font-size: 2.5em;
            color: #667eea;
        }

        .nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .nav a {
            padding: 10px 20px;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            transition: all 0.3s;
        }

        .nav a:hover {
            background: #667eea;
            color: white;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
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

        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #e1e5e9;
        }

        .tab {
            padding: 15px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab:hover {
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .edit-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        .edit-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        }

        .edit-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .search-container {
            margin-bottom: 20px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .template-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .template-card {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .template-card:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
        }

        .template-info {
            flex: 1;
        }

        .template-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 400px;
        }

        .template-subject {
            color: #666;
            margin-bottom: 8px;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 400px;
        }

        .template-attachment-count {
            color: #667eea;
            font-size: 12px;
            font-weight: bold;
            margin-top: 5px;
        }

        .template-date {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .template-actions-container {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }

        .template-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-download {
            background: #17a2b8;
            color: white;
        }

        .btn-download:hover {
            background: #138496;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-small {
            padding: 4px 8px;
            font-size: 12px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .file-input-wrapper {
            margin-bottom: 10px;
        }

        .file-input {
            border: 2px dashed #e1e5e9;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .file-input:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .selected-files-list {
            margin-top: 10px;
        }

        .selected-file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #e9ecef;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .remove-file-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 12px;
        }

        .existing-attachments {
            margin-bottom: 20px;
        }

        .existing-attachment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 4px;
            margin-bottom: 5px;
        }

        .attachment-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .attachment-icon {
            font-size: 18px;
        }

        .attachment-name {
            font-weight: 500;
            color: #333;
        }

        .attachment-size {
            font-size: 12px;
            color: #666;
        }

        .attachment-actions {
            display: flex;
            gap: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 15px;
            }

            .edit-sections {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .template-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .template-actions-container {
                align-items: flex-start;
                margin-top: 15px;
            }

            .template-actions {
                flex-direction: column;
                width: 100%;
            }

            .tabs {
                flex-direction: column;
            }

            .tab {
                text-align: left;
            }

            .template-name,
            .template-subject {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Mail Şablonları</h1>
        </div>

        <div class="nav">
            <a href="dashboard.php">🏠 Ana Sayfa</a>
            <a href="document_tracking.php">← Geri Dön</a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab <?php echo !$edit_template ? 'active' : ''; ?>" onclick="switchTab(event, 'templates-list')">📋 Mevcut Şablonlar</button>
            <button class="tab <?php echo $edit_template ? 'active' : ''; ?>" onclick="switchTab(event, '<?php echo $edit_template ? 'edit-template' : 'add-template'; ?>')">
                <?php echo $edit_template ? '✏️ Şablonu Düzenle' : '➕ Yeni Şablon Ekle'; ?>
            </button>
        </div>

        <!-- Mevcut Şablonlar Listesi -->
        <div id="templates-list" class="tab-content <?php echo !$edit_template ? 'active' : ''; ?>">
            <h2>📋 Mevcut Mail Şablonları</h2>
            
            <?php if (empty($templates)): ?>
                <div class="empty-state">
                    <h3>Henüz mail şablonu yok</h3>
                    <p>İlk mail şablonunuzu eklemek için "Yeni Şablon Ekle" sekmesini kullanın.</p>
                    <button class="btn btn-primary" onclick="switchTab(event, 'add-template')">
                        ➕ İlk Şablonu Ekle
                    </button>
                </div>
            <?php else: ?>
                <div class="search-container">
                    <input type="text" id="template-search" class="search-input" 
                           placeholder="🔍 Şablon adı veya konusuna göre arayın..." 
                           onkeyup="searchTemplates()">
                </div>
                
                <div class="template-list" id="template-list">
                    <?php foreach ($templates as $template): ?>
                        <div class="template-card" data-name="<?php echo strtolower(htmlspecialchars($template['name'] ?? 'İsimsiz Şablon')); ?>" 
                             data-subject="<?php echo strtolower(htmlspecialchars($template['subject'])); ?>">
                            <div class="template-info">
                                <div class="template-name" title="<?php echo htmlspecialchars($template['name'] ?? 'İsimsiz Şablon'); ?>">
                                    <?php echo htmlspecialchars($template['name'] ?? 'İsimsiz Şablon'); ?>
                                </div>
                                
                                <div class="template-subject" title="<?php echo htmlspecialchars($template['subject']); ?>">
                                    📧 <?php echo htmlspecialchars($template['subject']); ?>
                                </div>
                                
                                <?php if ($template['attachment_ids']): ?>
                                    <div class="template-attachment-count">
                                        📎 <?php echo count(explode(',', $template['attachment_ids'])); ?> ek dosya
                                    </div>
                                <?php endif; ?>
                                
                                <div class="template-date">
                                    📅 <?php echo date('d.m.Y H:i', strtotime($template['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="template-actions-container">
                                <div class="template-actions">
                                    <a href="?edit=<?php echo $template['id']; ?>" class="btn btn-warning btn-small">
                                        ✏️ Düzenle
                                    </a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Bu şablonu silmek istediğinizden emin misiniz?\\n\\nUyarı: Bu şablon ve tüm ek dosyalar kalıcı olarak silinecektir.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                        <button type="submit" class="btn btn-danger btn-small">🗑️ Sil</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Yeni Şablon Ekleme -->
        <div id="add-template" class="tab-content <?php echo !$edit_template ? '' : ''; ?>">
            <h2>➕ Yeni Mail Şablonu Ekle</h2>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                
                <div class="form-group">
                    <label for="template_name">🏷️ Şablon Adı</label>
                    <input type="text" name="template_name" id="template_name" 
                           placeholder="Şablon adını girin (örn: Başvuru Onay Maili)" required>
                </div>

                <div class="form-group">
                    <label for="subject">📧 Mail Konusu</label>
                    <input type="text" name="subject" id="subject" 
                           placeholder="Mail konusunu girin" required>
                </div>

                <div class="form-group">
                    <label for="content">📝 Mail İçeriği</label>
                    <textarea name="content" id="content" 
                              placeholder="Mail içeriğini girin..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="attachments">📎 Ek Dosyalar</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="attachments[]" id="attachments" multiple accept=".doc,.docx,.pdf,.txt,.odt,.jpg,.jpeg,.png,.gif" onchange="showSelectedFiles(this)" style="display: none;">
                        <div class="file-input" onclick="document.getElementById('attachments').click();">
                            <p>📁 Dosyaları seçmek için tıklayın</p>
                        </div>
                    </div>
                    <div id="selected-files" class="selected-files-list"></div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        ➕ Şablon Ekle
                    </button>
                </div>
            </form>
        </div>

        <!-- Şablon Düzenleme -->
        <div id="edit-template" class="tab-content <?php echo $edit_template ? 'active' : ''; ?>">
            <?php if ($edit_template): ?>
                <h2>✏️ Mail Şablonu Düzenle: <?php echo htmlspecialchars($edit_template['name']); ?></h2>
                
                <div class="edit-sections">
                    <!-- Sol Bölüm: Şablon Bilgileri -->
                    <div class="edit-section">
                        <h3>📝 Şablon Bilgilerini Güncelle</h3>
                        
                        <form method="post">
                            <input type="hidden" name="action" value="update_template_info">
                            <input type="hidden" name="template_id" value="<?php echo $edit_template['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                            
                            <div class="form-group">
                                <label for="edit_template_name">🏷️ Şablon Adı</label>
                                <input type="text" name="template_name" id="edit_template_name" 
                                       value="<?php echo htmlspecialchars($edit_template['name']); ?>"
                                       placeholder="Şablon adını girin" required>
                            </div>

                            <div class="form-group">
                                <label for="edit_subject">📧 Mail Konusu</label>
                                <input type="text" name="subject" id="edit_subject" 
                                       value="<?php echo htmlspecialchars($edit_template['subject']); ?>"
                                       placeholder="Mail konusunu girin" required>
                            </div>

                            <div class="form-group">
                                <label for="edit_content">📝 Mail İçeriği</label>
                                <textarea name="content" id="edit_content" 
                                          placeholder="Mail içeriğini girin..." required><?php echo htmlspecialchars($edit_template['content']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    💾 Şablon Bilgilerini Güncelle
                                </button>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary" style="margin-left: 10px;">
                                    ❌ İptal
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Sağ Bölüm: Ek Dosya Yönetimi -->
                    <div class="edit-section">
                        <h3>📎 Ek Dosya Yönetimi</h3>
                        
                        <!-- Mevcut Dosyalar -->
                        <?php if (!empty($edit_template['attachments'])): ?>
                            <h4>📂 Mevcut Ek Dosyalar</h4>
                            <div class="existing-attachments">
                                <?php foreach ($edit_template['attachments'] as $attachment): ?>
                                    <div class="existing-attachment-item">
                                        <div class="attachment-info">
                                            <span class="attachment-icon">
                                                <?php
                                                $ext = strtolower(pathinfo($attachment['original_filename'], PATHINFO_EXTENSION));
                                                switch($ext) {
                                                    case 'pdf': echo '📄'; break;
                                                    case 'doc':
                                                    case 'docx': echo '📝'; break;
                                                    case 'jpg':
                                                    case 'jpeg':
                                                    case 'png':
                                                    case 'gif': echo '🖼️'; break;
                                                    default: echo '📎'; break;
                                                }
                                                ?>
                                            </span>
                                            <div>
                                                <div class="attachment-name"><?php echo htmlspecialchars($attachment['original_filename']); ?></div>
                                                <div class="attachment-size"><?php echo formatFileSize($attachment['file_size']); ?></div>
                                            </div>
                                        </div>
                                        <div class="attachment-actions">
                                            <a href="?download=<?php echo $attachment['id']; ?>&edit=<?php echo $edit_template['id']; ?>" class="btn btn-download btn-small">
                                                ⬇️ İndir
                                            </a>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Bu dosyayı silmek istediğinizden emin misiniz?');">
                                                <input type="hidden" name="action" value="delete_attachment">
                                                <input type="hidden" name="attachment_id" value="<?php echo $attachment['id']; ?>">
                                                <input type="hidden" name="template_id" value="<?php echo $edit_template['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                                <button type="submit" class="btn btn-danger btn-small">🗑️</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: #666; background: #f8f9fa; border-radius: 5px; margin-bottom: 20px;">
                                📂 Bu şablona ait ek dosya bulunmuyor
                            </div>
                        <?php endif; ?>

                        <!-- Yeni Dosya Ekleme -->
                        <h4>➕ Yeni Dosya Ekle</h4>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_attachments">
                            <input type="hidden" name="template_id" value="<?php echo $edit_template['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                            
                            <div class="form-group">
                                <div class="file-input-wrapper">
                                    <input type="file" name="new_attachments[]" id="new_attachments" multiple accept=".doc,.docx,.pdf,.txt,.odt,.jpg,.jpeg,.png,.gif" onchange="showNewSelectedFiles(this)" style="display: none;">
                                    <div class="file-input" onclick="document.getElementById('new_attachments').click();">
                                        <p>📁 Yeni dosyaları seçmek için tıklayın</p>
                                    </div>
                                </div>
                                <div id="new-selected-files" class="selected-files-list"></div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    ➕ Dosyaları Ekle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let selectedFiles = [];
        let newSelectedFiles = [];

        function switchTab(evt, tabName) {
            var i, tabcontent, tabs;
            
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            tabs = document.getElementsByClassName("tab");
            for (i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        function searchTemplates() {
            const searchInput = document.getElementById('template-search');
            const filter = searchInput.value.toLowerCase();
            const templateCards = document.querySelectorAll('.template-card');
            
            templateCards.forEach(function(card) {
                const name = card.getAttribute('data-name');
                const subject = card.getAttribute('data-subject');
                
                if (name.includes(filter) || subject.includes(filter)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function showSelectedFiles(input) {
            const selectedFilesDiv = document.getElementById('selected-files');
            const fileInput = document.querySelector('.file-input p');
            const maxFiles = <?php echo $max_file_uploads; ?>;
            
            selectedFiles = Array.from(input.files);
            
            if (selectedFiles.length > maxFiles) {
                alert('En fazla ' + maxFiles + ' dosya seçebilirsiniz.');
                input.value = '';
                selectedFiles = [];
                selectedFilesDiv.innerHTML = '';
                fileInput.innerHTML = '📁 Dosyaları seçmek için tıklayın';
                return;
            }
            
            displaySelectedFiles(selectedFiles, selectedFilesDiv, fileInput, maxFiles, 'removeFile');
        }

        function showNewSelectedFiles(input) {
            const selectedFilesDiv = document.getElementById('new-selected-files');
            const fileInput = input.parentElement.querySelector('.file-input p');
            const maxFiles = <?php echo $max_file_uploads; ?>;
            
            newSelectedFiles = Array.from(input.files);
            
            <?php if ($edit_template): ?>
            const existingFileCount = <?php echo count($edit_template['attachments'] ?? []); ?>;
            if ((existingFileCount + newSelectedFiles.length) > maxFiles) {
                alert('Toplam dosya sayısı ' + maxFiles + ' adetini geçemez. (Mevcut: ' + existingFileCount + ', Yeni: ' + newSelectedFiles.length + ')');
                input.value = '';
                newSelectedFiles = [];
                selectedFilesDiv.innerHTML = '';
                fileInput.innerHTML = '📁 Yeni dosyaları seçmek için tıklayın';
                return;
            }
            <?php endif; ?>
            
            displaySelectedFiles(newSelectedFiles, selectedFilesDiv, fileInput, maxFiles, 'removeNewFile');
        }

        function displaySelectedFiles(files, container, inputText, maxFiles, removeFunction) {
            if (files.length > 0) {
                container.innerHTML = '';
                files.forEach((file, index) => {
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    const fileItem = document.createElement('div');
                    fileItem.className = 'selected-file-item';
                    fileItem.innerHTML = `
                        <div>
                            <strong>${file.name}</strong> (${fileSize} MB)
                        </div>
                        <button type="button" class="remove-file-btn" onclick="${removeFunction}(${index})">✕</button>
                    `;
                    container.appendChild(fileItem);
                });
                
                inputText.innerHTML = `📄 ${files.length}/${maxFiles} dosya seçildi`;
            } else {
                container.innerHTML = '';
                inputText.innerHTML = inputText.innerHTML.includes('Yeni') ? '📁 Yeni dosyaları seçmek için tıklayın' : '📁 Dosyaları seçmek için tıklayın';
            }
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            document.getElementById('attachments').files = dt.files;
            
            showSelectedFiles(document.getElementById('attachments'));
        }

        function removeNewFile(index) {
            newSelectedFiles.splice(index, 1);
            
            const dt = new DataTransfer();
            newSelectedFiles.forEach(file => dt.items.add(file));
            document.getElementById('new_attachments').files = dt.files;
            
            showNewSelectedFiles(document.getElementById('new_attachments'));
        }

        document.addEventListener('DOMContentLoaded', function() {
            const fileInputs = document.querySelectorAll('.file-input');

            fileInputs.forEach(fileInputDiv => {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    fileInputDiv.addEventListener(eventName, preventDefaults, false);
                    document.body.addEventListener(eventName, preventDefaults, false);
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    fileInputDiv.addEventListener(eventName, () => highlight(fileInputDiv), false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    fileInputDiv.addEventListener(eventName, () => unhighlight(fileInputDiv), false);
                });

                fileInputDiv.addEventListener('drop', (e) => handleDrop(e, fileInputDiv), false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            function highlight(element) {
                element.style.borderColor = '#667eea';
                element.style.background = '#f0f4ff';
            }

            function unhighlight(element) {
                element.style.borderColor = '#e1e5e9';
                element.style.background = '#f8f9fa';
            }

            function handleDrop(e, element) {
                const dt = e.dataTransfer;
                const files = dt.files;

                if (files.length > 0) {
                    if (element.onclick.toString().includes('attachments')) {
                        document.getElementById('attachments').files = files;
                        showSelectedFiles(document.getElementById('attachments'));
                    } else if (element.onclick.toString().includes('new_attachments')) {
                        document.getElementById('new_attachments').files = files;
                        showNewSelectedFiles(document.getElementById('new_attachments'));
                    }
                }
            }

            <?php if ($edit_template): ?>
            const editTab = document.querySelector('.tab:nth-child(2)');
            const editContent = document.getElementById('edit-template');
            if (editTab && editContent) {
                document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                editTab.classList.add('active');
                editContent.classList.add('active');
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>