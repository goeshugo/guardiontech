<?php
require __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

/** Helpers */
function load_users(): array {
    return db()->query("SELECT id, name FROM users WHERE status=1 ORDER BY name")->fetchAll();
}
function load_groups(): array {
    return db()->query("SELECT id, name FROM groups WHERE status=1 ORDER BY name")->fetchAll();
}
function load_envs(): array {
    return db()->query("SELECT id, name FROM environments WHERE status=1 ORDER BY name")->fetchAll();
}
function load_rule(int $id): ?array {
    $st = db()->prepare("SELECT * FROM rules WHERE id=?");
    $st->execute([$id]);
    $rule = $st->fetch();
    if (!$rule) return null;
    $t = db()->prepare("SELECT target_type, target_id FROM rule_targets WHERE rule_id=?");
    $t->execute([$id]);
    $targets = $t->fetchAll();
    $a = db()->prepare("SELECT environment_id FROM rule_areas WHERE rule_id=?");
    $a->execute([$id]);
    $areas = $a->fetchAll();
    $s = db()->prepare("SELECT weekday, time_start, time_end FROM rule_schedules WHERE rule_id=? ORDER BY id");
    $s->execute([$id]);
    $schedules = $s->fetchAll();
    $rule['targets'] = $targets;
    $rule['areas'] = $areas;
    $rule['schedules'] = $schedules;
    return $rule;
}

/** Actions */
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'update') {
            $name = trim($_POST['name'] ?? '');
            $priority = (int)($_POST['priority'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;
            $valid_from = $_POST['valid_from'] ?: null;
            $valid_to = $_POST['valid_to'] ?: null;

            if ($name === '') throw new Exception('Nome é obrigatório.');

            if ($action === 'create') {
                $st = db()->prepare("INSERT INTO rules (name, priority, status, valid_from, valid_to) VALUES (?,?,?,?,?)");
                $st->execute([$name, $priority, $status, $valid_from, $valid_to]);
                $rule_id = (int)db()->lastInsertId();
            } else {
                $rule_id = (int)($_POST['id'] ?? 0);
                if ($rule_id <= 0) throw new Exception('ID inválido.');
                $st = db()->prepare("UPDATE rules SET name=?, priority=?, status=?, valid_from=?, valid_to=? WHERE id=?");
                $st->execute([$name, $priority, $status, $valid_from, $valid_to, $rule_id]);

                // wipe children for replace
                db()->prepare("DELETE FROM rule_targets WHERE rule_id=?")->execute([$rule_id]);
                db()->prepare("DELETE FROM rule_areas WHERE rule_id=?")->execute([$rule_id]);
                db()->prepare("DELETE FROM rule_schedules WHERE rule_id=?")->execute([$rule_id]);
            }

            // Targets
            $user_ids = array_filter(array_map('intval', $_POST['target_users'] ?? []));
            $group_ids = array_filter(array_map('intval', $_POST['target_groups'] ?? []));
            if ($user_ids) {
                $ins = db()->prepare("INSERT INTO rule_targets (rule_id, target_type, target_id) VALUES (?,?,?)");
                foreach ($user_ids as $uid) $ins->execute([$rule_id, 'USER', $uid]);
            }
            if ($group_ids) {
                $ins = db()->prepare("INSERT INTO rule_targets (rule_id, target_type, target_id) VALUES (?,?,?)");
                foreach ($group_ids as $gid) $ins->execute([$rule_id, 'GROUP', $gid]);
            }

            // Areas
            $area_ids = array_filter(array_map('intval', $_POST['areas'] ?? []));
            if ($area_ids) {
                $ins = db()->prepare("INSERT INTO rule_areas (rule_id, environment_id) VALUES (?,?)");
                foreach ($area_ids as $eid) $ins->execute([$rule_id, $eid]);
            }

            // Schedules
            $weekdays = $_POST['weekday'] ?? []; // array of sets e.g. ["MON,TUE", "SAT"]
            $tstart = $_POST['time_start'] ?? []; // array aligned
            $tend = $_POST['time_end'] ?? [];
            $ins = db()->prepare("INSERT INTO rule_schedules (rule_id, weekday, time_start, time_end) VALUES (?,?,?,?)");
            for ($i=0; $i < max(count($weekdays), count($tstart), count($tend)); $i++) {
                $w = $weekdays[$i] ?? '';
                $ts = $tstart[$i] ?? '';
                $te = $tend[$i] ?? '';
                if ($w && $ts && $te) $ins->execute([$rule_id, $w, $ts, $te]);
            }

            flash('success', $action === 'create' ? 'Regra criada.' : 'Regra atualizada.');
            redirect('?page=permissions');
        }
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');
            db()->prepare("DELETE FROM rules WHERE id=?")->execute([$id]);
            flash('success', 'Regra removida.');
            redirect('?page=permissions');
        }
        if ($action === 'simulate') {
            // handled below to show results
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('?page=permissions');
    }
}

/** Load data for view */
$rules = db()->query("SELECT r.*,
  (SELECT COUNT(*) FROM rule_targets t WHERE t.rule_id = r.id) AS targets_count,
  (SELECT COUNT(*) FROM rule_areas a WHERE a.rule_id = r.id) AS areas_count,
  (SELECT COUNT(*) FROM rule_schedules s WHERE s.rule_id = r.id) AS schedules_count
  FROM rules r ORDER BY priority DESC, id DESC")->fetchAll();

$users = load_users();
$groups = load_groups();
$envs = load_envs();

/** Simple simulator */
$sim_result = null;
if ($action === 'simulate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sim_user = (int)($_POST['sim_user'] ?? 0);
    $sim_env = (int)($_POST['sim_env'] ?? 0);
    $sim_dt = $_POST['sim_dt'] ?? date('Y-m-d\TH:i');
    $dt = new DateTime($sim_dt);
    $weekday_map = ['SUN','MON','TUE','WED','THU','FRI','SAT'];
    $wd = $weekday_map[(int)$dt->format('w')]; // 0=Sun
    $time = $dt->format('H:i:s');

    // group ids of user
    $stg = db()->prepare("SELECT group_id FROM user_group WHERE user_id=?");
    $stg->execute([$sim_user]);
    $gids = array_map('intval', array_column($stg->fetchAll(), 'group_id'));

    // candidate rules that target user or user's groups and area
    $q = "
    SELECT r.* FROM rules r
    WHERE r.status=1
      AND (r.valid_from IS NULL OR r.valid_from <= :dt)
      AND (r.valid_to IS NULL OR r.valid_to >= :dt)
      AND EXISTS (SELECT 1 FROM rule_areas a WHERE a.rule_id=r.id AND a.environment_id=:env)
      AND (
            EXISTS (SELECT 1 FROM rule_targets t WHERE t.rule_id=r.id AND t.target_type='USER' AND t.target_id=:uid)
         OR EXISTS (SELECT 1 FROM rule_targets t WHERE t.rule_id=r.id AND t.target_type='GROUP' AND t.target_id IN (%s))
      )
      AND EXISTS (SELECT 1 FROM rule_schedules s WHERE s.rule_id=r.id AND FIND_IN_SET(:wd, s.weekday) AND s.time_start <= :tm AND s.time_end >= :tm)
    ORDER BY r.priority DESC, r.id DESC
    ";
    $in = $gids ? implode(',', array_fill(0, count($gids), '?')) : 'NULL';
    $q = sprintf($q, $in);
    $st = db()->prepare($q);
    $params = [':dt'=>$dt->format('Y-m-d H:i:s'), ':env'=>$sim_env, ':uid'=>$sim_user, ':wd'=>$wd, ':tm'=>$time];
    $bind = array_values($params);
    // PDO named + ? mix: we'll rebuild with positional to keep it simple
    $qpos = "
    SELECT r.* FROM rules r
    WHERE r.status=1
      AND (r.valid_from IS NULL OR r.valid_from <= ?)
      AND (r.valid_to IS NULL OR r.valid_to >= ?)
      AND EXISTS (SELECT 1 FROM rule_areas a WHERE a.rule_id=r.id AND a.environment_id=?)
      AND (
            EXISTS (SELECT 1 FROM rule_targets t WHERE t.rule_id=r.id AND t.target_type='USER' AND t.target_id=?)
         OR EXISTS (SELECT 1 FROM rule_targets t WHERE t.rule_id=r.id AND t.target_type='GROUP' AND t.target_id IN (%s))
      )
      AND EXISTS (SELECT 1 FROM rule_schedules s WHERE s.rule_id=r.id AND FIND_IN_SET(?, s.weekday) AND s.time_start <= ? AND s.time_end >= ?)
    ORDER BY r.priority DESC, r.id DESC
    ";
    $qpos = sprintf($qpos, $in);
    $args = [$dt->format('Y-m-d H:i:s'), $dt->format('Y-m-d H:i:s'), $sim_env, $sim_user];
    foreach ($gids as $g) $args[] = $g;
    $args[] = $wd; $args[] = $time; $args[] = $time;

    $st2 = db()->prepare($qpos);
    $st2->execute($args);
    $allow = $st2->fetch();

    if ($allow) {
        $sim_result = ['result'=>'ALLOW', 'rule'=>$allow['name'] . ' (prio ' . (int)$allow['priority'] . ')'];
    } else {
        $sim_result = ['result'=>'DENY', 'rule'=>null];
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 m-0">Permissões (Regras)</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">Nova Regra</button>
</div>

<div class="card mb-4">
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="action" value="simulate">
      <div class="col-md-3">
        <label class="form-label">Usuário</label>
        <select name="sim_user" class="form-select" required>
          <option value="">— Selecionar —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Ambiente</label>
        <select name="sim_env" class="form-select" required>
          <option value="">— Selecionar —</option>
          <?php foreach ($envs as $e): ?>
            <option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Data/Hora</label>
        <input type="datetime-local" name="sim_dt" class="form-control" value="<?= e(date('Y-m-d\TH:i')) ?>">
      </div>
      <div class="col-md-3">
        <button class="btn btn-outline-secondary w-100" type="submit">Simular</button>
      </div>
      <?php if ($sim_result): ?>
        <div class="col-12 mt-2">
          <?php if ($sim_result['result'] === 'ALLOW'): ?>
            <div class="alert alert-success mb-0">PERMITIDO pela regra: <strong><?= e($sim_result['rule']) ?></strong></div>
          <?php else: ?>
            <div class="alert alert-danger mb-0">NEGADO. Nenhuma regra válida encontrada.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>ID</th>
      <th>Nome</th>
      <th>Prioridade</th>
      <th>Vigência</th>
      <th>Targets</th>
      <th>Áreas</th>
      <th>Horários</th>
      <th>Status</th>
      <th class="text-end">Ações</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rules as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= e($r['name']) ?></td>
      <td><?= (int)$r['priority'] ?></td>
      <td>
        <?php if ($r['valid_from'] || $r['valid_to']): ?>
          <?= e($r['valid_from'] ? now_br($r['valid_from']) : '—') ?> → <?= e($r['valid_to'] ? now_br($r['valid_to']) : '—') ?>
        <?php else: ?>
          Permanente
        <?php endif; ?>
      </td>
      <td><span class="badge bg-info-subtle text-dark"><?= (int)$r['targets_count'] ?></span></td>
      <td><span class="badge bg-info-subtle text-dark"><?= (int)$r['areas_count'] ?></span></td>
      <td><span class="badge bg-info-subtle text-dark"><?= (int)$r['schedules_count'] ?></span></td>
      <td>
        <?php if ((int)$r['status'] === 1): ?>
          <span class="badge bg-success">Ativa</span>
        <?php else: ?>
          <span class="badge bg-danger">Inativa</span>
        <?php endif; ?>
      </td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalEdit"
                data-rule='<?= e(json_encode(load_rule((int)$r['id']), JSON_UNESCAPED_UNICODE)) ?>'>
          Editar
        </button>
        <button class="btn btn-sm btn-outline-danger"
                data-bs-toggle="modal"
                data-bs-target="#modalDelete"
                data-id="<?= (int)$r['id'] ?>"
                data-name="<?= e($r['name']) ?>">
          Excluir
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rules): ?>
    <tr><td colspan="9" class="text-center text-muted">Nenhuma regra cadastrada.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<!-- Modal Create -->
<div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h5 class="modal-title">Nova Regra</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php include __DIR__ . '/permissions_form_fields.php'; ?>
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
  <div class="modal-dialog modal-xl">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit-id">
      <div class="modal-header">
        <h5 class="modal-title">Editar Regra</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="edit-form-body">
        <?php include __DIR__ . '/permissions_form_fields.php'; ?>
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
        <h5 class="modal-title">Excluir Regra</h5>
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
// Populate edit modal
document.addEventListener('DOMContentLoaded', () => {
  const editModal = document.getElementById('modalEdit');
  editModal?.addEventListener('show.bs.modal', event => {
    const btn = event.relatedTarget;
    const data = JSON.parse(btn.getAttribute('data-rule'));

    // Basic
    document.getElementById('edit-id').value = data.id;
    document.querySelector('#modalEdit [name=\"name\"]').value = data.name || '';
    document.querySelector('#modalEdit [name=\"priority\"]').value = data.priority ?? 0;
    document.querySelector('#modalEdit [name=\"status\"]').checked = Number(data.status) === 1;
    document.querySelector('#modalEdit [name=\"valid_from\"]').value = data.valid_from ? data.valid_from.replace(' ', 'T').slice(0,16) : '';
    document.querySelector('#modalEdit [name=\"valid_to\"]').value = data.valid_to ? data.valid_to.replace(' ', 'T').slice(0,16) : '';

    // Targets
    const selUsers = document.querySelector('#modalEdit [name=\"target_users[]\"]');
    const selGroups = document.querySelector('#modalEdit [name=\"target_groups[]\"]');
    for (const opt of selUsers.options) opt.selected = false;
    for (const opt of selGroups.options) opt.selected = false;
    (data.targets || []).forEach(t => {
      if (t.target_type === 'USER') {
        for (const opt of selUsers.options) if (Number(opt.value) === Number(t.target_id)) opt.selected = true;
      } else if (t.target_type === 'GROUP') {
        for (const opt of selGroups.options) if (Number(opt.value) === Number(t.target_id)) opt.selected = true;
      }
    });

    // Areas
    const selAreas = document.querySelector('#modalEdit [name=\"areas[]\"]');
    for (const opt of selAreas.options) opt.selected = false;
    (data.areas || []).forEach(a => {
      for (const opt of selAreas.options) if (Number(opt.value) === Number(a.environment_id)) opt.selected = true;
    });

    // Schedules
    const tbody = document.querySelector('#modalEdit tbody#sched-rows');
    tbody.innerHTML = '';
    (data.schedules || []).forEach(s => {
      addScheduleRow('modalEdit', s.weekday, s.time_start.slice(0,5), s.time_end.slice(0,5));
    });
    if ((data.schedules || []).length === 0) addScheduleRow('modalEdit');
  });
});

function addScheduleRow(scope, weekdayCsv = '', start = '', end = '') {
  const tbody = document.querySelector('#' + scope + ' tbody#sched-rows');
  const row = document.createElement('tr');
  row.innerHTML = `
    <td>
      <select name="weekday[]" class="form-select" multiple size="3">
        <option value="MON" ${weekdayCsv.includes('MON')?'selected':''}>Seg</option>
        <option value="TUE" ${weekdayCsv.includes('TUE')?'selected':''}>Ter</option>
        <option value="WED" ${weekdayCsv.includes('WED')?'selected':''}>Qua</option>
        <option value="THU" ${weekdayCsv.includes('THU')?'selected':''}>Qui</option>
        <option value="FRI" ${weekdayCsv.includes('FRI')?'selected':''}>Sex</option>
        <option value="SAT" ${weekdayCsv.includes('SAT')?'selected':''}>Sáb</option>
        <option value="SUN" ${weekdayCsv.includes('SUN')?'selected':''}>Dom</option>
      </select>
      <div class="form-text">Selecione 1+ dias</div>
    </td>
    <td><input type="time" name="time_start[]" class="form-control" value="${start}"></td>
    <td><input type="time" name="time_end[]" class="form-control" value="${end}"></td>
    <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove()">Remover</button></td>
  `;
  tbody.appendChild(row);
}

// Collect multiple selected options into comma-separated string before submit
document.addEventListener('submit', (e) => {
  const form = e.target.closest('form');
  if (!form) return;
  const weekSelects = form.querySelectorAll('select[name=\"weekday[]\"]');
  weekSelects.forEach(sel => {
    // create hidden input to replace multiple selection with CSV
    const values = Array.from(sel.selectedOptions).map(o => o.value).join(',');
    const hid = document.createElement('input');
    hid.type = 'hidden';
    hid.name = 'weekday[]';
    hid.value = values;
    sel.parentElement.appendChild(hid);
    sel.disabled = true; // avoid duplicate posts
  });
});
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
