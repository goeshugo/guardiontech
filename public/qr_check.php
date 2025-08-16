<?php
// public/qr_check.php — valida passe e consome uso
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

$pdo = db();
$token = trim($_GET['token'] ?? '');
$now = date('Y-m-d H:i:s');

$pdo->beginTransaction();
// trava o registro para checagem/consumo atômico
$st = $pdo->prepare("SELECT p.*, v.name AS visitor_name FROM visitor_passes p JOIN visitors v ON v.id=p.visitor_id WHERE p.token=? FOR UPDATE");
$st->execute([$token]);
$pass = $st->fetch();

function finish($pdo){ if($pdo->inTransaction()) $pdo->commit(); }

$status = 'DENY'; $reason = '';
if (!$pass) { $reason = 'Passe não encontrado.'; finish($pdo); }
else {
  // expira automaticamente
  if ($pass['valid_until'] && strtotime($pass['valid_until']) < time()) {
    $pdo->prepare("UPDATE visitor_passes SET status='EXPIRED' WHERE id=?")->execute([$pass['id']]);
    $pass['status'] = 'EXPIRED';
  }
  if ($pass['status'] === 'REVOKED') $reason = 'Passe revogado.';
  elseif ($pass['status'] === 'EXPIRED') $reason = 'Passe expirado.';
  elseif ($pass['uses'] >= $pass['max_uses']) $reason = 'Limite de usos atingido.';
  elseif ($pass['valid_from'] && strtotime($pass['valid_from']) > time()) $reason = 'Passe ainda não está válido.';
  else {
    // consumir 1 uso
    $upd = $pdo->prepare("UPDATE visitor_passes SET uses=uses+1, last_used_at=?, status=IF(uses+1>=max_uses,'USED','ACTIVE') WHERE id=?");
    $upd->execute([$now, $pass['id']]);
    $status = 'ALLOW';
  }
  finish($pdo);
}

$ok = ($status === 'ALLOW');
?><!doctype html>
<html lang="pt-br">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verificação de Passe</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<body class="<?= $ok ? 'bg-success-subtle' : 'bg-danger-subtle' ?>">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card border-<?= $ok ? 'success' : 'danger' ?>">
        <div class="card-body text-center">
          <h1 class="display-6 mb-0"><?= $ok ? 'ACESSO LIBERADO' : 'ACESSO NEGADO' ?></h1>
          <p class="text-muted mb-4"><?= $ok ? 'Passe válido.' : e($reason) ?></p>

          <?php if($pass): ?>
          <div class="row text-start">
            <div class="col-md-6 mb-2"><strong>Visitante:</strong><br><?= e($pass['visitor_name']) ?></div>
            <div class="col-md-3 mb-2"><strong>Usos:</strong><br>
              <?php
              $stmt = db()->prepare("SELECT uses, max_uses FROM visitor_passes WHERE id=?");
              $stmt->execute([$pass['id']]);
              [$usesNow, $max] = array_values($stmt->fetch());
              echo (int)$usesNow . '/' . (int)$max;
              ?>
            </div>
            <div class="col-md-3 mb-2"><strong>Status:</strong><br>
              <?php
                $st2 = db()->prepare("SELECT status FROM visitor_passes WHERE id=?");
                $st2->execute([$pass['id']]); echo e($st2->fetchColumn());
              ?>
            </div>
            <div class="col-md-6 mb-2"><strong>Válido de:</strong><br><?= e($pass['valid_from'] ? date('d/m/Y H:i', strtotime($pass['valid_from'])) : 'imediato') ?></div>
            <div class="col-md-6 mb-2"><strong>Válido até:</strong><br><?= e($pass['valid_until'] ? date('d/m/Y H:i', strtotime($pass['valid_until'])) : 'indefinido') ?></div>
          </div>
          <?php endif; ?>

          <div class="mt-3">
            <a href="<?= e($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-secondary">Recarregar</a>
            <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
          </div>
        </div>
      </div>

      <p class="text-center small text-muted mt-3">Token: <?= e($token) ?></p>
    </div>
  </div>
</div>
</body></html>
