<?php
require_once '../api/config.php';
require_once '../api/auth-admin.php';

verificarAdmin();

$db = getDB();
$admin = $_SESSION['admin_data'] ?? [];

// Estatísticas
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as total FROM rats");
$stats['total_rats'] = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM rats WHERE status = 'rascunho'");
$stats['rascunho'] = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM rats WHERE status = 'enviado'");
$stats['enviado'] = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM tecnicos");
$stats['total_tecnicos'] = $stmt->fetch()['total'] ?? 0;

// RATs recentes
$stmt = $db->query("
    SELECT r.id, r.numero, t.nome as tecnico, r.status, r.data_criacao, r.cliente_empresa
    FROM rats r
    LEFT JOIN tecnicos t ON r.id_tecnico = t.id
    ORDER BY r.data_criacao DESC
    LIMIT 10
");
$rats_recentes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | Sistema RAT - Madetech</title>
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
            background: linear-gradient(135deg, #034c8c 0%, #0a5fa6 100%);
            color: white;
            padding: 0;
            box-shadow: 0 8px 24px rgba(3, 76, 140, 0.15);
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
        
        /* MAIN CONTAINER */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
        }
        
        /* GREETING */
        .greeting {
            color: #2d3748;
            margin-bottom: 40px;
        }
        
        .greeting h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #034c8c 0%, #0a5fa6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .greeting p {
            font-size: 15px;
            color: #718096;
        }
        
        /* STATS GRID */
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
            border-left: 4px solid #034c8c;
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
            color: #034c8c;
        }
        
        .stat-icon {
            font-size: 32px;
            color: #e0e7ff;
        }
        
        /* ACTIONS */
        .actions {
            display: flex;
            gap: 12px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #034c8c 0%, #0a5fa6 100%);
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
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.25);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(3, 76, 140, 0.35);
        }
        
        .btn-secondary {
            background: white;
            color: #034c8c;
            border: 2px solid #034c8c;
            box-shadow: none;
        }
        
        .btn-secondary:hover {
            background: #f8f9fa;
        }
        
        /* RECENT RATS */
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
            background: #f8f9fa;
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
            background: #f8f9fa;
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
            color: #034c8c;
        }
        
        /* RESPONSIVE */
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 12px 8px;
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
                    <div class="user-avatar">👨‍💼</div>
                    <div>
                        <div class="user-name">Administrador</div>
                        <div style="font-size: 12px; color: rgba(255,255,255,0.7);">Acesso Total</div>
                    </div>
                </div>
                <a href="tecnicos.php" class="header-btn">
                    <i class="fas fa-users"></i> Técnicos
                </a>
                <a href="../api/logout.php" class="header-btn">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
    </header>
    
    <!-- MAIN CONTENT -->
    <div class="container">
        <!-- GREETING -->
        <div class="greeting">
            <h1>Olá, Administrador! 👋</h1>
            <p>Bem-vindo ao Sistema RAT da Madetech. Acompanhe os relatórios de assistência técnica.</p>
        </div>
        
        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <h3>Total de RATs</h3>
                    <div class="stat-number"><?php echo $stats['total_rats']; ?></div>
                </div>
                <div class="stat-icon">📋</div>
            </div>
            
            <div class="stat-card" style="border-left-color: #f59e0b;">
                <div class="stat-content">
                    <h3>Em Rascunho</h3>
                    <div class="stat-number" style="color: #f59e0b;"><?php echo $stats['rascunho']; ?></div>
                </div>
                <div class="stat-icon">📝</div>
            </div>
            
            <div class="stat-card" style="border-left-color: #10b981;">
                <div class="stat-content">
                    <h3>Enviados</h3>
                    <div class="stat-number" style="color: #10b981;"><?php echo $stats['enviado']; ?></div>
                </div>
                <div class="stat-icon">✅</div>
            </div>
            
            <div class="stat-card" style="border-left-color: #8b5cf6;">
                <div class="stat-content">
                    <h3>Técnicos Ativos</h3>
                    <div class="stat-number" style="color: #8b5cf6;"><?php echo $stats['total_tecnicos']; ?></div>
                </div>
                <div class="stat-icon">👥</div>
            </div>
        </div>
        
        <!-- ACTIONS -->
        <div class="actions">
            <a href="tecnicos.php" class="btn">
                <i class="fas fa-plus-circle"></i> Gerenciar Técnicos
            </a>
            <a href="../tecnico/index.php" class="btn btn-secondary">
                <i class="fas fa-eye"></i> Ver Painel Técnico
            </a>
        </div>
        
        <!-- RECENT RATS -->
        <h2 class="section-title">RATs Recentes</h2>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Número RAT</th>
                        <th>Técnico</th>
                        <th>Empresa</th>
                        <th>Status</th>
                        <th>Data de Criação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rats_recentes as $rat): ?>
                        <tr>
                            <td><span class="rat-number"><?php echo htmlspecialchars($rat['numero']); ?></span></td>
                            <td><?php echo htmlspecialchars($rat['tecnico'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($rat['cliente_empresa'] ?? '-'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $rat['status']; ?>">
                                    <?php echo ucfirst($rat['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($rat['data_criacao'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
