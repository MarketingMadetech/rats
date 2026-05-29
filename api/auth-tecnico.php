<?php
/**
 * Autenticação de Técnico
 */

require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarTecnico() {
    if (!isset($_SESSION['tecnico_id'])) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . BASE_URL . 'tecnico/login.php' . ($redirect ? '?redirect=' . urlencode($redirect) : ''));
        exit;
    }
}

function fazerLoginTecnico($email, $senha) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tecnicos WHERE email = ?");
    $stmt->execute([$email]);
    $tecnico = $stmt->fetch();
    
    if ($tecnico && password_verify($senha, $tecnico['senha'])) {
        $_SESSION['tecnico_id'] = $tecnico['id'];
        $_SESSION['tecnico_nome'] = $tecnico['nome'];
        $_SESSION['tecnico_email'] = $tecnico['email'];
        return true;
    }
    
    return false;
}

function fazerLogoutTecnico() {
    session_destroy();
    header('Location: ' . BASE_URL . 'tecnico/login.php');
    exit;
}

function obterTecnicoAtual() {
    if (!isset($_SESSION['tecnico_id'])) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tecnicos WHERE id = ?");
    $stmt->execute([$_SESSION['tecnico_id']]);
    return $stmt->fetch();
}
?>
