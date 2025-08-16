<?php
// public/pass_qr.php — exibe o QR do passe
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

$token = trim($_GET['token'] ?? '');
$st = db()->prepare("SELECT p.*, v.name AS visitor_name FROM visitor_passes p JOIN visitors v ON v.id=p.visitor_id WHERE p.token=? LIMIT 1");
$st->execute([$token]);
$pass = $st->fetch();
if (!$pass) { http_response_code(404); echo "Passe não encontrado."; exit; }

// URL que será codificada no QR (validação online)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
$base = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').rtrim(dirname($_SERVER['SCRIPT_NAME']),'/');
$checkUrl = $base . '/qr_check.php?token=' . urlencode($token);
?><!doctype html>
<html lang="pt-br">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Passe de Visitante - <?= e($pass['visitor_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<body class="bg-light">
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h1 class="h5 mb-1">Passe de Visitante</h1>
          <div class="text-muted mb-3"><?= e($pass['visitor_name']) ?></div>

          <div id="qrcode" class="d-inline-block p-2 bg-white rounded border"></div>
          <div class="small text-muted mt-2">Apresentar na portaria</div>

          <hr>
          <div class="row text-start small">
            <div class="col-6"><strong>Válido de:</strong><br><?= e($pass['valid_from'] ? date('d/m/Y H:i', strtotime($pass['valid_from'])) : 'imediato') ?></div>
            <div class="col-6"><strong>Válido até:</strong><br><?= e($pass['valid_until'] ? date('d/m/Y H:i', strtotime($pass['valid_until'])) : 'indefinido') ?></div>
            <div class="col-6 mt-2"><strong>Usos:</strong><br><?= (int)$pass['uses'] ?>/<?= (int)$pass['max_uses'] ?></div>
            <div class="col-6 mt-2"><strong>Status:</strong><br><?= e($pass['status']) ?></div>
          </div>

          <div class="mt-3 d-print-none">
            <a class="btn btn-outline-secondary" href="<?= e($checkUrl) ?>" target="_blank">Abrir verificação</a>
            <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
          </div>
        </div>
      </div>
      <p class="text-center small text-muted mt-3">Token: <?= e($token) ?></p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
  QRCode.toCanvas(document.getElementById('qrcode'), <?= json_encode($checkUrl) ?>, { width: 256 });
</script>
</body></html>
