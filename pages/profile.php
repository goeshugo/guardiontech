<?php
require __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$me = current_user();
if (!$me) { redirect('?page=login'); }

$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
  try {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;

    if ($name === '') throw new Exception('Nome é obrigatório.');

    $st = db()->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?");
    $st->execute([$name, $email, $phone, (int)$me['id']]);

    // Troca de senha (opcional)
    $p1 = $_POST['new_password'] ?? '';
    $p2 = $_POST['new_password_confirm'] ?? '';
    if ($p1 !== '' || $p2 !== '') {
      if (strlen($p1) < 8) throw new Exception('Nova senha deve ter pelo menos 8 caracteres.');
      if ($p1 !== $p2) throw new Exception('Confirmação da nova senha não confere.');
      $hash = password_hash($p1, PASSWORD_DEFAULT);
      db()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, (int)$me['id']]);
    }

    // Atualiza sessão
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['email'] = $email;

    flash('success', 'Perfil atualizado.');
    redirect('?page=profile');
  } catch (Throwable $e) {
    flash('danger', $e->getMessage());
    redirect('?page=profile');
  }
}

// Carrega dados atuais
$st = db()->prepare("SELECT id, name, email, phone FROM users WHERE id=?");
$st->execute([(int)$me['id']]);
$row = $st->fetch();
?>
<h1 class="h3 mb-3">Meu perfil</h1>

<div class="card">
  <div class="card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="action" value="save">
      <div class="col-md-6">
        <label class="form-label">Nome *</label>
        <input type="text" name="name" class="form-control" value="<?= e($row['name']) ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" value="<?= e($row['email']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Telefone</label>
        <input type="text" name="phone" class="form-control" value="<?= e($row['phone']) ?>">
      </div>

      <div class="col-12"><hr></div>
      <div class="col-md-6">
        <label class="form-label">Nova senha (opcional)</label>
        <input type="password" name="new_password" class="form-control" minlength="8" placeholder="mín. 8 caracteres">
      </div>
      <div class="col-md-6">
        <label class="form-label">Confirmar nova senha</label>
        <input type="password" name="new_password_confirm" class="form-control" minlength="8">
      </div>

      <div class="col-12">
        <button class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
