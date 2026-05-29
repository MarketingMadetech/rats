<?php
/**
 * Teste de Conexão SMTP
 */

echo "<h2>Testando Conexão SMTP</h2>";
echo "<hr>";

// Configurações de teste
$smtpHost = 'mail.madetech.com.br';
$smtpPort = 587;
$smtpUser = 'marketing@madetech.com.br';
$smtpPass = 'ricardomadetech25';

echo "<p><strong>Configurações:</strong></p>";
echo "<ul>";
echo "<li>Host: $smtpHost</li>";
echo "<li>Porta: $smtpPort</li>";
echo "<li>Usuário: $smtpUser</li>";
echo "<li>Senha: " . str_repeat("*", strlen($smtpPass)) . "</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Testando conexão...</strong></p>";

// Teste 1: Verificar se pode conectar
echo "<h3>1. Conectando ao servidor SMTP...</h3>";
try {
    $connection = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 5);
    
    if ($connection) {
        echo "<span style='color: green;'>✓ Conexão estabelecida!</span><br>";
        fclose($connection);
    } else {
        echo "<span style='color: red;'>✗ Falha na conexão!</span><br>";
        echo "Erro: $errstr ($errno)<br>";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Exceção: " . $e->getMessage() . "</span>";
}

// Teste 2: Tentar usar PHPMailer (se disponível)
echo "<h3>2. Testando com PHPMailer...</h3>";

// Instalar PHPMailer se não existir
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<p style='color: orange;'>⚠ PHPMailer não instalado. Instalando...</p>";
    
    // Tentar instalar via Composer
    if (shell_exec('which composer')) {
        echo "<pre>";
        passthru('cd ' . __DIR__ . ' && composer require phpmailer/phpmailer 2>&1', $return);
        echo "</pre>";
    } else {
        // Instalar manualmente (download da biblioteca)
        echo "<p>PHPMailer não pode ser instalado automaticamente.</p>";
        echo "<p>Execute no terminal:</p>";
        echo "<code>composer require phpmailer/phpmailer</code>";
    }
} else {
    // Testar com PHPMailer
    require_once __DIR__ . '/vendor/autoload.php';
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configurações SMTP
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = 'tls';
        
        echo "<span style='color: green;'>✓ Configuração PHPMailer OK!</span><br>";
        
        // Tentar enviar email de teste
        $mail->setFrom($smtpUser, 'Sistema RAT - Teste');
        $mail->addAddress('marketing@madetech.com.br');
        $mail->Subject = 'Teste SMTP - Sistema RAT';
        $mail->Body = 'E-mail de teste enviado com sucesso em ' . date('d/m/Y H:i:s');
        
        if ($mail->send()) {
            echo "<span style='color: green;'>✓ E-mail de teste enviado com sucesso!</span><br>";
        } else {
            echo "<span style='color: red;'>✗ Erro ao enviar: " . $mail->ErrorInfo . "</span><br>";
        }
    } catch (Exception $e) {
        echo "<span style='color: red;'>✗ Erro PHPMailer: " . $e->getMessage() . "</span><br>";
    }
}

// Teste 3: Verificar extensões PHP
echo "<h3>3. Verificando extensões PHP...</h3>";
$extensoes = ['curl', 'openssl', 'sockets'];
foreach ($extensoes as $ext) {
    if (extension_loaded($ext)) {
        echo "<span style='color: green;'>✓ $ext</span><br>";
    } else {
        echo "<span style='color: red;'>✗ $ext não carregado</span><br>";
    }
}

echo "<hr>";
echo "<p><a href='javascript:location.reload()'>Atualizar</a> | <a href='admin/login.php'>Voltar</a></p>";
?>
