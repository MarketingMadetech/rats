<?php
echo "<h2>Diagnóstico - Sistema RAT</h2>";
echo "<pre>";

// 1. Versão PHP
echo "PHP: " . phpversion() . "\n";

// 2. Extensões necessárias
$exts = ['pdo', 'pdo_sqlite', 'sqlite3', 'openssl', 'mbstring'];
foreach ($exts as $ext) {
    echo "Extensão $ext: " . (extension_loaded($ext) ? "OK" : "FALTANDO") . "\n";
}

// 3. Permissões de pasta
$dirs = [
    __DIR__,
    __DIR__ . '/data',
    __DIR__ . '/admin',
    __DIR__ . '/tecnico',
    __DIR__ . '/api',
    __DIR__ . '/vendor',
];
echo "\n--- Permissões ---\n";
foreach ($dirs as $d) {
    if (is_dir($d)) {
        echo basename($d) . "/: " . substr(sprintf('%o', fileperms($d)), -4) . " | writable: " . (is_writable($d) ? "SIM" : "NÃO") . "\n";
    } else {
        echo basename($d) . "/: NÃO EXISTE\n";
    }
}

// 4. Arquivo config.php
echo "\n--- Config ---\n";
$configFile = __DIR__ . '/api/config.php';
if (file_exists($configFile)) {
    echo "config.php: EXISTE\n";
    // Testar include
    try {
        require_once $configFile;
        echo "config.php: CARREGOU OK\n";
        echo "DB_PATH: " . (defined('DB_PATH') ? DB_PATH : 'NÃO DEFINIDO') . "\n";
        echo "BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'NÃO DEFINIDO') . "\n";
    } catch (Throwable $e) {
        echo "config.php ERRO: " . $e->getMessage() . " na linha " . $e->getLine() . "\n";
    }
} else {
    echo "config.php: NÃO EXISTE\n";
}

// 5. Vendor/autoload
echo "\n--- PHPMailer ---\n";
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    echo "vendor/autoload.php: EXISTE\n";
} else {
    echo "vendor/autoload.php: NÃO EXISTE\n";
}

// 6. Testar criação do banco
echo "\n--- SQLite ---\n";
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    echo "Pasta data/: NÃO EXISTE, tentando criar...\n";
    if (@mkdir($dataDir, 0755, true)) {
        echo "Pasta data/: CRIADA OK\n";
    } else {
        echo "Pasta data/: FALHA AO CRIAR\n";
    }
}
if (is_dir($dataDir) && is_writable($dataDir)) {
    try {
        $testDb = new PDO('sqlite:' . $dataDir . '/test.db');
        $testDb->exec("CREATE TABLE IF NOT EXISTS test (id INTEGER PRIMARY KEY)");
        echo "SQLite: FUNCIONA\n";
        @unlink($dataDir . '/test.db');
    } catch (Exception $e) {
        echo "SQLite ERRO: " . $e->getMessage() . "\n";
    }
} else {
    echo "Pasta data/ sem permissão de escrita!\n";
}

// 7. Auth files
echo "\n--- Auth ---\n";
$authFiles = ['api/auth-admin.php', 'api/auth-tecnico.php'];
foreach ($authFiles as $f) {
    $path = __DIR__ . '/' . $f;
    echo "$f: " . (file_exists($path) ? "EXISTE" : "NÃO EXISTE") . "\n";
}

// 8. Login files
echo "\n--- Login ---\n";
$loginFiles = ['admin/login.php', 'admin/index.php', 'tecnico/login.php', 'tecnico/index.php'];
foreach ($loginFiles as $f) {
    $path = __DIR__ . '/' . $f;
    echo "$f: " . (file_exists($path) ? "EXISTE (" . filesize($path) . " bytes)" : "NÃO EXISTE") . "\n";
}

// 9. Error log recente
echo "\n--- PHP Error Display ---\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "error_reporting: " . ini_get('error_reporting') . "\n";
echo "error_log: " . ini_get('error_log') . "\n";

echo "</pre>";
echo "<hr><p>Acesse <a href='admin/login.php'>admin/login.php</a> | <a href='tecnico/login.php'>tecnico/login.php</a></p>";
?>
