<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$code = $_GET['code'] ?? '';
$st = db()->prepare("SELECT p.*, v.name AS visitor_name FROM visitor_passes p JOIN visitors v ON v.id=p.visitor_id WHERE code=?");
$st->execute([$code]);
$pass = $st->fetch();
$now = new DateTime();
$valid = false;
$msg = "Passe inválido.";
if ($pass) {
  $valid = ((int)$pass['status']===1) && (new DateTime($pass['valid_from']) <= $now) && (new DateTime($pass['valid_to']) >= $now);
  $msg = $valid ? "Passe válido" : "Passe inativo/expirado";
}
?><!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passe Temporário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-md-6">
          <div class="card shadow-sm">
            <div class="card-body text-center">
              <h1 class="h4 mb-3">Passe Temporário</h1>
              <?php if ($pass): ?>
                <div class="mb-2"><?= e($pass['visitor_name']) ?></div>
                <div class="mb-3 small text-muted"><?= e(now_br($pass['valid_from'])) ?> → <?= e(now_br($pass['valid_to'])) ?></div>
                <?php if ($valid): ?>
                  <span class="badge bg-success fs-6">VÁLIDO</span>
                <?php else: ?>
                  <span class="badge bg-secondary fs-6">INATIVO/EXPIRADO</span>
                <?php endif; ?>
              <?php else: ?>
                <div class="text-danger"><?= e($msg) ?></div>
              <?php endif; ?>
              <hr>
              <div class="small text-muted">Código: <?= e($code) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
