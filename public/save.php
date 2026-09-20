<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Please use the form to save changes.'); }
$target = url(['page' => 'buildings']);
try {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(), $_POST['csrf'])) throw new InvalidArgumentException('Your session expired. Reload the page and try again.');
    $action = Options::choice($_POST, 'action', array_fill_keys(['initialize', 'add_mini', 'save', 'history'], ''));
    $recordId = id($_POST['id'] ?? null);
    $target = url(['page' => in_array($action, ['initialize', 'add_mini'], true) ? 'equipment' : 'detail', 'id' => $recordId]);
    $token = Options::text($_POST, 'request_token', 64, true);
    if (!isset($_SESSION['forms'][$token])) { $_SESSION['flash'] = ['info', 'This form was already submitted or expired. Check the current information before trying again.']; redirect($target); }
    if ($action === 'initialize' || $action === 'add_mini') $repository->initialize($recordId, $action === 'add_mini');
    elseif ($action === 'save') $repository->save($recordId, $_POST);
    else $repository->addHistory($recordId, $_POST, $token);
    unset($_SESSION['forms'][$token]);
    if ($action !== 'initialize') $_SESSION['flash'] = ['success', $action === 'history' ? 'Maintenance record added.' : ($action === 'add_mini' ? 'Mini Split added.' : 'Equipment saved.')];
} catch (InvalidArgumentException | OutOfBoundsException $error) {
    $_SESSION['flash'] = ['error', $error->getMessage()];
    $_SESSION['old'] = ['id' => $recordId ?? 0, 'action' => $action ?? '', 'values' => $_POST];
}
redirect($target);
