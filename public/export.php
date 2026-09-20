<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/ExportService.php';
$format = $_GET['format'] ?? 'xlsx';
if (!is_string($format) || !in_array($format, ['xlsx', 'equipment', 'history'], true)) { http_response_code(400); exit('Invalid export format.'); }
if ($format === 'xlsx' && !class_exists(ZipArchive::class)) {
    $title = 'Export HVAC Data';
    require __DIR__ . '/../views/export.php';
    exit;
}
$tables = ExportService::tables(...$repository->export());
$stamp = date('Y-m-d');
if ($format !== 'xlsx') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="frv-hvac-' . $format . '-' . $stamp . '.csv"');
    $stream = fopen('php://output', 'wb');
    ExportService::csv($stream, $tables[$format === 'equipment' ? 'HVAC Equipment' : 'Maintenance History']);
    fclose($stream);
    exit;
}
$path = tempnam(sys_get_temp_dir(), 'frv-export-');
if ($path === false) throw new RuntimeException('Export unavailable');
try {
    ExportService::xlsx($path, $tables);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="frv-hvac-' . $stamp . '.xlsx"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
} finally { unlink($path); }
