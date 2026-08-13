<?php

require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    die('Oturum bulunamadı');
}

// Sadece muhasebeci erişebilir
if (!isset($userData['role']) || strtolower($userData['role']) !== 'muhasebeci') {
    die('Yetkisiz erişim');
}

// Belgeleri getir
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
                c.created_at,
                comp.short_name as company_name,
                comp.trade_name,
                comp.tax_office,
                comp.tax_number,
                dt.name as document_type,
                dt.standard,
                con.company_short_name as consultant_name
            FROM certifications c
            INNER JOIN companies comp ON c.company_id = comp.id
            INNER JOIN document_types dt ON c.document_type_id = dt.id
            LEFT JOIN consultants con ON c.consultant_id = con.id
            WHERE c.status = 'active'
            ORDER BY comp.short_name, c.created_at DESC";
            
    $stmt = $pdo->query($sql);
    $certifications = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Veri tabanı hatası: ' . $e->getMessage());
}

// CSV oluştur
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="belgelendirme_masraf_raporu_' . date('Y-m-d_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM ekle (Excel için Türkçe karakter desteği)
echo "\xEF\xBB\xBF";

// CSV output
$output = fopen('php://output', 'w');

// Başlıklar
$headers = [
    'Firma Kısa Adı',
    'Firma Ticari Ünvan',
    'Vergi Dairesi',
    'Vergi No',
    'Belge Türü',
    'Standart',
    'Belge No',
    'Kapsam',
    'Danışman',
    'Düzenleme Tarihi',
    'Geçerlilik Tarihi',
    'Belge Masrafı (₺)',
    'Belge Ödenen (₺)',
    'Belge Kalan (₺)',
    'Danışman Masrafı (₺)',
    'Danışman Ödenen (₺)',
    'Danışman Kalan (₺)',
    'Eğitim Masrafı (₺)',
    'Eğitim Ödenen (₺)',
    'Eğitim Kalan (₺)',
    'Toplam Masraf (₺)',
    'Toplam Ödenen (₺)',
    'Toplam Kalan (₺)'
];

fputcsv($output, $headers);

// Veriler
$toplam_belge_masraf = 0;
$toplam_belge_odenen = 0;
$toplam_danisman_masraf = 0;
$toplam_danisman_odenen = 0;
$toplam_egitim_masraf = 0;
$toplam_egitim_odenen = 0;

foreach ($certifications as $cert) {
    $belge_kalan = $cert['belge_masraf'] - $cert['belge_odenen'];
    $danisman_kalan = $cert['danisman_masraf'] - $cert['danisman_odenen'];
    $egitim_kalan = $cert['egitim_masraf'] - $cert['egitim_odenen'];
    
    $toplam_masraf = $cert['belge_masraf'] + $cert['danisman_masraf'] + $cert['egitim_masraf'];
    $toplam_odenen = $cert['belge_odenen'] + $cert['danisman_odenen'] + $cert['egitim_odenen'];
    $toplam_kalan = $toplam_masraf - $toplam_odenen;
    
    // Toplamları hesapla
    $toplam_belge_masraf += $cert['belge_masraf'];
    $toplam_belge_odenen += $cert['belge_odenen'];
    $toplam_danisman_masraf += $cert['danisman_masraf'];
    $toplam_danisman_odenen += $cert['danisman_odenen'];
    $toplam_egitim_masraf += $cert['egitim_masraf'];
    $toplam_egitim_odenen += $cert['egitim_odenen'];
    
    $row = [
        $cert['company_name'],
        $cert['trade_name'],
        $cert['tax_office'],
        $cert['tax_number'],
        $cert['document_type'],
        $cert['standard'],
        $cert['document_number'],
        $cert['scope'],
        $cert['consultant_name'] ?? '-',
        date('d.m.Y', strtotime($cert['issue_date'])),
        date('d.m.Y', strtotime($cert['expiry_date'])),
        number_format($cert['belge_masraf'], 2, ',', '.'),
        number_format($cert['belge_odenen'], 2, ',', '.'),
        number_format($belge_kalan, 2, ',', '.'),
        number_format($cert['danisman_masraf'], 2, ',', '.'),
        number_format($cert['danisman_odenen'], 2, ',', '.'),
        number_format($danisman_kalan, 2, ',', '.'),
        number_format($cert['egitim_masraf'], 2, ',', '.'),
        number_format($cert['egitim_odenen'], 2, ',', '.'),
        number_format($egitim_kalan, 2, ',', '.'),
        number_format($toplam_masraf, 2, ',', '.'),
        number_format($toplam_odenen, 2, ',', '.'),
        number_format($toplam_kalan, 2, ',', '.')
    ];
    
    fputcsv($output, $row);
}

// Toplam satırı ekle
fputcsv($output, []); // Boş satır
$toplam_row = [
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    'TOPLAM:',
    number_format($toplam_belge_masraf, 2, ',', '.'),
    number_format($toplam_belge_odenen, 2, ',', '.'),
    number_format($toplam_belge_masraf - $toplam_belge_odenen, 2, ',', '.'),
    number_format($toplam_danisman_masraf, 2, ',', '.'),
    number_format($toplam_danisman_odenen, 2, ',', '.'),
    number_format($toplam_danisman_masraf - $toplam_danisman_odenen, 2, ',', '.'),
    number_format($toplam_egitim_masraf, 2, ',', '.'),
    number_format($toplam_egitim_odenen, 2, ',', '.'),
    number_format($toplam_egitim_masraf - $toplam_egitim_odenen, 2, ',', '.'),
    number_format($toplam_belge_masraf + $toplam_danisman_masraf + $toplam_egitim_masraf, 2, ',', '.'),
    number_format($toplam_belge_odenen + $toplam_danisman_odenen + $toplam_egitim_odenen, 2, ',', '.'),
    number_format(($toplam_belge_masraf + $toplam_danisman_masraf + $toplam_egitim_masraf) - ($toplam_belge_odenen + $toplam_danisman_odenen + $toplam_egitim_odenen), 2, ',', '.')
];

fputcsv($output, $toplam_row);

// Log kaydet
try {
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, content, ip_address) 
                          VALUES (?, 'export', 'Masraf raporu Excel formatında indirildi', ?)");
    $stmt->execute([
        $userData['id'],
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ]);
} catch (Exception $e) {
    error_log("Log kaydı hatası: " . $e->getMessage());
}

fclose($output);
exit();
?>
