<?php
require __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

// Fallback caso redirect_with_flash ainda não exista no helpers
if (!function_exists('redirect_with_flash')) {
  function redirect_with_flash(string $type, string $msg, string $to='?page=login'): void {
    flash($type, $msg);
    header("Location: $to");
    exit;
  }
}

// --- Migração rápida: garante colunas ---
try {
  $db = db();
  $hasPwd = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password_hash'")->fetchColumn();
  if ((int)$hasPwd === 0) { $db->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email"); }
  $hasAdmin = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_admin'")->fetchColumn();
  if ((int)$hasAdmin === 0) { $db->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER status"); }
} catch (Throwable $e) { /* ok se já existir */ }

// --- Cria admin padrão se não existir ---
try {
  $hasAdmin = db()->query("SELECT COUNT(*) FROM users WHERE is_admin=1")->fetchColumn();
  if ((int)$hasAdmin === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $ins = db()->prepare("INSERT INTO users (name, person_type, email, status, is_admin, password_hash) VALUES (?,?,?,?,?,?)");
    $ins->execute(['Administrador', 'FISICA', 'admin@local', 1, 1, $hash]);
    flash('success','Admin criado: admin@local / admin123. Altere a senha depois.');
  }
} catch (Throwable $e) {}

// --- Ações login/logout ---
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($action === 'logout') {
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"] ?? false, $params["httponly"] ?? true);
  }
  session_destroy();
  session_write_close();
  session_regenerate_id(true);
  redirect_with_flash('success','Você saiu.','?page=login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  try {
    $st = db()->prepare("SELECT id,name,email,password_hash,is_admin,status FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u || !$u['password_hash'] || !password_verify($pass, $u['password_hash'])) { throw new Exception('Credenciais inválidas.'); }
    if ((int)$u['status'] !== 1) { throw new Exception('Usuário inativo.'); }

    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $_SESSION['user'] = ['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'is_admin'=>(int)$u['is_admin']];
    redirect_with_flash('success','Bem-vindo, '.$u['name'].'!','?page=dashboard');
  } catch (Throwable $e) {
    redirect_with_flash('danger',$e->getMessage(),'?page=login');
  }
}
?>

<div class="content-card" style="max-width:450px;margin:50px auto;">
  <div class="card shadow-sm login-card">
    <div class="card-body">
      <h1 class="h4 mb-3">Acessar</h1>
      <form method="post" novalidate>
        <input type="hidden" name="action" value="login">
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Senha</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn-modern btn-primary-modern w-100"
                type="submit"
                style="background: var(--warning-gradient);">
          <i class="fas fa-sign-in-alt"></i> Entrar
        </button>
      </form>
      
    </div>
  </div>
</div>
