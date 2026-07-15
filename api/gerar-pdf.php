<?php
/**
 * Gerar PDF do RAT - Madetech
 * Usa HTML to PDF nativo (via browser print)
 */
require_once 'config.php';

$db = getDB();

$id = $_GET['id'] ?? 0;

$stmt = $db->prepare("SELECT * FROM rats WHERE id = ?");
$stmt->execute([$id]);
$rat = $stmt->fetch();

if (!$rat) {
    die('RAT não encontrado.');
}

// Decodificar JSONs
$turnos = json_decode($rat['turnos_json'] ?: '[]', true);
$horasViagem = json_decode($rat['horas_viajadas_json'] ?: '[]', true);
$kms = json_decode($rat['kms_rodados_json'] ?: '[]', true);
$orcamento = json_decode($rat['orcamento_json'] ?: '[]', true);
$tecnicos_adicionais = json_decode($rat['tecnicos_adicionais_json'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?> - Madetech RAT</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #034c8c;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header img {
            height: 50px;
        }
        
        .header-info {
            text-align: right;
        }
        
        .header-info h1 {
            color: #034c8c;
            font-size: 14pt;
            margin-bottom: 5px;
        }
        
        .rat-number {
            font-size: 16pt;
            font-weight: bold;
            color: #f58220;
        }
        
        .section {
            margin-bottom: 20px;
        }
        
        .section-title {
            background: #034c8c;
            color: white;
            padding: 8px 12px;
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 6px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        
        .info-table .label {
            background: #f5f5f5;
            font-weight: bold;
            width: 25%;
            font-size: 10pt;
        }
        
        .text-box {
            border: 1px solid #ddd;
            padding: 10px;
            min-height: 60px;
            background: #fafafa;
        }
        
        .turnos-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        
        .turnos-table th,
        .turnos-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: center;
        }
        
        .turnos-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        .despesas-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .despesa-item {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        
        .despesa-item .label {
            font-size: 9pt;
            color: #666;
        }
        
        .despesa-item .value {
            font-size: 14pt;
            font-weight: bold;
            color: #034c8c;
        }
        
        .assinaturas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }
        
        .assinatura-box {
            text-align: center;
        }
        
        .assinatura-box img {
            max-width: 200px;
            max-height: 80px;
            border: 1px solid #ddd;
            margin-bottom: 5px;
        }
        
        .assinatura-line {
            border-top: 1px solid #333;
            padding-top: 5px;
            font-size: 10pt;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #034c8c;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 5px;
        }
        
        @media print {
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">
        🖨️ Imprimir / Salvar PDF
    </button>
    
    <!-- Header -->
    <div class="header">
        <img src="https://madetech.com.br/wp-content/uploads/2026/05/Logo-Madetech-Final.webp" alt="Madetech">
        <div class="header-info">
            <h1>Relatório de Assistência Técnica</h1>
            <div class="rat-number"><?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?></div>
            <div><?= date('d/m/Y H:i', strtotime($rat['data_preenchimento'] ?: $rat['data_criacao'])) ?></div>
        </div>
    </div>
    
    <!-- Dados do Cliente -->
    <div class="section">
        <div class="section-title">DADOS DO CLIENTE</div>
        <table class="info-table">
            <tr>
                <td class="label">Empresa</td>
                <td colspan="3"><?= htmlspecialchars($rat['cliente_empresa']) ?></td>
            </tr>
            <tr>
                <td class="label">Responsável</td>
                <td><?= htmlspecialchars($rat['cliente_responsavel']) ?></td>
                <td class="label">Cidade/UF</td>
                <td><?= htmlspecialchars($rat['cidade']) ?>/<?= htmlspecialchars($rat['estado']) ?></td>
            </tr>
            <tr>
                <td class="label">Endereço</td>
                <td colspan="3"><?= htmlspecialchars($rat['endereco']) ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Dados do Equipamento -->
    <div class="section">
        <div class="section-title">DADOS DO EQUIPAMENTO</div>
        <table class="info-table">
            <tr>
                <td class="label">Equipamento</td>
                <td><?= htmlspecialchars($rat['equipamento']) ?></td>
                <td class="label">Modelo</td>
                <td><?= htmlspecialchars($rat['modelo_maquina']) ?></td>
            </tr>
            <tr>
                <td class="label">Matrícula/Série</td>
                <td><?= htmlspecialchars($rat['matricula']) ?></td>
                <td class="label">Garantia</td>
                <td><?= htmlspecialchars($rat['garantia']) ?></td>
            </tr>
            <tr>
                <td class="label">Técnico</td>
                <td colspan="3"><?= htmlspecialchars($rat['tecnico_nome']) ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Serviço -->
    <?php 
    $tipos_pdf = array_map('trim', explode(',', $rat['tipo_servico'] ?? ''));
    $esconder_defeito_pdf = in_array('Treinamento', $tipos_pdf) && in_array('Instalação', $tipos_pdf);
    if (!$esconder_defeito_pdf): 
    ?>
    <div class="section">
        <div class="section-title">DEFEITO CONSTATADO</div>
        <div class="text-box"><?= nl2br(htmlspecialchars($rat['defeito_constatado'])) ?></div>
    </div>
    <?php endif; ?>
    
    <div class="section">
        <div class="section-title">TRABALHO EXECUTADO</div>
        <div class="text-box"><?= nl2br(htmlspecialchars($rat['trabalho_executado'])) ?></div>
    </div>
    
    <?php if (!empty($orcamento)): ?>
    <!-- Orçamento -->
    <div class="section">
        <div class="section-title">ORÇAMENTO</div>
        <table class="turnos-table">
            <tr>
                <th>Item</th>
                <th>Quantidade</th>
            </tr>
            <?php foreach ($orcamento as $item): ?>
            <tr>
                <td style="text-align: left; padding-left: 12px;"><?= htmlspecialchars($item['nome'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['quantidade'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Turnos -->
    <?php if (!empty($turnos)): ?>
    <div class="section">
        <div class="section-title">REGISTRO DE TURNOS</div>
        <table class="turnos-table">
            <tr>
                <th>Data</th>
                <th>Manhã Início</th>
                <th>Manhã Fim</th>
                <th>Tarde Início</th>
                <th>Tarde Fim</th>
            </tr>
            <?php foreach ($turnos as $turno): ?>
            <tr>
                <td><?= !empty($turno['data']) ? date('d/m/Y', strtotime($turno['data'])) : '-' ?></td>
                <td><?= $turno['manha_inicio'] ?? '-' ?></td>
                <td><?= $turno['manha_fim'] ?? '-' ?></td>
                <td><?= $turno['tarde_inicio'] ?? '-' ?></td>
                <td><?= $turno['tarde_fim'] ?? '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top: 10px; font-weight: bold;">Total dos Turnos: <?= htmlspecialchars($rat['total_turnos']) ?> | Horas de Serviço: <?= htmlspecialchars($rat['horas_servico']) ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Horas Viajadas -->
    <?php if (!empty($horasViagem)): ?>
    <div class="section">
        <div class="section-title">HORAS VIAJADAS</div>
        <table class="turnos-table">
            <tr>
                <th>Data</th>
                <th>Ida</th>
                <th>Volta</th>
            </tr>
            <?php foreach ($horasViagem as $viagem): ?>
            <tr>
                <td><?= !empty($viagem['data']) ? date('d/m/Y', strtotime($viagem['data'])) : '-' ?></td>
                <td><?= $viagem['ida'] ?? '-' ?></td>
                <td><?= $viagem['volta'] ?? '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top: 10px; font-weight: bold;">Total Horas Viajadas: <?= htmlspecialchars($rat['total_horas_viajadas']) ?></p>
    </div>
    <?php endif; ?>
    
    <!-- KMs Rodados -->
    <?php if (!empty($kms)): ?>
    <div class="section">
        <div class="section-title">KMS RODADOS</div>
        <table class="turnos-table">
            <tr>
                <th>Data</th>
                <th>Ida</th>
                <th>Volta</th>
                <th>Total</th>
            </tr>
            <?php foreach ($kms as $km): ?>
            <tr>
                <td><?= !empty($km['data']) ? date('d/m/Y', strtotime($km['data'])) : '-' ?></td>
                <td><?= $km['ida'] ?? '-' ?></td>
                <td><?= $km['volta'] ?? '-' ?></td>
                <td><?= $km['total'] ?? '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top: 10px; font-weight: bold;">Total KMs Rodados: <?= htmlspecialchars($rat['total_kms']) ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Despesas (só mostra se total > 0) -->
    <?php 
    $total_desp = floatval($rat['total_adicionais'] ?? 0);
    if ($total_desp > 0): 
    ?>
    <div class="section">
        <div class="section-title">ADICIONAIS</div>
            <?php if (floatval($rat['pedagio'] ?? 0) > 0 && intval($rat['pedagio_qtd'] ?? 0) >= 1): ?>
            <div class="despesa-item">
                <div class="label">Pedágio<?php if (intval($rat['pedagio_qtd'] ?? 0) > 1) echo ' (x' . intval($rat['pedagio_qtd']) . ')'; ?></div>
                <div class="value">R$ <?= number_format(floatval($rat['pedagio']) * max(intval($rat['pedagio_qtd'] ?? 1), 1), 2, ',', '.') ?></div>
            </div>
            <?php endif; ?>
            <?php if (floatval($rat['hospedagem'] ?? 0) > 0 && intval($rat['hospedagem_dias'] ?? 0) >= 1): ?>
            <div class="despesa-item">
                <div class="label">Hospedagem<?php if (intval($rat['hospedagem_dias'] ?? 0) > 1) echo ' (x' . intval($rat['hospedagem_dias']) . ' dias)'; ?></div>
                <div class="value">R$ <?= number_format(floatval($rat['hospedagem']) * max(intval($rat['hospedagem_dias'] ?? 1), 1), 2, ',', '.') ?></div>
            </div>
            <?php endif; ?>
            <?php if (floatval($rat['alimentacao'] ?? 0) > 0 && intval($rat['alimentacao_qtd'] ?? 0) >= 1): ?>
            <div class="despesa-item">
                <div class="label">Alimentação<?php if (intval($rat['alimentacao_qtd'] ?? 0) > 1) echo ' (x' . intval($rat['alimentacao_qtd']) . ')'; ?></div>
                <div class="value">R$ <?= number_format(floatval($rat['alimentacao']) * max(intval($rat['alimentacao_qtd'] ?? 1), 1), 2, ',', '.') ?></div>
            </div>
            <?php endif; ?>
            <?php if (floatval($rat['outros_despesas'] ?? 0) > 0 && intval($rat['outros_qtd'] ?? 0) >= 1): ?>
            <div class="despesa-item">
                <div class="label"><?= htmlspecialchars($rat['outros_despesas_desc'] ?: 'Outros') ?><?php if (intval($rat['outros_qtd'] ?? 0) > 1) echo ' (x' . intval($rat['outros_qtd']) . ')'; ?></div>
                <div class="value">R$ <?= number_format(floatval($rat['outros_despesas']) * max(intval($rat['outros_qtd'] ?? 1), 1), 2, ',', '.') ?></div>
            </div>
            <?php endif; ?>
        </div>
        <p style="margin-top: 15px; font-weight: bold; text-align: right; font-size: 14pt;">
            TOTAL DESPESAS: R$ <?= number_format($total_desp, 2, ',', '.') ?>
        </p>
    </div>
    <?php endif; ?>
    
    <?php foreach ($tecnicos_adicionais as $tec): ?>
    <div style="page-break-before: always;"></div>
    <div class="section">
        <div class="section-title">TÉCNICO ADICIONAL: <?= htmlspecialchars(strtoupper($tec['nome'] ?? '')) ?></div>
        
        <?php if (!empty($tec['turnos'])): ?>
        <h4 style="margin: 10px 0 5px; font-size: 10pt;">REGISTRO DE TURNOS</h4>
        <table class="turnos-table">
            <tr><th>Data</th><th>Manhã Início</th><th>Manhã Fim</th><th>Tarde Início</th><th>Tarde Fim</th></tr>
            <?php foreach ($tec['turnos'] as $turno): ?>
            <tr>
                <td><?= !empty($turno['dia']) ? date('d/m/Y', strtotime($turno['dia'])) : '-' ?></td>
                <td><?= $turno['manha']['inicio'] ?? '-' ?></td>
                <td><?= $turno['manha']['fim'] ?? '-' ?></td>
                <td><?= $turno['tarde']['inicio'] ?? '-' ?></td>
                <td><?= $turno['tarde']['fim'] ?? '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php if (!empty($tec['horas_viajadas'])): ?>
        <h4 style="margin: 10px 0 5px; font-size: 10pt;">HORAS VIAJADAS</h4>
        <table class="turnos-table">
            <tr><th>Data</th><th>Ida</th><th>Volta</th></tr>
            <?php foreach ($tec['horas_viajadas'] as $viagem): ?>
            <tr>
                <td><?= !empty($viagem['data']) ? date('d/m/Y', strtotime($viagem['data'])) : '-' ?></td>
                <td><?= $viagem['ida'] ?? '-' ?></td>
                <td><?= $viagem['volta'] ?? '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php if (!empty($tec['kms_rodados'])): ?>
        <h4 style="margin: 10px 0 5px; font-size: 10pt;">KMS RODADOS</h4>
        <table class="turnos-table">
            <tr><th>Data</th><th>Ida</th><th>Volta</th><th>Total</th></tr>
            <?php foreach ($tec['kms_rodados'] as $km): ?>
            <tr>
                <td><?= !empty($km['data']) ? date('d/m/Y', strtotime($km['data'])) : '-' ?></td>
                <td><?= $km['ida'] ?? '-' ?></td>
                <td><?= $km['volta'] ?? '-' ?></td>
                <td><?= $km['total'] ?? '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <?php 
        $ped = floatval($tec['despesas']['pedagio'] ?? 0);
        $ped_qtd = intval($tec['despesas']['pedagio_qtd'] ?? 0);
        $hosp = floatval($tec['despesas']['hospedagem'] ?? 0);
        $hosp_qtd = intval($tec['despesas']['hospedagem_dias'] ?? 0);
        $ali = floatval($tec['despesas']['alimentacao'] ?? 0);
        $ali_qtd = intval($tec['despesas']['alimentacao_qtd'] ?? 0);
        $out = floatval($tec['despesas']['outros_despesas'] ?? 0);
        $out_qtd = intval($tec['despesas']['outros_qtd'] ?? 0);
        
        if (($ped > 0 && $ped_qtd >= 1) || ($hosp > 0 && $hosp_qtd >= 1) || ($ali > 0 && $ali_qtd >= 1) || ($out > 0 && $out_qtd >= 1)):
        ?>
        <h4 style="margin: 10px 0 5px; font-size: 10pt;">ADICIONAIS (Despesas Extras)</h4>
        <div class="despesas-grid">
            <?php if ($ped > 0 && $ped_qtd >= 1): ?>
            <div class="despesa-item"><div class="label">Pedágio</div><div class="value">R$ <?= number_format($ped, 2, ',', '.') ?></div></div>
            <?php endif; ?>
            <?php if ($hosp > 0 && $hosp_qtd >= 1): ?>
            <div class="despesa-item"><div class="label">Hospedagem</div><div class="value">R$ <?= number_format($hosp, 2, ',', '.') ?></div></div>
            <?php endif; ?>
            <?php if ($ali > 0 && $ali_qtd >= 1): ?>
            <div class="despesa-item"><div class="label">Alimentação</div><div class="value">R$ <?= number_format($ali, 2, ',', '.') ?></div></div>
            <?php endif; ?>
            <?php if ($out > 0 && $out_qtd >= 1): ?>
            <div class="despesa-item"><div class="label">Outros (<?= htmlspecialchars($tec['despesas']['outros_despesas_desc'] ?? '') ?>)</div><div class="value">R$ <?= number_format($out, 2, ',', '.') ?></div></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <!-- Assinaturas -->
    <div style="page-break-inside: avoid;">
        <div class="section-title" style="margin-top: 20px;">ASSINATURAS</div>
        <div class="assinaturas-grid">
            <div class="assinatura-box">
                <?php if ($rat['assinatura_cliente']): ?>
                    <img src="<?= $rat['assinatura_cliente'] ?>" alt="Assinatura Cliente">
                <?php else: ?>
                    <div style="height: 80px;"></div>
                <?php endif; ?>
                <div class="assinatura-line">
                    <strong>Assinatura do Cliente</strong><br>
                    <?php if (!empty($rat['nome_assinatura'])): ?>
                        <?= htmlspecialchars($rat['nome_assinatura']) ?><br>
                        <span style="font-size: 9pt; color: #555;">
                            Cargo: <?= htmlspecialchars($rat['cargo_assinatura']) ?> | CPF: <?= htmlspecialchars($rat['cpf_assinatura']) ?>
                        </span>
                    <?php else: ?>
                        <?= htmlspecialchars($rat['cliente_responsavel']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="assinatura-box">
                <?php if ($rat['assinatura_tecnico']): ?>
                    <img src="<?= $rat['assinatura_tecnico'] ?>" alt="Assinatura Técnico">
                <?php else: ?>
                    <div style="height: 80px;"></div>
                <?php endif; ?>
                <div class="assinatura-line">
                    <strong>Assinatura do Técnico</strong><br>
                    <?= htmlspecialchars($rat['tecnico_nome']) ?>
                </div>
            </div>

            <?php foreach ($tecnicos_adicionais as $tec): ?>
            <div class="assinatura-box">
                <?php if (!empty($tec['assinatura'])): ?>
                    <img src="<?= htmlspecialchars($tec['assinatura']) ?>" alt="Assinatura Técnico Adicional">
                <?php else: ?>
                    <div style="height: 80px;"></div>
                <?php endif; ?>
                <div class="assinatura-line">
                    <strong>Assinatura do Técnico Adicional</strong><br>
                    <?= htmlspecialchars($tec['nome'] ?? '') ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <strong>Madetech & Madeparts</strong> - Assistência Técnica<br>
        www.madetech.com.br | Documento gerado em <?= date('d/m/Y H:i') ?>
    </div>
</body>
</html>
