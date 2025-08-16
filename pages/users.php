<?php
/**
 * pages/users.php
 * Usa o layout global (partials/header.php e footer.php) via index.php.
 * NÃO injeta Bootstrap nem head aqui.
 */

// Caso o index.php ainda não tenha carregado as dependências, fazemos fallback seguro.
if (!function_exists('db')) {
  @require_once __DIR__ . '/../app/db.php';
}
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('flash')) {
  function flash($type, $msg) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash'][] = ['t'=>$type,'m'=>$msg];
  }
}
if (!function_exists('redirect_with_flash')) {
  function redirect_with_flash(string $type, string $msg, string $to='?page=users'): void {
    flash($type, $msg);
    header("Location: $to");
    exit;
  }
}

// ---------- Helpers de foto ----------
function users_photo_url($maybe) {
  $u = trim((string)$maybe);
  if ($u === '') return 'public/assets/img/avatar-default.png';
  // Se for URL absoluta, retorna
  if (preg_match('/^https?:\\/\\//', $u)) return $u;
  // Se for relativo, checa existência; senão usa default
  $abs = __DIR__ . '/../' . ltrim($u,'/');
  if (is_file($abs)) return $u;
  return 'public/assets/img/avatar-default.png';
}

function users_hydrate_files_from_webcam(): void {
  if (empty($_POST['photo_webcam'])) return;
  $base64 = $_POST['photo_webcam'];
  if (!preg_match('/^data:image\/(\w+);base64,/', $base64, $m)) return;
  $ext = strtolower($m[1]);
  if (!in_array($ext, ['jpg','jpeg','png'])) $ext = 'jpg';
  $base64 = substr($base64, strpos($base64, ',') + 1);
  $data = base64_decode($base64);
  if ($data === false) return;

  $tmp = sys_get_temp_dir() . '/u_webcam_' . bin2hex(random_bytes(8)) . '.' . $ext;
  file_put_contents($tmp, $data);
  $_FILES['photo'] = [
    'name' => basename($tmp),
    'type' => ($ext === 'png' ? 'image/png' : 'image/jpeg'),
    'tmp_name' => $tmp,
    'error' => 0,
    'size' => filesize($tmp),
  ];
}

function users_save_photo_from_files(): ?string {
  if (empty($_FILES['photo']) || empty($_FILES['photo']['name'])) return null;
  $destDirAbs = __DIR__ . '/../public/uploads/users';
  if (!is_dir($destDirAbs)) { @mkdir($destDirAbs, 0775, true); }

  if (function_exists('safe_image_upload')) {
    $savedPathAbs = safe_image_upload('photo', $destDirAbs);
    if ($savedPathAbs && file_exists($savedPathAbs)) {
      return 'public/uploads/users/' . basename($savedPathAbs);
    }
    return null;
  }

  $f = $_FILES['photo'];
  if ($f['error'] !== UPLOAD_ERR_OK) return null;
  if ($f['size'] > 5 * 1024 * 1024) return null;
  $fi = @getimagesize($f['tmp_name']);
  if ($fi === false) return null;
  $mime = $fi['mime'] ?? '';
  $ext = '.jpg';
  if ($mime === 'image/png') $ext = '.png';
  elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') $ext = '.jpg';
  else return null;

  $basename = 'u_' . bin2hex(random_bytes(8)) . $ext;
  $destAbs = rtrim($destDirAbs,'/').'/'.$basename;
  if (!move_uploaded_file($f['tmp_name'], $destAbs)) return null;
  return 'public/uploads/users/' . $basename;
}

// ---------- Controller ----------
$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
  $pdo = db();
} catch (\Throwable $e) {
  echo '<div class="alert alert-danger">Erro ao conectar ao banco: '.e($e->getMessage()).'</div>';
  return;
}

// CREATE
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    users_hydrate_files_from_webcam();

    $name        = trim($_POST['name'] ?? '');
    $person_type = $_POST['person_type'] ?? 'FISICA';
    $cpf_cnpj    = trim($_POST['cpf_cnpj'] ?? '');
    $document    = trim($_POST['document'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $status      = isset($_POST['status']) ? 1 : 0;

    if ($name === '') redirect_with_flash('danger','Nome é obrigatório','?page=users&action=new');

    $stmt = $pdo->prepare("INSERT INTO users (name, person_type, cpf_cnpj, document, email, phone, status) 
                           VALUES (:name,:person_type,:cpf_cnpj,:document,:email,:phone,:status)");
    $stmt->execute([
      ':name' => $name,
      ':person_type' => $person_type,
      ':cpf_cnpj' => $cpf_cnpj ?: null,
      ':document' => $document ?: null,
      ':email' => $email ?: null,
      ':phone' => $phone ?: null,
      ':status' => $status,
    ]);
    $newId = (int)$pdo->lastInsertId();

    $photoWeb = users_save_photo_from_files();
    if ($photoWeb) {
      $up = $pdo->prepare("UPDATE users SET photo_url = :p WHERE id = :id");
      $up->execute([':p'=>$photoWeb, ':id'=>$newId]);
    }

    redirect_with_flash('success','Usuário criado com sucesso','?page=users');
  } catch (\Throwable $e) {
    redirect_with_flash('danger','Erro ao criar usuário: '.$e->getMessage(),'?page=users&action=new');
  }
}

// UPDATE
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
  try {
    users_hydrate_files_from_webcam();

    $name        = trim($_POST['name'] ?? '');
    $person_type = $_POST['person_type'] ?? 'FISICA';
    $cpf_cnpj    = trim($_POST['cpf_cnpj'] ?? '');
    $document    = trim($_POST['document'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $status      = isset($_POST['status']) ? 1 : 0;

    if ($name === '') redirect_with_flash('danger','Nome é obrigatório','?page=users&action=edit&id='.$id);

    $stmt = $pdo->prepare("UPDATE users SET 
        name=:name, person_type=:person_type, cpf_cnpj=:cpf_cnpj, document=:document,
        email=:email, phone=:phone, status=:status
        WHERE id=:id");
    $stmt->execute([
      ':name' => $name,
      ':person_type' => $person_type,
      ':cpf_cnpj' => $cpf_cnpj ?: null,
      ':document' => $document ?: null,
      ':email' => $email ?: null,
      ':phone' => $phone ?: null,
      ':status' => $status,
      ':id' => $id,
    ]);

    $photoWeb = users_save_photo_from_files();
    if ($photoWeb) {
      $up = $pdo->prepare("UPDATE users SET photo_url = :p WHERE id = :id");
      $up->execute([':p'=>$photoWeb, ':id'=>$id]);
    }

    redirect_with_flash('success','Usuário atualizado','?page=users');
  } catch (\Throwable $e) {
    redirect_with_flash('danger','Erro ao atualizar: '.$e->getMessage(),'?page=users&action=edit&id='.$id);
  }
}

// DELETE
if ($action === 'delete' && $id) {
  try {
    $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $del->execute([':id'=>$id]);
    redirect_with_flash('success','Usuário removido','?page=users');
  } catch (\Throwable $e) {
    redirect_with_flash('danger','Erro ao remover: '.$e->getMessage(),'?page=users');
  }
}

// ---------- Views ----------
function users_form($mode, $row = []) {
  $isEdit = ($mode === 'edit');
  $id     = $row['id'] ?? null;
  $name   = $row['name'] ?? '';
  $person = $row['person_type'] ?? 'FISICA';
  $cpf    = $row['cpf_cnpj'] ?? '';
  $doc    = $row['document'] ?? '';
  $email  = $row['email'] ?? '';
  $phone  = $row['phone'] ?? '';
  $status = (isset($row['status']) ? (int)$row['status'] : 1);
  $photo  = $row['photo_url'] ?? '';

  $formAction = $isEdit ? '?page=users&action=edit&id='. (int)$id : '?page=users&action=create';
  ?>
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong><?= $isEdit ? 'Editar Usuário' : 'Novo Usuário' ?></strong>
      <a class="btn btn-sm btn-secondary" href="?page=users">Voltar</a>
    </div>
    <div class="card-body">
      <form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" autocomplete="off">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nome <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= e($name) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Tipo de Pessoa</label>
            <select name="person_type" class="form-select">
              <option value="FISICA" <?= $person==='FISICA'?'selected':''; ?>>FÍSICA</option>
              <option value="JURIDICA" <?= $person==='JURIDICA'?'selected':''; ?>>JURÍDICA</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">CPF/CNPJ</label>
            <input type="text" name="cpf_cnpj" class="form-control" value="<?= e($cpf) ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Documento</label>
            <input type="text" name="document" class="form-control" value="<?= e($doc) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-control" value="<?= e($email) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Telefone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($phone) ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Foto (upload)</label>
            <input type="file" name="photo" accept="image/*" class="form-control">
            <div class="form-text">JPG/PNG até 5MB.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label d-block">Webcam</label>
            <button type="button" class="btn btn-outline-secondary mb-2" id="btn-open-webcam">Tirar foto</button>
            <div id="webcam-area" class="border rounded p-2" style="display:none;">
              <video id="webcam-video" width="240" height="180" autoplay playsinline style="background:#000;border-radius:6px;"></video>
              <canvas id="webcam-canvas" width="240" height="180" class="d-none"></canvas>
              <div class="mt-2 d-flex gap-2">
                <button type="button" class="btn btn-secondary btn-sm" id="btn-capture">Capturar</button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-close-webcam">Fechar</button>
              </div>
              <input type="hidden" name="photo_webcam" id="photo_webcam">
              <img id="preview-captured" class="mt-2 rounded d-none" width="120" height="90" alt="Prévia">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label d-block">Status</label>
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" name="status" id="status" <?= $status? 'checked':''; ?>>
              <label class="form-check-label" for="status"><?= $status? 'Ativo':'Inativo'; ?></label>
            </div>
            <?php if ($photo): ?>
              <div class="mt-3">
                <label class="form-label d-block">Foto atual</label>
                <img src="<?= e($photo) ?>" width="64" height="64" style="object-fit:cover;border-radius:50%;border:1px solid #ddd;">
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Salvar' : 'Criar' ?></button>
          <a href="?page=users" class="btn btn-light">Cancelar</a>
        </div>
      </form>
    </div>
  </div>

  <script>
  (function(){
    const openBtn = document.getElementById('btn-open-webcam');
    if (!openBtn) return;
    const area   = document.getElementById('webcam-area');
    const video  = document.getElementById('webcam-video');
    const canvas = document.getElementById('webcam-canvas');
    const captureBtn = document.getElementById('btn-capture');
    const closeBtn   = document.getElementById('btn-close-webcam');
    const hiddenInput = document.getElementById('photo_webcam');
    const previewImg  = document.getElementById('preview-captured');
    let stream = null;

    openBtn.addEventListener('click', async () => {
      area.style.display = 'block';
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        video.srcObject = stream;
      } catch (e) {
        alert('Não foi possível acessar a webcam: ' + e.message);
      }
    });

    captureBtn.addEventListener('click', () => {
      if (!stream) return;
      const ctx = canvas.getContext('2d');
      canvas.classList.remove('d-none');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const data = canvas.toDataURL('image/jpeg', 0.9);
      hiddenInput.value = data;
      previewImg.src = data;
      previewImg.classList.remove('d-none');
    });

    closeBtn.addEventListener('click', () => {
      if (stream) {
        stream.getTracks().forEach(t => t.stop());
        stream = null;
      }
      area.style.display = 'none';
      previewImg.classList.add('d-none');
    });
  })();
  </script>
  <?php
}

// Router de view
if ($action === 'new') { users_form('new', []); return; }
if ($action === 'edit' && $id) {
  $st = $pdo->prepare("SELECT * FROM users WHERE id = :id");
  $st->execute([':id'=>$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    echo '<div class="alert alert-warning">Usuário não encontrado.</div>';
    echo '<a href="?page=users" class="btn btn-secondary">Voltar</a>';
    return;
  }
  users_form('edit', $row);
  return;
}

// Listagem
$rows = [];
try {
  $q = $pdo->query("SELECT id, name, person_type, cpf_cnpj, email, phone, status, photo_url, created_at
                    FROM users ORDER BY id DESC");
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
  echo '<div class="alert alert-danger">Erro ao listar: '.e($e->getMessage()).'</div>';
  return;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Usuários</h4>
  <div>
    <a class="btn btn-primary" href="?page=users&action=new"><i class="bi bi-plus-lg"></i> Novo Usuário</a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:60px;">Foto</th>
          <th>Nome</th>
          <th>Tipo</th>
          <th>CPF/CNPJ</th>
          <th>E-mail</th>
          <th>Telefone</th>
          <th>Status</th>
          <th style="width:140px;">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td></tr>
        <?php else: foreach ($rows as $u): ?>
          <tr>
            <td>
              <?php $avatar = users_photo_url($u['photo_url'] ?? ''); ?>
              <img src="<?= e($avatar) ?>" alt="avatar" width="40" height="40" style="object-fit:cover;border-radius:50%;">
            </td>
            <td><?= e($u['name']) ?></td>
            <td><span class="badge bg-secondary"><?= e($u['person_type']) ?></span></td>
            <td><?= e($u['cpf_cnpj']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['phone']) ?></td>
            <td>
              <?php if ((int)$u['status'] === 1): ?>
                <span class="badge bg-success">Ativo</span>
              <?php else: ?>
                <span class="badge bg-danger">Inativo</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="btn-group btn-group-sm" role="group">
                <a class="btn btn-outline-primary" href="?page=users&action=edit&id=<?= (int)$u['id'] ?>"><i class="bi bi-pencil-square"></i></a>
                <form method="post" action="?page=users&action=delete&id=<?= (int)$u['id'] ?>" onsubmit="return confirm('Remover este usuário?');" style="display:inline;">
                  <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
