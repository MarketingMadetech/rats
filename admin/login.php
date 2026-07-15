<?php
require_once '../api/config.php';
require_once '../api/auth-admin.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if (fazerLoginAdmin($email, $senha)) {
        header('Location: ' . BASE_URL . 'admin/');
        exit;
    } else {
        $erro = 'Email ou senha incorretos!';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #034c8c;
            --primary-light: #0466c8;
            --secondary: #f58220;
            --success: #10b981;
            --gray-100: #f3f4f6;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0a2540 0%, #034c8c 50%, #0066cc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background Circles */
        body::before {
            content: '';
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            top: -100px;
            right: -100px;
            animation: float 20s ease-in-out infinite;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(245, 130, 32, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
            animation: float 25s ease-in-out infinite reverse;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(20px, -30px) scale(1.1); }
            50% { transform: translate(-10px, 30px) scale(0.95); }
            75% { transform: translate(30px, 10px) scale(1.05); }
        }

        .container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInScale 1s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .logo-item {
            animation: bounceIn 1.2s cubic-bezier(0.68, -0.55, 0.265, 1.55) both;
        }

        .logo-item:nth-child(2) { animation-delay: 0.2s; }

        @keyframes bounceIn {
            0% { opacity: 0; transform: scale(0.3) rotateY(180deg); }
            50% { opacity: 1; }
            100% { opacity: 1; transform: scale(1) rotateY(0); }
        }

        .logo-item img {
            height: 50px;
            filter: drop-shadow(0 4px 15px rgba(255, 255, 255, 0.2));
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .logo-item img:hover { transform: scale(1.15) rotateY(10deg); }

        .logo-section h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffff 0%, #d4e8f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .logo-section p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: cardSlide 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
        }

        @keyframes cardSlide {
            from { opacity: 0; transform: translateY(30px); filter: blur(10px); }
            to { opacity: 1; transform: translateY(0); filter: blur(0); }
        }

        .form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.6s ease-out both;
        }

        .form-group:nth-child(1) { animation-delay: 0.3s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--gray-300);
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: #f9fafb;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(3, 76, 140, 0.15), 0 8px 20px rgba(3, 76, 140, 0.2);
            transform: translateY(-2px);
        }

        .form-group input::placeholder { color: var(--gray-500); }

        .alert {
            padding: 12px 16px;
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            animation: shake 0.5s ease-in-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .btn-login {
            width: 100%;
            padding: 14px 20px;
            font-size: 16px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(3, 76, 140, 0.3);
            animation: fadeIn 0.6s ease-out 0.5s both;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 45px rgba(3, 76, 140, 0.4);
        }

        .btn-login:hover::before { left: 100%; }

        .btn-login:active { transform: translateY(-1px); }

        .info-box {
            background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 16px;
            margin-top: 20px;
            font-size: 13px;
            color: #1e40af;
            line-height: 1.6;
            animation: slideInRight 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.6s both;
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .info-box strong {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: var(--primary);
        }

        .info-box code {
            display: block;
            background: rgba(255, 255, 255, 0.5);
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            margin: 2px 0;
            font-size: 12px;
        }

        .footer-links {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            animation: fadeIn 0.6s ease-out 0.7s both;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
        }

        .footer-links a:hover { color: white; }

        @media (max-width: 600px) {
            .card { padding: 30px 20px; border-radius: 16px; }
            .logo-section h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <div class="logo-container">
                <div class="logo-item">
                    <img src="https://madetech.com.br/wp-content/uploads/2026/05/Logo-Madetech-Final.webp" alt="Madetech">
                </div>
                <div style="color: rgba(255, 255, 255, 0.3); font-size: 24px;">|</div>
                <div class="logo-item">
                    <img src="https://madetech.com.br/wp-content/uploads/2026/07/Logo-Madeparts-Final.png" alt="Madeparts">
                </div>
            </div>
            <h1>Sistema RAT</h1>
            <p>Painel Administrativo</p>
        </div>

        <div class="card">
            <?php if ($erro): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="admin@madetech.com.br" required autofocus>
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Entrar como Admin
                </button>

                <div class="info-box">
                    <strong>🔑 Credenciais Admin:</strong>
                    <code>admin@madetech.com.br</code>
                    <code>123456</code>
                </div>
            </form>
        </div>

        <div class="footer-links">
            <a href="../tecnico/login.php">← Painel Técnico</a>
        </div>
    </div>
</body>
</html>
