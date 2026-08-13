<?php
/* Demo inbox: shows the mail the platform just sent. The SMTP path is real —
   PHPMailer connects, authenticates and delivers — the catcher simply keeps the
   message here instead of handing it to the internet. */

$dir = dirname(__DIR__) . '/mail';
$files = is_dir($dir) ? glob($dir . '/*.eml') : [];
rsort($files);
$files = array_slice($files, 0, 40);

function headerValue($raw, $name) {
    if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/mi', $raw, $m)) {
        $v = trim($m[1]);
        /* decode the RFC 2047 words PHPMailer uses for non-ASCII subjects */
        $d = iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        return $d !== false ? $d : $v;
    }
    return '';
}

function bodyOf($raw) {
    $parts = preg_split("/\r?\n\r?\n/", $raw, 2);
    $body = $parts[1] ?? '';
    if (stripos($raw, 'Content-Transfer-Encoding: base64') !== false) {
        $decoded = base64_decode(preg_replace('/\s+/', '', strtok($body, '-')), true);
        if ($decoded !== false && $decoded !== '') {
            $body = $decoded;
        }
    } elseif (stripos($raw, 'Content-Transfer-Encoding: quoted-printable') !== false) {
        $body = quoted_printable_decode($body);
    }
    return $body;
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Demo Gelen Kutusu — Belgelendirme</title>
<style>
  body { margin:0; background:#f4f6fa; font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif; color:#222; }
  header { background:#667eea; color:#fff; padding:22px clamp(16px,4vw,40px); }
  header h1 { margin:0 0 4px; font-size:1.3rem; }
  header p { margin:0; opacity:.9; font-size:.9rem; }
  main { max-width:900px; margin:0 auto; padding:24px clamp(12px,4vw,24px) 60px; }
  .note { background:#fff; border-left:3px solid #667eea; border-radius:8px;
          padding:14px 18px; margin-bottom:22px; font-size:.9rem; color:#555; }
  .msg { background:#fff; border:1px solid #e3e7ef; border-radius:10px;
         margin-bottom:14px; overflow:hidden; }
  .msg summary { cursor:pointer; padding:14px 18px; list-style:none; }
  .msg summary::-webkit-details-marker { display:none; }
  .msg h2 { margin:0 0 4px; font-size:1rem; }
  .meta { font:12px ui-monospace,Consolas,monospace; color:#8a92a4; }
  .body { border-top:1px solid #e3e7ef; padding:16px 18px; background:#fbfcfe; }
  .body iframe { width:100%; min-height:340px; border:1px solid #e3e7ef;
                 border-radius:6px; background:#fff; }
  .empty { background:#fff; border:1px dashed #c9d0de; border-radius:10px;
           padding:40px; text-align:center; color:#8a92a4; }
  a.back { color:#fff; text-decoration:none; border-bottom:1px solid rgba(255,255,255,.5); }
</style>
</head>
<body>

<header>
  <h1>Demo Gelen Kutusu</h1>
  <p>Platformun gönderdiği e-postalar burada birikir · <a class="back" href="dashboard.php">panele dön</a></p>
</header>

<main>
  <div class="note">
    SMTP yolunun tamamı gerçekten çalışıyor: PHPMailer bağlanıyor, kimlik
    doğruluyor ve mesajı teslim ediyor. Tek fark, mesajı internete değil buraya
    teslim ediyor olması — herkese açık bir demoyu canlı bir posta hesabına
    bağlamak, onu spam aktarıcısına çevirmek olurdu.
  </div>

<?php if (!$files): ?>
  <div class="empty">
    Henüz e-posta yok. Panelden bir mail gönder, sayfayı yenile.
  </div>
<?php else: foreach ($files as $i => $file):
    $raw = file_get_contents($file);
    $subject = headerValue($raw, 'Subject') ?: '(konu yok)';
    $to      = headerValue($raw, 'X-Demo-To');
    $from    = headerValue($raw, 'X-Demo-From');
    $when    = date('d.m.Y H:i:s', (int) filemtime($file));
    $body    = bodyOf($raw);
?>
  <details class="msg"<?= $i === 0 ? ' open' : '' ?>>
    <summary>
      <h2><?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></h2>
      <span class="meta">
        <?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>
        &rarr; <?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>
        &nbsp;·&nbsp; <?= $when ?>
      </span>
    </summary>
    <div class="body">
      <iframe sandbox srcdoc="<?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?>"></iframe>
    </div>
  </details>
<?php endforeach; endif; ?>
</main>

</body>
</html>
