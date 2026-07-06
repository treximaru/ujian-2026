<?php
session_start();
header("Content-Type: text/html; charset=utf-8");

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$xreq = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
$sec_ch_ua = $_SERVER['HTTP_SEC_CH_UA'] ?? '';

$ok_patterns = ['com.duckduckgo.mobile.android','com.duckduckgo.mobile.ios','duckduckgo'];

function contains_any($haystack, $patterns) {
  foreach ($patterns as $p) {
    if ($p !== '' && stripos($haystack, $p) !== false) return true;
  }
  return false;
}

$bypass_tokens = [];
$env = getenv('BYPASS_TOKENS');
if ($env !== false && $env !== '') {
  $bypass_tokens = array_map('trim', explode(',', $env));
}

$allowed = false;
if (contains_any($ua, $ok_patterns)) $allowed = true;
if (contains_any($xreq, $ok_patterns)) $allowed = true;
if (contains_any($sec_ch_ua, $ok_patterns)) $allowed = true;

$input_token = '';
if (isset($_GET['allow'])) $input_token = trim((string)$_GET['allow']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bypass_token'])) {
  $input_token = trim((string)$_POST['bypass_token']);
}

if ($input_token !== '') {
  if (in_array($input_token, $bypass_tokens, true)) {
    $_SESSION['bypass_granted'] = true;
    header('Location: index.html');
    exit;
  } else {
    $_SESSION['bypass_error'] = 'Token tidak valid.';
  }
}

if (!empty($_SESSION['bypass_granted'])) {
  header('Location: index.html');
  exit;
}

$err = $_SESSION['bypass_error'] ?? '';
unset($_SESSION['bypass_error']);

echo "<!doctype html><html lang='id'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Akses Ditolak</title>
<style>
*{box-sizing:border-box} body{font-family:system-ui,sans-serif;padding:20px;background:#fff;color:#111}
.card{max-width:480px;margin:40px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08)}
h2{color:#b91c1c;margin-top:0} .actions a{display:inline-block;margin-right:8px;margin-top:8px;text-decoration:none;background:#16a34a;color:#fff;padding:10px 14px;border-radius:8px}
.actions a.app{background:#111} input,button{width:100%;padding:10px;margin-top:10px;border-radius:8px;border:1px solid #ccc}
button{background:#2563eb;color:#fff;border:none} .msg{color:#b91c1c;margin-top:8px;text-align:center}
</style>
</head><body>
<div class='card'>
<h2>Akses Ditolak — Hanya DuckDuckGo Browser yang Diperbolehkan!</h2>
<p>Silakan gunakan DuckDuckGo Browser. Unduh dari tautan berikut:</p>
<div class='actions'>
<a href='https://play.google.com/store/apps/details?id=com.duckduckgo.mobile.android' target='_blank' rel='noopener'>Google Play</a>
<a class='app' href='https://apps.apple.com/app/duckduckgo-privacy-browser/id663592361' target='_blank' rel='noopener'>App Store</a>
</div>
<p>Atau masukkan token bypass untuk sementara:</p>
<form method='post' action=''>
<input type='text' name='bypass_token' placeholder='Masukkan token bypass' autocomplete='off' />
<button type='submit'>OK</button>
</form>";

if ($err) {
$safe = htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo "<div class='msg'>{$safe}</div>";
}

echo "</div></body></html>";
