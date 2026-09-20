<?php
declare(strict_types=1);
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function id(mixed $value): int {
    if (!is_string($value) || !ctype_digit($value) || (int) $value < 1 || strlen($value) > 10 || (int) $value > 4294967295) {
        throw new InvalidArgumentException('Please select a valid building, apartment, or equipment.');
    }
    return (int) $value;
}
function url(array $params = []): string { return 'index.php' . ($params ? '?' . http_build_query($params) : ''); }
function redirect(string $location): never { header('Location: ' . $location, true, 303); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function formFields(string $action, int $id): void {
    $token = bin2hex(random_bytes(32));
    $_SESSION['forms'][$token] = time();
    $_SESSION['forms'] = array_slice($_SESSION['forms'], -100, null, true);
    echo '<input type="hidden" name="csrf" value="' . e(csrf()) . '"><input type="hidden" name="request_token" value="' . e($token) . '">';
    echo '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">';
}
function displayDate(?string $value): string {
    if (!$value) return 'Not recorded';
    $date = new DateTimeImmutable($value, new DateTimeZone(str_contains($value, ' ') ? 'UTC' : date_default_timezone_get()));
    return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('M j, Y');
}
function label(string $value, array $options): string { return $options[$value] ?? $value; }
function selectField(string $name, string $title, array $options, string $value): void {
    echo '<label>' . e($title) . '<select name="' . e($name) . '" required>';
    foreach ($options as $key => $text) echo '<option value="' . e($key) . '"' . ($key === $value ? ' selected' : '') . '>' . e($text) . '</option>';
    echo '</select></label>';
}
