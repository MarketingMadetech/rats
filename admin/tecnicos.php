<?php
require_once '../api/config.php';
require_once '../api/auth-admin.php';

verificarAdmin();

$db = getDB();
$mensagem = '';
$erro = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'criar') {
        $nome = $_POST['nome'] ?? '';
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $telefone = $_POST['telefone'] ?? '';
        
        if ($nome && $email && $senha) {
            $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
            try {
                $stmt = $db->prepare("INSERT INTO tecnicos (nome, email, senha, telefone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $senha_hash, $telefone]);
                $mensagem = "✅ Técnico criado com sucesso!";
            } catch (Exception $e) {
                $erro = "Email já cadastrado ou erro ao criar técnico.";
            }
        } else {
            $erro = "Preencha todos os campos obrigatórios.";
        }
    }
    
    elseif ($acao === 'editar') {
        $id = $_POST['id'] ?? '';
        $nome = $_POST['nome'] ?? '';
        $email = $_POST['email'] ?? '';
        $telefone = $_POST['telefone'] ?? '';
        $nova_senha = $_POST['nova_senha'] ?? '';
        
        if ($id && $nome && $email) {
            try {
                if ($nova_senha) {
                    // Atualizar com nova senha
                    $senha_hash = password_hash($nova_senha, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("UPDATE tecnicos SET nome = ?, email = ?, telefone = ?, senha = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $telefone, $senha_hash, $id]);
                    $mensagem = "✅ Técnico atualizado e senha alterada com sucesso!";
                } else {
                    // Atualizar sem alterar senha
                    $stmt = $db->prepare("UPDATE tecnicos SET nome = ?, email = ?, telefone = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $telefone, $id]);
                    $mensagem = "✅ Técnico atualizado com sucesso!";
                }
            } catch (Exception $e) {
                $erro = "Erro ao atualizar técnico.";
            }
        }
    }
    
    elseif ($acao === 'deletar') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            try {
                $stmt = $db->prepare("DELETE FROM tecnicos WHERE id = ?");
                $stmt->execute([$id]);
                $mensagem = "✅ Técnico removido com sucesso!";
            } catch (Exception $e) {
                $erro = "Erro ao remover técnico.";
            }
        }
    }
}

// Listar técnicos
$stmt = $db->query("SELECT t.*, COUNT(r.id) as total_rats FROM tecnicos t LEFT JOIN rats r ON t.id = r.id_tecnico GROUP BY t.id ORDER BY t.nome");
$tecnicos = $stmt->fetchAll();

$tecnico_edicao = null;
if (isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM tecnicos WHERE id = ?");
    $stmt->execute([$_GET['editar']]);
    $tecnico_edicao = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Técnicos | Sistema RAT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --color-primary: #034c8c;
            --color-primary-light: #0466c8;
            --color-bg: #f5f7fa;
            --color-light: #e5e7eb;
            --color-dark: #111827;
            --color-gray: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--color-bg) 0%, #e8ecf1 100%);
            color: var(--color-dark);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        header {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
            color: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 16px rgba(3, 76, 140, 0.2);
            animation: slideDown 0.6s ease-out;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        header h1 {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        header > div {
            display: flex;
            gap: 10px;
        }
        
        header a {
            color: white;
            text-decoration: none;
            padding: 10px 16px;
            background: rgba(255,255,255,0.15);
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        header a:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .msg {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .msg.sucesso {
            background: #dcfce7;
            color: #166534;
            border-color: var(--color-primary);
        }
        
        .msg.erro {
            background: #fee;
            color: #c33;
            border-color: #ef4444;
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(3, 76, 140, 0.08);
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }
        
        .form-card h2 {
            margin-bottom: 20px;
            color: var(--color-primary);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }

        small {
            color: var(--color-gray);
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: var(--color-dark);
        }
        
        input, select {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid var(--color-light);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(3, 76, 140, 0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-grid.full {
            grid-template-columns: 1fr;
        }
        
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
            animation: fadeInUp 0.6s ease-out 1.1s both;
        }
        
        button {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(3, 76, 140, 0.15);
        }
        
        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(3, 76, 140, 0.25);
        }
        
        .btn-secondary {
            background: var(--color-gray);
            box-shadow: 0 2px 8px rgba(107, 114, 128, 0.15);
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            box-shadow: 0 6px 16px rgba(107, 114, 128, 0.25);
        }
        
        .tecnicos-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(3, 76, 140, 0.08);
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--color-primary);
            font-size: 13px;
            text-transform: uppercase;
            border-bottom: 2px solid var(--color-light);
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid var(--color-light);
        }
        
        tbody tr:hover {
            background: #f9fafb;
            transition: background 0.2s;
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 8px 14px;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            box-shadow: 0 2px 6px rgba(3, 76, 140, 0.1);
        }
        
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.2);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.1);
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }

        @media print {
            header, .form-card, .form-buttons, .msg {
                display: none;
            }
            body {
                background: white;
            }
            .tecnicos-table {
                box-shadow: none;
                border: 1px solid var(--color-light);
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>👨‍💼 Gerenciar Técnicos</h1>
        <div>
            <a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
            <a href="../api/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </div>
    </header>
    
    <div class="container">
        <?php if ($mensagem): ?>
            <div class="msg sucesso"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="msg erro"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <!-- Formulário de Criação/Edição -->
        <div class="form-card">
            <h2><?php echo $tecnico_edicao ? '✏️ Editar Técnico' : '➕ Novo Técnico'; ?></h2>
            
            <form method="POST">
                <input type="hidden" name="acao" value="<?php echo $tecnico_edicao ? 'editar' : 'criar'; ?>">
                <?php if ($tecnico_edicao): ?>
                    <input type="hidden" name="id" value="<?php echo $tecnico_edicao['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome">Nome Completo *</label>
                        <input type="text" id="nome" name="nome" value="<?php echo $tecnico_edicao['nome'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="tel" id="telefone" name="telefone" value="<?php echo $tecnico_edicao['telefone'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo $tecnico_edicao['email'] ?? ''; ?>" required>
                </div>
                
                <?php if (!$tecnico_edicao): ?>
                    <div class="form-group">
                        <label for="senha">Senha *</label>
                        <input type="password" id="senha" name="senha" required>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="nova_senha">Nova Senha (deixe em branco para não alterar)</label>
                        <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite a nova senha ou deixe em branco">
                        <small style="color: #666; margin-top: 5px; display: block;">Se preenchido, a senha será alterada para este valor.</small>
                    </div>
                <?php endif; ?>
                
                <div class="form-buttons">
                    <button type="submit">
                        <?php echo $tecnico_edicao ? '💾 Atualizar' : '✅ Criar Técnico'; ?>
                    </button>
                    <?php if ($tecnico_edicao): ?>
                        <a href="tecnicos.php" class="btn-sm btn-secondary" style="padding: 12px 24px;">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Lista de Técnicos -->
        <div class="tecnicos-table">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>RATs Criados</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tecnicos as $t): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($t['nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($t['email']); ?></td>
                            <td><?php echo htmlspecialchars($t['telefone'] ?? '-'); ?></td>
                            <td><?php echo $t['total_rats']; ?></td>
                            <td class="action-btns">
                                <a href="?editar=<?php echo $t['id']; ?>" class="btn-sm">✏️ Editar</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja remover este técnico?');">
                                    <input type="hidden" name="acao" value="deletar">
                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" class="btn-sm btn-delete">🗑️ Deletar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
