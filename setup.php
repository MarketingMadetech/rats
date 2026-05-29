<?php
/**
 * Setup do Sistema RAT v2.0
 * Acesse esta página: http://localhost/rats-mdt2026/setup.php
 * 
 * Este script:
 * 1. Cria o banco de dados automaticamente
 * 2. Popula com dados de teste
 * 3. Mostra credenciais de acesso
 */

ob_start();

require_once __DIR__ . '/api/config.php';

$sucesso = false;
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['acao'] === 'setup') {
    try {
        $db = getDB();
        
        // Criar admin padrão
        $stmt = $db->query("SELECT COUNT(*) as total FROM administradores");
        $result = $stmt->fetch();
        
        if ($result['total'] == 0) {
            $senha_hash = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO administradores (nome, email, senha) VALUES (?, ?, ?)");
            $stmt->execute(['Administrador', 'admin@madetech.com.br', $senha_hash]);
        }
        
        // Criar técnicos de teste
        $tecnicos_teste = [
            ['Alan Araújo', 'alan@madetech.com.br', 'alan123', '11999999991'],
            ['Leonardo Araújo', 'leonardo@madetech.com.br', 'leonardo123', '11999999992'],
            ['André Neri', 'andre@madetech.com.br', 'andre123', '11999999993'],
            ['Fábio Leite', 'fabio@madetech.com.br', 'fabio123', '11999999994'],
            ['Gabriel Guilherme', 'gabriel@madetech.com.br', 'gabriel123', '11999999995'],
        ];
        
        $stmt = $db->query("SELECT COUNT(*) as total FROM tecnicos");
        $result = $stmt->fetch();
        
        if ($result['total'] == 0) {
            foreach ($tecnicos_teste as $t) {
                $senha_hash = password_hash($t[2], PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO tecnicos (nome, email, senha, telefone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$t[0], $t[1], $senha_hash, $t[3]]);
            }
        }
        
        $sucesso = true;
        $mensagem = "✅ Setup concluído! Banco criado e dados de teste inseridos.";
        
    } catch (Exception $e) {
        $mensagem = "❌ Erro: " . $e->getMessage();
    }
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup | Sistema RAT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #10b981;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert.sucesso {
            background: #dcfce7;
            color: #166534;
            border-color: #10b981;
        }
        
        .alert.erro {
            background: #fee;
            color: #c33;
            border-color: #ef4444;
        }
        
        .credentials {
            background: #f0fdf4;
            border: 2px solid #10b981;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .cred-item {
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .cred-item:last-child {
            border-bottom: none;
        }
        
        .label {
            font-weight: 600;
            color: #10b981;
            font-size: 14px;
        }
        
        .value {
            color: #333;
            font-family: 'Courier New', monospace;
            margin-top: 5px;
            padding: 8px;
            background: white;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .form-group {
            margin: 20px 0;
            text-align: center;
        }
        
        button {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #059669;
        }
        
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .steps {
            margin: 30px 0;
        }
        
        .step {
            padding: 15px;
            margin: 10px 0;
            background: #f9f9f9;
            border-left: 4px solid #10b981;
            border-radius: 4px;
        }
        
        .step strong {
            color: #10b981;
            display: block;
            margin-bottom: 5px;
        }
        
        .step p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .links {
            text-align: center;
            margin-top: 30px;
        }
        
        .links a {
            display: inline-block;
            padding: 12px 24px;
            background: #034c8c;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 5px;
            transition: background 0.3s;
        }
        
        .links a:hover {
            background: #0a5fa6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Setup - Sistema RAT v2.0</h1>
        <p class="subtitle">Inicializar banco de dados e dados de teste</p>
        
        <?php if ($sucesso): ?>
            <div class="alert sucesso">
                <strong>✅ Sucesso!</strong> <?php echo $mensagem; ?>
            </div>
            
            <div class="credentials">
                <div class="cred-item">
                    <div class="label">👨‍💼 Admin</div>
                    <div class="value">Email: admin@madetech.com.br</div>
                    <div class="value">Senha: admin123</div>
                </div>
            </div>
            
            <h3 style="color: #10b981; margin: 20px 0 15px 0;">👨‍🔧 Técnicos Criados</h3>
            
            <?php foreach (['Alan Araújo', 'Leonardo Araújo', 'André Neri', 'Fábio Leite', 'Gabriel Guilherme'] as $i => $nome): ?>
                <div class="credentials" style="margin: 10px 0;">
                    <div class="cred-item" style="border-bottom: none;">
                        <div class="label"><?php echo htmlspecialchars($nome); ?></div>
                        <div class="value">Email: <?php echo strtolower(str_replace(' ', '', explode(' ', $nome)[0])); ?>@madetech.com.br</div>
                        <div class="value">Senha: <?php echo strtolower(str_replace(' ', '', $nome)); ?>123</div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="links">
                <a href="admin/login.php">🔓 Acessar Painel Admin</a>
                <a href="tecnico/login.php">🔓 Acessar Painel Técnico</a>
            </div>
            
        <?php elseif ($mensagem): ?>
            <div class="alert erro">
                <strong>❌ Erro!</strong> <?php echo $mensagem; ?>
            </div>
        <?php else: ?>
            <div class="steps">
                <div class="step">
                    <strong>Passo 1:</strong>
                    <p>Clique no botão abaixo para criar o banco de dados SQLite e inserir dados de teste automaticamente.</p>
                </div>
                
                <div class="step">
                    <strong>Passo 2:</strong>
                    <p>Após o setup, você receberá as credenciais do Admin e dos Técnicos para fazer login.</p>
                </div>
                
                <div class="step">
                    <strong>Passo 3:</strong>
                    <p>Use as credenciais do Admin para gerenciar técnicos e visualizar RATs. Técnicos criam seus próprios RATs.</p>
                </div>
            </div>
            
            <form method="POST" style="text-align: center;">
                <input type="hidden" name="acao" value="setup">
                <button type="submit">🚀 Iniciar Setup</button>
            </form>
        <?php endif; ?>
        
        <p style="text-align: center; margin-top: 30px; color: #999; font-size: 12px;">
            Sistema RAT v2.0 &nbsp;|&nbsp; Madetech 2026
        </p>
    </div>
</body>
</html>
