<?php

require_once 'config.php';

requireLogin();

$documentId = isset($_GET['doc_id']) ? intval($_GET['doc_id']) : 0;
$inspectionType = isset($_GET['type']) ? intval($_GET['type']) : 0;

if ($documentId == 0 || !in_array($inspectionType, [1, 2])) {
    header('HTTP/1.0 404 Not Found');
    exit('İstek geçersiz');
}

try {
    $pdo = getConnection();
    
    $sql = "SELECT ir.report_file, c.document_number, comp.short_name
            FROM inspection_records ir
            JOIN certifications c ON ir.document_id = c.id
            JOIN companies comp ON c.company_id = comp.id
            WHERE ir.document_id = ? AND ir.inspection_type = ? AND ir.report_file IS NOT NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$documentId, $inspectionType]);
    $record = $stmt->fetch();
    
    if (!$record || !file_exists($record['report_file'])) {
        header('HTTP/1.0 404 Not Found');
        exit('Kayıt bulunamadı');
    }
    
    $filePath = $record['report_file'];
    if (!isSecurePath($filePath, 'uploads/')) {
        header('HTTP/1.0 403 Forbidden');
        exit('İstek geçersiz');
    }
    if (!isAllowedFileType($filePath)) {
        header('HTTP/1.0 403 Forbidden');
        exit('İstek geçersiz');
    }

    $originalExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $downloadName = $record['short_name'] . '_' . $record['document_number'] . '_' . $inspectionType . '.tetkik.' . $originalExt;

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    
    $chunkSize = 8192;
    $fp = fopen($filePath, 'rb');
    if ($fp === false) {
        header('HTTP/1.0 500 Internal Server Error');
        exit('Bir hata oluştu');
    }
    while (!feof($fp)) {
        echo fread($fp, $chunkSize);
        @ob_flush();
        flush();
    }
    fclose($fp);

        $logContent = "Tetkik raporu indirildi - Belge ID: $documentId, Tetkik: $inspectionType";
        $logSql = "INSERT INTO system_logs (user_id, log_type, level, content, ip_address, created_at) 
                  VALUES (?, 'data_change', 'INFO', ?, ?, NOW())";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([$_SESSION['user_id'], $logContent, $_SERVER['REMOTE_ADDR'] ?? '']);
    exit();
    
} catch (Exception $e) {
    error_log('Report download error: ' . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit('Bir hata oluştu');
}
?>