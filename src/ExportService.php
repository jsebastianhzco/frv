<?php
declare(strict_types=1);

/** Dependency-free SpreadsheetML workbook using PHP's standard ZIP extension. */
final class ExportService
{
    public static function tables(array $equipment, array $history): array {
        $units = [['Building', 'Apartment', 'Equipment Type', 'Equipment Name', 'Overall Status', 'Line Set / Pipe Status', 'Drain Line Status', 'Pending Work', 'Last Deep Cleaning', 'Last Preventive Maintenance', 'Last Filter Change', 'Last Updated']];
        $statuses = Options::STATUSES + ['not_applicable' => 'Not Applicable'];
        foreach ($equipment as $u) $units[] = [$u['building'], $u['apartment'], Options::TYPES[$u['equipment_type']] ?? $u['equipment_type'], $u['unit_name'],
            $statuses[$u['overall_status']] ?? $u['overall_status'], $statuses[$u['line_set_status']] ?? $u['line_set_status'], $statuses[$u['drain_line_status']] ?? $u['drain_line_status'],
            $u['pending_work'], $u['last_deep_cleaning'], $u['last_preventive_maintenance'], $u['last_filter_change'], $u['updated_at']];
        $events = [['Building', 'Apartment', 'Equipment', 'Service Date', 'Maintenance Type', 'Notes']];
        foreach ($history as $h) $events[] = [$h['building'], $h['apartment'], $h['equipment'], $h['service_date'], Options::MAINTENANCE[$h['maintenance_type']] ?? $h['maintenance_type'], $h['notes']];
        return ['HVAC Equipment' => $units, 'Maintenance History' => $events];
    }
    public static function csv($stream, array $rows): void {
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $safe = array_map(static function ($value): string {
                $value = (string) $value;
                // Neutralize spreadsheet formulas, including whitespace-prefixed input.
                return preg_match('/^[\s\x00-\x20]*[=+@-]/u', $value) ? "'" . $value : $value;
            }, $row);
            fputcsv($stream, $safe, ',', '"', '', "\r\n");
        }
    }
    private static function xml(string $text): string {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
    public static function xlsx(string $path, array $tables): void {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Export unavailable');
        try {
            $types = '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
            $workbook = '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
            $rels = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
            $i = 0;
            foreach ($tables as $name => $rows) {
                $i++;
                $workbook .= '<sheet name="' . self::xml($name) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
                $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
                $types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="12" width="24" customWidth="1"/></cols><sheetData>';
                foreach ($rows as $r => $row) {
                    $xml .= '<row r="' . ($r + 1) . '">';
                    foreach ($row as $value) $xml .= '<c t="inlineStr"><is><t xml:space="preserve">' . self::xml((string) $value) . '</t></is></c>';
                    $xml .= '</row>';
                }
                $xml .= '</sheetData><autoFilter ref="A1:' . chr(64 + count($rows[0])) . count($rows) . '"/></worksheet>';
                if (!$zip->addFromString('xl/worksheets/sheet' . $i . '.xml', $xml)) throw new RuntimeException('Export unavailable');
            }
            $zip->addFromString('[Content_Types].xml', $types . '</Types>');
            $zip->addFromString('xl/workbook.xml', $workbook . '</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', $rels . '</Relationships>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        } catch (Throwable $error) { $zip->close(); throw $error; }
        if (!$zip->close()) throw new RuntimeException('Export unavailable');
    }
}
