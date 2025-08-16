<?php
require __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$action = $_POST['action'] ?? $_GET['action'] ?? null;

// Helpers to load selects
function load_environments(): array {
    $st = db()->query("SELECT id, name FROM environments WHERE status = 1 ORDER BY name");
    return $st->fetchAll();
}
function load_clients(): array {
    $st = db()->query("SELECT id, name FROM users WHERE status = 1 ORDER BY name");
    return $st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $dtype = $_POST['device_type'] ?? '';
            $environment_id = (int)($_POST['environment_id'] ?? 0) ?: null;
            $client_id = (int)($_POST['client_id'] ?? 0) ?: null;
            $ip = trim($_POST['ip'] ?? '') ?: null;
            $mac = trim($_POST['mac'] ?? '') ?: null;
            $fw = trim($_POST['fw_version'] ?? '') ?: null;
            $status = isset($_POST['status']) ? 1 : 0;

            if ($name === '') throw new Exception('Nome é obrigatório.');
            $allowedTypes = ['FACE','RFID','QRCODE','BIOMETRIA','FECHADURA','CONTROLADORA'];
            if (!in_array($dtype, $allowedTypes, true)) throw new Exception('Tipo de dispositivo inválido.');

            $stmt = db()->prepare("INSERT INTO devices (name, device_type, environment_id, client_id, ip, mac, status, fw_version) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$name,$dtype,$environment_id,$client_id,$ip,$mac,$status,$fw]);
            $device_id = (int)db()->lastInsertId();

            // Optional topics (MQTT)
            $t_cmd = trim($_POST['topic_cmd'] ?? '');
            $t_status = trim($_POST['topic_status'] ?? '');
            $t_event = trim($_POST['topic_event'] ?? '');
            if ($t_cmd !== '' && $t_status !== '') {
                $st2 = db()->prepare("INSERT INTO device_topics (device_id, topic_cmd, topic_status, topic_event) VALUES (?,?,?,?)");
                $st2->execute([$device_id, $t_cmd, $t_status, $t_event ?: null]);
            }

            flash('success', 'Dispositivo criado com sucesso.');
            redirect('?page=devices');
        }
        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');

            $name = trim($_POST['name'] ?? '');
            $dtype = $_POST['device_type'] ?? '';
            $environment_id = (int)($_POST['environment_id'] ?? 0) ?: null;
            $client_id = (int)($_POST['client_id'] ?? 0) ?: null;
            $ip = trim($_POST['ip'] ?? '') ?: null;
            $mac = trim($_POST['mac'] ?? '') ?: null;
            $fw = trim($_POST['fw_version'] ?? '') ?: null;
            $status = isset($_POST['status']) ? 1 : 0;

            if ($name === '') throw new Exception('Nome é obrigatório.');
            $allowedTypes = ['FACE','RFID','QRCODE','BIOMETRIA','FECHADURA','CONTROLADORA'];
            if (!in_array($dtype, $allowedTypes, true)) throw new Exception('Tipo de dispositivo inválido.');

            $stmt = db()->prepare("UPDATE devices SET name=?, device_type=?, environment_id=?, client_id=?, ip=?, mac=?, status=?, fw_version=? WHERE id=?");
            $stmt->execute([$name,$dtype,$environment_id,$client_id,$ip,$mac,$status,$fw,$id]);

            // Upsert topics
            $t_cmd = trim($_POST['topic_cmd'] ?? '');
            $t_status = trim($_POST['topic_status'] ?? '');
            $t_event = trim($_POST['topic_event'] ?? '');

            $check = db()->prepare("SELECT id FROM device_topics WHERE device_id=?");
            $check->execute([$id]);
            $existing = $check->fetchColumn();

            if ($t_cmd !== '' && $t_status !== '') {
                if ($existing) {
                    $st2 = db()->prepare("UPDATE device_topics SET topic_cmd=?, topic_status=?, topic_event=? WHERE device_id=?");
                    $st2->execute([$t_cmd, $t_status, $t_event ?: null, $id]);
                } else {
                    $st2 = db()->prepare("INSERT INTO device_topics (device_id, topic_cmd, topic_status, topic_event) VALUES (?,?,?,?)");
                    $st2->execute([$id, $t_cmd, $t_status, $t_event ?: null]);
                }
            } else {
                // if all empty and existed, remove row
                if ($existing) {
                    $del = db()->prepare("DELETE FROM device_topics WHERE device_id=?");
                    $del->execute([$id]);
                }
            }

            flash('success', 'Dispositivo atualizado.');
            redirect('?page=devices');
        }
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');
            $stmt = db()->prepare("DELETE FROM devices WHERE id=?");
            $stmt->execute([$id]);
            flash('success', 'Dispositivo removido.');
            redirect('?page=devices');
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('?page=devices');
    }
}

// Load lists for table
$sql = "SELECT d.*, e.name AS environment_name, u.name AS client_name,
        (SELECT topic_cmd FROM device_topics dt WHERE dt.device_id = d.id LIMIT 1) AS topic_cmd,
        (SELECT topic_status FROM device_topics dt WHERE dt.device_id = d.id LIMIT 1) AS topic_status,
        (SELECT topic_event FROM device_topics dt WHERE dt.device_id = d.id LIMIT 1) AS topic_event
        FROM devices d
        LEFT JOIN environments e ON e.id = d.environment_id
        LEFT JOIN users u ON u.id = d.client_id
        ORDER BY d.id DESC";
$devices = db()->query($sql)->fetchAll();
$envs = load_environments();
$clients = load_clients();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Dispositivos</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">Novo Dispositivo</button>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>ID</th>
      <th>Nome</th>
      <th>Tipo</th>
      <th>Ambiente</th>
      <th>Cliente</th>
      <th>IP</th>
      <th>MAC</th>
      <th>Status</th>
      <th>Últ. comunicação</th>
      <th>FW</th>
      <th class="text-end">Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($devices as $d): ?>
    <tr>
      <td><?= (int)$d['id'] ?></td>
      <td><?= e($d['name']) ?></td>
      <td><span class="badge bg-secondary"><?= e($d['device_type']) ?></span></td>
      <td><?= e($d['environment_name'] ?? '-') ?></td>
      <td><?= e($d['client_name'] ?? '-') ?></td>
      <td><?= e($d['ip']) ?></td>
      <td><?= e($d['mac']) ?></td>
      <td>
        <?php if ((int)$d['status'] === 1): ?>
          <span class="badge bg-success">Ativo</span>
        <?php else: ?>
          <span class="badge bg-danger">Inativo</span>
        <?php endif; ?>
      </td>
      <td><?= $d['last_seen'] ? e(now_br($d['last_seen'])) : '-' ?></td>
      <td><?= e($d['fw_version']) ?></td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalEdit"
                data-device='<?= e(json_encode($d, JSON_UNESCAPED_UNICODE)) ?>'>
          Editar
        </button>
        <button class="btn btn-sm btn-outline-danger"
                data-bs-toggle="modal"
                data-bs-target="#modalDelete"
                data-id="<?= (int)$d['id'] ?>"
                data-name="<?= e($d['name']) ?>">
          Excluir
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$devices): ?>
    <tr><td colspan="11" class="text-center text-muted">Nenhum dispositivo cadastrado.</td></tr>
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
        <h5 class="modal-title">Novo Dispositivo</h5>
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
            <select name="device_type" class="form-select" required>
              <option value="FACE">Leitor facial</option>
              <option value="RFID">RFID</option>
              <option value="QRCODE">QR Code</option>
              <option value="BIOMETRIA">Biometria</option>
              <option value="FECHADURA">Fechadura</option>
              <option value="CONTROLADORA">Controladora</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Ambiente</label>
            <select name="environment_id" class="form-select">
              <option value="">— Selecionar —</option>
              <?php foreach ($envs as $e): ?>
                <option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Cliente</label>
            <select name="client_id" class="form-select">
              <option value="">— Selecionar —</option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">IP</label>
            <input type="text" name="ip" class="form-control" placeholder="192.168.0.100">
          </div>
          <div class="col-md-4">
            <label class="form-label">MAC</label>
            <input type="text" name="mac" class="form-control" placeholder="AA:BB:CC:DD:EE:FF">
          </div>
          <div class="col-md-4">
            <label class="form-label">FW Version</label>
            <input type="text" name="fw_version" class="form-control" placeholder="v1.0.0">
          </div>
          <div class="col-md-12">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="status" id="statusCreate" checked>
              <label class="form-check-label" for="statusCreate">Ativo</label>
            </div>
          </div>
          <div class="col-12"><hr></div>
          <div class="col-12">
            <h6 class="mb-2">MQTT (opcional)</h6>
          </div>
          <div class="col-md-4">
            <label class="form-label">topic_cmd</label>
            <input type="text" name="topic_cmd" class="form-control" placeholder="/w/mod/XX/cmd">
          </div>
          <div class="col-md-4">
            <label class="form-label">topic_status</label>
            <input type="text" name="topic_status" class="form-control" placeholder="/w/mod/XX/status">
          </div>
          <div class="col-md-4">
            <label class="form-label">topic_event</label>
            <input type="text" name="topic_event" class="form-control" placeholder="/w/mod/XX/evt">
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
        <h5 class="modal-title">Editar Dispositivo</h5>
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
            <select name="device_type" id="edit-device_type" class="form-select" required>
              <option value="FACE">Leitor facial</option>
              <option value="RFID">RFID</option>
              <option value="QRCODE">QR Code</option>
              <option value="BIOMETRIA">Biometria</option>
              <option value="FECHADURA">Fechadura</option>
              <option value="CONTROLADORA">Controladora</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Ambiente</label>
            <select name="environment_id" id="edit-environment_id" class="form-select">
              <option value="">— Selecionar —</option>
              <?php foreach ($envs as $e): ?>
                <option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Cliente</label>
            <select name="client_id" id="edit-client_id" class="form-select">
              <option value="">— Selecionar —</option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">IP</label>
            <input type="text" name="ip" id="edit-ip" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">MAC</label>
            <input type="text" name="mac" id="edit-mac" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">FW Version</label>
            <input type="text" name="fw_version" id="edit-fw_version" class="form-control">
          </div>
          <div class="col-md-12">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="status" id="edit-status">
              <label class="form-check-label" for="edit-status">Ativo</label>
            </div>
          </div>
          <div class="col-12"><hr></div>
          <div class="col-12">
            <h6 class="mb-2">MQTT (opcional)</h6>
          </div>
          <div class="col-md-4">
            <label class="form-label">topic_cmd</label>
            <input type="text" name="topic_cmd" id="edit-topic_cmd" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">topic_status</label>
            <input type="text" name="topic_status" id="edit-topic_status" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">topic_event</label>
            <input type="text" name="topic_event" id="edit-topic_event" class="form-control">
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
        <h5 class="modal-title">Excluir Dispositivo</h5>
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
    const button = event.relatedTarget;
    const data = JSON.parse(button.getAttribute('data-device'));

    document.getElementById('edit-id').value = data.id;
    document.getElementById('edit-name').value = data.name || '';
    document.getElementById('edit-device_type').value = data.device_type || 'CONTROLADORA';
    document.getElementById('edit-environment_id').value = data.environment_id || '';
    document.getElementById('edit-client_id').value = data.client_id || '';
    document.getElementById('edit-ip').value = data.ip || '';
    document.getElementById('edit-mac').value = data.mac || '';
    document.getElementById('edit-fw_version').value = data.fw_version || '';
    document.getElementById('edit-status').checked = Number(data.status) === 1;

    document.getElementById('edit-topic_cmd').value = data.topic_cmd || '';
    document.getElementById('edit-topic_status').value = data.topic_status || '';
    document.getElementById('edit-topic_event').value = data.topic_event || '';
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
