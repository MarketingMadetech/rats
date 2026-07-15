<?php
/**
 * Dashboard - Sistema RAT Madetech
 */
require_once 'api/config.php';

$db = getDB();

// Buscar todos os RATs
$stmt = $db->query("SELECT * FROM rats ORDER BY id DESC");
$rats = $stmt->fetchAll();

// Contar por status
$stats = [
    'total' => count($rats),
    'pendente' => 0,
    'enviado' => 0,
    'preenchido' => 0
];

foreach ($rats as $rat) {
    if (isset($stats[$rat['status']])) {
        $stats[$rat['status']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard RAT - Madetech</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar Premium -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logos-container">
                    <img src="https://madetech.com.br/wp-content/uploads/2026/05/Logo-Madetech-Final.webp" alt="Madetech" class="logo">
                    <img src="https://madetech.com.br/wp-content/uploads/2026/07/Logo-Madeparts-Final.png" alt="Madeparts" class="logo">
                </div>
                <h2>Sistema RAT</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item active">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
                <a href="criar.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>Novo RAT</span>
                </a>
                <div class="nav-divider"></div>
                <a href="#" class="nav-item" onclick="fazerBackup(); return false;">
                    <i class="fas fa-cloud-download-alt"></i>
                    <span>Backup</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="footer-brand">
                    <i class="fas fa-tools"></i>
                    <div>
                        <p>Madetech & Madeparts</p>
                        <small>Assistência Técnica Premium</small>
                    </div>
                </div>
                <div class="footer-version">v2.0.0 • 2026</div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-title">
                    <h1><i class="fas fa-clipboard-list"></i> Relatórios de Assistência Técnica</h1>
                    <p class="header-subtitle">Gerencie todos os seus relatórios de serviço em um só lugar</p>
                </div>
                <div class="header-actions">
                    <button onclick="fazerBackup()" class="btn btn-secondary" title="Fazer backup">
                        <i class="fas fa-download"></i> Backup
                    </button>
                    <button onclick="abrirModalReset()" class="btn btn-danger" title="Resetar banco">
                        <i class="fas fa-trash-alt"></i> Reset
                    </button>
                    <a href="criar.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Novo RAT
                    </a>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-info">
                        <span class="stat-number"><?= $stats['total'] ?></span>
                        <span class="stat-label">Total de RATs</span>
                    </div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <span class="stat-number"><?= $stats['pendente'] ?></span>
                        <span class="stat-label">Pendentes</span>
                    </div>
                </div>
                <div class="stat-card sent">
                    <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
                    <div class="stat-info">
                        <span class="stat-number"><?= $stats['enviado'] ?></span>
                        <span class="stat-label">Enviados</span>
                    </div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <span class="stat-number"><?= $stats['preenchido'] ?></span>
                        <span class="stat-label">Preenchidos</span>
                    </div>
                </div>
            </div>

            <!-- RATs Table -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Todos os Relatórios</h2>
                    <div class="table-filters">
                        <input type="text" id="searchInput" placeholder="Buscar por número, cliente ou técnico..." class="search-input">
                    </div>
                </div>

                <?php if (empty($rats)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>Nenhum RAT cadastrado</h3>
                        <p>Clique em "Novo RAT" para criar o primeiro relatório.</p>
                        <a href="criar.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Criar Primeiro RAT
                        </a>
                    </div>
                <?php else: ?>
                    <table class="data-table" id="ratsTable">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Técnico</th>
                                <th>Equipamento</th>
                                <th>Status</th>
                                <th>Data Criação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rats as $rat): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?></strong></td>
                                    <td><?= htmlspecialchars($rat['cliente_empresa'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($rat['tecnico_nome'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($rat['equipamento'] ?? '-') ?></td>
                                    <td>
                                        <span class="status-badge <?= $rat['status'] ?>">
                                            <?= ucfirst($rat['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($rat['data_criacao'])) ?></td>
                                    <td class="actions">
                                        <a href="visualizar.php?id=<?= $rat['id'] ?>" class="btn-icon" title="Ver detalhes">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($rat['status'] === 'pendente'): ?>
                                            <a href="<?= formatWhatsAppLink(WHATSAPP_NOTIFY, 'Link para RAT ' . $rat['numero'] . ': ' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . 'formulario.php?token=' . $rat['token']) ?>" 
                                               target="_blank" 
                                               class="btn-icon whatsapp" 
                                               title="Enviar via WhatsApp"
                                               onclick="marcarEnviado(<?= $rat['id'] ?>)">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="api/gerar-pdf.php?id=<?= $rat['id'] ?>" class="btn-icon pdf" title="Baixar PDF" <?= $rat['status'] !== 'preenchido' ? 'style="opacity:0.3;pointer-events:none;"' : '' ?>>
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal de Reset -->
    <div id="modalReset" class="modal" style="display:none;">
        <div class="modal-overlay" onclick="fecharModalReset()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Reset</h3>
                <button onclick="fecharModalReset()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p><strong>Atenção!</strong> Esta ação irá:</p>
                <ul>
                    <li>Deletar TODOS os relatórios do banco de dados</li>
                    <li>Zerar a numeração dos RATs</li>
                    <li>Esta ação NÃO pode ser desfeita!</li>
                </ul>
                <div class="modal-warning">
                    <i class="fas fa-info-circle"></i>
                    Recomendamos fazer um backup antes de resetar.
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="fazerBackup()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Fazer Backup Primeiro
                </button>
                <button onclick="confirmarReset()" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Sim, Resetar Tudo
                </button>
                <button onclick="fecharModalReset()" class="btn btn-outline">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    <style>
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #546e7a, #607d8b);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            font-size: 0.95rem;
        }
        .btn-secondary:hover { 
            background: linear-gradient(135deg, #607d8b, #78909c);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        }
        .btn-danger {
            background: linear-gradient(135deg, #e53935, #f44336);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(229,57,53,0.3);
            font-size: 0.95rem;
        }
        .btn-danger:hover { 
            background: linear-gradient(135deg, #c62828, #e53935);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(229,57,53,0.4);
        }
        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            padding: 12px 24px;
            border: 2px solid var(--gray-300);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .btn-outline:hover { 
            background: var(--gray-100);
            border-color: var(--gray-400);
        }
        
        /* Modal Premium */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
        }
        .modal-content {
            position: relative;
            background: white;
            border-radius: 24px;
            max-width: 520px;
            width: 90%;
            box-shadow: 0 25px 80px rgba(0,0,0,0.35);
            animation: modalIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.9) translateY(-30px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-header {
            padding: 24px 28px;
            background: linear-gradient(135deg, #ffebee, #fff);
            border-bottom: 1px solid #ffcdd2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            color: #c62828;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
            font-weight: 700;
        }
        .modal-header h3 i {
            font-size: 1.5rem;
            animation: shake 0.5s ease-in-out infinite;
        }
        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 32px;
            color: var(--gray-400);
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover { 
            color: #c62828;
            background: rgba(198,40,40,0.1);
        }
        .modal-body {
            padding: 28px;
        }
        .modal-body p {
            font-size: 1.05rem;
            color: var(--gray-700);
            margin-bottom: 15px;
        }
        .modal-body ul {
            margin: 0 0 20px 25px;
            color: var(--gray-600);
        }
        .modal-body li {
            margin-bottom: 10px;
            position: relative;
        }
        .modal-body li::marker {
            color: #c62828;
        }
        .modal-warning {
            background: linear-gradient(135deg, #fff8e1, #ffecb3);
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 16px 18px;
            color: #e65100;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            font-weight: 500;
        }
        .modal-warning i {
            font-size: 1.3rem;
        }
        .modal-footer {
            padding: 24px 28px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
    </style>

    <script>
        // Busca na tabela
        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#ratsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });

        // Marcar como enviado
        function marcarEnviado(id) {
            fetch('api/atualizar-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, status: 'enviado' })
            });
        }

        // Funções de Backup e Reset
        function fazerBackup() {
            fetch('api/backup.php?action=backup')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message + '\n\nArquivo: ' + data.arquivo + '\nRegistros: ' + data.registros);
                        // Oferecer download
                        if (confirm('Deseja baixar o arquivo de backup?')) {
                            window.location.href = 'api/backup.php?action=download_backup';
                        }
                    } else {
                        alert('❌ Erro: ' + data.message);
                    }
                })
                .catch(err => alert('❌ Erro ao fazer backup: ' + err));
        }

        function abrirModalReset() {
            document.getElementById('modalReset').style.display = 'flex';
        }

        function fecharModalReset() {
            document.getElementById('modalReset').style.display = 'none';
        }

        function confirmarReset() {
            if (!confirm('ÚLTIMA CONFIRMAÇÃO: Você tem certeza absoluta que deseja deletar TODOS os RATs?')) {
                return;
            }
            
            fetch('api/backup.php?action=reset')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        fecharModalReset();
                        window.location.reload();
                    } else {
                        alert('❌ Erro: ' + data.message);
                    }
                })
                .catch(err => alert('❌ Erro ao resetar: ' + err));
        }
    </script>
</body>
</html>
