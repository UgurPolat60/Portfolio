<?php
require_once 'config.php';

header('Content-Type: application/json');

$token = getCsrfToken();

echo json_encode([
    'success' => true,
    'token' => $token,
]);
exit();
?>


