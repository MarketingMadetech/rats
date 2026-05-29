<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        echo "<br><b style='color:red'>ERRO FATAL:</b><br>";
        echo "Tipo: " . $error['type'] . "<br>";
        echo "Mensagem: " . $error['message'] . "<br>";
        echo "Arquivo: " . $error['file'] . "<br>";
        echo "Linha: " . $error['line'] . "<br>";
    }
});

echo "<h2>Diagnóstico Técnico</h2><pre>";

// Verificar .htaccess
echo "--- .htaccess tecnico ---\n";
$ht = __DIR__ . '/tecnico/.htaccess';
if (file_exists($ht)) {
    echo file_get_contents($ht);
} else {
    echo "(NÃO EXISTE)\n";
}

echo "\n--- Testando login.php ---\n";
$output = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/tecnico/login.php') . ' 2>&1');
echo "Sintaxe: $output\n";

echo "--- Testando auth-tecnico.php ---\n";
$output = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/api/auth-tecnico.php') . ' 2>&1');
echo "Sintaxe: $output\n";

echo "--- Conteúdo auth-tecnico.php (primeiras 10 linhas) ---\n";
$lines = file(__DIR__ . '/api/auth-tecnico.php');
for ($i = 0; $i < min(10, count($lines)); $i++) {
    echo ($i+1) . ": " . $lines[$i];
}

echo "\n--- Tentando carregar tecnico/login.php ---\n";
echo "</pre>";

chdir(__DIR__ . '/tecnico');
try {
    include __DIR__ . '/tecnico/login.php';
} catch (Throwable $e) {
    echo "<pre><b style='color:red'>EXCEÇÃO:</b> " . $e->getMessage();
    echo "\nArquivo: " . $e->getFile();
    echo "\nLinha: " . $e->getLine();
    echo "\nTrace:\n" . $e->getTraceAsString() . "</pre>";
}
?>
