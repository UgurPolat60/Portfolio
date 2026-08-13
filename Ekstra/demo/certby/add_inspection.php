<?php
require_once 'config.php';
requireLogin();
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
try {
    $pdo = getConnection();
} catch(Exception $e) {
    die('Bir hata oluştu');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfOnPost();
    try {
        $company_id = $_POST['company_id'];
        $completed_inspections = $_POST['completed_inspections'];
        $inspection_notes = $_POST['inspection_notes'] ?? '';
        $uploaded_files = [];
        if (isset($_FILES['inspection_files']) && !empty($_FILES['inspection_files']['name'][0])) {
            $upload_dir = "uploads/temp_inspection_reports/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_count = count($_FILES['inspection_files']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                $error = $_FILES['inspection_files']['error'][$i];
                if ($error === UPLOAD_ERR_OK) {
                    $original_name = $_FILES['inspection_files']['name'][$i];
                    $tmp_path = $_FILES['inspection_files']['tmp_name'][$i];
                    $size_bytes = $_FILES['inspection_files']['size'][$i];

                    if (!isValidUpload($tmp_path, $original_name, $size_bytes)) { throw new Exception('İstek geçersiz'); }

                    $safe_ext = getFileExtensionSafe($original_name);
                    $file_name = time() . "_{$company_id}_temp_{$i}." . $safe_ext;
                    $file_path = $upload_dir . $file_name;

                    if (!move_uploaded_file($tmp_path, $file_path)) { throw new Exception('İşlem gerçekleştirilemedi'); }

                    $uploaded_files[] = $file_path;
                } elseif ($error !== UPLOAD_ERR_NO_FILE) {
                    throw new Exception('Bir hata oluştu');
                }
            }
        }
        $inspection_files_json = !empty($uploaded_files) ? json_encode($uploaded_files) : null;
        $stmt = $pdo->prepare("INSERT INTO inspection_temp_records (company_id, completed_inspections, inspection_notes, inspection_files, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$company_id, $completed_inspections, $inspection_notes, $inspection_files_json, $user_id]);
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, log_type, level, content, ip_address) VALUES (?, 'data_change', 'INFO', ?, ?)");
        $stmt->execute([$user_id, "Tetkik ile firma eklendi: Geçici kayıt", $_SERVER['REMOTE_ADDR'] ?? '::1']);
        
        $success_message = "Tetkik kaydı başarıyla eklendi! Planlama sayfasından belgelendirme bilgilerini tamamlayabilirsiniz.";
        
    } catch(PDOException $e) {
        $error_message = 'Bir hata oluştu';
    }
}
$companies_stmt = $pdo->prepare("SELECT id, short_name, trade_name FROM companies WHERE status = 'active' ORDER BY short_name");
$companies_stmt->execute();
$companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tetkik ile Firma Ekle - Belgelendirme</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background-color: #6c5ce7;
            padding: 1rem 0;
        }

        .navbar .btn {
            margin-left: 10px;
        }

        .main-container {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: white;
            margin-bottom: 2rem;
        }

        .card-header {
            background-color: #6c5ce7;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .btn-primary {
            background-color: #6c5ce7;
            border-color: #6c5ce7;
            border-radius: 10px;
            padding: 0.7rem 1.5rem;
            font-weight: 500;
        }

        .btn-primary:hover {
            background-color: #5a4fcf;
            border-color: #5a4fcf;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.7rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #6c5ce7;
            box-shadow: 0 0 0 0.2rem rgba(108, 92, 231, 0.25);
        }

        .alert {
            border: none;
            border-radius: 15px;
            padding: 1rem 1.5rem;
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
            border-left: 4px solid #00b894;
        }

        .alert-danger {
            background: rgba(232, 67, 147, 0.1);
            color: #e84393;
            border-left: 4px solid #e84393;
        }

        .searchable-select {
            position: relative;
        }

        .searchable-select .select-wrapper {
            position: relative;
        }

        .searchable-select input[type="text"] {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .searchable-select input[type="text"]:focus {
            outline: none;
            border-color: #6c5ce7;
            box-shadow: 0 0 0 0.2rem rgba(108, 92, 231, 0.25);
        }

        .searchable-select .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e9ecef;
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .searchable-select .dropdown-list.show {
            display: block;
        }

        .searchable-select .dropdown-item {
            padding: 0.7rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
        }

        .searchable-select .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .searchable-select .dropdown-item.selected {
            background-color: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
            font-weight: 500;
        }

        .searchable-select .dropdown-arrow {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #666;
            font-size: 12px;
        }

        .file-upload-area {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .file-upload-area:hover {
            border-color: #6c5ce7;
            background: rgba(108, 92, 231, 0.05);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <div class="navbar-nav ms-auto">
                <a href="plans.php" class="btn btn-light me-2">
                    <i class="fas fa-arrow-left me-1"></i>Planlamaya Dön
                </a>
            </div>
        </div>
    </nav>

    <div class="container main-container" style="margin-top: 6rem;">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    Tetkik ile Firma Ekle
                </h4>
                
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="inspectionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_id" class="form-label">Firma <span class="text-danger">*</span></label>
                                <div class="searchable-select" id="companySearchable">
                                    <div class="select-wrapper">
                                        <input type="text" id="companySearch" placeholder="Firma arayın veya seçin..." autocomplete="off" required>
                                        <span class="dropdown-arrow">▼</span>
                                        <div class="dropdown-list" id="companyDropdown">
                                            <?php foreach ($companies as $company): ?>
                                                <div class="dropdown-item" data-value="<?php echo $company['id']; ?>" data-text="<?php echo htmlspecialchars($company['short_name'] . ' - ' . $company['trade_name']); ?>">
                                                    <?php echo htmlspecialchars($company['short_name'] . ' - ' . $company['trade_name']); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <input type="hidden" id="company_id" name="company_id" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="completed_inspections" class="form-label">Tamamlanan Tetkik Sayısı <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="completed_inspections" name="completed_inspections" placeholder="Tetkik sayısını giriniz" min="1" max="2" required>
                                 
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="inspection_notes" class="form-label">Tetkik Notları</label>
                        <textarea class="form-control" id="inspection_notes" name="inspection_notes" rows="4" placeholder="Tetkik ile ilgili notlarınızı buraya yazabilirsiniz..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="inspection_files" class="form-label">Tetkik Dosyaları</label>
                        <div class="file-upload-area">
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <h6>Tetkik dosyalarını yükleyin</h6>
                            <input type="file" class="form-control" id="inspection_files" name="inspection_files[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple>
                            <small class="text-muted mt-2 d-block">PDF, Word veya resim dosyaları yükleyebilirsiniz. </small>
                        </div>
                    </div>

                    <hr class="my-4">
                    
                    <div class="text-end">
                        <button type="reset" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-redo me-1"></i>Temizle
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Tetkik Kaydını Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function initSearchableSelect(searchInputId, dropdownId, hiddenInputId) {
            const searchInput = document.getElementById(searchInputId);
            const dropdown = document.getElementById(dropdownId);
            const hiddenInput = document.getElementById(hiddenInputId);
            const allItems = dropdown.querySelectorAll('.dropdown-item');
            searchInput.addEventListener('click', function() {
                dropdown.classList.add('show');
                filterItems('');
            });
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filterItems(searchTerm);
                dropdown.classList.add('show');
            });
            document.addEventListener('click', function(e) {
                if (!searchInput.parentElement.parentElement.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
            allItems.forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.dataset.value;
                    const text = this.dataset.text;
                    
                    searchInput.value = text;
                    hiddenInput.value = value;
                    dropdown.classList.remove('show');
                    allItems.forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });
            function filterItems(searchTerm) {
                let hasResults = false;
                
                allItems.forEach(item => {
                    const text = item.dataset.text.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = 'block';
                        hasResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
                let noResultsItem = dropdown.querySelector('.no-results');
                if (!hasResults && searchTerm) {
                    if (!noResultsItem) {
                        noResultsItem = document.createElement('div');
                        noResultsItem.className = 'dropdown-item no-results';
                        noResultsItem.textContent = 'Sonuç bulunamadı';
                        noResultsItem.style.color = '#666';
                        noResultsItem.style.fontStyle = 'italic';
                        noResultsItem.style.cursor = 'default';
                        dropdown.appendChild(noResultsItem);
                    }
                    noResultsItem.style.display = 'block';
                } else if (noResultsItem) {
                    noResultsItem.style.display = 'none';
                }
            }
            return {
                clear: function() {
                    searchInput.value = '';
                    hiddenInput.value = '';
                    allItems.forEach(i => i.classList.remove('selected'));
                }
            };
        }
        document.addEventListener('DOMContentLoaded', function() {
            const companySelect = initSearchableSelect('companySearch', 'companyDropdown', 'company_id');
            document.getElementById('inspectionForm').addEventListener('reset', function() {
                setTimeout(() => {
                    companySelect.clear();
                }, 10);
            });
        });
    </script>
</body>
</html>