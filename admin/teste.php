<?php
// Teste simples dentro de admin/
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "PHP funciona em admin/<br>";
echo "PHP " . phpversion() . "<br>";

// Tentar carregar o que o login.php carrega
echo "<br>Testando includes do login.php:<br>";

try {
    require_once __DIR__ . '/../api/config.php';
    echo "config.php: OK<br>";
} catch (Throwable $e) {
    echo "config.php ERRO: " . $e->getMessage() . "<br>";
}

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    // Não redirecionar - só testar se carrega
    echo "Session: OK<br>";
} catch (Throwable $e) {
    echo "Session ERRO: " . $e->getMessage() . "<br>";
}

echo "<br>Se voce ve isso, o admin/ funciona. O problema esta no login.php.<br>";
echo "<br><a href='login.php'>Testar login.php</a>";
?>
