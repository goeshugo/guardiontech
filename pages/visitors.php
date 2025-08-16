<?php
require __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/mail.php';

// [Todo o código PHP de processamento permanece igual]
try {
  $hasPhoto = db()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visitors' AND COLUMN_NAME='photo_url'")->fetchColumn();
  if ((int)$hasPhoto === 0) { db()->exec("ALTER TABLE visitors ADD COLUMN photo_url VARCHAR(255) NULL AFTER contact"); }
  $hasReview = db()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visitors' AND COLUMN_NAME='review_status'")->fetchColumn();
  if ((int)$hasReview === 0) { db()->exec("ALTER TABLE visitors ADD COLUMN review_status ENUM('INVITED','PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'INVITED' AFTER status"); }
  $hasInviteAt = db()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visitors' AND COLUMN_NAME='invite_sent_at'")->fetchColumn();
  if ((int)$hasInviteAt === 0) { db()->exec("ALTER TABLE visitors ADD COLUMN invite_sent_at DATETIME NULL AFTER review_status"); }
  $hasRegAt = db()->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='visitors' AND COLUMN_NAME='registered_at'")->fetchColumn();
  if ((int)$hasRegAt === 0) { db()->exec("ALTER TABLE visitors ADD COLUMN registered_at DATETIME NULL AFTER invite_sent_at"); }

  db()->exec("CREATE TABLE IF NOT EXISTS visitor_invites (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL UNIQUE,
    host_user_id BIGINT UNSIGNED NULL,
    email VARCHAR(120) NULL,
    status ENUM('PENDING','USED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    expires_at DATETIME NULL,
    used_visitor_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (used_visitor_id) REFERENCES visitors(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  db()->exec("CREATE TABLE IF NOT EXISTS visitor_passes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    max_uses INT UNSIGNED NOT NULL DEFAULT 1,
    uses INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ACTIVE','USED','EXPIRED','REVOKED') NOT NULL DEFAULT 'ACTIVE',
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) {}

function upload_visitor_photo(?array $file): ?string {
  if (!$file || empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
  if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Falha no upload da foto.');
  $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  $mime = function_exists('finfo_open')
    ? (function($tmp){ $fi=finfo_open(FILEINFO_MIME_TYPE); $m=finfo_file($fi,$tmp); finfo_close($fi); return $m; })($file['tmp_name'])
    : mime_content_type($file['tmp_name']);
  if (!isset($allowed[$mime])) throw new Exception('Formato inválido. Envie JPG, PNG ou WEBP.');
  if ($file['size'] > 5*1024*1024) throw new Exception('Arquivo muito grande (máx. 5 MB).');

  $ext = $allowed[$mime];
  $root = dirname(__DIR__);
  $dir  = $root . '/public/uploads/visitors';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $fname = 'v_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest = $dir . '/' . $fname;
  if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('Não foi possível salvar a foto.');
  return 'public/uploads/visitors/' . $fname;
}

function badge_review_status(string $rs): string {
  switch ($rs) {
    case 'INVITED':  return '<span class="status-badge invited">convidado</span>';
    case 'PENDING':  return '<span class="status-badge pending">pendente</span>';
    case 'APPROVED': return '<span class="status-badge approved">confirmado</span>';
    case 'REJECTED': return '<span class="status-badge rejected">cancelado</span>';
    default:         return '<span class="status-badge">'.e($rs).'</span>';
  }
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;

// [Processamento de formulários...]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
  if ($action === 'invite_create') {
    process_form_and_redirect(function() {
      $name   = trim($_POST['name'] ?? '');
      $cpf    = trim($_POST['cpf'] ?? '');
      $email  = trim($_POST['email'] ?? '');
      $host   = (int)($_POST['host_user_id'] ?? 0) ?: null;
      $days   = (int)($_POST['days'] ?? 7);

      if ($name === '')  throw new Exception('Nome é obrigatório.');
      if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('E-mail inválido.');

      $st = db()->prepare("INSERT INTO visitors (name, document, contact, host_user_id, status, review_status, invite_sent_at)
                           VALUES (?,?,?,?,0,'INVITED', NOW())");
      $st->execute([$name, $cpf ?: null, $email, $host]);
      $visitor_id = db()->lastInsertId();

      $code    = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
      $expires = $days > 0 ? date('Y-m-d H:i:s', time() + $days*86400) : null;
      $ins = db()->prepare("INSERT INTO visitor_invites (code, host_user_id, email, status, expires_at, used_visitor_id) VALUES (?,?,?,?,?,?)");
      $ins->execute([$code, $host, $email, 'PENDING', $expires, $visitor_id]);

      $link = app_base_url() . '/public/visitor_register.php?code=' . $code;

      try {
        $app = APP_NAME ?? 'Sistema';
        $validStr = $expires ? date('d/m/Y H:i', strtotime($expires)) : 'sem prazo';
        $subject = "Finalize seu cadastro de visita - {$app}";
        $html = '
          <p>Olá '.e($name).'!</p>
          <p>Para finalizar seu cadastro de visitante, use o link abaixo:</p>
          <p><a href="'.e($link).'">'.e($link).'</a></p>
          <p>Validade do convite: <strong>'.e($validStr).'</strong></p>
          <p>Se você não esperava este e-mail, ignore.</p>';
        send_email($email, $subject, $html, strip_tags($html));
        return 'Convite criado e enviado para '.$email;
      } catch (Throwable $ex) {
        return 'Convite criado, mas falhou o envio por e-mail: '.$ex->getMessage();
      }
    }, '?page=visitors');
  }

  if ($action === 'approve') {
    try {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido.');
      
      $st = db()->prepare("UPDATE visitors SET review_status='APPROVED', status=1 WHERE id=?");
      $st->execute([$id]);
      
      redirect_with_flash('success', 'Cadastro aprovado com sucesso!', '?page=visitors');
      
    } catch (Exception $e) {
      redirect_with_flash('danger', $e->getMessage(), '?page=visitors');
    }
  }

  if ($action === 'reject') {
    try {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('ID inválido.');
      
      $st = db()->prepare("UPDATE visitors SET review_status='REJECTED', status=0 WHERE id=?");
      $st->execute([$id]);
      
      redirect_with_flash('warning', 'Cadastro rejeitado.', '?page=visitors');
      
    } catch (Exception $e) {
      redirect_with_flash('danger', $e->getMessage(), '?page=visitors');
    }
  }
}

$q = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? '';

// Query com filtros
$where_conditions = [];
$params = [];

if ($q !== '') {
  $where_conditions[] = "(v.name LIKE ? OR v.document LIKE ? OR v.contact LIKE ?)";
  $like = "%{$q}%";
  $params = array_merge($params, [$like, $like, $like]);
}

if ($status_filter !== '') {
  $where_conditions[] = "v.review_status = ?";
  $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$sql = "
  SELECT v.*, u.name AS host_name
  FROM visitors v
  LEFT JOIN users u ON u.id = v.host_user_id
  $where_clause
  ORDER BY v.id DESC
";

$st = db()->prepare($sql);
$st->execute($params);
$visitors = $st->fetchAll();

$users = db()->query("SELECT id, name FROM users WHERE status=1 ORDER BY name")->fetchAll();
?>

<style>
/* Estilos da tabela moderna */
.visitors-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    border: 1px solid #f0f0f0;
}

.visitors-table table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.visitors-table th {
    background: #f8f9fa;
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}

.visitors-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    color: #334155;
}

.visitors-table tr:hover {
    background: #f8fafc;
}

.visitors-table tr:last-child td {
    border-bottom: none;
}

.visitor-avatar-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.visitor-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}

.visitor-info {
    min-width: 0;
}

.visitor-name {
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 4px 0;
    font-size: 14px;
}

.visitor-email {
    color: #64748b;
    font-size: 12px;
    margin: 0;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 11px;
    font-weight: 600;
    text-transform: lowercase;
    letter-spacing: 0.25px;
    display: inline-block;
}

.status-badge.invited {
    background: #e2e8f0;
    color: #475569;
}

.status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.approved {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.actions-cell {
    text-align: right;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #64748b;
    background: transparent;
    margin-left: 4px;
}

.action-btn:hover {
    background: #f1f5f9;
    transform: scale(1.1);
}

.more-actions {
    position: relative;
    display: inline-block;
}

.more-actions-btn {
    background: none;
    border: none;
    padding: 8px;
    border-radius: 6px;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s ease;
}

.more-actions-btn:hover {
    background: #f1f5f9;
}

.header-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.search-bar {
    flex: 1;
    max-width: 400px;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 12px 16px 12px 40px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    transition: border-color 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: #3b82f6;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
}

.filter-tabs {
    display: flex;
    gap: 4px;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.filter-tab:hover,
.filter-tab.active {
    background: white;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.datetime-cell {
    font-size: 13px;
    color: #64748b;
}
</style>

<!-- Header com controles -->
<div class="content-card">
    <div class="content-header">
        <h2 class="content-title">
            <i class="fas fa-users me-2"></i>
            Visitantes
        </h2>
        <div style="display: flex; gap: 10px;">
            <button class="btn-primary-modern btn-modern" data-bs-toggle="modal" data-bs-target="#modalInvite">
                <i class="fas fa-user-plus"></i>
                Novo Convite
            </button>
        </div>
    </div>
    
    <div class="content-body">
        <!-- Controles de busca e filtro -->
        <div class="header-controls">
            <form method="get" class="search-bar">
                <input type="hidden" name="page" value="visitors">
                <input type="hidden" name="status" value="<?= e($status_filter) ?>">
                <div style="position: relative;">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           class="search-input" 
                           name="q" 
                           value="<?= e($q) ?>" 
                           placeholder="Buscar visitantes...">
                </div>
            </form>
            
            <div style="display: flex; gap: 10px;">
                <?php if ($q): ?>
                <a href="?page=visitors" class="btn-modern" style="background: #6c757d; color: white;">
                    <i class="fas fa-times"></i>
                    Limpar
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Filtros por abas -->
        <div class="filter-tabs">
            <a href="?page=visitors<?= $q ? '&q='.urlencode($q) : '' ?>" 
               class="filter-tab <?= $status_filter === '' ? 'active' : '' ?>">
                Todos
            </a>
            <a href="?page=visitors&status=PENDING<?= $q ? '&q='.urlencode($q) : '' ?>" 
               class="filter-tab <?= $status_filter === 'PENDING' ? 'active' : '' ?>">
                Pendentes
            </a>
            <a href="?page=visitors&status=APPROVED<?= $q ? '&q='.urlencode($q) : '' ?>" 
               class="filter-tab <?= $status_filter === 'APPROVED' ? 'active' : '' ?>">
                Confirmados
            </a>
            <a href="?page=visitors&status=REJECTED<?= $q ? '&q='.urlencode($q) : '' ?>" 
               class="filter-tab <?= $status_filter === 'REJECTED' ? 'active' : '' ?>">
                Cancelados
            </a>
            <a href="?page=visitors&status=INVITED<?= $q ? '&q='.urlencode($q) : '' ?>" 
               class="filter-tab <?= $status_filter === 'INVITED' ? 'active' : '' ?>">
                Convidados
            </a>
        </div>
    </div>
</div>

<!-- Tabela de visitantes -->
<div class="visitors-table">
    <?php if (empty($visitors)): ?>
        <div class="empty-state">
            <i class="fas fa-users empty-state-icon"></i>
            <h3 style="margin-bottom: 8px; color: #1e293b;">Nenhum visitante encontrado</h3>
            <p style="margin-bottom: 24px;">
                <?= $q ? 'Não encontramos visitantes com os critérios de busca.' : 'Comece criando seu primeiro convite de visitante.' ?>
            </p>
            <button class="btn-primary-modern btn-modern" data-bs-toggle="modal" data-bs-target="#modalInvite">
                <i class="fas fa-user-plus"></i>
                Gerar Primeiro Convite
            </button>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Visitante</th>
                    <th>CPF</th>
                    <th>Empresa</th>
                    <th>Data do Convite</th>
                    <th>Data do Cadastro</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($visitors as $v): ?>
                <tr>
                    <!-- Visitante -->
                    <td>
                        <div class="visitor-avatar-cell">
                            <?php if (!empty($v['photo_url'])): ?>
                                <img src="<?= e($v['photo_url']) ?>" 
                                     class="visitor-avatar" 
                                     alt="foto" 
                                     style="object-fit: cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="visitor-avatar" style="display: none;">
                                    <?= strtoupper(substr($v['name'], 0, 2)) ?>
                                </div>
                            <?php else: ?>
                                <div class="visitor-avatar">
                                    <?= strtoupper(substr($v['name'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="visitor-info">
                                <div class="visitor-name"><?= e($v['name']) ?></div>
                                <div class="visitor-email"><?= e($v['contact'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    
                    <!-- CPF -->
                    <td><?= e($v['document'] ?? '-') ?></td>
                    
                    <!-- Empresa -->
                    <td><?= e($v['host_name'] ?? '-') ?></td>
                    
                    <!-- Data do Convite -->
                    <td class="datetime-cell">
                        <?= !empty($v['invite_sent_at']) ? date('d/m/Y, H:i', strtotime($v['invite_sent_at'])) : '-' ?>
                    </td>
                    
                    <!-- Data do Cadastro -->
                    <td class="datetime-cell">
                        <?= !empty($v['registered_at']) ? date('d/m/Y, H:i', strtotime($v['registered_at'])) : '-' ?>
                    </td>
                    
                    <!-- Status -->
                    <td>
                        <?= badge_review_status($v['review_status'] ?? 'INVITED') ?>
                    </td>
                    
                    <!-- Ações -->
                    <td class="actions-cell">
                        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 4px;">
                            <?php if (($v['review_status'] ?? '') === 'PENDING'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Aprovar este visitante?')">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                <button type="submit" class="action-btn" title="Aprovar" style="color: #059669;">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            
                            <form method="post" class="d-inline" onsubmit="return confirm('Rejeitar este visitante?')">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                <button type="submit" class="action-btn" title="Rejeitar" style="color: #dc2626;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <div class="more-actions">
                                <button class="more-actions-btn" onclick="toggleActions(<?= $v['id'] ?>)">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                
                                <div id="actions-<?= $v['id'] ?>" class="dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); z-index: 1000; min-width: 150px;">
                                    <a href="#" class="dropdown-item" style="padding: 8px 16px; display: block; color: #374151; text-decoration: none; font-size: 13px;">
                                        <i class="fas fa-edit me-2"></i>Editar
                                    </a>
                                    <a href="#" class="dropdown-item" style="padding: 8px 16px; display: block; color: #374151; text-decoration: none; font-size: 13px;">
                                        <i class="fas fa-qrcode me-2"></i>Gerar Passe
                                    </a>
                                    <a href="#" class="dropdown-item" style="padding: 8px 16px; display: block; color: #dc2626; text-decoration: none; font-size: 13px;" onclick="confirmDelete(<?= $v['id'] ?>)">
                                        <i class="fas fa-trash me-2"></i>Remover
                                    </a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal para criar convite -->
<div class="modal fade" id="modalInvite" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden;">
            <div class="modal-header" style="background: var(--primary-gradient); color: white; border: none;">
                <h5 class="modal-title">
                    <i class="fas fa-envelope me-2"></i>
                    Gerar Convite de Visitante
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" onsubmit="this.querySelector('[type=submit]').disabled=true">
                <div class="modal-body" style="padding: 25px;">
                    <input type="hidden" name="action" value="invite_create">
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Nome Completo</label>
                        <input type="text" class="form-control" name="name" required 
                               style="border-radius: 8px; border: 2px solid #e9ecef; padding: 12px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">E-mail</label>
                        <input type="email" class="form-control" name="email" required 
                               style="border-radius: 8px; border: 2px solid #e9ecef; padding: 12px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">CPF (opcional)</label>
                        <input type="text" class="form-control" name="cpf" 
                               style="border-radius: 8px; border: 2px solid #e9ecef; padding: 12px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Anfitrião</label>
                        <select class="form-select" name="host_user_id" 
                                style="border-radius: 8px; border: 2px solid #e9ecef; padding: 12px;">
                            <option value="">Selecione...</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Validade do Convite</label>
                        <select class="form-select" name="days" 
                                style="border-radius: 8px; border: 2px solid #e9ecef; padding: 12px;">
                            <option value="1">1 dia</option>
                            <option value="7" selected>7 dias</option>
                            <option value="15">15 dias</option>
                            <option value="30">30 dias</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border: none; padding: 25px; background: #f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
                            style="border-radius: 8px; padding: 10px 20px;">