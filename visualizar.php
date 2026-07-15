<?php
/**
 * Visualizar RAT - Madetech
 */
require_once 'api/config.php';

$db = getDB();

$id = $_GET['id'] ?? 0;
$novo = isset($_GET['novo']);

$stmt = $db->prepare("SELECT * FROM rats WHERE id = ?");
$stmt->execute([$id]);
$rat = $stmt->fetch();

if (!$rat) {
    header("Location: index.php");
    exit;
}

// URL do formulário
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$formulario_url = "{$protocol}://{$host}" . BASE_URL . "formulario.php?token=" . $rat['token'];

// Mensagem WhatsApp
$whatsapp_msg = "Olá! Segue o link para preenchimento do RAT {$rat['numero']}:\n\n{$formulario_url}\n\nMadetech - Assistência Técnica";
$whatsapp_link = formatWhatsAppLink(WHATSAPP_NOTIFY, $whatsapp_msg);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null) ?> - Sistema RAT Madetech</title>
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
                    <img src="https://madetech.com.br/wp-content/uploads/2026/05/Logo-Madetech-Final.webp" alt="Madetech" class="logo">
                    <img src="https://madetech.com.br/wp-content/uploads/2026/07/Logo-Madeparts-Final.png" alt="Madeparts" class="logo">
                </div>
                <h2>Sistema RAT</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="criar.php" class="nav-item">
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
                <h1>
                    <i class="fas fa-file-alt"></i> 
                    <?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?>
                    <span class="status-badge <?= $rat['status'] ?>"><?= ucfirst($rat['status']) ?></span>
                </h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </header>

            <?php if ($novo): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <strong>RAT criado com sucesso!</strong> Copie o link abaixo e envie para o técnico.
                </div>
            <?php endif; ?>

            <!-- Link do Formulário -->
            <div class="card highlight">
                <h3><i class="fas fa-link"></i> Link do Formulário para o Técnico</h3>
                <div class="link-box">
                    <input type="text" value="<?= htmlspecialchars($formulario_url) ?>" id="linkFormulario" readonly>
                    <button onclick="copiarLink()" class="btn btn-primary" id="btnCopiar">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                </div>
                <div class="link-actions">
                    <a href="<?= $whatsapp_link ?>" target="_blank" class="btn btn-whatsapp" onclick="marcarEnviado()">
                        <i class="fab fa-whatsapp"></i> Enviar via WhatsApp
                    </a>
                    <a href="<?= htmlspecialchars($formulario_url) ?>" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-external-link-alt"></i> Abrir Formulário
                    </a>
                </div>
            </div>

            <!-- Dados do RAT -->
            <div class="cards-grid">
                <div class="card">
                    <h3><i class="fas fa-info-circle"></i> Informações Gerais</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Número</span>
                            <span class="info-value"><?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value"><span class="status-badge <?= $rat['status'] ?>"><?= ucfirst($rat['status']) ?></span></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Criado em</span>
                            <span class="info-value"><?= date('d/m/Y H:i', strtotime($rat['data_criacao'])) ?></span>
                        </div>
                        <?php if ($rat['data_preenchimento']): ?>
                        <div class="info-item">
                            <span class="info-label">Preenchido em</span>
                            <span class="info-value"><?= date('d/m/Y H:i', strtotime($rat['data_preenchimento'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fas fa-user-tie"></i> Técnico</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Nome</span>
                            <span class="info-value"><?= htmlspecialchars($rat['tecnico_nome'] ?: '-') ?></span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fas fa-building"></i> Cliente</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Empresa</span>
                            <span class="info-value"><?= htmlspecialchars($rat['cliente_empresa'] ?: '-') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Responsável</span>
                            <span class="info-value"><?= htmlspecialchars($rat['cliente_responsavel'] ?: '-') ?></span>
                        </div>
                        <div class="info-item full-width">
                            <span class="info-label">Endereço</span>
                            <span class="info-value"><?= htmlspecialchars(implode(', ', array_filter([$rat['endereco'], $rat['cidade'], $rat['estado']])) ?: '-') ?></span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fas fa-cog"></i> Equipamento</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Tipo</span>
                            <span class="info-value"><?= htmlspecialchars($rat['equipamento'] ?: '-') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Modelo</span>
                            <span class="info-value"><?= htmlspecialchars($rat['modelo_maquina'] ?: '-') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Matrícula</span>
                            <span class="info-value"><?= htmlspecialchars($rat['matricula'] ?: '-') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Garantia</span>
                            <span class="info-value"><?= htmlspecialchars($rat['garantia'] ?: '-') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($rat['status'] === 'preenchido'): ?>
            <!-- Dados do Serviço (apenas se preenchido) -->
            <div class="card full-width">
                <h3><i class="fas fa-tools"></i> Serviço Executado</h3>
                <div class="service-details">
                    <?php 
                    $tipos_vis = array_map('trim', explode(',', $rat['tipo_servico'] ?? ''));
                    $esconder_defeito_vis = in_array('Treinamento', $tipos_vis) && in_array('Instalação', $tipos_vis);
                    if (!$esconder_defeito_vis): 
                    ?>
                    <div class="service-item">
                        <h4>Defeito Constatado</h4>
                        <p><?= nl2br(htmlspecialchars($rat['defeito_constatado'] ?: '-')) ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="service-item">
                        <h4>Trabalho Executado</h4>
                        <p><?= nl2br(htmlspecialchars($rat['trabalho_executado'] ?: '-')) ?></p>
                    </div>
                </div>
            </div>

            <?php 
            $orcamento_vis = json_decode($rat['orcamento_json'] ?? '[]', true);
            if (!empty($orcamento_vis)): 
            ?>
            <div class="card full-width">
                <h3><i class="fas fa-file-invoice-dollar"></i> Orçamento</h3>
                <table style="width:100%; border-collapse:collapse; margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:10px 12px; background:#f3f4f6; border:1px solid #e5e7eb;">Item</th>
                            <th style="text-align:center; padding:10px 12px; background:#f3f4f6; border:1px solid #e5e7eb; width:100px;">Qtd.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orcamento_vis as $item): ?>
                        <tr>
                            <td style="padding:10px 12px; border:1px solid #e5e7eb;"><?= htmlspecialchars($item['nome'] ?? '') ?></td>
                            <td style="text-align:center; padding:10px 12px; border:1px solid #e5e7eb;"><?= htmlspecialchars($item['quantidade'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Assinaturas -->
            <div class="cards-grid">
                <div class="card">
                    <h3><i class="fas fa-signature"></i> Assinatura do Cliente</h3>
                    <?php if ($rat['assinatura_cliente']): ?>
                        <img src="<?= $rat['assinatura_cliente'] ?>" alt="Assinatura Cliente" class="signature-img">
                    <?php else: ?>
                        <p class="no-signature">Não assinado</p>
                    <?php endif; ?>
                </div>
                <div class="card">
                    <h3><i class="fas fa-signature"></i> Assinatura do Técnico</h3>
                    <?php if ($rat['assinatura_tecnico']): ?>
                        <img src="<?= $rat['assinatura_tecnico'] ?>" alt="Assinatura Técnico" class="signature-img">
                    <?php else: ?>
                        <p class="no-signature">Não assinado</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ações -->
            <div class="card-actions">
                <a href="api/gerar-pdf.php?id=<?= $rat['id'] ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-file-pdf"></i> Baixar PDF
                </a>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
        function copiarLink() {
            const input = document.getElementById('linkFormulario');
            input.select();
            document.execCommand('copy');
            
            const btn = document.getElementById('btnCopiar');
            btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
            btn.classList.add('copied');
            
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-copy"></i> Copiar';
                btn.classList.remove('copied');
            }, 2000);
        }

        function marcarEnviado() {
            fetch('api/atualizar-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: <?= $rat['id'] ?>, status: 'enviado' })
            });
        }
    </script>
</body>
</html>
