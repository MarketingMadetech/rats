<?php
// Capturar TODOS os erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Handler customizado para erros fatais
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

echo "<h2>Tentando carregar admin/index.php</h2>";
echo "<p>Se aparecer erro abaixo, essa é a causa do 500:</p>";
echo "<hr>";

// Simular acesso ao admin/index.php
chdir(__DIR__ . '/admin');
try {
    include __DIR__ . '/admin/index.php';
} catch (Throwable $e) {
    echo "<br><b style='color:red'>EXCEÇÃO:</b> " . $e->getMessage();
    echo "<br>Arquivo: " . $e->getFile();
    echo "<br>Linha: " . $e->getLine();
    echo "<br>Trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
