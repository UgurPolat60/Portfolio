<?php

require_once 'config.php';
requireLogin();

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_doc_types FROM document_types");
    $stmt->execute();
    $totalDocTypes = $stmt->fetch()['total_doc_types'];
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_certifications FROM certifications WHERE status = 'active'");
    $stmt->execute();
    $activeCertifications = $stmt->fetch()['active_certifications'];
    $stmt = $pdo->prepare("SELECT COUNT(*) as expiring_soon FROM certifications WHERE status = 'active' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $expiringSoon = $stmt->fetch()['expiring_soon'];
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT company_id) as total_companies FROM certifications WHERE status = 'active'");
    $stmt->execute();
    $totalCompanies = $stmt->fetch()['total_companies'];
    
    jsonResponse(true, 'İşlem başarılı', [
        'stats' => [
            'total_doc_types' => $totalDocTypes,
            'active_certifications' => $activeCertifications,
            'expiring_soon' => $expiringSoon,
            'total_companies' => $totalCompanies
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Stats error: " . $e->getMessage());
    jsonResponse(false, 'Bir hata oluştu');
}
?>