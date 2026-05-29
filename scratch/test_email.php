<?php
require_once 'api/config.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    echo "Iniciando teste de email...\n";
    echo "Configurações: \n";
    echo "Host: " . SMTP_HOST . "\n";
    echo "User: " . SMTP_USER . "\n";
    echo "Secure: " . SMTP_SECURE . "\n";
    echo "Port: " . SMTP_PORT . "\n";

    $mail->SMTPDebug = 2; // Output verbose debug information
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_USER, 'Teste de Sistema');
    $mail->addAddress(SMTP_USER);

    $mail->Subject = 'Teste de Email - Sistema RAT';
    $mail->Body    = 'Este é um teste para verificar se o envio de e-mail está funcionando.';

    echo "Tentando enviar...\n";
    if($mail->send()) {
        echo "\n✓ Email enviado com sucesso!\n";
    }
} catch (Exception $e) {
    echo "\n✗ Erro ao enviar email: {$mail->ErrorInfo}\n";
}
