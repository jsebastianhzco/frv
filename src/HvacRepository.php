<?php
declare(strict_types=1);

final class HvacRepository
{
    public function __construct(private PDO $db) {}
    public function rows(string $sql, array $params = []): array {
        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll();
    }
    private function execute(string $sql, array $params): void {
        $query = $this->db->prepare($sql);
        $query->execute($params);
    }
    public function buildings(): array { return $this->rows('SELECT * FROM buildings ORDER BY code'); }
    public function building(int $id): array {
        return $this->rows('SELECT * FROM buildings WHERE id = ?', [$id])[0] ?? throw new OutOfBoundsException('Building not found.');
    }
    public function apartments(int $id): array { return $this->rows('SELECT * FROM apartments WHERE building_id = ? ORDER BY unit', [$id]); }
    public function apartment(int $id): array {
        return $this->rows('SELECT a.*, b.code AS building_code FROM apartments a JOIN buildings b ON b.id = a.building_id WHERE a.id = ?', [$id])[0]
            ?? throw new OutOfBoundsException('Apartment not found.');
    }
    public function equipment(int $id): array {
        return $this->rows('SELECT u.*, a.unit, a.building_id, b.code AS building_code,
            (SELECT MAX(service_date) FROM hvac_maintenance_history h WHERE h.hvac_unit_id = u.id) AS last_maintenance
            FROM hvac_units u JOIN apartments a ON a.id = u.apartment_id JOIN buildings b ON b.id = a.building_id WHERE u.id = ?', [$id])[0]
            ?? throw new OutOfBoundsException('Equipment not found.');
    }
    public function units(int $id): array {
        return $this->rows("SELECT * FROM hvac_units WHERE apartment_id = ? ORDER BY CASE equipment_type WHEN 'central_ac' THEN 0 ELSE 1 END, unit_number, id", [$id]);
    }
    private function transaction(callable $callback): mixed {
        $this->db->beginTransaction();
        try { $result = $callback(); $this->db->commit(); return $result; }
        catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }
    // The parent lock serializes initialization and numbering across ALL sessions.
    public function initialize(int $apartmentId, bool $addMiniSplit = false): void {
        $this->transaction(function () use ($apartmentId, $addMiniSplit): void {
            if (!$this->rows('SELECT id FROM apartments WHERE id = ? FOR UPDATE', [$apartmentId])) throw new OutOfBoundsException('Apartment not found.');
            foreach (['central_ac' => 'Central A/C', 'mini_split' => 'Mini Split 1'] as $type => $name) {
                $this->execute("INSERT INTO hvac_units (apartment_id, equipment_type, unit_number, unit_name, pending_work)
                    VALUES (?, ?, 1, ?, '') ON DUPLICATE KEY UPDATE id = id", [$apartmentId, $type, $name]);
            }
            if ($addMiniSplit) {
                $number = (int) $this->rows("SELECT MAX(unit_number) AS number FROM hvac_units WHERE apartment_id = ? AND equipment_type = 'mini_split'", [$apartmentId])[0]['number'] + 1;
                $this->execute("INSERT INTO hvac_units (apartment_id, equipment_type, unit_number, unit_name, pending_work) VALUES (?, 'mini_split', ?, ?, '')", [$apartmentId, $number, 'Mini Split ' . $number]);
            }
        });
    }
    public function save(int $equipmentId, array $input): void {
        $name = Options::text($input, 'unit_name', 100, true);
        $overall = Options::choice($input, 'overall_status', Options::STATUSES);
        $pipeChoices = Options::STATUSES;
        if ($this->equipment($equipmentId)['equipment_type'] === 'central_ac') $pipeChoices['not_applicable'] = 'Not Applicable';
        $line = Options::choice($input, 'line_set_status', $pipeChoices);
        $drain = Options::choice($input, 'drain_line_status', $pipeChoices);
        $pending = Options::text($input, 'pending_work', 10000);
        $dates = [];
        foreach (Options::SUMMARY as $field) $dates[] = Options::date($input, $field);
        $version = id($input['version'] ?? null);
        $query = $this->db->prepare('UPDATE hvac_units SET unit_name = ?, overall_status = ?, line_set_status = ?, drain_line_status = ?, pending_work = ?,
            last_deep_cleaning = ?, last_preventive_maintenance = ?, last_filter_change = ?, version = version + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND version = ?');
        $query->execute([$name, $overall, $line, $drain, $pending, ...$dates, $equipmentId, $version]);
        if ($query->rowCount() !== 1) throw new InvalidArgumentException('This equipment changed since you opened it. Reload the page before saving again.');
    }
    public function addHistory(int $equipmentId, array $input, string $token): void {
        $date = Options::date($input, 'service_date', true);
        $type = Options::choice($input, 'maintenance_type', Options::MAINTENANCE);
        $notes = Options::text($input, 'notes', 10000);
        $this->transaction(function () use ($equipmentId, $date, $type, $notes, $token): void {
            if (!$this->rows('SELECT id FROM hvac_units WHERE id = ? FOR UPDATE', [$equipmentId])) throw new OutOfBoundsException('Equipment not found.');
            if ($this->rows('SELECT id FROM hvac_maintenance_history WHERE request_token = ?', [$token])) return;
            $this->execute('INSERT INTO hvac_maintenance_history (hvac_unit_id, service_date, maintenance_type, notes, request_token) VALUES (?, ?, ?, ?, ?)', [$equipmentId, $date, $type, $notes, $token]);
            $field = Options::SUMMARY[$type] ?? null;
            // An older service event must not move the latest summary backwards.
            if ($field) $this->execute("UPDATE hvac_units SET $field = CASE WHEN $field IS NULL OR $field < ? THEN ? ELSE $field END WHERE id = ?", [$date, $date, $equipmentId]);
            $this->execute('UPDATE hvac_units SET version = version + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$equipmentId]);
        });
    }
    public function history(int $id): array {
        return $this->rows('SELECT * FROM hvac_maintenance_history WHERE hvac_unit_id = ? ORDER BY service_date DESC, id DESC', [$id]);
    }
    public function export(): array {
        $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        return $this->transaction(fn () => [
            $this->rows('SELECT b.code AS building, a.unit AS apartment, u.* FROM hvac_units u JOIN apartments a ON a.id = u.apartment_id JOIN buildings b ON b.id = a.building_id ORDER BY b.code, a.unit, u.equipment_type, u.unit_number'),
            $this->rows('SELECT b.code AS building, a.unit AS apartment, u.unit_name AS equipment, h.* FROM hvac_maintenance_history h JOIN hvac_units u ON u.id = h.hvac_unit_id JOIN apartments a ON a.id = u.apartment_id JOIN buildings b ON b.id = a.building_id ORDER BY h.service_date DESC, h.id DESC')
        ]);
    }
}
