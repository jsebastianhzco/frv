<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> | FRV Maintenance</title><link rel="stylesheet" href="assets/app.css"><script src="assets/app.js" defer></script></head>
<body><header><a class="brand" href="index.php">FRV <span>Maintenance</span></a><span class="property">Forest Ridge Villas</span></header>
<main><nav aria-label="Breadcrumb"><?php foreach ($crumbs as [$text, $link]): ?><a href="<?= e($link) ?>"><?= e($text) ?></a><?php endforeach ?></nav>
<?php if ($flash): ?><div class="notice <?= e($flash[0]) ?>" role="status"><?= e($flash[1]) ?></div><?php endif ?>
<div class="page-heading"><div><p class="eyebrow">HVAC maintenance</p><h1><?= e($title) ?></h1></div><?php if ($page === 'buildings'): ?><a class="button secondary" href="export.php">Export HVAC Data</a><?php endif ?></div>
<?php if ($page === 'home'): ?>
    <p class="muted">Choose a department to get started.</p><div class="grid"><a class="card module" href="<?= e(url(['page' => 'buildings'])) ?>"><strong>HVAC</strong><span>Equipment &amp; maintenance history</span><span class="arrow" aria-hidden="true">→</span></a></div>
<?php elseif ($page === 'buildings'): ?>
    <p class="muted">Choose the building you are working in.</p><div class="grid compact"><?php foreach ($items as $item): ?><a class="card" href="<?= e(url(['page' => 'apartments', 'id' => $item['id']])) ?>"><strong><?= e($item['code']) ?></strong><?php if ($item['name']): ?><span><?= e($item['name']) ?></span><?php endif ?></a><?php endforeach ?></div><?php if (!$items): ?><p class="empty">No buildings found.</p><?php endif ?>
<?php elseif ($page === 'apartments'): ?>
    <p class="muted">Select an apartment.</p><div class="grid compact"><?php foreach ($items as $item): ?>
    <form method="post" action="save.php"><?php formFields('initialize', (int) $item['id']); ?><button class="card" type="submit"><strong><?= e($item['unit']) ?></strong><span>View equipment →</span></button></form>
    <?php endforeach ?></div><?php if (!$items): ?><p class="empty">No apartments found for this building.</p><?php endif ?>
<?php elseif ($page === 'equipment'): ?>
    <p class="muted">Building <?= e($apartment['building_code']) ?> · Select HVAC equipment.</p><div class="grid">
    <?php foreach ($items as $item): ?><a class="card" href="<?= e(url(['page' => 'detail', 'id' => $item['id']])) ?>"><span class="eyebrow"><?= e(label($item['equipment_type'], Options::TYPES)) ?><?= $item['equipment_type'] === 'mini_split' ? ' ' . e($item['unit_number']) : '' ?></span><strong><?= e($item['unit_name']) ?></strong><span class="badge <?= e($item['overall_status']) ?>"><?= e(label($item['overall_status'], Options::STATUSES)) ?></span></a><?php endforeach ?></div>
    <?php if (!$items): ?><form method="post" action="save.php" class="panel"><p>Open this apartment’s default Central A/C and Mini Split 1.</p><?php formFields('initialize', (int) $apartment['id']); ?><button>Open HVAC Equipment</button></form><?php else: ?><form method="post" action="save.php" class="action-row"><?php formFields('add_mini', (int) $apartment['id']); ?><button class="secondary">+ Add Mini Split</button></form><?php endif ?>
<?php elseif ($page === 'detail'): require __DIR__ . '/detail.php'; endif ?>
</main><footer>Forest Ridge Villas · Maintenance</footer></body></html>
