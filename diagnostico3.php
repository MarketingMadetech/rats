<?php
echo "<h2>Diagnóstico .htaccess</h2><pre>";

$files = [
    __DIR__ . '/.htaccess',
    __DIR__ . '/admin/.htaccess',
    __DIR__ . '/tecnico/.htaccess',
    __DIR__ . '/data/.htaccess',
    __DIR__ . '/backups/.htaccess',
    __DIR__ . '/api/.htaccess',
];

foreach ($files as $f) {
    $rel = str_replace(__DIR__, '', $f);
    echo "\n========== $rel ==========\n";
    if (file_exists($f)) {
        echo file_get_contents($f);
    } else {
        echo "(NÃO EXISTE)\n";
    }
    echo "\n";
}

// Testar acesso direto simulado
echo "\n========== TESTE DE ACESSO ==========\n";

// Verificar se o .htaccess raiz esta causando erro
$rootHt = __DIR__ . '/.htaccess';
if (file_exists($rootHt)) {
    $content = file_get_contents($rootHt);
    if (strpos($content, 'Order') !== false && strpos($content, 'mod_authz_core') === false) {
        echo "PROBLEMA: .htaccess raiz usa sintaxe Apache 2.2 sem fallback!\n";
    }
    if (strpos($content, 'php_value') !== false || strpos($content, 'php_flag') !== false) {
        echo "PROBLEMA: .htaccess raiz usa php_value/php_flag (incompatível com CGI/FPM)!\n";
    }
    if (strpos($content, 'mod_headers') !== false) {
        echo "AVISO: .htaccess usa mod_headers (pode causar 500 se módulo não instalado)\n";
    }
}

echo "\nTamanho dos arquivos de login:\n";
echo "admin/login.php: " . filesize(__DIR__ . '/admin/login.php') . " bytes\n";
echo "tecnico/login.php: " . filesize(__DIR__ . '/tecnico/login.php') . " bytes\n";

echo "</pre>";
?>
