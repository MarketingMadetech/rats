<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = isset($_SESSION['admin_id']);
$isTecnico = isset($_SESSION['tecnico_id']);

// Also check referer just in case
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referer, '/admin/') !== false) {
    $isAdmin = true;
} elseif (strpos($referer, '/tecnico/') !== false) {
    $isTecnico = true;
}

session_destroy();

if (isset($_GET['type'])) {
    if ($_GET['type'] === 'admin') {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    } elseif ($_GET['type'] === 'tecnico') {
        header('Location: ' . BASE_URL . 'tecnico/login.php');
        exit;
    }
}

if ($isAdmin) {
    header('Location: ' . BASE_URL . 'admin/login.php');
} elseif ($isTecnico) {
    header('Location: ' . BASE_URL . 'tecnico/login.php');
} else {
    header('Location: ' . BASE_URL . 'admin/login.php');
}
exit;
