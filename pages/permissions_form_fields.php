<?php
// Shared form fields for create/edit (expects $users, $groups, $envs to be available)
?>
<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Nome *</label>
    <input type="text" name="name" class="form-control" required>
  </div>
  <div class="col-md-2">
    <label class="form-label">Prioridade</label>
    <input type="number" name="priority" class="form-control" value="0" min="0">
  </div>
  <div class="col-md-4 d-flex align-items-end">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="status" id="statusRule" checked>
      <label class="form-check-label" for="statusRule">Ativa</label>
    </div>
  </div>
  <div class="col-md-6">
    <label class="form-label">Válida a partir de</label>
    <input type="datetime-local" name="valid_from" class="form-control">
  </div>
  <div class="col-md-6">
    <label class="form-label">Válida até</label>
    <input type="datetime-local" name="valid_to" class="form-control">
  </div>

  <div class="col-12"><hr></div>
  <div class="col-md-6">
    <label class="form-label">Usuários (alvo)</label>
    <select name="target_users[]" class="form-select" multiple size="6">
      <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="form-text">Use CTRL/Shift para selecionar vários.</div>
  </div>
  <div class="col-md-6">
    <label class="form-label">Grupos (alvo)</label>
    <select name="target_groups[]" class="form-select" multiple size="6">
      <?php foreach ($groups as $g): ?>
        <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-12"><hr></div>
  <div class="col-md-12">
    <label class="form-label">Ambientes permitidos</label>
    <select name="areas[]" class="form-select" multiple size="6" required>
      <?php foreach ($envs as $e): ?>
        <option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-12"><hr></div>
  <div class="col-12 d-flex justify-content-between align-items-center">
    <h6 class="m-0">Janelas de horário</h6>
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addScheduleRow(this.closest('.modal').id)">Adicionar janela</button>
  </div>
  <div class="col-12">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th style="width:45%">Dias da semana</th>
            <th style="width:20%">Início</th>
            <th style="width:20%">Fim</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="sched-rows">
          <tr>
            <td>
              <select name="weekday[]" class="form-select" multiple size="3">
                <option value="MON">Seg</option>
                <option value="TUE">Ter</option>
                <option value="WED">Qua</option>
                <option value="THU">Qui</option>
                <option value="FRI">Sex</option>
                <option value="SAT">Sáb</option>
                <option value="SUN">Dom</option>
              </select>
              <div class="form-text">Selecione 1+ dias</div>
            </td>
            <td><input type="time" name="time_start[]" class="form-control"></td>
            <td><input type="time" name="time_end[]" class="form-control"></td>
            <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove()">Remover</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
