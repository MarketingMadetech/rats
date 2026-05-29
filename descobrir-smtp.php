<?php
/**
 * Auto-discovery de Configuração SMTP
 */

echo "<h1>Descobrindo Configuração SMTP</h1>";
echo "<hr>";

$smtpHost = 'mail.madetech.com.br';
$smtpUser = 'marketing@madetech.com.br';
$smtpPass = 'ricardomadetech25';

// Testar as portas mais comuns
$portasParaTentar = [
    587 => 'TLS (SMTP recomendado)',
    465 => 'SSL (SMTPS)',
    25  => 'SMTP padrão (raramente funciona)',
    2525 => 'Alternativa TLS'
];

echo "<p><strong>Host:</strong> $smtpHost</p>";
echo "<p><strong>Testando portas...</strong></p>";
echo "<table border='1' cellpadding='10' style='width:100%;'>";
echo "<tr><th>Porta</th><th>Tipo</th><th>Status</th><th>Tempo</th></tr>";

$portaFuncional = null;
$menorTempo = PHP_INT_MAX;

foreach ($portasParaTentar as $porta => $tipo) {
    $inicio = microtime(true);
    
    // Tentar conexão
    $conexao = @fsockopen($smtpHost, $porta, $errno, $errstr, 3);
    
    $tempo = microtime(true) - $inicio;
    $tempo = round($tempo * 1000, 0) . 'ms';
    
    if ($conexao) {
        fclose($conexao);
        echo "<tr style='background-color: #d4edda;'>";
        echo "<td><strong>$porta</strong></td>";
        echo "<td>$tipo</td>";
        echo "<td style='color: green;'><strong>✓ FUNCIONA</strong></td>";
        echo "<td>$tempo</td>";
        echo "</tr>";
        
        if (microtime(true) < $menorTempo) {
            $portaFuncional = $porta;
            $menorTempo = microtime(true);
        }
    } else {
        echo "<tr style='background-color: #f8d7da;'>";
        echo "<td>$porta</td>";
        echo "<td>$tipo</td>";
        echo "<td style='color: red;'>✗ Sem conexão</td>";
        echo "<td>$tempo</td>";
        echo "</tr>";
    }
}

echo "</table>";

echo "<hr>";

if ($portaFuncional) {
    echo "<h2 style='color: green;'>✓ PORTA DESCOBERTA: <strong>$portaFuncional</strong></h2>";
    
    // Agora vamos gravar a configuração
    echo "<p>Gravando configuração no arquivo...</p>";
    
    $configFile = dirname(__FILE__) . '/api/config.php';
    $configContent = file_get_contents($configFile);
    
    // Determinar tipo de segurança
    $securityType = ($portaFuncional == 465) ? 'ssl' : 'tls';
    
    // Substituir as configurações
    $configContent = preg_replace(
        "/define\('SMTP_HOST', '[^']*'\)/",
        "define('SMTP_HOST', '$smtpHost')",
        $configContent
    );
    
    $configContent = preg_replace(
        "/define\('SMTP_PORT', \d+\)/",
        "define('SMTP_PORT', $portaFuncional)",
        $configContent
    );
    
    $configContent = preg_replace(
        "/define\('SMTP_USER', '[^']*'\)/",
        "define('SMTP_USER', '$smtpUser')",
        $configContent
    );
    
    $configContent = preg_replace(
        "/define\('SMTP_PASS', '[^']*'\)/",
        "define('SMTP_PASS', '$smtpPass')",
        $configContent
    );
    
    // Se não tiver a configuração de segurança, adicionar
    if (strpos($configContent, 'SMTP_SECURE') === false) {
        $configContent = preg_replace(
            "/(define\('SMTP_PASS',.*?\);)/",
            "$1\ndefine('SMTP_SECURE', '$securityType');",
            $configContent
        );
    } else {
        $configContent = preg_replace(
            "/define\('SMTP_SECURE', '[^']*'\)/",
            "define('SMTP_SECURE', '$securityType')",
            $configContent
        );
    }
    
    // Gravar arquivo
    if (file_put_contents($configFile, $configContent)) {
        echo "<div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
        echo "<h3 style='color: green;'>✓ Configuração Gravada!</h3>";
        echo "<p><strong>Dados salvos:</strong></p>";
        echo "<ul>";
        echo "<li>Host: $smtpHost</li>";
        echo "<li>Porta: $portaFuncional</li>";
        echo "<li>Usuário: $smtpUser</li>";
        echo "<li>Segurança: " . strtoupper($securityType) . "</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<p style='margin-top: 20px;'>";
        echo "<a href='setup.php' style='padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Voltar ao Setup</a> ";
        echo "<a href='admin/login.php' style='padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Ir para Login Admin</a>";
        echo "</p>";
    } else {
        echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px; margin-top: 10px;'>";
        echo "<h3 style='color: red;'>✗ Erro ao gravar arquivo</h3>";
        echo "<p>Arquivo: $configFile</p>";
        echo "<p>Verifique as permissões da pasta.</p>";
        echo "</div>";
    }
    
} else {
    echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h2 style='color: red;'>✗ Nenhuma porta funcionou</h2>";
    echo "<p>Possíveis causas:</p>";
    echo "<ul>";
    echo "<li>Host SMTP incorreto (não é 'mail.madetech.com.br'?)</li>";
    echo "<li>Firewall bloqueando a conexão</li>";
    echo "<li>Servidor SMTP indisponível</li>";
    echo "</ul>";
    echo "<p><a href='setup.php'>Voltar</a></p>";
    echo "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    background-color: #f5f5f5;
}

h1, h2, h3 {
    color: #333;
}

table {
    background-color: white;
    border-collapse: collapse;
}

a {
    display: inline-block;
}
</style>
