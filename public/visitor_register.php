<?php
$ROOT = dirname(__DIR__);
require_once $ROOT . '/app/db.php';
require_once $ROOT . '/app/helpers.php';
require_once $ROOT . '/app/mail.php';

function vr_upload_photo(?array $file): string {
  if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new Exception('Envie uma foto (JPG/PNG/WEBP).');
  if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Falha no upload da foto.');
  $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  $mime = function_exists('finfo_open')
    ? (function($tmp){ $fi=finfo_open(FILEINFO_MIME_TYPE); $m=finfo_file($fi,$tmp); finfo_close($fi); return $m; })($file['tmp_name'])
    : mime_content_type($file['tmp_name']);
  if (!isset($allowed[$mime])) throw new Exception('Formato inválido. Use JPG, PNG ou WEBP.');
  if ($file['size'] > 5*1024*1024) throw new Exception('Arquivo muito grande (máx. 5 MB).');

  $dir = $ROOT = dirname(__DIR__) . '/public/uploads/visitors';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $name = 'v_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
  $dest = $dir . '/' . $name;
  if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('Não foi possível salvar a foto.');
  return 'public/uploads/visitors/' . $name;
}

function vr_now(): string { return date('Y-m-d H:i:s'); }

$code = trim($_GET['code'] ?? '');
$invite = null; $err = null; $success = null;

try {
  if ($code === '') throw new Exception('Convite inválido.');
  $st = db()->prepare("
    SELECT vi.*, v.id AS visitor_id, v.name AS v_name, v.document AS v_doc, v.contact AS v_contact,
           u.name AS host_name, u.email AS host_email
    FROM visitor_invites vi
    LEFT JOIN visitors v ON v.id = vi.used_visitor_id
    LEFT JOIN users u ON u.id = vi.host_user_id
    WHERE vi.code = ?
    LIMIT 1
  ");
  $st->execute([$code]);
  $invite = $st->fetch();
  if (!$invite) throw new Exception('Convite inválido (não encontrado).');
  if ($invite['status'] === 'CANCELLED') throw new Exception('Este convite foi cancelado.');
  if ($invite['expires_at'] && strtotime($invite['expires_at']) < time()) throw new Exception('Este convite expirou.');
  if ($invite['status'] === 'USED') throw new Exception('Este convite já foi utilizado.');

  if (!$invite['visitor_id']) {
    $ins = db()->prepare("INSERT INTO visitors (name, document, contact, host_user_id, status, review_status, invite_sent_at)
                          VALUES (?,?,?,?,0,'INVITED', NOW())");
    $ins->execute([null, null, $invite['email'], $invite['host_user_id'] ?: null]);
    $vid = db()->lastInsertId();
    $up = db()->prepare("UPDATE visitor_invites SET used_visitor_id=? WHERE id=?");
    $up->execute([$vid, $invite['id']]);
    $st->execute([$code]);
    $invite = $st->fetch();
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf = trim($_POST['document'] ?? '');
    if ($name === '') throw new Exception('Informe seu nome.');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Informe um e-mail válido.');
    if ($cpf === '') throw new Exception('Informe o CPF.');
    $photo_url = vr_upload_photo($_FILES['photo'] ?? null);

    $upd = db()->prepare("UPDATE visitors
      SET name=?, document=?, contact=?, photo_url=?, review_status='PENDING', registered_at=?
      WHERE id=?");
    $upd->execute([$name, $cpf, $email, $photo_url, vr_now(), $invite['visitor_id']]);

    $up2 = db()->prepare("UPDATE visitor_invites SET status='USED' WHERE id=?");
    $up2->execute([$invite['id']]);

    if (!empty($invite['host_email']) && filter_var($invite['host_email'], FILTER_VALIDATE_EMAIL)) {
      try {
        $subject = 'Novo cadastro de visitante aguardando aprovação';
        $html = '<p>Visitante <strong>'.e($name).'</strong> concluiu o cadastro e aguarda aprovação.</p>';
        send_email($invite['host_email'], $subject, $html, strip_tags($html));
      } catch (Throwable $e) {}
    }

    $success = 'Cadastro enviado com sucesso! Agora aguarde a aprovação. Você receberá um e-mail assim que for aprovado.';
  }

} catch (Throwable $e) { $err = $e->getMessage(); }
?><!doctype html>
<html lang="pt-br"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastro de Visitante</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>:root{--bs-primary:#ff6b00;--bs-link-color:#ff6b00;--bs-link-hover-color:#cc5700}.card{border-radius:14px}</style>
</head><body class="bg-light">
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-6">
<div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h4 mb-3">Cadastro de Visitante</h1>
<?php if ($err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
  <p class="text-muted small mb-0">Se você acredita que isso é um engano, solicite um novo convite ao seu contato.</p>
<?php elseif ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
  <p class="mb-0"><a class="btn btn-primary mt-2" href="../">Voltar ao site</a></p>
<?php else: ?>
  <div class="alert alert-info small">
    Convite destinado a: <strong><?= e($invite['email'] ?: '—') ?></strong>
    <?php if($invite['host_name']): ?> · Anfitrião: <strong><?= e($invite['host_name']) ?></strong><?php endif; ?>
    <?php if($invite['expires_at']): ?> · Válido até: <strong><?= date('d/m/Y H:i', strtotime($invite['expires_at'])) ?></strong><?php endif; ?>
  </div>
  <form method="post" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="action" value="register">
    <div class="col-12">
      <label class="form-label">Nome completo *</label>
      <input type="text" name="name" class="form-control" required value="<?= e($invite['v_name'] ?? '') ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">E-mail *</label>
      <input type="email" name="email" class="form-control" required value="<?= e($invite['v_contact'] ?: $invite['email']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">CPF *</label>
      <input type="text" name="document" class="form-control" required value="<?= e($invite['v_doc'] ?? '') ?>">
    </div>
    <div class="col-12">
      <label class="form-label">Foto (rosto) *</label>
      <input type="file" name="photo" class="form-control" accept="image/*" required>
      <div class="form-text">JPG/PNG/WEBP até 5MB. A foto será usada apenas para identificação na portaria.</div>
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-primary">Enviar cadastro</button>
      <a href="../" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </form>
<?php endif; ?>
</div></div>
<p class="text-center text-muted small mt-3 mb-0">© <?= date('Y') ?> <?= e(APP_NAME ?? 'Sistema') ?></p>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>