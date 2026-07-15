<?php
require_once '../api/config.php';
require_once '../api/auth-admin.php';
require_once '../api/enviar-email.php';

verificarAdmin();

$db = getDB();
$rat_id = $_GET['id'] ?? '';

// ── Apagar RAT ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'apagar_rat') {
    $rid = intval($_POST['rat_id'] ?? 0);
    if ($rid > 0) {
        $stmt = $db->prepare("DELETE FROM rats WHERE id = ?");
        $stmt->execute([$rid]);
    }
    header('Location: index.php');
    exit;
}

// ── Reenviar e-mail versão cliente ────────────────────────────────────────────
$reenvio_mensagem = '';
$reenvio_tipo     = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'reenviar_cliente') {
    $rid   = intval($_POST['rat_id'] ?? 0);
    $email = trim($_POST['email_destino'] ?? '');
    if ($rid > 0 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $ok = reenviarEmailCliente($rid, $email, $db);
        $reenvio_mensagem = $ok
            ? "E-mail enviado com sucesso para {$email}!"
            : 'Erro ao enviar o e-mail. Verifique os logs do servidor.';
        $reenvio_tipo = $ok ? 'sucesso' : 'erro';
    } else {
        $reenvio_mensagem = 'E-mail invalido.';
        $reenvio_tipo = 'erro';
    }
}

// ── Salvar Feedback do Suporte ──────────────────────────────────────────────
$feedback_mensagem = '';
$feedback_tipo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_feedback') {
    $rid = intval($_POST['rat_id'] ?? 0);
    $feedback_texto = trim($_POST['feedback_suporte'] ?? '');
    
    if ($rid > 0) {
        $data_fb = date('Y-m-d H:i:s');
        try {
            $stmt = $db->prepare("UPDATE rats SET feedback_suporte = ?, data_feedback = ? WHERE id = ?");
            if ($stmt->execute([$feedback_texto, $data_fb, $rid])) {
                $feedback_mensagem = 'Feedback pós-atendimento salvo com sucesso!';
                $feedback_tipo = 'sucesso';
            } else {
                $feedback_mensagem = 'Erro ao salvar o feedback.';
                $feedback_tipo = 'erro';
            }
        } catch (Exception $e) {
            $feedback_mensagem = 'Erro no banco de dados. Tente novamente.';
            $feedback_tipo = 'erro';
        }
    }
}

if (!$rat_id) {
    header('Location: index.php');
    exit;
}

// Buscar RAT
$stmt = $db->prepare("SELECT r.*, t.nome as tecnico_nome FROM rats r LEFT JOIN tecnicos t ON r.id_tecnico = t.id WHERE r.id = ?");
$stmt->execute([$rat_id]);
$rat = $stmt->fetch();

if (!$rat) {
    die("RAT não encontrado!");
}

// Decodificar JSON
$turnos_lista        = json_decode($rat['turnos_json']        ?? '[]', true) ?: [];
$horas_viajadas_lista = json_decode($rat['horas_viajadas_json'] ?? '[]', true) ?: [];
$kms_rodados_lista   = json_decode($rat['kms_rodados_json']   ?? '[]', true) ?: [];

// Helper: converte "2h 30min" ou decimal "2.5" / "1,30" → minutos
function horaParaMin($val) {
    if (empty($val)) return 0;
    $val = trim($val);
    if (strpos($val, 'h') !== false) {
        preg_match('/(\d+)h\s*(\d*)/', $val, $m);
        return intval($m[1] ?? 0) * 60 + intval($m[2] ?? 0);
    }
    // decimal com ponto ou vírgula (ex: 2.5 ou 1,30)
    $dec = floatval(str_replace(',', '.', $val));
    return intval($dec) * 60 + (int)round(($dec - intval($dec)) * 60);
}
function minParaHM($min) {
    return intdiv($min, 60) . 'h ' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . 'min';
}

// Calcular total horas trabalhadas
function diffMin($t1, $t2) {
    if (!$t1 || !$t2) return 0;
    list($h1, $m1) = array_map('intval', explode(':', $t1));
    list($h2, $m2) = array_map('intval', explode(':', $t2));
    $d = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
    return $d > 0 ? $d : 0;
}
$total_trabalhado_min = 0;
foreach ($turnos_lista as $t) {
    $total_trabalhado_min += diffMin($t['manha']['inicio'] ?? '', $t['manha']['fim'] ?? '');
    $total_trabalhado_min += diffMin($t['tarde']['inicio'] ?? '', $t['tarde']['fim'] ?? '');
}

// Calcular total horas viajadas
$total_viagem_min = 0;
foreach ($horas_viajadas_lista as $hv) {
    $total_viagem_min += horaParaMin($hv['ida'] ?? '');
    $total_viagem_min += horaParaMin($hv['volta'] ?? '');
}

// Calcular total KMs
$total_kms = 0;
foreach ($kms_rodados_lista as $km) {
    $total_kms += floatval($km['total'] ?? (floatval($km['ida'] ?? 0) + floatval($km['volta'] ?? 0)));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar RAT | Sistema RAT</title>
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
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(3, 76, 140, 0.08);
            animation: fadeInUp 0.6s ease-out both;
        }

        .info-card:nth-of-type(1) { animation-delay: 0.1s; }
        .info-card:nth-of-type(2) { animation-delay: 0.2s; }
        .info-card:nth-of-type(3) { animation-delay: 0.3s; }
        .info-card:nth-of-type(4) { animation-delay: 0.4s; }
        .info-card:nth-of-type(5) { animation-delay: 0.5s; }
        .info-card:nth-of-type(6) { animation-delay: 0.6s; }
        .info-card:nth-of-type(7) { animation-delay: 0.7s; }
        .info-card:nth-of-type(8) { animation-delay: 0.8s; }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 0;
        }

        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-field {
            padding: 15px;
            border-left: 4px solid var(--color-primary);
            background: linear-gradient(135deg, rgba(3, 76, 140, 0.02) 0%, rgba(4, 102, 200, 0.02) 100%);
            border-radius: 6px;
            transition: all 0.3s;
        }

        .info-field:hover {
            background: linear-gradient(135deg, rgba(3, 76, 140, 0.05) 0%, rgba(4, 102, 200, 0.05) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(3, 76, 140, 0.1);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--color-primary);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            color: var(--color-dark);
            margin-top: 8px;
            font-size: 14px;
            line-height: 1.5;
        }

        .info-value strong {
            font-weight: 600;
            color: var(--color-primary);
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-primary);
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--color-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 13px;
        }
        
        th {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--color-primary);
            border-bottom: 2px solid var(--color-light);
            font-size: 12px;
            text-transform: uppercase;
        }
        
        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--color-light);
        }

        tbody tr:hover {
            background: #f9fafb;
            transition: background 0.2s;
        }
        
        .signature-img {
            max-width: 200px;
            max-height: 80px;
            border: 2px solid var(--color-primary);
            border-radius: 6px;
            margin: 10px 0;
            box-shadow: 0 2px 6px rgba(3, 76, 140, 0.15);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            animation: fadeInUp 0.6s ease-out 0.9s both;
        }

        .btn-primary {
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
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(3, 76, 140, 0.25);
        }

        .btn-secondary {
            padding: 12px 24px;
            background: white;
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: var(--color-primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(3, 76, 140, 0.25);
        }

        .btn-danger {
            padding: 12px 24px;
            background: white;
            color: #dc2626;
            border: 2px solid #fca5a5;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }

        .btn-danger:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.25);
        }

        .btn-green {
            padding: 12px 24px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }
        .btn-green:hover {
            background: #059669;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(16,185,129,0.3);
        }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: white;
            border-radius: 12px;
            padding: 32px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-box h3 { margin: 0 0 8px; color: #1e293b; font-size: 18px; }
        .modal-box p  { margin: 0 0 20px; color: #64748b; font-size: 14px; }
        .modal-box input[type=email] {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 16px;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .modal-box input[type=email]:focus { border-color: #10b981; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .alert-reenvio {
            margin: 0 0 20px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-sucesso { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-erro    { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        @media print {
            header, .action-buttons {
                display: none;
            }
            body {
                background: white;
            }
            .info-card {
                box-shadow: 1px 1px 2px rgba(0,0,0,0.1);
                page-break-inside: avoid;
            }
            .section-title {
                page-break-after: avoid;
            }
            table {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>📋 RAT: <?php echo htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)); ?></h1>
        <a href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a>
    </header>
    
    <div class="container">
        <!-- Informações Gerais -->
        <div class="info-card">
            <div class="section-title">Informações Gerais</div>
            <div class="info-grid">
                <div class="info-field">
                    <div class="info-label">Número RAT</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)); ?></strong></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Status</div>
                    <div class="info-value" style="display: flex; flex-direction: column; gap: 4px;">
                        <span><?php echo ucfirst($rat['status']); ?></span>
                        <?php if ($rat['status'] === 'enviado'): ?>
                        <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 2px;">
                            <label class="reembolso-label" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: <?= $rat['pago_reembolso'] ? '#10b981' : '#64748b' ?>; cursor: pointer; transition: color 0.2s;">
                                <input type="checkbox" class="toggle-reembolso" data-id="<?= $rat['id'] ?>" <?= $rat['pago_reembolso'] ? 'checked' : '' ?> style="accent-color: #10b981; cursor: pointer; width: 14px; height: 14px; margin: 0;">
                                <span><?= $rat['pago_reembolso'] ? 'Reembolso Pago' : 'Pagar Reembolso' ?></span>
                            </label>
                            <label class="lancado-label" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: <?= !empty($rat['lancado_reembolso']) ? '#0284c7' : '#64748b' ?>; cursor: pointer; transition: color 0.2s;">
                                <input type="checkbox" class="toggle-lancado" data-id="<?= $rat['id'] ?>" <?= !empty($rat['lancado_reembolso']) ? 'checked' : '' ?> style="accent-color: #0284c7; cursor: pointer; width: 14px; height: 14px; margin: 0;">
                                <span><?= !empty($rat['lancado_reembolso']) ? 'Lançado Financeiro' : 'Lançar Financeiro' ?></span>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-field">
                    <div class="info-label">Técnico</div>
                    <div class="info-value"><?php echo htmlspecialchars($rat['tecnico_nome'] ?? '-'); ?></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Criado em</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($rat['data_criacao'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Dados do Cliente -->
        <div class="info-card">
            <div class="section-title">Dados do Cliente</div>
            <div class="info-grid">
                <div class="info-field">
                    <div class="info-label">Empresa</div>
                    <div class="info-value"><?php echo htmlspecialchars($rat['cliente_empresa'] ?? '-'); ?></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Responsável</div>
                    <div class="info-value"><?php echo htmlspecialchars($rat['cliente_responsavel'] ?? '-'); ?></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($rat['email_cliente'] ?? '-'); ?></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Endereço</div>
                    <div class="info-value"><?php echo htmlspecialchars(($rat['endereco'] ?? '-') . ', ' . ($rat['cidade'] ?? '') . ' - ' . ($rat['estado'] ?? '')); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Dados do Equipamento -->
        <div class="info-card">
            <div class="section-title">Dados do Equipamento</div>
            <div class="info-grid">
                <div class="info-field">
                    <div class="info-label">Equipamento</div>
                    <div class="info-value"><?php echo htmlspecialchars($rat['equipamento'] ?? '-'); ?></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Modelo</div>
                    <div class="info-value"><?php echo htmlspecialchars($rat['modelo_maquina'] ?? '-'); ?></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Matrícula</div>
                    <div class="info-value"><?php echo htmlspecialchars($rat['matricula'] ?? '-'); ?></div>
                </div>
                <div class="info-field">
                    <div class="info-label">Garantia</div>
                    <div class="info-value"><?php echo htmlspecialchars($rat['garantia'] ?? '-'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Serviço -->
        <div class="info-card">
            <div class="section-title">Serviço</div>
            <div class="info-field" style="margin-top: 20px;">
                <div class="info-label">Tipo de Serviço</div>
                <div class="info-value"><strong><?php echo htmlspecialchars($rat['tipo_servico'] ?? '-'); ?></strong></div>
            </div>
            <?php 
            $tipos_rv = array_map('trim', explode(',', $rat['tipo_servico'] ?? ''));
            $esconder_defeito_rv = in_array('Treinamento', $tipos_rv) && in_array('Instalação', $tipos_rv);
            if (!$esconder_defeito_rv): 
            ?>
            <div class="info-field" style="margin-top: 20px;">
                <div class="info-label">Defeito Constatado</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($rat['defeito_constatado'] ?? '-')); ?></div>
            </div>
            <?php endif; ?>
            <div class="info-field" style="margin-top: 20px;">
                <div class="info-label">Trabalho Executado</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($rat['trabalho_executado'] ?? '-')); ?></div>
            </div>
        </div>
        
        <?php
        $orcamento_rv = json_decode($rat['orcamento_json'] ?? '[]', true);
        if (!empty($orcamento_rv)):
        ?>
        <div class="info-card">
            <div class="section-title">Orçamento</div>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantidade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orcamento_rv as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['nome'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['quantidade'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Turnos -->
        <?php if (!empty($turnos_lista)): ?>
        <div class="info-card">
            <div class="section-title">⏱️ Turnos de Trabalho</div>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Manhã Início</th>
                        <th>Manhã Fim</th>
                        <th>Tarde Início</th>
                        <th>Tarde Fim</th>
                        <th>Total do Dia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($turnos_lista as $turno):
                        $dm = diffMin($turno['manha']['inicio'] ?? '', $turno['manha']['fim'] ?? '');
                        $dt = diffMin($turno['tarde']['inicio'] ?? '', $turno['tarde']['fim'] ?? '');
                        $dia_min = $dm + $dt;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($turno['dia'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($turno['manha']['inicio'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($turno['manha']['fim'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($turno['tarde']['inicio'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($turno['tarde']['fim'] ?? '-'); ?></td>
                        <td><strong><?php echo $dia_min > 0 ? minParaHM($dia_min) : '-'; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($total_trabalhado_min > 0): ?>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#eff6ff,#dbeafe);">
                        <td colspan="5"><strong>Total Horas Trabalhadas</strong></td>
                        <td><strong style="color:#1d4ed8;"><?php echo minParaHM($total_trabalhado_min); ?></strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>

        <!-- Horas de Viagem -->
        <?php if (!empty($horas_viajadas_lista)): ?>
        <div class="info-card">
            <div class="section-title">✈️ Horas de Viagem</div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Data</th>
                        <th>Tempo Ida</th>
                        <th>Tempo Volta</th>
                        <th>Total do Dia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($horas_viajadas_lista as $i => $hv):
                        $min_dia = horaParaMin($hv['ida'] ?? '') + horaParaMin($hv['volta'] ?? '');
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($hv['data'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($hv['ida'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($hv['volta'] ?? '-'); ?></td>
                        <td><strong><?php echo $min_dia > 0 ? minParaHM($min_dia) : '-'; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($total_viagem_min > 0): ?>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);">
                        <td colspan="4"><strong>Total Tempo de Viagem</strong></td>
                        <td><strong style="color:#15803d;"><?php echo minParaHM($total_viagem_min); ?></strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>

        <!-- Quilometragem -->
        <?php if (!empty($kms_rodados_lista)): ?>
        <div class="info-card">
            <div class="section-title">🛣️ Quilometragem</div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Data</th>
                        <th>KM Ida</th>
                        <th>KM Volta</th>
                        <th>KM Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kms_rodados_lista as $i => $km):
                        $kt = floatval($km['total'] ?? (floatval($km['ida'] ?? 0) + floatval($km['volta'] ?? 0)));
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($km['data'] ?? '-'); ?></td>
                        <td><?php echo number_format(floatval($km['ida'] ?? 0), 1, ',', '.'); ?> km</td>
                        <td><?php echo number_format(floatval($km['volta'] ?? 0), 1, ',', '.'); ?> km</td>
                        <td><strong><?php echo number_format($kt, 1, ',', '.'); ?> km</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($total_kms > 0): ?>
                <tfoot>
                    <tr style="background:linear-gradient(135deg,#fff7ed,#ffedd5);">
                        <td colspan="4"><strong>Total KMs Rodados</strong></td>
                        <td><strong style="color:#c2410c;"><?php echo number_format($total_kms, 1, ',', '.'); ?> km</strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Adicionais (só mostra se total > 0) -->
        <?php 
        $total_desp_rv = floatval($rat['total_adicionais'] ?? 0);
        if ($total_desp_rv > 0): 
        ?>
        <div class="info-card">
            <div class="section-title">Despesas Adicionais</div>
            <table>
                <thead>
                    <tr>
                        <th>Despesa</th>
                        <th>Valor Unit.</th>
                        <th>Qtd/Dias</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $pedagio_val     = floatval($rat['pedagio'] ?? 0);
                    $pedagio_qtd     = intval($rat['pedagio_qtd'] ?? 0);
                    $pedagio_sub     = $pedagio_qtd > 0 ? $pedagio_val * $pedagio_qtd : $pedagio_val;
                    $pedagio_qtd_txt = $pedagio_qtd > 0 ? $pedagio_qtd : '—';

                    $hospedagem_val  = floatval($rat['hospedagem'] ?? 0);
                    $hospedagem_dias = intval($rat['hospedagem_dias'] ?? 0);
                    $hospedagem_sub  = $hospedagem_dias > 0 ? $hospedagem_val * $hospedagem_dias : $hospedagem_val;
                    $hospedagem_txt  = $hospedagem_dias > 0 ? $hospedagem_dias . ' dia(s)' : '—';

                    $alimentacao_val = floatval($rat['alimentacao'] ?? 0);
                    $alimentacao_qtd = intval($rat['alimentacao_qtd'] ?? 0);
                    $alimentacao_sub = $alimentacao_qtd > 0 ? $alimentacao_val * $alimentacao_qtd : $alimentacao_val;
                    $alimentacao_txt = $alimentacao_qtd > 0 ? $alimentacao_qtd : '—';

                    $outros_val      = floatval($rat['outros_despesas'] ?? 0);
                    $outros_qtd_val  = intval($rat['outros_qtd'] ?? 0);
                    $outros_sub      = $outros_qtd_val > 0 ? $outros_val * $outros_qtd_val : $outros_val;
                    $outros_txt      = $outros_qtd_val > 0 ? $outros_qtd_val : '—';
                    $outros_desc     = $rat['outros_despesas_desc'] ?? '';
                    ?>
                    <?php if ($pedagio_val > 0): ?>
                    <tr>
                        <td><strong>Pedágio</strong></td>
                        <td>R$ <?php echo number_format($pedagio_val, 2, ',', '.'); ?></td>
                        <td><?php echo $pedagio_qtd_txt; ?></td>
                        <td>R$ <?php echo number_format($pedagio_sub, 2, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($hospedagem_val > 0): ?>
                    <tr>
                        <td><strong>Hospedagem</strong></td>
                        <td>R$ <?php echo number_format($hospedagem_val, 2, ',', '.'); ?></td>
                        <td><?php echo $hospedagem_txt; ?></td>
                        <td>R$ <?php echo number_format($hospedagem_sub, 2, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($alimentacao_val > 0): ?>
                    <tr>
                        <td><strong>Alimentação</strong></td>
                        <td>R$ <?php echo number_format($alimentacao_val, 2, ',', '.'); ?></td>
                        <td><?php echo $alimentacao_txt; ?></td>
                        <td>R$ <?php echo number_format($alimentacao_sub, 2, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($outros_val > 0): ?>
                    <tr>
                        <td><strong>Outros</strong><?php echo !empty($outros_desc) ? '<br><small style="color:#6b7280;">' . htmlspecialchars($outros_desc) . '</small>' : ''; ?></td>
                        <td>R$ <?php echo number_format($outros_val, 2, ',', '.'); ?></td>
                        <td><?php echo $outros_txt; ?></td>
                        <td>R$ <?php echo number_format($outros_sub, 2, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);">
                        <td colspan="3"><strong>Total Geral</strong></td>
                        <td><strong>R$ <?php echo number_format($total_desp_rv, 2, ',', '.'); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Assinaturas (Técnico Principal) -->
        <div class="info-card">
            <div class="section-title">Assinaturas</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>Cliente</h4>
                    <?php if ($rat['assinatura_cliente']): ?>
                        <img src="<?php echo htmlspecialchars($rat['assinatura_cliente']); ?>" alt="Assinatura Cliente" class="signature-img">
                    <?php else: ?>
                        <p style="color: #999;">Sem assinatura</p>
                    <?php endif; ?>
                </div>
                <div>
                    <h4>Técnico: <?php echo htmlspecialchars($rat['tecnico_nome'] ?? '-'); ?></h4>
                    <?php if ($rat['assinatura_tecnico']): ?>
                        <img src="<?php echo htmlspecialchars($rat['assinatura_tecnico']); ?>" alt="Assinatura Técnico" class="signature-img">
                    <?php else: ?>
                        <p style="color: #999;">Sem assinatura</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Técnicos Adicionais -->
        <?php 
        $tecnicos_adicionais = json_decode($rat['tecnicos_adicionais_json'] ?? '[]', true) ?: [];
        foreach ($tecnicos_adicionais as $tec):
        ?>
        <div class="info-card" style="border-left: 4px solid #10b981;">
            <div class="section-title" style="background: #10b981;">Técnico Adicional: <?= htmlspecialchars($tec['nome'] ?? '') ?></div>
            
            <div style="margin-top: 15px;">
                <strong><i class="fas fa-calendar-alt"></i> Turnos:</strong>
                <?php if (!empty($tec['turnos'])): ?>
                <table style="margin-top: 10px; font-size: 13px;">
                    <thead><tr><th>Dia</th><th>Data</th><th>Manhã</th><th>Tarde</th></tr></thead>
                    <tbody>
                        <?php foreach($tec['turnos'] as $i => $t): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($t['dia'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($t['manha']['inicio'] ?? '-') ?> às <?= htmlspecialchars($t['manha']['fim'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($t['tarde']['inicio'] ?? '-') ?> às <?= htmlspecialchars($t['tarde']['fim'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>-</p>
                <?php endif; ?>
            </div>

            <div style="margin-top: 20px;">
                <strong><i class="fas fa-wallet"></i> Despesas:</strong>
                <table style="margin-top: 10px; font-size: 13px;">
                    <thead><tr><th>Despesa</th><th>Valor Unit.</th><th>Qtd/Dias</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php
                        $ped_v = floatval($tec['despesas']['pedagio'] ?? 0);
                        $ped_q = intval($tec['despesas']['pedagio_qtd'] ?? 0);
                        if ($ped_v > 0): ?>
                        <tr><td>Pedágio</td><td>R$ <?= number_format($ped_v,2,',','.') ?></td><td><?= $ped_q ?></td><td>R$ <?= number_format($ped_v * max($ped_q,1),2,',','.') ?></td></tr>
                        <?php endif; ?>
                        
                        <?php
                        $hosp_v = floatval($tec['despesas']['hospedagem'] ?? 0);
                        $hosp_d = intval($tec['despesas']['hospedagem_dias'] ?? 0);
                        if ($hosp_v > 0): ?>
                        <tr><td>Hospedagem</td><td>R$ <?= number_format($hosp_v,2,',','.') ?></td><td><?= $hosp_d ?> dia(s)</td><td>R$ <?= number_format($hosp_v * max($hosp_d,1),2,',','.') ?></td></tr>
                        <?php endif; ?>

                        <?php
                        $alim_v = floatval($tec['despesas']['alimentacao'] ?? 0);
                        $alim_q = intval($tec['despesas']['alimentacao_qtd'] ?? 0);
                        if ($alim_v > 0): ?>
                        <tr><td>Alimentação</td><td>R$ <?= number_format($alim_v,2,',','.') ?></td><td><?= $alim_q ?></td><td>R$ <?= number_format($alim_v * max($alim_q,1),2,',','.') ?></td></tr>
                        <?php endif; ?>

                        <?php
                        $outros_v = floatval($tec['despesas']['outros_despesas'] ?? 0);
                        $outros_q = intval($tec['despesas']['outros_qtd'] ?? 0);
                        if ($outros_v > 0): ?>
                        <tr><td>Outros (<?= htmlspecialchars($tec['despesas']['outros_despesas_desc'] ?? '') ?>)</td><td>R$ <?= number_format($outros_v,2,',','.') ?></td><td><?= $outros_q ?></td><td>R$ <?= number_format($outros_v * max($outros_q,1),2,',','.') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px;">
                <strong><i class="fas fa-signature"></i> Assinatura:</strong><br>
                <?php if (!empty($tec['assinatura'])): ?>
                    <img src="<?= htmlspecialchars($tec['assinatura']) ?>" alt="Assinatura" class="signature-img" style="margin-top:10px;">
                <?php else: ?>
                    <p style="color: #999;">Sem assinatura</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Notas Fiscais -->
        <span id="notas-fiscais"></span>
        <?php $notas_fiscais = json_decode($rat['notas_fiscais_json'] ?? '[]', true) ?: []; ?>
        <?php if (!empty($notas_fiscais)): ?>
        <div class="section">
            <div class="section-title">🧾 Notas Fiscais</div>
            <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:8px;">
                <?php foreach ($notas_fiscais as $nota):
                    $nota_url = '../uploads/notas/' . $rat['id'] . '/' . rawurlencode(basename($nota));
                    $is_pdf = strtolower(pathinfo($nota, PATHINFO_EXTENSION)) === 'pdf';
                ?>
                <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;width:140px;background:#f8fafc;box-shadow:0 2px 6px rgba(0,0,0,.06);text-align:center;">
                    <?php if ($is_pdf): ?>
                    <a href="<?= $nota_url ?>" target="_blank" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:120px;text-decoration:none;color:#334155;font-size:12px;font-weight:600;gap:6px;">
                        <i class="fas fa-file-pdf" style="font-size:40px;color:#ef4444;"></i>
                        Abrir PDF
                    </a>
                    <?php else: ?>
                    <a href="<?= $nota_url ?>" target="_blank">
                        <img src="<?= $nota_url ?>" alt="Nota Fiscal" style="width:140px;height:120px;object-fit:cover;display:block;">
                    </a>
                    <div style="font-size:11px;color:#64748b;padding:4px 6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars(basename($nota)) ?>">
                        <?= htmlspecialchars(basename($nota)) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="section">
            <div class="section-title">🧾 Notas Fiscais</div>
            <p style="color:#94a3b8;font-size:13px;margin-top:8px;">Nenhuma nota fiscal anexada para este RAT.</p>
        </div>
        <?php endif; ?>

        <?php if ($reenvio_mensagem): ?>
        <div class="alert-reenvio alert-<?= $reenvio_tipo === 'sucesso' ? 'sucesso' : 'erro' ?>">
            <?= htmlspecialchars($reenvio_mensagem) ?>
        </div>
        <?php endif; ?>

        <?php if ($feedback_mensagem): ?>
        <div class="alert-reenvio alert-<?= $feedback_tipo === 'sucesso' ? 'sucesso' : 'erro' ?>" style="margin-top: 20px;">
            <?= htmlspecialchars($feedback_mensagem) ?>
        </div>
        <?php endif; ?>

        <!-- Feedback Pós-Atendimento (Suporte) -->
        <div class="info-card" id="secao-feedback">
            <div class="section-title">
                <i class="fas fa-comments"></i> Feedback Pós-Atendimento (Exclusivo Interno)
            </div>
            <div style="background-color: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981;">
                <p style="font-size: 13px; color: #475569; margin-bottom: 12px;">
                    Use este espaço para registrar o retorno do cliente após o atendimento. Esta anotação não aparece para o técnico nem para o cliente.
                </p>
                <?php if (!empty($rat['data_feedback'])): ?>
                    <p style="font-size: 12px; color: #64748b; margin-bottom: 10px;">
                        <em>Última atualização: <?= date('d/m/Y H:i', strtotime($rat['data_feedback'])) ?></em>
                    </p>
                <?php endif; ?>
                <form method="POST" action="#secao-feedback">
                    <input type="hidden" name="acao" value="salvar_feedback">
                    <input type="hidden" name="rat_id" value="<?= $rat['id'] ?>">
                    <textarea name="feedback_suporte" rows="4" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; resize: vertical; margin-bottom: 15px;" placeholder="Digite aqui como foi a conversa com o cliente..."><?= htmlspecialchars($rat['feedback_suporte'] ?? '') ?></textarea>
                    <button type="submit" class="btn-primary" style="padding: 10px 20px;"><i class="fas fa-save"></i> Salvar Feedback</button>
                </form>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="index.php" class="btn-secondary"><i class="fas fa-list"></i> Ver Todos os RATs</a>
            <button class="btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
            <button class="btn-green" onclick="document.getElementById('modalReenvio').classList.add('open')"><i class="fas fa-paper-plane"></i> Enviar para Cliente</button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Apagar o RAT <?= htmlspecialchars($rat['numero']) ?>?\nEsta ação não pode ser desfeita.')">
                <input type="hidden" name="acao" value="apagar_rat">
                <input type="hidden" name="rat_id" value="<?= $rat['id'] ?>">
                <button type="submit" class="btn-danger"><i class="fas fa-trash-alt"></i> Apagar RAT</button>
            </form>
        </div>
    </div>

    <!-- Modal: Reenviar versão cliente -->
    <div class="modal-overlay" id="modalReenvio">
        <div class="modal-box">
            <h3><i class="fas fa-paper-plane" style="color:#10b981;"></i> Enviar Relatório ao Cliente</h3>
            <p>Será enviada a versão do cliente (sem valores internos) do RAT <strong><?= htmlspecialchars($rat['numero']) ?></strong>.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="reenviar_cliente">
                <input type="hidden" name="rat_id" value="<?= $rat['id'] ?>">
                <input type="email" name="email_destino" placeholder="email@empresa.com.br" required autofocus>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="document.getElementById('modalReenvio').classList.remove('open')">Cancelar</button>
                    <button type="submit" class="btn-green"><i class="fas fa-paper-plane"></i> Enviar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('modalReenvio').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });

    // Toggle Reembolso Pago
    document.querySelectorAll('.toggle-reembolso').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const ratId = this.dataset.id;
            const isChecked = this.checked ? 1 : 0;
            const labelText = this.nextElementSibling;
            const labelContainer = this.closest('.reembolso-label');
            
            if (labelContainer) {
                labelContainer.style.opacity = '0.5';
            }
            
            fetch('../api/atualizar-pagamento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: ratId,
                    pago: isChecked
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na requisição');
                }
                return response.json();
            })
            .then(data => {
                if (labelContainer) {
                    labelContainer.style.opacity = '1';
                }
                if (data.success) {
                    if (isChecked === 1) {
                        labelContainer.style.color = '#10b981';
                        if (labelText) labelText.textContent = 'Reembolso Pago';
                    } else {
                        labelContainer.style.color = '#64748b';
                        if (labelText) labelText.textContent = 'Pagar Reembolso';
                    }
                } else {
                    alert('Erro ao atualizar status do reembolso: ' + (data.error || 'Erro desconhecido'));
                    this.checked = !this.checked;
                }
            })
            .catch(err => {
                if (labelContainer) {
                    labelContainer.style.opacity = '1';
                }
                alert('Erro de conexão ou permissão insuficiente.');
                this.checked = !this.checked;
            });
        });
    });

    // Toggle Reembolso Lançado
    document.querySelectorAll('.toggle-lancado').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const ratId = this.dataset.id;
            const isChecked = this.checked ? 1 : 0;
            const labelText = this.nextElementSibling;
            const labelContainer = this.closest('.lancado-label');
            
            if (labelContainer) {
                labelContainer.style.opacity = '0.5';
            }
            
            fetch('../api/atualizar-lancamento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: ratId,
                    lancado: isChecked
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na requisição');
                }
                return response.json();
            })
            .then(data => {
                if (labelContainer) {
                    labelContainer.style.opacity = '1';
                }
                if (data.success) {
                    if (isChecked === 1) {
                        labelContainer.style.color = '#0284c7';
                        if (labelText) labelText.textContent = 'Lançado Financeiro';
                    } else {
                        labelContainer.style.color = '#64748b';
                        if (labelText) labelText.textContent = 'Lançar Financeiro';
                    }
                } else {
                    alert('Erro ao atualizar status do lançamento: ' + (data.error || 'Erro desconhecido'));
                    this.checked = !this.checked;
                }
            })
            .catch(err => {
                if (labelContainer) {
                    labelContainer.style.opacity = '1';
                }
                alert('Erro de conexão ou permissão insuficiente.');
                this.checked = !this.checked;
            });
        });
    });
    </script>

</body>
</html>
