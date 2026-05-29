<?php
require_once '../api/config.php';
require_once '../api/auth-tecnico.php';

verificarTecnico();

$db = getDB();
$tecnico = obterTecnicoAtual($db);

// Estatísticas do técnico
$stmt = $db->prepare("SELECT COUNT(*) as total FROM rats WHERE id_tecnico = ?");
$stmt->execute([$_SESSION['tecnico_id']]);
$total_rats = $stmt->fetch()['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM rats WHERE id_tecnico = ? AND status = 'rascunho'");
$stmt->execute([$_SESSION['tecnico_id']]);
$rascunho = $stmt->fetch()['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM rats WHERE id_tecnico = ? AND status = 'enviado'");
$stmt->execute([$_SESSION['tecnico_id']]);
$enviado = $stmt->fetch()['total'] ?? 0;

// RATs do técnico
$stmt = $db->prepare("SELECT * FROM rats WHERE id_tecnico = ? ORDER BY data_criacao DESC LIMIT 10");
$stmt->execute([$_SESSION['tecnico_id']]);
$rats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Técnico | Sistema RAT - Madetech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            color: #2d3748;
            min-height: 100vh;
        }
        
        /* HEADER */
        header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -1px;
        }
        
        .logo-icon {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 20px;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 15px;
            border-right: 1px solid rgba(255,255,255,0.2);
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .user-name {
            font-size: 14px;
            font-weight: 500;
        }
        
        .header-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .header-btn:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
        }
        
        /* CONTAINER */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
        }
        
        /* GREETING */
        .greeting {
            margin-bottom: 40px;
        }
        
        .greeting h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .greeting p {
            font-size: 15px;
            color: #718096;
        }
        
        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid #10b981;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-content h3 {
            font-size: 13px;
            font-weight: 500;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #10b981;
        }
        
        .stat-icon {
            font-size: 32px;
            color: #d1fae5;
        }
        
        /* BUTTONS */
        .actions {
            display: flex;
            gap: 12px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
        }
        
        .btn-secondary {
            background: white;
            color: #10b981;
            border: 2px solid #10b981;
            box-shadow: none;
        }
        
        .btn-secondary:hover {
            background: #f0fdf4;
        }
        
        /* TABLE */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
        }
        
        .table-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f0fdf4;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background: #f0fdf4;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-rascunho {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-enviado {
            background: #d1fae5;
            color: #065f46;
        }
        
        .rat-number {
            font-weight: 600;
            color: #10b981;
        }
        
        .btn-action {
            padding: 6px 12px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
            background: #059669;
        }
        
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #718096;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        @media (max-width: 768px) {
            .header-content {
                padding: 16px 20px;
                flex-direction: column;
                gap: 16px;
            }
            
            .container {
                padding: 20px;
            }
            
            .greeting h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <div class="header-left">
                <div class="logo">
                    <span class="logo-icon">⚙️</span>
                    SISTEMA RAT
                </div>
                <span style="font-size: 13px; color: rgba(255,255,255,0.7);">Madetech</span>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">👷</div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($tecnico['nome']); ?></div>
                        <div style="font-size: 12px; color: rgba(255,255,255,0.7);">Técnico</div>
                    </div>
                </div>
                <a href="../api/logout.php" class="header-btn">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
    </header>
    
    <!-- MAIN -->
    <div class="container">
        <!-- GREETING -->
        <div class="greeting">
            <h1>Olá, <?php echo htmlspecialchars(explode(' ', $tecnico['nome'])[0]); ?>! 👋</h1>
            <p>Bem-vindo ao seu painel de relatórios técnicos.</p>
        </div>
        
        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Meus RATs</h3>
                    <div class="stat-number"><?php echo $total_rats; ?></div>
                </div>
                <div class="stat-icon">📋</div>
            </div>
            
            <div class="stat-card" style="border-left-color: #f59e0b;">
                <div class="stat-content">
                    <h3>Em Rascunho</h3>
                    <div class="stat-number" style="color: #f59e0b;"><?php echo $rascunho; ?></div>
                </div>
                <div class="stat-icon">📝</div>
            </div>
            
            <div class="stat-card" style="border-left-color: #10b981;">
                <div class="stat-content">
                    <h3>Enviados</h3>
                    <div class="stat-number" style="color: #10b981;"><?php echo $enviado; ?></div>
                </div>
                <div class="stat-icon">✅</div>
            </div>
        </div>
        
        <!-- ACTIONS -->
        <div class="actions">
            <a href="criar-rat.php" class="btn">
                <i class="fas fa-plus-circle"></i> Criar Novo RAT
            </a>
        </div>
        
        <!-- RATS LIST -->
        <h2 class="section-title">Meus Relatórios Técnicos</h2>
        
        <div class="table-wrapper">
            <?php if (count($rats) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Número RAT</th>
                            <th>Empresa</th>
                            <th>Status</th>
                            <th>Data de Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rats as $rat): ?>
                            <tr>
                                <td><span class="rat-number"><?php echo htmlspecialchars($rat['numero']); ?></span></td>
                                <td><?php echo htmlspecialchars($rat['cliente_empresa'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $rat['status']; ?>">
                                        <?php echo ucfirst($rat['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($rat['data_criacao'])); ?></td>
                                <td>
                                    <a href="editar-rat.php?id=<?php echo $rat['id']; ?>" class="btn-action">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3>Nenhum RAT Criado</h3>
                    <p>Clique no botão "Criar Novo RAT" para começar!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
