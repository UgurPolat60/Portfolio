<?php

$directories = [
    'uploads',
    'uploads/inspection_reports',
    'uploads/mail_attachments'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Klasör oluşturuldu: $dir\n";
        } else {
            echo "❌ Klasör oluşturulamadı: $dir\n";
        }
    } else {
        echo "ℹ️ Klasör zaten mevcut: $dir\n";
    }
}

$htaccessContent = "# Uploads directory rules (Apache 2.4 syntax)\nOptions -Indexes\n\n# Allow only safe downloadable types\n<FilesMatch \\\"\\\\.(pdf|doc|docx|jpg|jpeg|png)$\\\">\n    Require all granted\n</FilesMatch>\n\n# Deny script and markup execution\n<FilesMatch \\\"\\\\.(php|phtml|phar|html|htm|js|cgi|pl|sh)$\\\">\n    Require all denied\n</FilesMatch>\n\n# Prevent content-type sniffing\nHeader set X-Content-Type-Options nosniff";

$htaccessPath = 'uploads/.htaccess';
if (file_put_contents($htaccessPath, $htaccessContent) !== false) {
    echo "✅ .htaccess dosyası güncellendi: $htaccessPath\n";
} else {
    echo "❌ .htaccess dosyası yazılamadı: $htaccessPath\n";
}

echo "\n🎉 Kurulum tamamlandı!\n";
?>