<?php
declare(strict_types=1);
require __DIR__ . '/../src/Options.php';
require __DIR__ . '/../src/Support.php';
require __DIR__ . '/../src/ExportService.php';
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $callback): void {
    try { $callback(); } catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException('Expected invalid input to be rejected');
}
rejects(fn () => Options::date(['date' => '2025-02-29'], 'date'));
rejects(fn () => Options::date(['date' => '2999-01-01'], 'date'));
rejects(fn () => Options::date(['date' => ['2024-01-01']], 'date'));
rejects(fn () => Options::choice(['status' => 'hacked'], 'status', Options::STATUSES));
rejects(fn () => Options::text(['name' => ['x']], 'name', 100));
rejects(fn () => Options::text(['name' => str_repeat('x', 101)], 'name', 100));
rejects(fn () => id('0'));
rejects(fn () => id(['1']));
rejects(fn () => id('1 OR 1=1'));
rejects(fn () => id('4294967296'));
check(Options::date(['date' => '2024-02-29'], 'date') === '2024-02-29', 'Leap day');
check(Options::date([], 'date') === null, 'Optional date');
check(e('<script>') === '&lt;script&gt;', 'HTML escaping');
$stream = fopen('php://temp', 'w+');
ExportService::csv($stream, [['=1+1', ' @SUM(1)', 'normal', '001', "two\nlines"]]);
rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
check(str_contains($csv, "'=1+1") && str_contains($csv, "' @SUM(1)"), 'CSV formula protection');
if (class_exists(ZipArchive::class)) {
    $path = tempnam(sys_get_temp_dir(), 'frv-test-');
    try {
        ExportService::xlsx($path, ['HVAC Equipment' => [['Header'], ['=1+1'], ['001'], ["A\x01&B"]], 'Maintenance History' => [['Notes'], ['<repair>']]]);
        $zip = new ZipArchive(); check($zip->open($path) === true, 'Workbook opens');
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        check(str_contains($sheet, 't="inlineStr"') && !str_contains($sheet, '<f>'), 'Cells remain text');
        check(str_contains($sheet, 'A&amp;B') && !str_contains($sheet, "\x01"), 'XML sanitization');
        check($zip->getFromName('xl/worksheets/sheet2.xml') !== false, 'Both sheets exist'); $zip->close();
    } finally { unlink($path); }
}
echo "Validation and export tests passed.\n";
