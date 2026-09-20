<?php
declare(strict_types=1);

final class Options
{
    public const STATUSES = ['correct' => 'Correct', 'needs_attention' => 'Needs Attention', 'unknown' => 'Unknown'];
    public const TYPES = ['central_ac' => 'Central A/C', 'mini_split' => 'Mini Split'];
    public const MAINTENANCE = [
        'preventive_maintenance' => 'Preventive Maintenance', 'deep_cleaning' => 'Deep Cleaning',
        'filter_change' => 'Filter Change', 'drain_cleaning' => 'Drain Cleaning',
        'drain_repair' => 'Drain Repair', 'line_set_insulation_repair' => 'Line Set Insulation Repair',
        'leak_inspection' => 'Leak Inspection', 'condensate_issue' => 'Condensate Issue',
        'general_repair' => 'General Repair', 'inspection' => 'Inspection', 'other' => 'Other',
    ];
    public const SUMMARY = ['deep_cleaning' => 'last_deep_cleaning',
        'preventive_maintenance' => 'last_preventive_maintenance', 'filter_change' => 'last_filter_change'];

    public static function text(array $input, string $key, int $max, bool $required = false): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || !preg_match('//u', $value)) {
            throw new InvalidArgumentException('Please enter valid text.');
        }
        $value = trim($value);
        if (preg_match_all('/./us', $value) > $max || ($required && $value === '')) {
            throw new InvalidArgumentException('Please complete the required fields and keep text within the displayed limits.');
        }
        return $value;
    }

    public static function date(array $input, string $key, bool $required = false): ?string
    {
        $value = self::text($input, $key, 10, $required);
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01' || $value > date('Y-m-d')) {
            throw new InvalidArgumentException('Use a valid date that is not in the future.');
        }
        return $value;
    }

    public static function choice(array $input, string $key, array $choices): string
    {
        $value = self::text($input, $key, 60, true);
        if (!array_key_exists($value, $choices)) throw new InvalidArgumentException('Please select a valid option.');
        return $value;
    }
}
