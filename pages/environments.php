<?php
require __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

/**
 * Lightweight auto-migrations for environments:
 * - Add environments.primary_device_id (nullable FK to devices.id)
 * - Create environment_devices_aux (env_id, device_id)
 */
try {
    db()->exec("ALTER TABLE environments ADD COLUMN primary_device_id BIGINT UNSIGNED NULL AFTER env_type");
} catch (Throwable $e) { /* column may already exist */ }
try {
    db()->exec("ALTER TABLE environments ADD CONSTRAINT fk_env_primary_device FOREIGN KEY (primary_device_id) REFERENCES devices(id) ON DELETE SET NULL");
} catch (Throwable $e) { /* fk may already exist */ }
try {
    db()->exec("CREATE TABLE IF NOT EXISTS environment_devices_aux (
        environment_id BIGINT UNSIGNED NOT NULL,
        device_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (environment_id, device_id),
        FOREIGN KEY (environment_id) REFERENCES environments(id) ON DELETE CASCADE,
        FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { /* table may already exist */ }

function load_devices(): array {
    $st = db()->query("SELECT id, name FROM devices WHERE status = 1 ORDER BY name");
    return $st->fetchAll();
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['env_type'] ?? 'PORTA';
            $status = isset($_POST['status']) ? 1 : 0;
            $primary_device_id = (int)($_POST['primary_device_id'] ?? 0) ?: null;
            $aux = $_POST['aux_devices'] ?? [];

            if ($name === '') throw new Exception('Nome é obrigatório.');
            $allowed = ['PORTA','PORTAO','CANCELA','CATRACA','ELEVADOR','AREA'];
            if (!in_array($type, $allowed, true)) throw new Exception('Tipo inválido.');

            $st = db()->prepare("INSERT INTO environments (name, env_type, primary_device_id, status) VALUES (?,?,?,?)");
            $st->execute([$name, $type, $primary_device_id, $status]);
            $env_id = (int)db()->lastInsertId();

            if (is_array($aux) && $aux) {
                $ins = db()->prepare("INSERT IGNORE INTO environment_devices_aux (environment_id, device_id) VALUES (?,?)");
                foreach ($aux as $d) {
                    $did = (int)$d;
                    if ($did > 0) $ins->execute([$env_id, $did]);
                }
            }

            flash('success', 'Ambiente criado com sucesso.');
            redirect('?page=environments');
        }
        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['env_type'] ?? 'PORTA';
            $status = isset($_POST['status']) ? 1 : 0;
            $primary_device_id = (int)($_POST['primary_device_id'] ?? 0) ?: null;
            $aux = $_POST['aux_devices'] ?? [];

            if ($name === '') throw new Exception('Nome é obrigatório.');
            $allowed = ['PORTA','PORTAO','CANCELA','CATRACA','ELEVADOR','AREA'];
            if (!in_array($type, $allowed, true)) throw new Exception('Tipo inválido.');

            $st = db()->prepare("UPDATE environments SET name=?, env_type=?, primary_device_id=?, status=? WHERE id=?");
            $st->execute([$name, $type, $primary_device_id, $status, $id]);

            // Reset and insert AUX
            db()->prepare("DELETE FROM environment_devices_aux WHERE environment_id=?")->execute([$id]);
            if (is_array($aux) && $aux) {
                $ins = db()->prepare("INSERT IGNORE INTO environment_devices_aux (environment_id, device_id) VALUES (?,?)");
                foreach ($aux as $d) {
                    $did = (int)$d;
                    if ($did > 0) $ins->execute([$id, $did]);
                }
            }

            flash('success', 'Ambiente atualizado.');
            redirect('?page=environments');
        }
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');
            db()->prepare("DELETE FROM environments WHERE id=?")->execute([$id]);
            flash('success', 'Ambiente removido.');
            redirect('?page=environments');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('?page=environments');
    }
}

// Load data for list and selects
$devices = load_devices();

$sql = "SELECT e.*, d.name AS primary_device_name
        FROM environments e
        LEFT JOIN devices d ON d.id = e.primary_device_id
        ORDER BY e.id DESC";
$envs = db()->query($sql)->fetchAll();

// Load aux devices per env
$aux_map = [];
$rows = db()->query("SELECT environment_id, device_id FROM environment_devices_aux")->fetchAll();
foreach ($rows as $r) {
    $aux_map[(int)$r['environment_id']][] = (int)$r['device_id'];
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Ambientes</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">Novo Ambiente</button>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>ID</th>
      <th>Nome</th>
      <th>Tipo</th>
      <th>Dispositivo Principal</th>
      <th>Status</th>
      <th class="text-end">Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($envs as $e): ?>
    <tr>
      <td><?= (int)$e['id'] ?></td>
      <td><?= e($e['name']) ?></td>
      <td><span class="badge bg-secondary"><?= e($e['env_type']) ?></span></td>
      <td><?= e($e['primary_device_name'] ?? '-') ?></td>
      <td>
        <?php if ((int)$e['status'] === 1): ?>
          <span class="badge bg-success">Ativo</span>
        <?php else: ?>
          <span class="badge bg-danger">Inativo</span>
        <?php endif; ?>
      </td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalEdit"
                data-env='<?= e(json_encode($e, JSON_UNESCAPED_UNICODE)) ?>'
                data-aux='<?= e(json_encode($aux_map[(int)$e["id"]] ?? [], JSON_UNESCAPED_UNICODE)) ?>'>
          Editar
        </button>
        <button class="btn btn-sm btn-outline-danger"
                data-bs-toggle="modal"
                data-bs-target="#modalDelete"
                data-id="<?= (int)$e['id'] ?>"
                data-name="<?= e($e['name']) ?>">
          Excluir
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$envs): ?>
    <tr><td colspan="6" class="text-center text-muted">Nenhum ambiente cadastrado.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<!-- Modal Create -->
<div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h5 class="modal-title">Novo Ambiente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nome *</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tipo *</label>
            <select name="env_type" class="form-select" required>
              <option value="PORTA">Porta</option>
              <option value="PORTAO">Portão</option>
              <option value="CANCELA">Cancela</option>
              <option value="CATRACA">Catraca</option>
              <option value="ELEVADOR">Elevador</option>
              <option value="AREA">Área Comum</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Dispositivo Principal</label>
            <select name="primary_device_id" class="form-select">
              <option value="">— Selecionar —</option>
              <?php foreach ($devices as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Dispositivos Auxiliares</label>
            <select name="aux_devices[]" class="form-select" multiple size="6">
              <?php foreach ($devices as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Use CTRL/Shift para selecionar vários.</div>
          </div>
          <div class="col-12">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="status" id="statusCreate" checked>
              <label class="form-check-label" for="statusCreate">Ativo</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit-id">
      <div class="modal-header">
        <h5 class="modal-title">Editar Ambiente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nome *</label>
            <input type="text" name="name" id="edit-name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tipo *</label>
            <select name="env_type" id="edit-env_type" class="form-select" required>
              <option value="PORTA">Porta</option>
              <option value="PORTAO">Portão</option>
              <option value="CANCELA">Cancela</option>
              <option value="CATRACA">Catraca</option>
              <option value="ELEVADOR">Elevador</option>
              <option value="AREA">Área Comum</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Dispositivo Principal</label>
            <select name="primary_device_id" id="edit-primary_device_id" class="form-select">
              <option value="">— Selecionar —</option>
              <?php foreach ($devices as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Dispositivos Auxiliares</label>
            <select name="aux_devices[]" id="edit-aux_devices" class="form-select" multiple size="6">
              <?php foreach ($devices as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Use CTRL/Shift para selecionar vários.</div>
          </div>
          <div class="col-12">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="status" id="edit-status">
              <label class="form-check-label" for="edit-status">Ativo</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar alterações</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Delete -->
<div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="delete-id">
      <div class="modal-header">
        <h5 class="modal-title">Excluir Ambiente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Tem certeza que deseja excluir <strong id="delete-name"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">Excluir</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const editModal = document.getElementById('modalEdit');
  editModal?.addEventListener('show.bs.modal', event => {
    const btn = event.relatedTarget;
    const data = JSON.parse(btn.getAttribute('data-env'));
    const aux = JSON.parse(btn.getAttribute('data-aux') || '[]');

    document.getElementById('edit-id').value = data.id;
    document.getElementById('edit-name').value = data.name || '';
    document.getElementById('edit-env_type').value = data.env_type || 'PORTA';
    document.getElementById('edit-primary_device_id').value = data.primary_device_id || '';

    const sel = document.getElementById('edit-aux_devices');
    for (const option of sel.options) {
      option.selected = aux.includes(Number(option.value));
    }

    document.getElementById('edit-status').checked = Number(data.status) === 1;
  });

  const delModal = document.getElementById('modalDelete');
  delModal?.addEventListener('show.bs.modal', event => {
    const button = event.relatedTarget;
    const id = button.getAttribute('data-id');
    const name = button.getAttribute('data-name');
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-name').textContent = name;
  });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
