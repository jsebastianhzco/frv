<?php
declare(strict_types=1);
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/HvacRepository.php';
$name = getenv('FRV_TEST_DATABASE');
if (!$name || !str_starts_with($name, 'frv_test_')) throw new RuntimeException('Use a disposable frv_test_* database');
$db = Database::connect(['host' => getenv('FRV_TEST_HOST') ?: '127.0.0.1', 'port' => getenv('FRV_TEST_PORT') ?: 3306,
    'database' => $name, 'username' => getenv('FRV_TEST_USER') ?: 'root', 'password' => getenv('FRV_TEST_PASSWORD') ?: '']);
$repo = new HvacRepository($db);
if (($argv[1] ?? '') === 'worker') { $repo->initialize((int) $argv[2], true); exit; }
// All workers initialize and add to the same previously empty apartment.
$query = $db->prepare('INSERT INTO apartments (building_id, unit) VALUES (1, ?)');
$query->execute(['RACE-' . bin2hex(random_bytes(4))]);
$apartmentId = (int) $db->lastInsertId();
$processes = [];
for ($i = 0; $i < 8; $i++) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, 'worker', (string) $apartmentId], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Worker failed to start');
    fclose($pipes[0]);
    $processes[] = [$process, $pipes];
}
foreach ($processes as [$process, $pipes]) {
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException('Concurrent worker failed: ' . $output);
}
$units = $repo->units($apartmentId);
if (count($units) !== 10 || $units[0]['equipment_type'] !== 'central_ac' || array_map('intval', array_column(array_slice($units, 1), 'unit_number')) !== range(1, 9)) {
    throw new RuntimeException('Concurrent initialization/numbering failed');
}
echo "Eight concurrent initialization/addition requests passed.\n";
