<?php
declare(strict_types=1);
// Run only against an EMPTY, explicitly named disposable database.
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/HvacRepository.php';
require __DIR__ . '/run.php';
$name = getenv('FRV_TEST_DATABASE');
if (!$name || !str_starts_with($name, 'frv_test_')) throw new RuntimeException('Use a disposable frv_test_* database');
$db = Database::connect(['host' => getenv('FRV_TEST_HOST') ?: '127.0.0.1', 'port' => getenv('FRV_TEST_PORT') ?: 3306,
    'database' => $name, 'username' => getenv('FRV_TEST_USER') ?: 'root', 'password' => getenv('FRV_TEST_PASSWORD') ?: '']);
check($db->query('SHOW TABLES')->fetchAll() === [], 'Integration database must be empty');
$sql = file_get_contents(__DIR__ . '/../database/schema.sql');
foreach (explode(';', $sql) as $statement) if (trim($statement)) $db->exec($statement);
// Reapplying the fresh schema must be non-destructive.
foreach (explode(';', $sql) as $statement) if (trim($statement)) $db->exec($statement);
$db->exec("INSERT INTO buildings (code, name) VALUES ('TEST', 'Disposable test fixture')");
$db->exec("INSERT INTO apartments (building_id, unit) VALUES (1, 'TEST-A'), (1, 'TEST-B')");
$repo = new HvacRepository($db);
$repo->initialize(1); $repo->initialize(1);
check(count($repo->units(1)) === 2, 'Defaults are idempotent');
$repo->initialize(1, true); $repo->initialize(1, true);
$units = $repo->units(1);
check(array_column($units, 'unit_name') === ['Central A/C', 'Mini Split 1', 'Mini Split 2', 'Mini Split 3'], 'Equipment ordering and numbering');
check(count($repo->units(2)) === 0, 'Other apartments unchanged');
$unitId = (int) $units[1]['id'];
$form = ['unit_name' => 'Bedroom', 'overall_status' => 'needs_attention', 'line_set_status' => 'correct', 'drain_line_status' => 'unknown', 'pending_work' => '<repair>',
    'last_deep_cleaning' => '', 'last_preventive_maintenance' => '', 'last_filter_change' => '', 'version' => '1'];
$repo->save($unitId, $form);
rejects(fn () => $repo->save($unitId, $form));
check($repo->equipment($unitId)['pending_work'] === '<repair>', 'Text round-trip');
$token = str_repeat('a', 64);
$repo->addHistory($unitId, ['service_date' => '2024-05-20', 'maintenance_type' => 'deep_cleaning', 'notes' => 'Cleaned'], $token);
$repo->addHistory($unitId, ['service_date' => '2024-05-20', 'maintenance_type' => 'deep_cleaning', 'notes' => 'Cleaned'], $token);
check(count($repo->history($unitId)) === 1, 'Duplicate maintenance prevented');
$repo->addHistory($unitId, ['service_date' => '2024-01-01', 'maintenance_type' => 'deep_cleaning', 'notes' => 'Backfill'], str_repeat('b', 64));
check($repo->equipment($unitId)['last_deep_cleaning'] === '2024-05-20', 'Backfill does not regress date');
check($repo->history($unitId)[0]['service_date'] === '2024-05-20', 'Newest service first');
foreach (['preventive_maintenance' => 'last_preventive_maintenance', 'filter_change' => 'last_filter_change'] as $type => $field) {
    $repo->addHistory($unitId, ['service_date' => '2024-06-01', 'maintenance_type' => $type, 'notes' => ''], hash('sha256', $type));
    check($repo->equipment($unitId)[$field] === '2024-06-01', 'Summary sync: ' . $type);
}
check($repo->equipment((int) $units[2]['id'])['last_deep_cleaning'] === null, 'Equipment independence');
$form['version'] = (string) $repo->equipment($unitId)['version']; $form['last_deep_cleaning'] = '2024-04-01';
$repo->save($unitId, $form);
check($repo->equipment($unitId)['last_deep_cleaning'] === '2024-04-01', 'Manual date correction');
check(count($repo->history($unitId)) === 4, 'History retained after correction');
[$equipment, $history] = $repo->export();
check(count($equipment) === 4 && count($history) === 4, 'Complete export');
try { $db->exec("INSERT INTO hvac_units (apartment_id,equipment_type,unit_number,unit_name,pending_work) VALUES (1,'central_ac',2,'Duplicate','')"); throw new RuntimeException('Central constraint missing'); } catch (PDOException $error) {}
try { $repo->initialize(9999); throw new RuntimeException('Missing apartment accepted'); } catch (OutOfBoundsException $error) {}
echo "MySQL/MariaDB integration tests passed.\n";
