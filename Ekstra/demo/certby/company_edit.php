<?php

require_once 'config.php';

requireLogin();

$userData = getUserData($_SESSION['user_id']);

if (!$userData) {
    session_destroy();
    header('Location: index.html');
    exit();
}

if (!in_array($userData['role'], ['operator','user'])) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'İstek geçersiz';
    header('Location: company_management.php');
    exit();
}

$companyId = $_GET['id'];

function getCompanyById($id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM companies 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Firma bilgileri alınamadı: " . $e->getMessage());
        return null;
    }
}

$company = getCompanyById($companyId);

if (!$company) {
    $_SESSION['error_message'] = 'Kayıt bulunamadı';
    header('Location: company_management.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $shortName = trim($_POST['short_name'] ?? '');
    $tradeName = trim($_POST['trade_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $invoiceAddress = trim($_POST['invoice_address'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $corporateEmail = trim($_POST['corporate_email'] ?? '');
    $authorizedPerson = trim($_POST['authorized_person'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $taxOffice = trim($_POST['tax_office'] ?? '');
    $taxNumber = trim($_POST['tax_number'] ?? '');
    
    if (empty($shortName)) {
        $errors['short_name'] = "Firma kısa adı boş bırakılamaz.";
    } elseif (strlen($shortName) < 2) {
        $errors['short_name'] = "Firma kısa adı en az 2 karakter olmalıdır.";
    } elseif (strlen($shortName) > 100) {
        $errors['short_name'] = "Firma kısa adı en fazla 100 karakter olabilir.";
    }
    
    if (empty($tradeName)) {
        $errors['trade_name'] = "Ticaret unvanı boş bırakılamaz.";
    } elseif (strlen($tradeName) > 200) {
        $errors['trade_name'] = "Ticaret unvanı en fazla 200 karakter olabilir.";
    }
    
    if (empty($address)) {
        $errors['address'] = "Adres boş bırakılamaz.";
    }
    
    if (empty($contactPerson)) {
        $errors['contact_person'] = "İletişim kişisi boş bırakılamaz.";
    } elseif (strlen($contactPerson) > 100) {
        $errors['contact_person'] = "İletişim kişisi en fazla 100 karakter olabilir.";
    }
    
    if (empty($contactPhone)) {
        $errors['contact_phone'] = "İletişim telefonu boş bırakılamaz.";
    } elseif (!preg_match('/^[0-9\s\-\+\(\)]+$/', $contactPhone)) {
        $errors['contact_phone'] = "Telefon numarası geçerli bir formatta olmalıdır.";
    }
    
    if (empty($contactEmail)) {
        $errors['contact_email'] = "İletişim e-postası boş bırakılamaz.";
    } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['contact_email'] = "Geçerli bir e-posta adresi giriniz.";
    }
    
    if (empty($taxOffice)) {
        $errors['tax_office'] = "Vergi dairesi boş bırakılamaz.";
    } elseif (strlen($taxOffice) > 100) {
        $errors['tax_office'] = "Vergi dairesi en fazla 100 karakter olabilir.";
    }
    
    if (empty($taxNumber)) {
        $errors['tax_number'] = "Vergi numarası boş bırakılamaz.";
    } elseif (!preg_match('/^[0-9]{10,11}$/', $taxNumber)) {
        $errors['tax_number'] = "Vergi numarası 10 veya 11 haneli olmalıdır.";
    }
    
    if (!empty($corporateEmail) && !filter_var($corporateEmail, FILTER_VALIDATE_EMAIL)) {
        $errors['corporate_email'] = "Geçerli bir e-posta adresi giriniz.";
    }
    
    if (empty($errors['short_name'])) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM companies 
                WHERE short_name = ? AND status = 'active' AND id != ?
            ");
            $stmt->execute([$shortName, $companyId]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors['short_name'] = 'Kayıt mevcut';
            }
        } catch (Exception $e) {
            $errors['short_name'] = 'Bir hata oluştu';
        }
    }
    
    if (empty($errors['trade_name'])) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM companies 
                WHERE trade_name = ? AND status = 'active' AND id != ?
            ");
            $stmt->execute([$tradeName, $companyId]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors['trade_name'] = 'Kayıt mevcut';
            }
        } catch (Exception $e) {
            $errors['trade_name'] = 'Bir hata oluştu';
        }
    }
    
    if (empty($errors['tax_number'])) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM companies 
                WHERE tax_number = ? AND status = 'active' AND id != ?
            ");
            $stmt->execute([$taxNumber, $companyId]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors['tax_number'] = 'Kayıt mevcut';
            }
        } catch (Exception $e) {
            $errors['tax_number'] = 'Bir hata oluştu';
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("
                UPDATE companies SET 
                    short_name = ?, 
                    trade_name = ?, 
                    address = ?, 
                    invoice_address = ?,
                    website = ?,
                    phone = ?,
                    corporate_email = ?,
                    authorized_person = ?,
                    contact_person = ?, 
                    contact_phone = ?, 
                    contact_email = ?, 
                    tax_office = ?, 
                    tax_number = ?, 
                    updated_at = NOW()
                WHERE id = ? AND status = 'active'
            ");
            
            $stmt->execute([
                $shortName, $tradeName, $address, $invoiceAddress, $website, $phone, 
                $corporateEmail, $authorizedPerson, $contactPerson, $contactPhone, 
                $contactEmail, $taxOffice, $taxNumber, $companyId
            ]);
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            }
            
            $_SESSION['success_message'] = "Firma bilgileri başarıyla güncellendi.";
            header('Location: company_management.php');
            exit();
            
        } catch (Exception $e) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => ['general' => 'Bir hata oluştu']]);
                exit();
            }
            $errors['general'] = 'Bir hata oluştu';
            error_log('Company update error: ' . $e->getMessage());
        }
    } else {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgelendirme - Firma Düzenle</title>
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
            line-height: 1.6;
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

        .nav-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }

        .main-content {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-title {
            text-align: center;
            margin-bottom: 30px;
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

        .form-sections {
            display: flex;
            flex-direction: column;
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.4em;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            font-size: 1em;
            display: block;
        }

        .required {
            color: #e74c3c;
            margin-left: 3px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input.error {
            border-color: #e74c3c;
        }

        .error-message {
            color: #e74c3c;
            font-size: 0.9em;
            margin-top: 5px;
            min-height: 20px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .loading-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .form-section {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }
        }

        @media print {
            body {
                background: white;
            }
            
            .header, .form-actions {
                display: none;
            }
            
            .form-section {
                box-shadow: none;
                border: 1px solid #ddd;
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
            <div class="nav-info">
                <span>Hoşgeldiniz, <?php echo htmlspecialchars($userData['full_name']); ?></span>
                <a href="company_management.php" class="back-btn">← Firma Yönetimi</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="page-title">
            <h1>Firma Düzenle</h1>
        </div>

        <form id="companyForm" action="" method="POST">
            <div class="form-sections">
                <div class="form-section">
                    <h3 class="section-title">Firma Bilgileri</h3>
                    
                    <div class="form-group">
                        <label for="short_name">
                            Firma Kısa Adı <span class="required">*</span>
                        </label>
                        <input type="text" id="short_name" name="short_name" required maxlength="100" 
                               value="<?php echo htmlspecialchars($company['short_name']); ?>" placeholder="Örn: ABC Ltd.">
                        <div class="error-message" id="short_name_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="trade_name">
                            Firma Ticari Ünvanı <span class="required">*</span>
                        </label>
                        <input type="text" id="trade_name" name="trade_name" required maxlength="200" 
                               value="<?php echo htmlspecialchars($company['trade_name']); ?>" placeholder="Örn: ABC Ticaret Limited Şirketi">
                        <div class="error-message" id="trade_name_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="address">
                            Firma Adresi <span class="required">*</span>
                        </label>
                        <textarea id="address" name="address" required placeholder="Tam adres bilgisini giriniz..."><?php echo htmlspecialchars($company['address']); ?></textarea>
                        <div class="error-message" id="address_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="invoice_address">
                            Fatura Adresi
                        </label>
                        <textarea id="invoice_address" name="invoice_address" placeholder="Fatura adresi (boş bırakılırsa firma adresi kullanılır)"><?php echo htmlspecialchars($company['invoice_address']); ?></textarea>
                        <div class="error-message" id="invoice_address_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="tax_number">
                            Vergi Numarası <span class="required">*</span>
                        </label>
                        <input type="text" id="tax_number" name="tax_number" required maxlength="20" 
                               value="<?php echo htmlspecialchars($company['tax_number']); ?>" placeholder="10 veya 11 haneli vergi numarası">
                        <div class="error-message" id="tax_number_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="tax_office">
                            Vergi Dairesi <span class="required">*</span>
                        </label>
                        <input type="text" id="tax_office" name="tax_office" required maxlength="100" 
                               value="<?php echo htmlspecialchars($company['tax_office']); ?>" placeholder="Örn: Beşiktaş Vergi Dairesi">
                        <div class="error-message" id="tax_office_error"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="section-title">İletişim Bilgileri</h3>
                    
                    <div class="form-group">
                        <label for="website">
                            Web Sitesi
                        </label>
                        <input type="text" id="website" name="website" maxlength="200" 
                               value="<?php echo htmlspecialchars($company['website']); ?>" placeholder="https://www.example.com">
                        <div class="error-message" id="website_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            Telefon
                        </label>
                        <input type="tel" id="phone" name="phone" maxlength="20" 
                               value="<?php echo htmlspecialchars($company['phone']); ?>" placeholder="+90 212 XXX XX XX">
                        <div class="error-message" id="phone_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="corporate_email">
                            Kurumsal İletişim E-postası
                        </label>
                        <input type="email" id="corporate_email" name="corporate_email" maxlength="100" 
                               value="<?php echo htmlspecialchars($company['corporate_email']); ?>" placeholder="info@example.com">
                        <div class="error-message" id="corporate_email_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="authorized_person">
                            Yetkili Kişi Adı ve Soyadı
                        </label>
                        <input type="text" id="authorized_person" name="authorized_person" maxlength="100" 
                               value="<?php echo htmlspecialchars($company['authorized_person']); ?>" placeholder="İmza yetkili kişi">
                        <div class="error-message" id="authorized_person_error"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="section-title">İrtibat Kişisi</h3>
                    
                    <div class="form-group">
                        <label for="contact_person">
                            İrtibat Kişisi Adı ve Soyadı <span class="required">*</span>
                        </label>
                        <input type="text" id="contact_person" name="contact_person" required maxlength="100" 
                               value="<?php echo htmlspecialchars($company['contact_person']); ?>" placeholder="İrtibat kurulacak kişi">
                        <div class="error-message" id="contact_person_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">
                            İrtibat Kişi Telefonu <span class="required">*</span>
                        </label>
                        <input type="tel" id="contact_phone" name="contact_phone" required maxlength="20" 
                               value="<?php echo htmlspecialchars($company['contact_phone']); ?>" placeholder="+90 5XX XXX XX XX">
                        <div class="error-message" id="contact_phone_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="contact_email">
                            İrtibat Kişi E-postası <span class="required">*</span>
                        </label>
                        <input type="email" id="contact_email" name="contact_email" required maxlength="100" 
                               value="<?php echo htmlspecialchars($company['contact_email']); ?>" placeholder="irtibat@example.com">
                        <div class="error-message" id="contact_email_error"></div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    💾 Firmayı Güncelle
                </button>
                <a href="company_management.php" class="btn btn-secondary">
                    ✖ İptal Et
                </a>
            </div>
        </form>
    </div>

    <div class="loading" id="loading">
        <div class="loading-content">
            <div class="spinner"></div>
            <p>Firma güncelleniyor...</p>
        </div>
    </div>

    <script>
        document.getElementById('companyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            clearErrors();
            if (!validateForm()) {
                return;
            }
            
            const companyId = <?php echo $companyId; ?>;
            
            document.getElementById('loading').style.display = 'block';
            
            const shortName = document.getElementById('short_name').value.trim();
            const tradeName = document.getElementById('trade_name').value.trim();
            const taxNumber = document.getElementById('tax_number').value.trim();
            
            Promise.all([
                checkFieldExists('short_name', shortName, companyId),
                checkFieldExists('trade_name', tradeName, companyId),
                checkFieldExists('tax_number_only', taxNumber, companyId)
            ]).then(results => {
                const hasErrors = results.some(result => result.exists);
                
                if (hasErrors) {
                    document.getElementById('loading').style.display = 'none';
                    return;
                }
                
                submitFormAjax();
            }).catch(error => {
                console.error('Kontrol hatası:', error);
                document.getElementById('loading').style.display = 'none';
                alert('Kontrol sırasında bir hata oluştu. Lütfen tekrar deneyin.');
            });
        });

        function submitFormAjax() {
            const formData = new FormData(document.getElementById('companyForm'));
            
            fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessAndRedirect();
                } else {
                    document.getElementById('loading').style.display = 'none';
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            showError(field, data.errors[field]);
                        });
                    } else {
                        alert('Bir hata oluştu: ' + (data.message || 'Bilinmeyen hata'));
                    }
                }
            })
            .catch(error => {
                console.error('Form gönderme hatası:', error);
                document.getElementById('loading').style.display = 'none';
                alert('Bir hata oluştu. Lütfen tekrar deneyin.');
            });
        }

        function showSuccessAndRedirect() {
            const loadingContent = document.querySelector('.loading-content');
            loadingContent.innerHTML = `
                <div style="color: #28a745; font-size: 48px; margin-bottom: 20px;">✅</div>
                <h3 style="color: #28a745; margin-bottom: 15px; font-size: 1.5em;">Başarılı!</h3>
                <p style="font-size: 1.1em; margin-bottom: 10px;">Firma başarıyla güncellendi</p>
                <p style="font-size: 0.9em; color: #666; margin-top: 15px;">
                    Firma yönetim sayfasına yönlendiriliyorsunuz...
                </p>
            `;
            setTimeout(() => {
                window.location.href = 'company_management.php';
            }, 2000);
        }

        function checkFieldExists(type, value, excludeId) {
            if (!value || value.trim() === '') {
                return Promise.resolve({ exists: false });
            }
            
            const requestBody = {
                type: type,
                exclude_id: excludeId
            };
            
            if (type === 'short_name') {
                requestBody.short_name = value;
            } else if (type === 'trade_name') {
                requestBody.trade_name = value;
            } else if (type === 'tax_number_only') {
                requestBody.tax_number = value;
            }
            
            return fetch('check_company_exists.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody)
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    let fieldName = type === 'tax_number_only' ? 'tax_number' : type;
                    showError(fieldName, data.message);
                }
                return data;
            });
        }

        function checkShortName(shortName, excludeId) {
            if (!shortName || shortName.trim() === '') return;
            
            fetch('check_company_exists.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: 'short_name',
                    short_name: shortName,
                    exclude_id: excludeId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    showError('short_name', data.message || 'Bu kısa ad ile zaten bir firma kayıtlı');
                } else {
                    clearFieldError('short_name');
                }
            })
            .catch(error => {
                console.error('Kontrol hatası:', error);
            });
        }

        function checkTradeName(tradeName, excludeId) {
            if (!tradeName || tradeName.trim() === '') return;
            
            fetch('check_company_exists.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: 'trade_name',
                    trade_name: tradeName,
                    exclude_id: excludeId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    showError('trade_name', data.message || 'Bu ticari ünvan ile zaten bir firma kayıtlı');
                } else {
                    clearFieldError('trade_name');
                }
            })
            .catch(error => {
                console.error('Kontrol hatası:', error);
            });
        }

        function checkTaxNumber(taxNumber, excludeId) {
            if (!taxNumber || taxNumber.trim() === '') return;
            
            fetch('check_company_exists.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: 'tax_number_only',
                    tax_number: taxNumber,
                    exclude_id: excludeId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    showError('tax_number', data.message || 'Bu vergi numarası ile kayıtlı firma zaten mevcut!');
                } else {
                    clearFieldError('tax_number');
                }
            })
            .catch(error => {
                console.error('Kontrol hatası:', error);
            });
        }

        function validateForm() {
            let isValid = true;
            const requiredFields = [
                'short_name',
                'trade_name', 
                'address',
                'contact_person',
                'contact_phone',
                'contact_email',
                'tax_office',
                'tax_number'
            ];
            
            requiredFields.forEach(field => {
                const element = document.getElementById(field);
                if (!element.value.trim()) {
                    showError(field, 'Bu alan zorunludur');
                    isValid = false;
                }
            });

            const emailFields = ['corporate_email', 'contact_email'];
            emailFields.forEach(field => {
                const element = document.getElementById(field);
                if (element.value.trim() && !isValidEmail(element.value)) {
                    showError(field, 'Geçerli bir e-mail adresi giriniz');
                    isValid = false;
                }
            });

            const website = document.getElementById('website');
            if (website.value.trim() && !isValidWebsite(website.value)) {
                showError('website', 'Geçerli bir web sitesi adresi giriniz');
                isValid = false;
            }

            const taxNumber = document.getElementById('tax_number');
            if (taxNumber.value.trim() && !isValidTaxNumber(taxNumber.value)) {
                showError('tax_number', 'Vergi numarası 10 veya 11 haneli olmalıdır');
                isValid = false;
            }
            
            return isValid;
        }

        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + '_error');
            
            field.classList.add('error');
            errorDiv.textContent = message;
        }

        function clearErrors() {
            const errorMessages = document.querySelectorAll('.error-message');
            const errorFields = document.querySelectorAll('.error');
            
            errorMessages.forEach(msg => msg.textContent = '');
            errorFields.forEach(field => field.classList.remove('error'));
        }

        function clearFieldError(fieldId) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + '_error');
            
            field.classList.remove('error');
            errorDiv.textContent = '';
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function isValidTaxNumber(taxNumber) {
            const cleaned = taxNumber.replace(/\D/g, '');
            return cleaned.length === 10 || cleaned.length === 11;
        }

        function isValidWebsite(website) {
            if (!website || website.trim() === '') return true; 
            website = website.trim().toLowerCase();
            
            const urlRegex = /^(https?:\/\/)?(www\.)?[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}(\/.*)?$/;
            
            return urlRegex.test(website);
        }

        document.getElementById('short_name').addEventListener('blur', function() {
            const shortName = this.value.trim();
            const companyId = <?php echo $companyId; ?>;
            if (shortName) {
                checkShortName(shortName, companyId);
            }
        });

        document.getElementById('trade_name').addEventListener('blur', function() {
            const tradeName = this.value.trim();
            const companyId = <?php echo $companyId; ?>;
            if (tradeName) {
                checkTradeName(tradeName, companyId);
            }
        });

        document.getElementById('tax_number').addEventListener('blur', function() {
            const taxNumber = this.value.trim();
            const companyId = <?php echo $companyId; ?>;
            if (taxNumber) {
                checkTaxNumber(taxNumber, companyId);
            }
        });

        document.getElementById('website').addEventListener('blur', function() {
            let value = this.value.trim();
            if (value && !value.startsWith('http://') && !value.startsWith('https://')) {
                if (value.startsWith('www.')) {
                    this.value = 'https://' + value;
                } 
                else if (value.includes('.') && !value.includes('/') && !value.includes(' ')) {
                    this.value = 'https://www.' + value;
                }
                else if (value.includes('.')) {
                    this.value = 'https://' + value;
                }
            }
        });

        document.getElementById('website').addEventListener('input', function() {
            const value = this.value.trim();
            if (value && !isValidWebsite(value)) {
                this.classList.add('error');
                document.getElementById('website_error').textContent = 'Geçerli bir web sitesi adresi giriniz';
            } else {
                this.classList.remove('error');
                document.getElementById('website_error').textContent = '';
            }
        });

        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^\d+\s\-()]/g, '');
            });
        });

        document.getElementById('tax_number').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>