<?php
// app/helpers.php - helpers mínimos e seguros p/ o painel

// Iniciar sessão apenas se ainda não foi iniciada e headers não foram enviados
if (session_status() === PHP_SESSION_NONE && !headers_sent()) { 
    session_start(); 
}

/** Escape seguro para HTML */
function e($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Flash messages (exibidas como toasts) */
function flash($type, $msg) {
  if (!isset($_SESSION['flash'])) $_SESSION['flash'] = [];
  if (!isset($_SESSION['flash'][$type])) $_SESSION['flash'][$type] = [];
  $_SESSION['flash'][$type][] = $msg;
}

/** Redirect helper MELHORADO */
function redirect($url) {
  // Limpar qualquer output anterior para evitar "headers already sent"
  if (ob_get_level()) {
    ob_end_clean();
  }
  
  // Verificar se headers já foram enviados
  if (headers_sent($file, $line)) {
    // Se headers já foram enviados, usar JavaScript
    echo "<script>window.location.href = " . json_encode($url) . ";</script>";
    echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url) . "'></noscript>";
    exit;
  }
  
  // Redirect normal via PHP
  header("Location: $url", true, 302);
  header("Cache-Control: no-cache, must-revalidate");
  exit;
}

/** Marca item ativo do menu lateral */
function active($name) {
  $p = $_GET['page'] ?? 'dashboard';
  return $p === $name ? 'active' : '';
}

/** Formata data/hora no padrão BR */
function now_br($dt) {
  if (!$dt) return '';
  $ts = is_numeric($dt) ? (int)$dt : strtotime($dt);
  if ($ts === false) return (string)$dt;
  return date('d/m/Y H:i', $ts);
}

/** Sessão do usuário logado */
function current_user() {
  return $_SESSION['user'] ?? null;
}

function is_logged_in() {
  return !empty($_SESSION['user']);
}

// ==== SETTINGS (key/value) ====
function settings_bootstrap_table(){
  try {
    db()->exec("CREATE TABLE IF NOT EXISTS settings (
      key_name VARCHAR(64) PRIMARY KEY,
      value TEXT NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  } catch (Throwable $e) { /* ok */ }
}

function setting(string $key, $default=null){
  settings_bootstrap_table();
  static $cache = [];
  if (array_key_exists($key, $cache)) return $cache[$key];
  $st = db()->prepare("SELECT value FROM settings WHERE key_name=?");
  $st->execute([$key]);
  $val = $st->fetchColumn();
  if ($val === false) return $cache[$key] = $default;
  return $cache[$key] = $val;
}

function setting_set(string $key, $value): void {
  settings_bootstrap_table();
  $st = db()->prepare("INSERT INTO settings (key_name, value) VALUES (?,?)
                      ON DUPLICATE KEY UPDATE value=VALUES(value)");
  $st->execute([$key, $value]);
}

// ==== FLASH + REDIRECT PADRONIZADOS - VERSÃO MELHORADA ====

/** URL de retorno segura (fallback para a página atual sem query, ou uma URL que você passar) */
function back_url(string $fallback='?page=dashboard'): string {
  $ref = $_SERVER['HTTP_REFERER'] ?? '';
  // Evita open redirect: só aceita mesmo host
  if ($ref && parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) return $ref;
  return $fallback;
}

/** Envia flash e redireciona (PRG) - VERSÃO CORRIGIDA */
function redirect_with_flash(string $type, string $msg, string $to=''): void {
  // Adicionar mensagem flash
  flash($type, $msg);
  
  // Determinar URL de destino
  if ($to === '') $to = back_url();
  
  // Limpar output buffer para evitar "headers already sent"
  while (ob_get_level()) {
    ob_end_clean();
  }
  
  // Verificar se headers já foram enviados
  if (headers_sent($file, $line)) {
    // Headers já enviados - usar JavaScript + meta refresh
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Redirecionando...</title>
        <script>
            // Redirecionar imediatamente
            window.location.replace(<?= json_encode($to) ?>);
            
            // Fallback se não redirecionar
            setTimeout(function() {
                window.location.href = <?= json_encode($to) ?>;
            }, 1000);
        </script>
        <noscript>
            <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($to) ?>">
        </noscript>
    </head>
    <body>
        <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
            <h3>Redirecionando...</h3>
            <p>Se não foi redirecionado automaticamente, <a href="<?= htmlspecialchars($to) ?>">clique aqui</a>.</p>
            <script>document.write('<p>JavaScript ativo: redirecionando em 1 segundo...</p>');</script>
        </div>
    </body>
    </html>
    <?php
    exit;
  }
  
  // Headers ainda não enviados - redirect normal
  header("Location: $to", true, 302);
  header("Cache-Control: no-cache, must-revalidate");
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
  exit;
}

// ==== FUNÇÕES AUXILIARES PARA FORMS ====

/** Processar formulário com padrão POST-Redirect-GET seguro */
function process_form_and_redirect(callable $processFunction, string $redirectUrl, string $successMessage = 'Operação realizada com sucesso!'): void {
  try {
    // Executar a função de processamento
    $result = $processFunction();
    
    // Se a função retorna uma string, usar como mensagem
    if (is_string($result)) {
      $successMessage = $result;
    }
    
    // Redirecionar com sucesso
    redirect_with_flash('success', $successMessage, $redirectUrl);
    
  } catch (Exception $e) {
    // Em caso de erro, redirecionar com mensagem de erro
    redirect_with_flash('danger', $e->getMessage(), $redirectUrl);
  }
}

/** Verificar se é request AJAX */
function is_ajax_request(): bool {
  return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
         strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/** Resposta JSON para requests AJAX */
function json_response(array $data, int $httpCode = 200): void {
  // Limpar output buffer
  while (ob_get_level()) {
    ob_end_clean();
  }
  
  // Headers JSON
  http_response_code($httpCode);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-cache, must-revalidate');
  
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

/** Safe redirect que sempre funciona */
function safe_redirect(string $url, string $message = '', string $type = 'success'): void {
  if ($message) {
    flash($type, $message);
  }
  
  // Múltiplos métodos de redirect para garantir que funcione
  
  // Método 1: PHP header (preferido)
  if (!headers_sent()) {
    header("Location: $url", true, 302);
    exit;
  }
  
  // Método 2: JavaScript + Meta refresh (fallback)
  ?>
  <!DOCTYPE html>
  <html>
  <head>
      <meta charset="utf-8">
      <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($url) ?>">
      <title>Redirecionando...</title>
  </head>
  <body>
      <script>
          window.location.replace(<?= json_encode($url) ?>);
      </script>
      <noscript>
          <p>Redirecionando... <a href="<?= htmlspecialchars($url) ?>">Clique aqui se não foi redirecionado</a></p>
      </noscript>
  </body>
  </html>
  <?php
  exit;
}

// ==== FUNÇÕES PARA UPLOAD DE ARQUIVOS ====

/** Upload seguro de imagens */
function safe_image_upload(array $file, string $uploadDir, string $prefix = 'img_'): string {
  if ($file['error'] !== UPLOAD_ERR_OK) {
    throw new Exception('Falha no upload: ' . $file['error']);
  }
  
  // Verificar tipo MIME
  $allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png', 
    'image/webp' => 'webp',
    'image/gif' => 'gif'
  ];
  
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);
  
  if (!isset($allowedTypes[$mimeType])) {
    throw new Exception('Tipo de arquivo não permitido. Use: JPG, PNG, WEBP ou GIF.');
  }
  
  // Verificar tamanho (máx 5MB)
  if ($file['size'] > 5 * 1024 * 1024) {
    throw new Exception('Arquivo muito grande. Máximo: 5MB.');
  }
  
  // Criar diretório se não existir
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }
  
  // Gerar nome único
  $extension = $allowedTypes[$mimeType];
  $filename = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
  $filepath = $uploadDir . '/' . $filename;
  
  // Mover arquivo
  if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    throw new Exception('Não foi possível salvar o arquivo.');
  }
  
  return $filename;
}

// ==== UTILITÁRIOS ====

/** Gerar URL base da aplicação - DECLARADA APENAS UMA VEZ */
if (!function_exists('app_base_url')) {
  function app_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return $scheme . '://' . $host . $path;
  }
}

/** Debug helper - apenas em desenvolvimento */
function dd($var): void {
  if (defined('DEBUG') && DEBUG) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    exit;
  }
}

/** Log de erro customizado */
function log_error(string $message, array $context = []): void {
  $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
  if (!empty($context)) {
    $logMessage .= ' - Context: ' . json_encode($context);
  }
  error_log($logMessage);
}
?>