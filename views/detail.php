<?php
$values = $equipment;
$recordValues = ['service_date' => date('Y-m-d'), 'maintenance_type' => 'preventive_maintenance', 'notes' => ''];
if ($old && (int) $old['id'] === (int) $equipment['id']) {
    // Retain only scalar form fields; forged arrays must never reach output helpers.
    $safeOld = array_filter($old['values'], 'is_string');
    if ($old['action'] === 'save') $values = array_replace($values, $safeOld);
    if ($old['action'] === 'history') $recordValues = array_replace($recordValues, $safeOld);
}
$pipeOptions = Options::STATUSES;
if ($equipment['equipment_type'] === 'central_ac') $pipeOptions['not_applicable'] = 'Not Applicable';
$lastMaintenanceDates = array_filter([$equipment['last_maintenance'], $equipment['last_deep_cleaning'], $equipment['last_preventive_maintenance'], $equipment['last_filter_change']]);
?>
<p class="muted">Building <?= e($equipment['building_code']) ?> · Apartment <?= e($equipment['unit']) ?> · <?= e(label($equipment['equipment_type'], Options::TYPES)) ?><?= $equipment['equipment_type'] === 'mini_split' ? ' ' . e($equipment['unit_number']) : '' ?></p>
<section class="panel summary" aria-label="Equipment summary"><div><span class="badge <?= e($equipment['overall_status']) ?>"><?= e(label($equipment['overall_status'], Options::STATUSES)) ?></span></div><dl><div><dt>Last Maintenance</dt><dd><?= e(displayDate($lastMaintenanceDates ? max($lastMaintenanceDates) : null)) ?></dd></div><div><dt>Last Updated</dt><dd><?= e(displayDate($equipment['updated_at'])) ?></dd></div><div class="wide"><dt>Pending Work</dt><dd class="multiline"><?= e($equipment['pending_work'] ?: 'None') ?></dd></div></dl><a class="button" href="#add-maintenance">Add Maintenance Record</a></section>
<details class="panel" <?= $old && $old['action'] === 'save' ? 'open' : '' ?>><summary>Edit Equipment</summary>
<form method="post" action="save.php" class="fields"><?php formFields('save', (int) $equipment['id']); ?><input type="hidden" name="version" value="<?= e($values['version']) ?>">
<label>Unit Name <span class="hint">Maximum 100 characters</span><input name="unit_name" value="<?= e($values['unit_name']) ?>" maxlength="100" required></label>
<?php selectField('overall_status', 'Overall Status', Options::STATUSES, $values['overall_status']); selectField('line_set_status', 'Line Set / Pipe Status', $pipeOptions, $values['line_set_status']); selectField('drain_line_status', 'Drain Line Status', $pipeOptions, $values['drain_line_status']); ?>
<label class="wide">Pending Work <span class="hint">Maximum 10,000 characters</span><textarea name="pending_work" rows="4" maxlength="10000"><?= e($values['pending_work']) ?></textarea></label>
<?php foreach (Options::SUMMARY as $type => $field): ?><label>Last <?= e(Options::MAINTENANCE[$type]) ?><input type="date" name="<?= e($field) ?>" value="<?= e($values[$field]) ?>" min="1000-01-01" max="<?= date('Y-m-d') ?>"></label><?php endforeach ?>
<div class="wide"><button>Save Equipment</button></div></form></details>
<section id="add-maintenance" class="panel"><h2>Add Maintenance Record</h2><form method="post" action="save.php" class="fields"><?php formFields('history', (int) $equipment['id']); ?>
<label>Date<input name="service_date" type="date" required min="1000-01-01" max="<?= date('Y-m-d') ?>" value="<?= e($recordValues['service_date']) ?>"></label>
<?php selectField('maintenance_type', 'Maintenance Type', Options::MAINTENANCE, $recordValues['maintenance_type']); ?>
<label class="wide">Notes <span class="hint">Optional · maximum 10,000 characters</span><textarea name="notes" rows="3" maxlength="10000" placeholder="What was done? Any observations?"><?= e($recordValues['notes']) ?></textarea></label>
<div class="wide"><button>Save Maintenance Record</button></div></form></section>
<section><h2>Maintenance History <span class="count"><?= count($history) ?></span></h2><?php if (!$history): ?><p class="empty">No maintenance history yet.</p><?php endif ?>
<?php foreach ($history as $entry): ?><article class="panel history"><div class="history-heading"><h3><?= e(label($entry['maintenance_type'], Options::MAINTENANCE)) ?></h3><time datetime="<?= e($entry['service_date']) ?>"><?= e(displayDate($entry['service_date'])) ?></time></div><p class="multiline"><?= e($entry['notes'] ?: 'No notes recorded.') ?></p></article><?php endforeach ?></section>
