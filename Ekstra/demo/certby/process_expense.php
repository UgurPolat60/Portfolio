<?php

require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    jsonResponse(false, 'Oturum bulunamadı');
}


if (!isset($userData['role']) || strtolower($userData['role']) !== 'muhasebeci') {
    jsonResponse(false, 'Yetkisiz erişim');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Geçersiz istek');
}

requireCsrfOnPost();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(false, 'Geçersiz istek');
}

$certificationId = intval($input['certification_id'] ?? 0);
$belgeMasraf = floatval($input['belge_masraf'] ?? 0);
$belgeOdenen = floatval($input['belge_odenen'] ?? 0);
$danismanMasraf = floatval($input['danisman_masraf'] ?? 0);
$danismanOdenen = floatval($input['danisman_odenen'] ?? 0);
$egitimMasraf = floatval($input['egitim_masraf'] ?? 0);
$egitimOdenen = floatval($input['egitim_odenen'] ?? 0);

if ($certificationId <= 0) {
    jsonResponse(false, 'Geçersiz belge ID');
}


if ($belgeMasraf < 0 || $belgeOdenen < 0 || $danismanMasraf < 0 || 
    $danismanOdenen < 0 || $egitimMasraf < 0 || $egitimOdenen < 0) {
    jsonResponse(false, 'Masraf ve ödeme tutarları negatif olamaz');
}


if ($belgeOdenen > $belgeMasraf || $danismanOdenen > $danismanMasraf || $egitimOdenen > $egitimMasraf) {
    jsonResponse(false, 'Ödenen tutar masraftan fazla olamaz');
}

try {
    $pdo = getConnection();
    

    $stmt = $pdo->prepare("SELECT belge_masraf, belge_odenen, danisman_masraf, danisman_odenen, 
                                   egitim_masraf, egitim_odenen 
                           FROM certifications WHERE id = ?");
    $stmt->execute([$certificationId]);
    $oldData = $stmt->fetch();
    
    if (!$oldData) {
        jsonResponse(false, 'Belge bulunamadı');
    }
    

    $stmt = $pdo->prepare("UPDATE certifications 
                          SET belge_masraf = ?,
                              belge_odenen = ?,
                              danisman_masraf = ?,
                              danisman_odenen = ?,
                              egitim_masraf = ?,
                              egitim_odenen = ?,
                              expense_updated_at = NOW(),
                              expense_updated_by = ?
                          WHERE id = ?");
    
    $stmt->execute([
        $belgeMasraf,
        $belgeOdenen,
        $danismanMasraf,
        $danismanOdenen,
        $egitimMasraf,
        $egitimOdenen,
        $userData['id'],
        $certificationId
    ]);
    

    if ($oldData['belge_masraf'] != $belgeMasraf || $oldData['belge_odenen'] != $belgeOdenen) {
        $stmt = $pdo->prepare("INSERT INTO expense_history 
                              (certification_id, expense_type, old_amount, new_amount, old_paid, new_paid, created_by) 
                              VALUES (?, 'belge', ?, ?, ?, ?, ?)");
        $stmt->execute([
            $certificationId,
            $oldData['belge_masraf'],
            $belgeMasraf,
            $oldData['belge_odenen'],
            $belgeOdenen,
            $userData['id']
        ]);
    }
    

    if ($oldData['danisman_masraf'] != $danismanMasraf || $oldData['danisman_odenen'] != $danismanOdenen) {
        $stmt = $pdo->prepare("INSERT INTO expense_history 
                              (certification_id, expense_type, old_amount, new_amount, old_paid, new_paid, created_by) 
                              VALUES (?, 'danisman', ?, ?, ?, ?, ?)");
        $stmt->execute([
            $certificationId,
            $oldData['danisman_masraf'],
            $danismanMasraf,
            $oldData['danisman_odenen'],
            $danismanOdenen,
            $userData['id']
        ]);
    }
    

    if ($oldData['egitim_masraf'] != $egitimMasraf || $oldData['egitim_odenen'] != $egitimOdenen) {
        $stmt = $pdo->prepare("INSERT INTO expense_history 
                              (certification_id, expense_type, old_amount, new_amount, old_paid, new_paid, created_by) 
                              VALUES (?, 'egitim', ?, ?, ?, ?, ?)");
        $stmt->execute([
            $certificationId,
            $oldData['egitim_masraf'],
            $egitimMasraf,
            $oldData['egitim_odenen'],
            $egitimOdenen,
            $userData['id']
        ]);
    }
    

    $logContent = "Belge ID: $certificationId için masraf güncellendi";
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, content, ip_address) 
                          VALUES (?, 'expense_update', ?, ?)");
    $stmt->execute([
        $userData['id'],
        $logContent,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ]);
    
    jsonResponse(true, 'Masraf bilgileri başarıyla güncellendi', [
        'belge_kalan' => $belgeMasraf - $belgeOdenen,
        'danisman_kalan' => $danismanMasraf - $danismanOdenen,
        'egitim_kalan' => $egitimMasraf - $egitimOdenen,
        'toplam_kalan' => ($belgeMasraf - $belgeOdenen) + ($danismanMasraf - $danismanOdenen) + ($egitimMasraf - $egitimOdenen)
    ]);
    
} catch (Exception $e) {
    error_log("Masraf güncelleme hatası: " . $e->getMessage());
    jsonResponse(false, 'Bir hata oluştu');
}
?>
