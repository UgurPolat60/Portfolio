<?php

require_once 'config.php';
require_once 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'save_smtp_template') {
    try {
        $pdo = getConnection();
        
        $template_name = trim($_POST['template_name'] ?? '');
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = intval($_POST['smtp_port'] ?? 587);
        $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
        $smtp_username = trim($_POST['smtp_username'] ?? '');
        $smtp_password = $_POST['smtp_password'] ?? '';
        $from_name = trim($_POST['from_name'] ?? '');
        
        if (empty($template_name) || empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
            throw new Exception("Tüm alanlar doldurulmalıdır.");
        }
        
        $encrypted_password = encryptSecret($smtp_password);
        if ($encrypted_password === false) {
            throw new Exception("Şifreleme başarısız");
        }
        
        $stmt = $pdo->prepare("INSERT INTO smtp_templates (template_name, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, from_name, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$template_name, $smtp_host, $smtp_port, $smtp_encryption, $smtp_username, $encrypted_password, $from_name, $_SESSION['user_id']]);
        
        $success_message = "SMTP şablonu başarıyla kaydedildi!";
        
    } catch (Exception $e) {
        $error_message = "SMTP şablonu kaydedilemedi: " . $e->getMessage();
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_smtp_template') {
    try {
        $pdo = getConnection();
        $template_id = intval($_POST['template_id']);
        
        $stmt = $pdo->prepare("DELETE FROM smtp_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        
        $success_message = "SMTP şablonu silindi!";
        
    } catch (Exception $e) {
        $error_message = "SMTP şablonu silinemedi: " . $e->getMessage();
    }
}

if (isset($_GET['download']) && isset($_GET['file'])) {
    $file_path = sanitizeInput($_GET['file']);
    $original_name = isset($_GET['name']) ? sanitizeInput($_GET['name']) : basename($file_path);
    
if (!isSecurePath($file_path, 'uploads/')) {
    die('İstek geçersiz');
}
    
if (!file_exists($file_path)) {
    die('Kayıt bulunamadı');
}
    
if (!isAllowedFileType($file_path)) {
    die('İşlem gerçekleştirilemedi');
}
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $original_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('X-Content-Type-Options: nosniff');
    
    readfile($file_path);
    exit();
}

$contact_email = isset($_GET['email']) ? sanitizeInput($_GET['email']) : '';
$company_name = isset($_GET['company']) ? sanitizeInput($_GET['company']) : '';
$cc_emails = isset($_GET['cc']) ? sanitizeInput($_GET['cc']) : '';

if (empty($contact_email) || empty($company_name)) {
    header('Location: dashboard.php');
    exit();
}

if (!validateEmail($contact_email)) {
    die('İstek geçersiz');
}

function parseEmails($emailString) {
    $emails = [];
    
    if (empty($emailString)) {
        return $emails;
    }
    
    $parts = preg_split('/[;,]+/', $emailString);
    
    foreach ($parts as $part) {
        $email = trim($part);
        
        if (empty($email)) {
            continue;
        }
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $clean_email = filter_var($email, FILTER_SANITIZE_EMAIL);
            if ($clean_email && filter_var($clean_email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $clean_email;
                error_log("✓ Geçerli CC email bulundu: $clean_email");
            } else {
                error_log("✗ Email temizleme başarısız: '$email'");
            }
        } else {
            error_log("✗ Geçersiz CC email atlandı: '$email'");
        }
    }
    
    return array_unique($emails);
}

if (isset($_POST['get_template']) && isset($_POST['template_id'])) {
    requireCsrfOnPost();
    header('Content-Type: application/json');
    
    try {
        $template_id = intval($_POST['template_id']);
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM mail_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template) {
            $attachments = [];
            
            $attachStmt = $pdo->prepare("
                SELECT file_path, original_filename, file_size 
                FROM mail_template_attachments 
                WHERE template_id = ? 
                ORDER BY id
            ");
            $attachStmt->execute([$template_id]);
            $attachmentRows = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($attachmentRows as $row) {
                $file_path = $row['file_path'];
                $original_filename = $row['original_filename'];
                $stored_file_size = $row['file_size'];
                
                $file_exists = file_exists($file_path);
                $file_readable = $file_exists && is_readable($file_path);
                $actual_file_size = $file_exists ? filesize($file_path) : 0;
                
                $attachments[] = [
                    'path' => $file_path,
                    'filename' => $original_filename ?: basename($file_path),
                    'exists' => $file_exists,
                    'readable' => $file_readable,
                    'size' => $actual_file_size ?: $stored_file_size,
                    'download_url' => $file_exists ? "?download=1&file=" . urlencode($file_path) . "&name=" . urlencode($original_filename) : null
                ];
            }
            
            echo json_encode([
                'success' => true,
                'subject' => $template['subject'] ?? '',
                'content' => $template['content'] ?? '',
                'attachments' => $attachments
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Kayıt bulunamadı'
            ]);
        }
    } catch (Exception $e) {
        error_log("Template fetch error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Bir hata oluştu'
        ]);
    }
    exit();
}

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'send_mail') {
    requireCsrfOnPost();
    try {
        $pdo = getConnection();
        
        $subject = trim($_POST['subject'] ?? '');
        $content = $_POST['content'] ?? '';
        $cc_emails_input = isset($_POST['cc_emails']) ? trim($_POST['cc_emails']) : '';
        $template_id = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
        $is_html = isset($_POST['is_html']) && $_POST['is_html'] == '1';
        
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = intval($_POST['smtp_port'] ?? 587);
        $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
        $smtp_username = trim($_POST['smtp_username'] ?? '');
        $smtp_password = $_POST['smtp_password'] ?? '';
        $from_name = trim($_POST['from_name'] ?? '');
        
        if (empty($subject) || empty($content)) {
            throw new Exception("Mail konusu ve içeriği boş olamaz.");
        }
        
        if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
            throw new Exception("SMTP ayarları eksik.");
        }
        
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE short_name = ? OR trade_name = ?");
        $stmt->execute([$company_name, $company_name]);
        $company = $stmt->fetch();
        $company_id = $company ? $company['id'] : null;
        
        $cc_email_list = parseEmails($cc_emails_input);
        
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            
            if ($smtp_encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port = $smtp_port;
            $mail->SMTPDebug = 0;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'quoted-printable';
            $mail->Timeout = 60;
            
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            $mail->setFrom($smtp_username, $from_name);
            
            $clean_main_email = filter_var(trim($contact_email), FILTER_SANITIZE_EMAIL);
            if ($clean_main_email && filter_var($clean_main_email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($clean_main_email);
            } else {
                throw new Exception("Ana alıcı email adresi geçersiz: $contact_email");
            }
            
            $cc_added = 0;
            $cc_added_list = [];
            
            foreach ($cc_email_list as $cc_email) {
                $clean_cc = filter_var(trim($cc_email), FILTER_SANITIZE_EMAIL);
                
                if ($clean_cc && filter_var($clean_cc, FILTER_VALIDATE_EMAIL)) {
                    if ($clean_cc !== $clean_main_email) {
                        $mail->addCC($clean_cc);
                        $cc_added++;
                        $cc_added_list[] = $clean_cc;
                    }
                }
            }
            
            $attachmentCount = 0;
            
            if ($template_id) {
                $attachStmt = $pdo->prepare("
                    SELECT file_path, original_filename 
                    FROM mail_template_attachments 
                    WHERE template_id = ? 
                    ORDER BY id
                ");
                $attachStmt->execute([$template_id]);
                $attachmentRows = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($attachmentRows as $row) {
                    $attachment_path = $row['file_path'];
                    $original_filename = $row['original_filename'];
                    
                    if (!empty($attachment_path) && file_exists($attachment_path) && 
                        isSecurePath($attachment_path, 'uploads/') && 
                        isAllowedFileType($attachment_path)) {
                        
                        $filename = $original_filename ?: basename($attachment_path);
                        
                        try {
                            $mail->addAttachment($attachment_path, $filename);
                            $attachmentCount++;
                        } catch (Exception $attachError) {
                            error_log("Ek dosya ekleme hatası: " . $attachError->getMessage());
                        }
                    }
                }
            }
            
            $mail->Subject = $subject;
            
            if ($is_html) {
                $mail->isHTML(true);
                $mail->Body = $content;
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $content));
            } else {
                $mail->isHTML(false);
                $mail->Body = $content;
            }
            
            $result = $mail->send();
            
            if ($result) {
                $all_recipients = $clean_main_email;
                if (!empty($cc_added_list)) {
                    $all_recipients .= ' (CC: ' . implode(', ', $cc_added_list) . ')';
                }
                
                $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                
                $stmt = $pdo->prepare("INSERT INTO mail_history (company_id, template_id, recipient_email, subject, content, sent_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'sent')");
                $stmt->execute([$company_id, $template_id, $all_recipients, $subject, $safe_content]);
                
                logActivity($_SESSION['user_id'], 'mail', 'Mail gönderildi: ' . $all_recipients . ' - ' . $subject . " (Ek: $attachmentCount)" . ($cc_added > 0 ? " (CC: $cc_added kişi)" : ""));
                
                $success_message = "Mail başarıyla gönderildi!" . 
                                 ($attachmentCount > 0 ? " ($attachmentCount ek dosya ile)" : "") . 
                                 ($cc_added > 0 ? " (CC: $cc_added kişi)" : "");
            } else {
                throw new Exception("Mail gönderilemedi - PHPMailer result false");
            }
            
        } catch (Exception $e) {
            error_log("PHPMailer hatası: " . $e->getMessage());
            throw new Exception("Mail gönderilemedi. Hata: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        $error_message = "Mail gönderimi sırasında hata oluştu: " . $e->getMessage();
        error_log("Mail gönderim hatası: " . $e->getMessage());
        
        try {
            if (isset($pdo) && isset($company_id) && isset($template_id) && isset($contact_email) && isset($subject) && isset($content)) {
                $all_recipients = $contact_email;
                if (!empty($cc_email_list)) {
                    $all_recipients .= ' (CC: ' . implode(', ', $cc_email_list) . ')';
                }
                $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                $stmt = $pdo->prepare("INSERT INTO mail_history (company_id, template_id, recipient_email, subject, content, sent_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'failed')");
                $stmt->execute([$company_id, $template_id, $all_recipients, $subject, $safe_content]);
            }
        } catch (Exception $e2) {
            error_log("Mail history kayıt hatası: " . $e2->getMessage());
        }
    }
}

try {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM mail_templates ORDER BY name, created_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Template listesi getirme hatası: " . $e->getMessage());
    $templates = [];
}

try {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM smtp_templates WHERE is_active = 1 ORDER BY template_name");
    $smtp_templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("SMTP şablon listesi getirme hatası: " . $e->getMessage());
    $smtp_templates = [];
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
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Gönder - Belgelendirme</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #6f42c1;
            color: white;
            text-align: center;
            padding: 30px 0;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .navigation {
            text-align: center;
            margin-bottom: 30px;
        }

        .nav-link {
            display: inline-block;
            margin: 0 15px;
            padding: 10px 20px;
            color: #6f42c1;
            text-decoration: none;
            border: 2px solid #6f42c1;
            border-radius: 5px;
        }

        .nav-link:hover {
            background-color: #6f42c1;
            color: white;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: #6f42c1;
            margin-bottom: 20px;
            border-bottom: 2px solid #6f42c1;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #6f42c1;
        }

        .form-textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .provider-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .provider-option {
            flex: 1;
            min-width: 120px;
        }

        .provider-option input[type="radio"] {
            display: none;
        }

        .provider-option label {
            display: block;
            padding: 15px;
            background-color: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            font-weight: 500;
        }

        .provider-option input[type="radio"]:checked + label {
            background-color: #6f42c1;
            color: white;
            border-color: #6f42c1;
        }

        .recipient-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .recipient-row {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
        }

        .recipient-item {
            flex: 1;
        }

        .recipient-label {
            font-weight: 600;
            color: #6f42c1;
            margin-bottom: 5px;
        }

        .smtp-templates {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .template-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .save-template-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px solid #dee2e6;
        }

        .save-template-form {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-top: 15px;
        }

        .save-template-form input {
            flex: 1;
        }

        .attachments-preview {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 2px dashed #dee2e6;
            display: none;
        }

        .attachments-preview.show {
            display: block;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: white;
            border-radius: 5px;
            margin-bottom: 8px;
            border: 1px solid #dee2e6;
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .attachment-details {
            font-size: 12px;
            color: #6c757d;
        }

        .attachment-actions {
            display: flex;
            gap: 8px;
        }

        .toggle-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            border-radius: 24px;
            transition: .4s;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            border-radius: 50%;
            transition: .4s;
        }

        input:checked + .toggle-slider {
            background-color: #6f42c1;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .html-preview {
            margin-top: 15px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 2px dashed #dee2e6;
            display: none;
        }

        .html-preview.show {
            display: block;
        }

        .preview-content {
            background: white;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            min-height: 100px;
        }

        .success-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #28a745;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .success-message.show {
            display: flex;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #6f42c1;
            color: white;
        }

        .btn-primary:hover {
            background-color: #5a2d91;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 12px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .form-row {
                flex-direction: column;
            }

            .provider-options {
                flex-direction: column;
            }

            .recipient-row {
                flex-direction: column;
                gap: 10px;
            }

            .save-template-form {
                flex-direction: column;
                align-items: stretch;
            }

            .form-actions {
                flex-direction: column;
            }

            .attachment-item {
                flex-direction: column;
                align-items: start;
                gap: 10px;
            }

            .attachment-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="success-message" id="successMessage">
        <i class="fas fa-check-circle"></i>
        <span id="successText"></span>
    </div>

    <div class="header">
        <h1><i class="fas fa-paper-plane"></i> Mail Gönder</h1>
    </div>

    <div class="container">
        <div class="navigation">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-home"></i> Ana Sayfa
            </a>
            <a href="javascript:history.back()" class="nav-link">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title"><i class="fas fa-server"></i> SMTP Mail Sunucu Ayarları</h2>

            <?php if (!empty($smtp_templates)): ?>
            <div class="smtp-templates">
                <h4><i class="fas fa-bookmark"></i> Kayıtlı SMTP Şablonları</h4>
                <select id="smtpTemplateSelect" class="form-select">
                    <option value="">-- Şablon Seçin --</option>
                    <?php foreach ($smtp_templates as $template): ?>
                        <option value="<?php echo $template['id']; ?>" 
                                data-host="<?php echo htmlspecialchars($template['smtp_host']); ?>"
                                data-port="<?php echo $template['smtp_port']; ?>"
                                data-username="<?php echo htmlspecialchars($template['smtp_username']); ?>"
                                data-password="<?php echo htmlspecialchars(decryptSecret($template['smtp_password']) ?: ''); ?>"
                                data-encryption="<?php echo $template['smtp_encryption']; ?>"
                                data-from-name="<?php echo htmlspecialchars($template['from_name']); ?>">
                            <?php echo htmlspecialchars($template['template_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="template-actions">
                    <button type="button" class="btn btn-success btn-small" onclick="applySmtpTemplate()">
                        <i class="fas fa-check"></i> Uygula
                    </button>
                    <button type="button" class="btn btn-danger btn-small" onclick="deleteSmtpTemplate()">
                        <i class="fas fa-trash"></i> Sil
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Mail Sağlayıcısı</label>
                <div class="provider-options">
                    <div class="provider-option">
                        <input type="radio" id="gmail" name="smtp_provider" value="gmail">
                        <label for="gmail">Gmail</label>
                    </div>
                    <div class="provider-option">
                        <input type="radio" id="outlook" name="smtp_provider" value="outlook">
                        <label for="outlook">Outlook</label>
                    </div>
                    <div class="provider-option">
                        <input type="radio" id="yahoo" name="smtp_provider" value="yahoo">
                        <label for="yahoo">Yahoo</label>
                    </div>
                    <div class="provider-option">
                        <input type="radio" id="yandex" name="smtp_provider" value="yandex">
                        <label for="yandex">Yandex</label>
                    </div>
                    <div class="provider-option">
                        <input type="radio" id="custom" name="smtp_provider" value="custom">
                        <label for="custom">Özel</label>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="smtp_host">SMTP Host</label>
                    <input type="text" id="smtp_host" class="form-input" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="smtp_port">Port</label>
                    <input type="number" id="smtp_port" class="form-input" value="587" min="1" max="65535">
                </div>
                <div class="form-group">
                    <label class="form-label" for="smtp_encryption">Şifreleme</label>
                    <select id="smtp_encryption" class="form-select">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="smtp_username">Email Adresi</label>
                    <input type="email" id="smtp_username" class="form-input" placeholder="ornek@gmail.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="smtp_password">Şifre / App Password</label>
                    <input type="password" id="smtp_password" class="form-input" placeholder="Şifre veya uygulama şifresi">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="from_name">Gönderen İsim</label>
                <input type="text" id="from_name" class="form-input" placeholder="Gönderen ismi">
            </div>

            <div class="save-template-section">
                <h4><i class="fas fa-save"></i> Bu Ayarları Şablon Olarak Kaydet</h4>
                <div class="save-template-form">
                    <input type="text" id="template_name" class="form-input" placeholder="Şablon adı (örn: Belgelendirme Sistemi)">
                    <button type="button" class="btn btn-success" onclick="saveSmtpTemplate()">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title"><i class="fas fa-users"></i> Alıcı Bilgileri</h2>
            
            <div class="recipient-info">
                <div class="recipient-row">
                    <div class="recipient-item">
                        <div class="recipient-label"><i class="fas fa-building"></i> Firma</div>
                        <div><?php echo htmlspecialchars($company_name); ?></div>
                    </div>
                    <div class="recipient-item">
                        <div class="recipient-label"><i class="fas fa-envelope"></i> Ana Alıcı</div>
                        <div><?php echo htmlspecialchars($contact_email); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="cc_emails_field">
                    <i class="fas fa-users"></i> Ekstra Alıcılar (CC)
                </label>
                <input type="text" id="cc_emails_field" class="form-input" 
                       placeholder="ornek1@gmail.com ; ornek2@gmail.com" 
                       value="<?php echo htmlspecialchars($cc_emails); ?>">
                <small style="color: #6c757d; margin-top: 5px; display: block;">
                    <i class="fas fa-info-circle"></i> Birden fazla email için ; veya , kullanın
                </small>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title"><i class="fas fa-edit"></i> Mail Hazırla</h2>
            
            <form method="post" id="mailForm">
                <input type="hidden" name="action" value="send_mail">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="cc_emails" id="cc_emails_hidden">
                <input type="hidden" name="smtp_host" id="smtp_host_hidden">
                <input type="hidden" name="smtp_port" id="smtp_port_hidden">
                <input type="hidden" name="smtp_encryption" id="smtp_encryption_hidden">
                <input type="hidden" name="smtp_username" id="smtp_username_hidden">
                <input type="hidden" name="smtp_password" id="smtp_password_hidden">
                <input type="hidden" name="from_name" id="from_name_hidden">
                
                <div class="form-group">
                    <label class="form-label" for="templateSelect">
                        <i class="fas fa-template"></i> Mail Şablonu
                    </label>
                    <select id="templateSelect" name="template_id" class="form-select" onchange="loadTemplateData()">
                        <option value="">-- Şablon Seçin --</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template['id']; ?>">
                                <?php echo htmlspecialchars($template['name'] ?: $template['subject']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div class="attachments-preview" id="attachmentsList">
                        <h4><i class="fas fa-paperclip"></i> Ek Dosyalar</h4>
                        <div id="attachmentsContent"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="subject">
                        <i class="fas fa-heading"></i> Konu *
                    </label>
                    <input type="text" name="subject" id="subject" class="form-input" placeholder="Mail konusu" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="content">
                        <i class="fas fa-align-left"></i> İçerik *
                    </label>
                    
                    <div class="toggle-container">
                        <label class="toggle-switch">
                            <input type="checkbox" id="is_html" name="is_html" value="1" onchange="toggleHtmlPreview()">
                            <span class="toggle-slider"></span>
                        </label>
                        <span><i class="fas fa-code"></i> HTML formatında gönder</span>
                    </div>
                    
                    <textarea name="content" id="content" class="form-textarea" placeholder="Mail içeriği..." required oninput="updateHtmlPreview()"></textarea>
                    
                    <div class="html-preview" id="htmlPreview">
                        <h4><i class="fas fa-eye"></i> Önizleme</h4>
                        <div class="preview-content" id="previewContent"></div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Mail Gönder
                    </button>
                    <a href="javascript:history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Geri
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const smtpConfigs = {
            gmail: { host: 'smtp.gmail.com', port: 587, encryption: 'tls' },
            outlook: { host: 'smtp-mail.outlook.com', port: 587, encryption: 'tls' },
            yahoo: { host: 'smtp.mail.yahoo.com', port: 587, encryption: 'tls' },
            yandex: { host: 'smtp.yandex.com', port: 587, encryption: 'tls' }
        };

        function showSuccessMessage(message) {
            const successMessage = document.getElementById('successMessage');
            const successText = document.getElementById('successText');
            successText.textContent = message;
            successMessage.classList.add('show');
            
            setTimeout(() => {
                successMessage.classList.remove('show');
            }, 3000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('gmail').checked = true;
            updateSmtpConfig('gmail');
        });

        document.querySelectorAll('input[name="smtp_provider"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value !== 'custom') {
                    updateSmtpConfig(this.value);
                } else {
                    document.getElementById('smtp_host').value = '';
                    document.getElementById('smtp_port').value = '587';
                    document.getElementById('smtp_encryption').value = 'tls';
                }
            });
        });

        function updateSmtpConfig(provider) {
            const config = smtpConfigs[provider];
            if (config) {
                document.getElementById('smtp_host').value = config.host;
                document.getElementById('smtp_port').value = config.port;
                document.getElementById('smtp_encryption').value = config.encryption;
            }
        }

        function applySmtpTemplate() {
            const select = document.getElementById('smtpTemplateSelect');
            const option = select.selectedOptions[0];
            
            if (!option || !option.value) {
                alert('Lütfen bir şablon seçin!');
                return;
            }
            
            document.getElementById('smtp_host').value = option.dataset.host;
            document.getElementById('smtp_port').value = option.dataset.port;
            document.getElementById('smtp_encryption').value = option.dataset.encryption;
            document.getElementById('smtp_username').value = option.dataset.username;
            document.getElementById('smtp_password').value = option.dataset.password;
            document.getElementById('from_name').value = option.dataset.fromName;
            
            const host = option.dataset.host;
            let provider = 'custom';
            for (const [key, config] of Object.entries(smtpConfigs)) {
                if (config.host === host) {
                    provider = key;
                    break;
                }
            }
            document.getElementById(provider).checked = true;
            
            showSuccessMessage('SMTP şablonu uygulandı!');
        }

        function deleteSmtpTemplate() {
            const select = document.getElementById('smtpTemplateSelect');
            const templateId = select.value;
            
            if (!templateId) {
                alert('Lütfen silinecek şablonu seçin!');
                return;
            }
            
            if (confirm('Bu SMTP şablonunu silmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <input type="hidden" name="action" value="delete_smtp_template">
                    <input type="hidden" name="template_id" value="${templateId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function saveSmtpTemplate() {
            const templateName = document.getElementById('template_name').value.trim();
            const smtpHost = document.getElementById('smtp_host').value.trim();
            const smtpPort = document.getElementById('smtp_port').value;
            const smtpEncryption = document.getElementById('smtp_encryption').value;
            const smtpUsername = document.getElementById('smtp_username').value.trim();
            const smtpPassword = document.getElementById('smtp_password').value.trim();
            const fromName = document.getElementById('from_name').value.trim();
            
            if (!templateName || !smtpHost || !smtpUsername || !smtpPassword) {
                alert('Lütfen tüm alanları doldurun!');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                <input type="hidden" name="action" value="save_smtp_template">
                <input type="hidden" name="template_name" value="${templateName}">
                <input type="hidden" name="smtp_host" value="${smtpHost}">
                <input type="hidden" name="smtp_port" value="${smtpPort}">
                <input type="hidden" name="smtp_encryption" value="${smtpEncryption}">
                <input type="hidden" name="smtp_username" value="${smtpUsername}">
                <input type="hidden" name="smtp_password" value="${smtpPassword}">
                <input type="hidden" name="from_name" value="${fromName}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function loadTemplateData() {
            const templateId = document.getElementById('templateSelect').value;
            const attachmentsList = document.getElementById('attachmentsList');
            
            if (templateId) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `get_template=1&template_id=${templateId}&csrf_token=<?php echo getCsrfToken(); ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('subject').value = data.subject || '';
                        document.getElementById('content').value = data.content || '';
                        
                        updateHtmlPreview();
                        
                        if (data.attachments && data.attachments.length > 0) {
                            let html = '';
                            data.attachments.forEach(att => {
                                const size = formatFileSize(att.size);
                                const statusIcon = att.exists ? 
                                    '<i class="fas fa-check-circle" style="color: #28a745;"></i>' : 
                                    '<i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>';
                                const statusText = att.exists ? 'Mevcut' : 'Dosya bulunamadı';
                                
                                html += `
                                    <div class="attachment-item">
                                        <i class="fas fa-paperclip" style="color: #6f42c1; font-size: 18px;"></i>
                                        <div class="attachment-info">
                                            <div class="attachment-name">${att.filename}</div>
                                            <div class="attachment-details">
                                                Boyut: ${size} • Durum: ${statusText}
                                            </div>
                                        </div>
                                        <div class="attachment-actions">
                                            ${statusIcon}
                                            ${att.download_url ? `<a href="${att.download_url}" class="btn btn-primary btn-small" target="_blank">
                                                <i class="fas fa-download"></i> İndir
                                            </a>` : ''}
                                        </div>
                                    </div>
                                `;
                            });
                            document.getElementById('attachmentsContent').innerHTML = html;
                            attachmentsList.classList.add('show');
                        } else {
                            attachmentsList.classList.remove('show');
                        }
                    } else {
                        alert('Şablon yüklenemedi: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Bir hata oluştu: ' + error.message);
                });
            } else {
                document.getElementById('subject').value = '';
                document.getElementById('content').value = '';
                attachmentsList.classList.remove('show');
                updateHtmlPreview();
            }
        }

        function toggleHtmlPreview() {
            const isChecked = document.getElementById('is_html').checked;
            const preview = document.getElementById('htmlPreview');
            
            if (isChecked) {
                preview.classList.add('show');
                updateHtmlPreview();
            } else {
                preview.classList.remove('show');
            }
        }

        function updateHtmlPreview() {
            const isHtmlMode = document.getElementById('is_html').checked;
            const content = document.getElementById('content').value;
            const previewContent = document.getElementById('previewContent');
            
            if (isHtmlMode && content.trim()) {
                previewContent.innerHTML = content;
            } else {
                previewContent.innerHTML = '<p style="color: #6c757d; font-style: italic;">Önizleme için HTML modunu aktifleştirin ve içerik girin...</p>';
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        document.getElementById('mailForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const ccField = document.getElementById('cc_emails_field');
            const ccHidden = document.getElementById('cc_emails_hidden');
            
            const subject = document.getElementById('subject').value.trim();
            const content = document.getElementById('content').value.trim();
            const smtpHost = document.getElementById('smtp_host').value.trim();
            const smtpUsername = document.getElementById('smtp_username').value.trim();
            const smtpPassword = document.getElementById('smtp_password').value.trim();
            
            if (!subject || !content) {
                alert('Mail konusu ve içeriği zorunludur!');
                return;
            }
            
            if (!smtpHost || !smtpUsername || !smtpPassword) {
                alert('SMTP ayarları eksik!');
                return;
            }
                    
            ccHidden.value = ccField ? ccField.value.trim() : '';
            document.getElementById('smtp_host_hidden').value = smtpHost;
            document.getElementById('smtp_port_hidden').value = document.getElementById('smtp_port').value;
            document.getElementById('smtp_encryption_hidden').value = document.getElementById('smtp_encryption').value;
            document.getElementById('smtp_username_hidden').value = smtpUsername;
            document.getElementById('smtp_password_hidden').value = smtpPassword;
            document.getElementById('from_name_hidden').value = document.getElementById('from_name').value.trim();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            
            this.submit();
        });

        const textarea = document.getElementById('content');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>