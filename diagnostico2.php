<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Teste de Login - Admin</h2><pre>";

// Testar se o login.php tem erro de sintaxe
echo "Testando sintaxe admin/login.php...\n";
$output = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/admin/login.php') . ' 2>&1');
echo "Resultado: $output\n";

echo "Testando sintaxe tecnico/login.php...\n";
$output = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/tecnico/login.php') . ' 2>&1');
echo "Resultado: $output\n";

echo "Testando sintaxe api/config.php...\n";
$output = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/api/config.php') . ' 2>&1');
echo "Resultado: $output\n";

echo "Testando sintaxe api/auth-admin.php...\n";
$output = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/api/auth-admin.php') . ' 2>&1');
echo "Resultado: $output\n";

echo "Testando sintaxe api/auth-tecnico.php...\n";
$output = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/api/auth-tecnico.php') . ' 2>&1');
echo "Resultado: $output\n";

// Versão do PHP
echo "\nPHP: " . phpversion() . "\n";

// Testar include do config
echo "\n--- Testando require config ---\n";
try {
    require_once __DIR__ . '/api/config.php';
    echo "config.php: OK\n";
} catch (Throwable $e) {
    echo "config.php ERRO: " . $e->getMessage() . " em " . $e->getFile() . " linha " . $e->getLine() . "\n";
}

// Testar include do auth-admin
echo "\n--- Testando require auth-admin ---\n";
try {
    // Simular session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once __DIR__ . '/api/auth-admin.php';
    echo "auth-admin.php: OK\n";
} catch (Throwable $e) {
    echo "auth-admin.php ERRO: " . $e->getMessage() . " em " . $e->getFile() . " linha " . $e->getLine() . "\n";
}

// Checar error_log do servidor
echo "\n--- Error Log ---\n";
$logFile = __DIR__ . '/error_log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $last = array_slice($lines, -20);
    foreach ($last as $l) echo $l;
} else {
    echo "Nenhum error_log local encontrado\n";
    // Tentar o error_log do diretório pai
    $parentLog = dirname(__DIR__) . '/error_log';
    if (file_exists($parentLog)) {
        echo "Encontrado error_log no diretório pai:\n";
        $lines = file($parentLog);
        $last = array_slice($lines, -20);
        foreach ($last as $l) echo $l;
    }
}

echo "</pre>";
?>
