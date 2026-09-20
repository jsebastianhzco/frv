<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
try {
    $page = $_GET['page'] ?? 'home';
    if (!is_string($page) || !in_array($page, ['home', 'buildings', 'apartments', 'equipment', 'detail'], true)) throw new OutOfBoundsException('Page not found.');
    $title = 'FRV Maintenance';
    $crumbs = [['Home', url()]];
    if ($page !== 'home') $crumbs[] = ['HVAC', url(['page' => 'buildings'])];
    if ($page === 'buildings') { $title = 'Select Building'; $items = $repository->buildings(); }
    if ($page === 'apartments') {
        $building = $repository->building(id($_GET['id'] ?? null));
        $title = 'Building ' . $building['code']; $items = $repository->apartments((int) $building['id']);
        $crumbs[] = [$building['code'], url(['page' => 'apartments', 'id' => $building['id']])];
    }
    if ($page === 'equipment' || $page === 'detail') {
        if ($page === 'detail') { $equipment = $repository->equipment(id($_GET['id'] ?? null)); $apartment = $repository->apartment((int) $equipment['apartment_id']); }
        else $apartment = $repository->apartment(id($_GET['id'] ?? null));
        $crumbs[] = [$apartment['building_code'], url(['page' => 'apartments', 'id' => $apartment['building_id']])];
        $crumbs[] = ['Apartment ' . $apartment['unit'], url(['page' => 'equipment', 'id' => $apartment['id']])];
        $title = 'Apartment ' . $apartment['unit'];
        if ($page === 'equipment') $items = $repository->units((int) $apartment['id']);
        else { $title = $equipment['unit_name']; $history = $repository->history((int) $equipment['id']); }
    }
    $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    $old = $_SESSION['old'] ?? null; unset($_SESSION['old']);
    require __DIR__ . '/../views/layout.php';
} catch (InvalidArgumentException | OutOfBoundsException $error) {
    http_response_code($error instanceof OutOfBoundsException ? 404 : 400);
    $title = 'Unable to open this page'; $message = $error->getMessage();
    require __DIR__ . '/../views/error.php';
}
