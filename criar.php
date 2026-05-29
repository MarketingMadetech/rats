<?php
/**
 * Criar novo RAT - Madetech
 */
require_once 'api/config.php';

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    $token = gerarToken();
    
    try {
        $db->beginTransaction();
        
        // Inserir com número temporário único
        $placeholder = 'TEMP-' . $token;
        $stmt = $db->prepare("
            INSERT INTO rats (numero, token, tecnico_nome, observacoes, pedagio_qtd, hospedagem_dias, alimentacao_qtd, outros_qtd)
            VALUES (:numero, :token, :tecnico, :observacoes, 0, 0, 0, 0)
        ");
        
        $stmt->execute([
            ':numero' => $placeholder,
            ':token' => $token,
            ':tecnico' => $_POST['tecnico_nome'] ?? '',
            ':observacoes' => $_POST['observacoes'] ?? ''
        ]);
        
        $rat_id = $db->lastInsertId();
        
        // Gerar número baseado no id real atribuído pelo banco
        $numero = gerarNumeroRAT($rat_id);
        $db->prepare("UPDATE rats SET numero = ? WHERE id = ?")->execute([$numero, $rat_id]);
        
        $db->commit();
        
        // Redirecionar para página do RAT criado
        header("Location: visualizar.php?id={$rat_id}&novo=1");
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $mensagem = "Erro ao criar RAT: " . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// Lista de técnicos
$tecnicos = [
    'Leonardo Araújo',
    'Allan Araújo',
    'André Neri',
    'Fábio Leite',
    'Gabriel Guilherme'
];

// Lista de equipamentos
$equipamentos = [
    'Coladeira de Bordas',
    'Seccionadora',
    'Centro de Usinagem CNC',
    'Furadeira',
    'Prensa Térmica',
    'Guilhotina',
    'Outro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo RAT - Madetech</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logos-container">
                    <img src="https://www.madetech.com.br/loja/wp-content/uploads/2025/05/Logo-Madetech-Final.png" alt="Madetech" class="logo">
                    <img src="https://sitenovo.madetech.com.br/assets/logomadeparts.avif" alt="Madeparts" class="logo">
                </div>
                <h2>Sistema RAT</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="criar.php" class="nav-item active">
                    <i class="fas fa-plus-circle"></i>
                    Novo RAT
                </a>
            </nav>
            <div class="sidebar-footer">
                <p>Madetech & Madeparts</p>
                <small>Assistência Técnica</small>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <h1><i class="fas fa-plus-circle"></i> Criar Novo RAT</h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </header>

            <?php if ($mensagem): ?>
                <div class="alert <?= $tipo_mensagem ?>">
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" class="rat-form">
                    <div class="form-section">
                        <h3><i class="fas fa-user-tie"></i> Informações do Técnico</h3>
                        
                        <div class="form-group">
                            <label for="tecnico_nome">Técnico Responsável *</label>
                            <select name="tecnico_nome" id="tecnico_nome" required>
                                <option value="">Selecione o técnico</option>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <option value="<?= $tecnico ?>"><?= $tecnico ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-sticky-note"></i> Observações Internas</h3>
                        
                        <div class="form-group">
                            <label for="observacoes">Observações (não aparece no formulário do técnico)</label>
                            <textarea name="observacoes" id="observacoes" rows="3" placeholder="Anotações internas..."></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-check"></i> Criar RAT
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
