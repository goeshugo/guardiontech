<?php
require __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/mail.php';

if (!function_exists('redirect_with_flash')) {
  function redirect_with_flash(string $type, string $msg, string $to='?page=settings'): void {
    flash($type, $msg);
    header("Location: $to");
    exit;
  }
}

$act = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if ($act === 'save_email') {
      $method = strtoupper(trim($_POST['email_method'] ?? 'MAIL'));
      if (!in_array($method, ['MAIL','SMTP'], true)) $method = 'MAIL';

      $from_name  = trim($_POST['from_name'] ?? '');
      $from_email = trim($_POST['from_email'] ?? '');
      if ($from_email === '' || !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Informe um e-mail remetente válido.');
      }

      $smtp_host   = trim($_POST['smtp_host'] ?? '');
      $smtp_port   = (int)($_POST['smtp_port'] ?? 587);
      if ($smtp_port < 1 || $smtp_port > 65535) $smtp_port = 587;

      $smtp_secure = strtolower(trim($_POST['smtp_secure'] ?? 'tls'));
      if (!in_array($smtp_secure, ['tls','ssl','none'], true)) $smtp_secure = 'tls';

      $smtp_user = trim($_POST['smtp_user'] ?? '');
      $smtp_pass = $_POST['smtp_pass'] ?? '__KEEP__';

      if ($method === 'SMTP') {
        if ($smtp_host === '') throw new Exception('Para SMTP, informe o host do servidor de saída.');
        if ($smtp_user === '') throw new Exception('Para SMTP, informe o usuário (e-mail completo).');
      }

      setting_set('email.method', $method);
      setting_set('email.from_name', $from_name);
      setting_set('email.from_email', $from_email);
      setting_set('smtp.host', $smtp_host);
      setting_set('smtp.port', $smtp_port);
      setting_set('smtp.secure', $smtp_secure);
      setting_set('smtp.user', $smtp_user);
      if ($smtp_pass !== '__KEEP__') { setting_set('smtp.pass', $smtp_pass ?? ''); }

      $human = ($method === 'SMTP')
        ? "SMTP salvo ({$smtp_host}:{$smtp_port}, {$smtp_secure})."
        : "Método MAIL salvo (php mail()).";
      redirect_with_flash('success', 'Configurações de e-mail salvas. '.$human, '?page=settings#email');
    }

    if ($act === 'send_test') {
      $to = trim($_POST['test_to'] ?? '');
      if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new Exception('E-mail de teste inválido.');

      $subject = 'Teste de e-mail - '.(APP_NAME ?? 'Sistema');
      $body = '<p>Este é um teste do sistema <strong>'.e(APP_NAME).'</strong>.</p>'
            . '<p>Método: <code>'.e(mailer_method()).'</code></p>'
            . '<p>Remetente: <code>'.e(mailer_from_name()).' &lt;'.e(mailer_from_email()).'&gt;</code></p>';

      send_email($to, $subject, $body, strip_tags($body));
      redirect_with_flash('success', 'E-mail de teste enviado para '.$to, '?page=settings#email');
    }

  } catch (Throwable $e) {
    redirect_with_flash('danger', $e->getMessage(), '?page=settings#email');
  }
}

$method     = setting('email.method','MAIL');
$from_name  = setting('email.from_name', APP_NAME ?? 'Sistema');
$from_email = setting('email.from_email','no-reply@seu-dominio.com.br');
$smtp_host  = setting('smtp.host','');
$smtp_port  = (int)setting('smtp.port',587);
$smtp_sec   = setting('smtp.secure','tls');
$smtp_user  = setting('smtp.user','');
$hasPass    = setting('smtp.pass','') !== '';
?>

<div class="content-card">
  <h1 class="h3 mb-3">Configurações</h1>

  <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-email-tab" data-bs-toggle="tab" data-bs-target="#tab-email" type="button" role="tab">E-mail</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-geral-tab" data-bs-toggle="tab" data-bs-target="#tab-geral" type="button" role="tab">Geral</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-outros-tab" data-bs-toggle="tab" data-bs-target="#tab-outros" type="button" role="tab">Outros</button>
    </li>
  </ul>

  <div class="tab-content pt-3">
    <div class="tab-pane fade show active" id="tab-email" role="tabpanel" aria-labelledby="tab-email-tab">
      <div class="row g-3">
        <div class="col-lg-8">
          <div class="card">
            <div class="card-header">E-mail de envio</div>
            <div class="card-body">
              <form method="post" class="row g-3">
                <input type="hidden" name="action" value="save_email">

                <div class="col-md-4">
                  <label class="form-label">Método</label>
                  <select name="email_method" class="form-select">
                    <option value="MAIL" <?= $method==='MAIL'?'selected':'' ?>>MAIL (php mail)</option>
                    <option value="SMTP" <?= $method==='SMTP'?'selected':'' ?>>SMTP (requer PHPMailer)</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">De (Nome)</label>
                  <input type="text" name="from_name" class="form-control" value="<?= e($from_name) ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">De (E-mail)</label>
                  <input type="email" name="from_email" class="form-control" value="<?= e($from_email) ?>" required>
                </div>

                <div class="col-12"><hr></div>

                <div class="col-md-6">
                  <label class="form-label">SMTP Host</label>
                  <input type="text" name="smtp_host" class="form-control" value="<?= e($smtp_host) ?>" placeholder="mail.seu-dominio.com.br">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Porta</label>
                  <input type="number" name="smtp_port" class="form-control" value="<?= e($smtp_port) ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Criptografia</label>
                  <select name="smtp_secure" class="form-select">
                    <option value="tls"  <?= $smtp_sec==='tls' ?'selected':'' ?>>TLS (587)</option>
                    <option value="ssl"  <?= $smtp_sec==='ssl' ?'selected':'' ?>>SSL (465)</option>
                    <option value="none" <?= $smtp_sec==='none'?'selected':'' ?>>Nenhuma</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Usuário (e-mail completo)</label>
                  <input type="text" name="smtp_user" class="form-control" value="<?= e($smtp_user) ?>" placeholder="ex.: convites@seu-dominio.com.br">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Senha</label>
                  <input type="password" name="smtp_pass" class="form-control" value="<?= $hasPass?'__KEEP__':'' ?>" autocomplete="new-password">
                  <div class="form-text"><?= $hasPass?'(deixe "__KEEP__" para manter a senha atual)':'digite a senha' ?></div>
                </div>

                <div class="col-12 d-flex gap-2">
                  <button class="btn-modern btn-primary-modern" style="background: var(--warning-gradient);">Salvar</button>
                  <button class="btn-modern btn-outline-secondary" type="button"
                          onclick="document.getElementById('testForm').classList.toggle('d-none')">
                    Enviar e-mail de teste
                  </button>
                </div>
              </form>

              <form id="testForm" method="post" class="row g-3 mt-3 d-none">
                <input type="hidden" name="action" value="send_test">
                <div class="col-md-8">
                  <label class="form-label">Enviar teste para</label>
                  <input type="email" name="test_to" class="form-control" placeholder="email@exemplo.com">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                  <button class="btn-modern btn-outline-primary">Enviar teste</button>
                </div>
              </form>

              <div class="alert alert-info mt-3 mb-0 small">
                <strong>Dica:</strong> se usar SMTP (ex.: <code>mail.goesconnect.com.br</code>, porta <code>465</code>, <em>SSL</em>),
                confirme que o <em>From (E-mail)</em> é do mesmo domínio e que o PHPMailer está instalado/autoloadado.
                Em caso de SPAM, configure SPF/DKIM no cPanel.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-geral" role="tabpanel" aria-labelledby="tab-geral-tab">
      <div class="alert alert-secondary">
        Em breve: configurações gerais (nome do sistema, logo, cores, fuso-horário).
      </div>
    </div>

    <div class="tab-pane fade" id="tab-outros" role="tabpanel" aria-labelledby="tab-outros-tab">
      <div class="alert alert-secondary">
        Em breve: integrações (WhatsApp, SMS), segurança, auditoria de envios.
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const hash = (location.hash || '').toLowerCase();
  if (hash) {
    const btn = document.querySelector(`[data-bs-target="#tab-${hash.replace('#','')}"]`);
    if (btn) new bootstrap.Tab(btn).show();
  }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
