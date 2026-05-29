<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        echo "<br><b style='color:red'>ERRO FATAL:</b><br>";
        echo "Tipo: " . $error['type'] . "<br>";
        echo "Mensagem: " . htmlspecialchars($error['message']) . "<br>";
        echo "Arquivo: " . $error['file'] . "<br>";
        echo "Linha: " . $error['line'] . "<br>";
    }
});

echo "<h2>Diagnóstico Técnico Index</h2><pre>";

// Verificar versão do auth-tecnico no servidor
echo "--- auth-tecnico.php no servidor ---\n";
$content = file_get_contents(__DIR__ . '/api/auth-tecnico.php');
echo htmlspecialchars($content);

echo "\n\n--- Tentando carregar tecnico/index.php ---\n";
echo "</pre>";

// Simular sessão de técnico para testar
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['tecnico_id'] = 1;
$_SESSION['tecnico_nome'] = 'Teste';
$_SESSION['tecnico_email'] = 'teste@teste.com';

chdir(__DIR__ . '/tecnico');
try {
    include __DIR__ . '/tecnico/index.php';
} catch (Throwable $e) {
    echo "<pre><b style='color:red'>EXCEÇÃO:</b> " . $e->getMessage();
    echo "\nArquivo: " . $e->getFile();
    echo "\nLinha: " . $e->getLine();
    echo "\nTrace:\n" . $e->getTraceAsString() . "</pre>";
}
?>
