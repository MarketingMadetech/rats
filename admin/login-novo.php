<?php
require_once '../api/auth-admin.php';

$erro = '';
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if (fazerLoginAdmin($email, $senha)) {
        header('Location: index.php');
        exit;
    } else {
        $erro = 'Email ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin | Sistema RAT - Madetech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #034c8c 0%, #0a5fa6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(3, 76, 140, 0.3);
            padding: 48px 40px;
            animation: slideUp 0.6s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #034c8c;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #718096;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #2d3748;
        }
        
        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
            background: #f7fafc;
        }
        
        input:focus {
            outline: none;
            border-color: #034c8c;
            background: white;
            box-shadow: 0 0 0 3px rgba(3, 76, 140, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #034c8c 0%, #0a5fa6 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.25);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(3, 76, 140, 0.35);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .erro {
            background: #fef2f2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #dc2626;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .help-text {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }
        
        .help-text p {
            color: #718096;
            font-size: 13px;
            margin-bottom: 8px;
        }
        
        .help-text a {
            color: #034c8c;
            text-decoration: none;
            font-weight: 600;
        }
        
        .help-text a:hover {
            text-decoration: underline;
        }
        
        .credentials-box {
            background: #f0f7ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .credentials-box strong {
            color: #034c8c;
            display: block;
            margin-bottom: 8px;
        }
        
        .credentials-box code {
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #dc2626;
        }
        
        .switch-panel {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .switch-panel p {
            color: #718096;
            font-size: 13px;
            margin-bottom: 12px;
        }
        
        .btn-switch {
            display: inline-block;
            padding: 10px 20px;
            background: white;
            color: #034c8c;
            border: 2px solid #034c8c;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-switch:hover {
            background: #f0f7ff;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">⚙️</div>
                <h1>Sistema RAT</h1>
                <p>Painel Administrativo</p>
            </div>
            
            <?php if ($erro): ?>
                <div class="erro">
                    <strong>Erro:</strong> <?php echo $erro; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="admin@madetech.com.br" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
                </div>
                
                <button type="submit" class="btn-login">Entrar no Painel Admin</button>
            </form>
            
            <div class="credentials-box">
                <strong>🔐 Credenciais de Demo:</strong>
                <div>
                    Email: <code>admin@madetech.com.br</code>
                </div>
                <div style="margin-top: 6px;">
                    Senha: <code>admin123</code>
                </div>
            </div>
            
            <div class="switch-panel">
                <p>É técnico?</p>
                <a href="../tecnico/login.php" class="btn-switch">
                    Ir para Painel Técnico
                </a>
            </div>
        </div>
    </div>
</body>
</html>
