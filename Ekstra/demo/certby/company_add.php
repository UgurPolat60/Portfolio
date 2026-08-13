<?php require_once 'config.php'; requireLogin(); $userData = getUserData($_SESSION['user_id']); ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belgelendirme - Firma Ekle</title>
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
                <span>Hoşgeldiniz, <?php echo isset($userData) ? htmlspecialchars($userData['full_name']) : 'Kullanıcı'; ?></span>
                <a href="dashboard.php" class="back-btn">← Ana Sayfa</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="page-title">
            <h1>Yeni Firma Ekle</h1>
        </div>

        <form id="companyForm" action="process_company.php" method="POST">
            <div class="form-sections">
                <div class="form-section">
                    <h3 class="section-title">Firma Bilgileri</h3>
                    
                    <div class="form-group">
                        <label for="short_name">
                            Firma Kısa Adı <span class="required">*</span>
                        </label>
                        <input type="text" id="short_name" name="short_name" required maxlength="100" placeholder="Örn: ABC Ltd.">
                        <div class="error-message" id="short_name_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="trade_name">
                            Firma Ticari Ünvanı <span class="required">*</span>
                        </label>
                        <input type="text" id="trade_name" name="trade_name" required maxlength="200" placeholder="Örn: ABC Ticaret Limited Şirketi">
                        <div class="error-message" id="trade_name_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="address">
                            Firma Adresi <span class="required">*</span>
                        </label>
                        <textarea id="address" name="address" required placeholder="Tam adres bilgisini giriniz..."></textarea>
                        <div class="error-message" id="address_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="invoice_address">
                            Fatura Adresi
                        </label>
                        <textarea id="invoice_address" name="invoice_address" placeholder="Fatura adresinizi giriniz... "></textarea>
                        <div class="error-message" id="invoice_address_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="tax_number">
                            Vergi Numarası <span class="required">*</span>
                        </label>
                        <input type="text" id="tax_number" name="tax_number" required maxlength="20" placeholder="10 veya 11 haneli vergi numarası">
                        <div class="error-message" id="tax_number_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="tax_office">
                            Vergi Dairesi <span class="required">*</span>
                        </label>
                        <input type="text" id="tax_office" name="tax_office" required maxlength="100" placeholder="Örn: Beşiktaş Vergi Dairesi">
                        <div class="error-message" id="tax_office_error"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="section-title">İletişim Bilgileri</h3>
                    
                    <div class="form-group">
                        <label for="website">
                            Web Sitesi
                        </label>
                        <input type="text" id="website" name="website" maxlength="200" placeholder="https://www.example.com">
                        <div class="error-message" id="website_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            Telefon
                        </label>
                        <input type="tel" id="phone" name="phone" maxlength="20" placeholder="+90 212 XXX XX XX">
                        <div class="error-message" id="phone_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="corporate_email">
                            Kurumsal İletişim E-postası
                        </label>
                        <input type="email" id="corporate_email" name="corporate_email" maxlength="100" placeholder="info@example.com">
                        <div class="error-message" id="corporate_email_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="authorized_person">
                            Yetkili Kişi Adı ve Soyadı
                        </label>
                        <input type="text" id="authorized_person" name="authorized_person" maxlength="100" placeholder="İmza yetkili kişi">
                        <div class="error-message" id="authorized_person_error"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="section-title">İrtibat Kişisi</h3>
                    <div class="form-group">
                        <label for="contact_person">
                            Kişi Adı ve Soyadı <span class="required">*</span>
                        </label>
                        <input type="text" id="contact_person" name="contact_person" required maxlength="100" placeholder="İrtibat kurulacak kişi">
                        <div class="error-message" id="contact_person_error"></div>
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">
                            Kişi Telefonu <span class="required">*</span>
                        </label>
                        <input type="tel" id="contact_phone" name="contact_phone" required maxlength="20" placeholder="+90 5XX XXX XX XX">
                        <div class="error-message" id="contact_phone_error"></div>
                    </div>

                    <div class="form-group">
                        <label for="contact_email">
                            Kişi E-postası <span class="required">*</span>
                        </label>
                        <input type="email" id="contact_email" name="contact_email" required maxlength="100" placeholder="irtibat@example.com">
                        <div class="error-message" id="contact_email_error"></div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    💾 Firmayı Kaydet
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    ❌ İptal Et
                </a>
            </div>
        </form>
    </div>

    <div class="loading" id="loading">
        <div class="loading-content">
            <div class="spinner"></div>
            <p>Firma kaydediliyor...</p>
        </div>
    </div>

    <script>
        document.getElementById('companyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            clearErrors();
            if (!validateForm()) {
                return;
            }
            const taxNumber = document.getElementById('tax_number').value.trim();
            
            if (taxNumber) {
                document.getElementById('loading').style.display = 'block';
                checkTaxNumberFinal(taxNumber, () => {
                    submitFormAjax();
                }, () => {
                    document.getElementById('loading').style.display = 'none';
                });
            } else {
                document.getElementById('loading').style.display = 'block';
                submitFormAjax();
            }
        });
        function submitFormAjax() {
            const formData = new FormData(document.getElementById('companyForm'));
            
            fetch('process_company.php', {
                method: 'POST',
                body: formData
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
                        alert('Bu vergi numarası ile kayıtlı firma zaten mevcut!');
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
                <p style="font-size: 1.1em; margin-bottom: 10px;">Firma başarıyla eklendi</p>
                <p style="font-size: 0.9em; color: #666; margin-top: 15px;">
                    Firma yönetim sayfasına yönlendiriliyorsunuz...
                </p>
            `;
            setTimeout(() => {
                window.location.href = 'company_management.php';
            }, 2000);
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
        document.getElementById('tax_number').addEventListener('blur', function() {
            const taxNumber = this.value.trim();
            if (taxNumber) {
                checkTaxNumber(taxNumber);
            }
        });
        function checkTaxNumber(taxNumber) {
    fetch('check_company_exists.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            type: 'tax_number_only',
            tax_number: taxNumber
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

function checkTaxNumberFinal(taxNumber, successCallback, errorCallback) {
    fetch('check_company_exists.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            type: 'tax_number_only',
            tax_number: taxNumber
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.exists) {
            showError('tax_number', data.message || 'Bu vergi numarası ile kayıtlı firma zaten mevcut!');
            errorCallback();
        } else {
            successCallback();
        }
    })
    .catch(error => {
        console.error('Kontrol hatası:', error);
        successCallback();
    });
}

function checkShortName(shortName) {
    if (!shortName || shortName.trim() === '') return;
    
    fetch('check_company_exists.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            type: 'short_name',
            short_name: shortName
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

function checkTradeName(tradeName) {
    if (!tradeName || tradeName.trim() === '') return;
    
    fetch('check_company_exists.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            type: 'trade_name',
            trade_name: tradeName
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

document.getElementById('short_name').addEventListener('blur', function() {
    const shortName = this.value.trim();
    if (shortName) {
        checkShortName(shortName);
    }
});

document.getElementById('trade_name').addEventListener('blur', function() {
    const tradeName = this.value.trim();
    if (tradeName) {
        checkTradeName(tradeName);
    }
});
    </script>
</body>
</html>