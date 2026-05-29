<?php
/**
 * Autenticação de Administrador
 */

require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }
}

function fazerLoginAdmin($email, $senha) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM administradores WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($senha, $admin['senha'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_nome'] = $admin['nome'];
        $_SESSION['admin_email'] = $admin['email'];
        return true;
    }
    
    return false;
}

function fazerLogoutAdmin() {
    session_destroy();
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}

function criarAdminPadrao() {
    $db = getDB();
    
    // Verificar se já existe algum admin
    $stmt = $db->query("SELECT COUNT(*) as total FROM administradores");
    $result = $stmt->fetch();
    
    if ($result['total'] == 0) {
        // Criar admin padrão
        $senha_hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO administradores (nome, email, senha) VALUES (?, ?, ?)");
        $stmt->execute(['Administrador', 'admin@madetech.com.br', $senha_hash]);
    }
}

// Criar admin padrão se não existir
criarAdminPadrao();
?>
