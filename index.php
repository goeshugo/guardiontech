<?php
/**
 * index.php (revisado)
 * - Mantém guard de login e rotas
 * - Inclui layout global: partials/header.php e partials/footer.php
 * - Logout robusto
 */

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- LOGOUT / LOGOFF ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    // Limpa sessão com segurança
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    // Evita fixation
    session_start();
    session_regenerate_id(true);
    // Redireciona ao login
    header('Location: ?page=login');
    exit;
}

/** Páginas permitidas (arquivos em /pages) */
$allowed = [
  'dashboard','users','devices','environments','groups','permissions',
  'logs','visitors','settings','login','profile'
];

$page = $_GET['page'] ?? '';

/** Guard: exige login para tudo, exceto 'login' */
if (!is_logged_in()) {
  if ($page !== 'login') {
    header('Location: ?page=login');
    exit;
  }
} else {
  // Se já está logado e tentar acessar login/raiz, manda para o dashboard
  if ($page === '' || $page === 'login') {
    header('Location: ?page=dashboard');
    exit;
  }
}

/** Normaliza página pedida */
if (!in_array($page, $allowed, true)) {
  $page = is_logged_in() ? 'dashboard' : 'login';
}

/** Roteia */
$path = __DIR__ . "/pages/{$page}.php";
if (!file_exists($path)) {
  http_response_code(404);
  echo "Página não encontrada";
  exit;
}

// ---- Layout Global ----
require __DIR__ . '/partials/header.php';
require $path;
require __DIR__ . '/partials/footer.php';
