<?php
/**
 * Formulário Público do RAT - Madetech
 * Acesso via token único (sem login)
 * Campos IDÊNTICOS ao JotForm original
 */
require_once 'api/config.php';

$db = getDB();

$token = $_GET['token'] ?? '';
$sucesso = isset($_GET['sucesso']);

if (!$token && !$sucesso) {
    die('<h1>Acesso Inválido</h1><p>Token não fornecido.</p>');
}

if ($token) {
    $stmt = $db->prepare("SELECT * FROM rats WHERE token = ?");
    $stmt->execute([$token]);
    $rat = $stmt->fetch();
    
    if (!$rat) {
        die('<h1>RAT Não Encontrado</h1><p>O link utilizado é inválido ou expirou.</p>');
    }
    
    if ($rat['status'] === 'preenchido') {
        $sucesso = true;
    }
}

// Processar envio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$sucesso) {
    try {
        // Dados do cliente
        $cliente_empresa = $_POST['cliente_empresa'] ?? '';
        $cliente_responsavel = $_POST['cliente_responsavel'] ?? '';
        $endereco = $_POST['endereco'] ?? '';
        $cidade = $_POST['cidade'] ?? '';
        $estado = $_POST['estado'] ?? '';
        
        // Dados do equipamento
        $modelo_maquina = $_POST['modelo_maquina'] ?? '';
        $matricula = $_POST['matricula'] ?? '';
        $garantia = $_POST['garantia'] ?? '';
        
        // Dados do serviço
        $defeito_constatado = $_POST['defeito_constatado'] ?? '';
        $trabalho_executado = $_POST['trabalho_executado'] ?? '';
        $horas_servico = $_POST['horas_servico'] ?? '';
        
        // Turnos (5 dias fixos como no JotForm)
        $turnos = [];
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($_POST["turno{$i}_data"])) {
                $turnos[] = [
                    'data' => $_POST["turno{$i}_data"],
                    'manha_inicio' => $_POST["turno{$i}_manha_inicio"] ?? '',
                    'manha_fim' => $_POST["turno{$i}_manha_fim"] ?? '',
                    'tarde_inicio' => $_POST["turno{$i}_tarde_inicio"] ?? '',
                    'tarde_fim' => $_POST["turno{$i}_tarde_fim"] ?? '',
                ];
            }
        }
        $turnos_json = json_encode($turnos);
        $total_turnos = $_POST['total_turnos'] ?? '';
        
        // Horas viajadas (com ida e volta)
        $horas_viajadas = [];
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($_POST["viagem{$i}_data"])) {
                $horas_viajadas[] = [
                    'data' => $_POST["viagem{$i}_data"],
                    'ida' => $_POST["viagem{$i}_ida"] ?? '',
                    'volta' => $_POST["viagem{$i}_volta"] ?? '',
                ];
            }
        }
        $horas_viajadas_json = json_encode($horas_viajadas);
        $total_horas_viajadas = $_POST['total_horas_viajadas'] ?? '';
        
        // KMs rodados (com ida, volta e total)
        $kms_rodados = [];
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($_POST["km{$i}_data"])) {
                $kms_rodados[] = [
                    'data' => $_POST["km{$i}_data"],
                    'ida' => $_POST["km{$i}_ida"] ?? '',
                    'volta' => $_POST["km{$i}_volta"] ?? '',
                    'total' => $_POST["km{$i}_total"] ?? '',
                ];
            }
        }
        $kms_rodados_json = json_encode($kms_rodados);
        $total_kms = $_POST['total_kms'] ?? '';
        
        // Valores adicionais
        $pedagio = $_POST['pedagio'] ?? '';
        $hospedagem = $_POST['hospedagem'] ?? '';
        $alimentacao = $_POST['alimentacao'] ?? '';
        $total_adicionais = $_POST['total_adicionais'] ?? '';
        
        // Assinaturas
        $assinatura_cliente = $_POST['assinatura_cliente'] ?? '';
        $assinatura_tecnico = $_POST['assinatura_tecnico'] ?? '';
        $nome_assinatura = trim($_POST['nome_assinatura'] ?? '');
        $cargo_assinatura = trim($_POST['cargo_assinatura'] ?? '');
        $cpf_assinatura = trim($_POST['cpf_assinatura'] ?? '');
        $aceite_cliente = isset($_POST['aceite_cliente']) ? 1 : 0;
        $aceite_tecnico = isset($_POST['aceite_tecnico']) ? 1 : 0;
        
        // Validações
        if (empty($cliente_empresa) || empty($cliente_responsavel)) {
            throw new Exception('Os dados do cliente são obrigatórios.');
        }
        if (empty($defeito_constatado) || empty($trabalho_executado)) {
            throw new Exception('O defeito e trabalho executado são obrigatórios.');
        }
        if (empty($assinatura_cliente) || empty($assinatura_tecnico)) {
            throw new Exception('As assinaturas são obrigatórias.');
        }
        
        // Atualizar RAT
        $sql = "UPDATE rats SET
            cliente_empresa = ?,
            cliente_responsavel = ?,
            endereco = ?,
            cidade = ?,
            estado = ?,
            modelo_maquina = ?,
            matricula = ?,
            garantia = ?,
            defeito_constatado = ?,
            trabalho_executado = ?,
            horas_servico = ?,
            turnos_json = ?,
            total_turnos = ?,
            horas_viajadas_json = ?,
            total_horas_viajadas = ?,
            kms_rodados_json = ?,
            total_kms = ?,
            pedagio = ?,
            hospedagem = ?,
            alimentacao = ?,
            total_adicionais = ?,
            assinatura_cliente = ?,
            assinatura_tecnico = ?,
            aceite_cliente = ?,
            aceite_tecnico = ?,
            nome_assinatura = ?,
            cargo_assinatura = ?,
            cpf_assinatura = ?,
            status = 'preenchido',
            data_preenchimento = datetime('now')
            WHERE token = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $cliente_empresa, $cliente_responsavel, $endereco, $cidade, $estado,
            $modelo_maquina, $matricula, $garantia,
            $defeito_constatado, $trabalho_executado, $horas_servico,
            $turnos_json, $total_turnos,
            $horas_viajadas_json, $total_horas_viajadas,
            $kms_rodados_json, $total_kms,
            $pedagio, $hospedagem, $alimentacao, $total_adicionais,
            $assinatura_cliente, $assinatura_tecnico, $aceite_cliente, $aceite_tecnico,
            $nome_assinatura, $cargo_assinatura, $cpf_assinatura,
            $token
        ]);
        
        // Guardar dados para página de sucesso
        $numero_rat = $rat['numero'];
        $tecnico_rat = $rat['tecnico_nome'];
        $sucesso = true;
        
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $sucesso ? 'RAT Enviado!' : 'Relatório de Assistência Técnica - ' . (exibirNumeroRAT($rat['numero'] ?? '', $rat['numero_sequencial'] ?? null)) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #034c8c;
            --primary-light: #0466c8;
            --secondary: #f58220;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-100);
            color: var(--gray-900);
            line-height: 1.6;
        }
        
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 16px;
        }
        
        .header img { height: 60px; margin-bottom: 15px; }
        .header h1 { font-size: 1.6rem; margin-bottom: 5px; }
        .header .subtitle { font-size: 1rem; opacity: 0.9; }
        .header .rat-number {
            font-size: 1.1rem;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 10px;
        }
        
        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section h2 i { color: var(--secondary); }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .form-group { margin-bottom: 15px; }
        .form-group.full-width { grid-column: 1 / -1; }
        
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        
        .form-group label .required { color: var(--danger); }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(3,76,140,0.1);
        }
        
        .form-group textarea { min-height: 120px; resize: vertical; }
        
        /* Tabela de Turnos */
        .turno-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .turno-table th {
            background: var(--gray-100);
            padding: 10px;
            text-align: center;
            font-weight: 600;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }
        
        .turno-table td {
            padding: 8px;
            border: 1px solid var(--gray-200);
            text-align: center;
        }
        
        .turno-table input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            text-align: center;
        }
        
        .turno-table .time-group {
            display: flex;
            align-items: center;
            gap: 5px;
            justify-content: center;
        }
        
        .turno-table .time-group input { width: 80px; }
        .turno-table .time-group span { color: var(--gray-500); font-size: 0.8rem; }
        
        /* Tabela de Viagem */
        .viagem-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .viagem-table th {
            background: var(--gray-100);
            padding: 10px;
            text-align: center;
            font-weight: 600;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }
        
        .viagem-table td {
            padding: 8px;
            border: 1px solid var(--gray-200);
        }
        
        .viagem-table input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            text-align: center;
        }
        
        .viagem-table .row-num {
            background: var(--gray-50);
            font-weight: 500;
            width: 40px;
            text-align: center;
        }
        
        /* Tabela de Adicionais */
        .adicionais-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .adicionais-table th {
            background: var(--gray-100);
            padding: 12px;
            text-align: center;
            font-weight: 600;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }
        
        .adicionais-table td {
            padding: 10px;
            border: 1px solid var(--gray-200);
        }
        
        .adicionais-table input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            text-align: right;
        }
        
        .adicionais-table .total-cell {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        .adicionais-table .total-cell input {
            background: rgba(255,255,255,0.9);
            font-weight: 600;
        }
        
        /* Total Field */
        .total-field {
            background: var(--gray-50);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .total-field label { font-weight: 600; color: var(--primary); }
        
        .total-field input {
            background: white;
            font-weight: 600;
            color: var(--primary);
        }
        
        /* Assinatura Canvas */
        .signature-container {
            border: 2px dashed var(--gray-300);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background: white;
            margin-bottom: 15px;
        }
        
        .signature-container canvas {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            height: 150px;
            touch-action: none;
            cursor: crosshair;
            background: white;
        }
        
        .signature-actions { margin-top: 10px; }
        
        .signature-actions button {
            background: var(--gray-200);
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .aceite-box {
            background: var(--gray-50);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            margin-bottom: 15px;
        }
        
        .aceite-box p {
            font-size: 0.9rem;
            color: var(--gray-700);
            margin-bottom: 10px;
        }
        
        .aceite-check {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .aceite-check input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .aceite-check label { cursor: pointer; font-weight: 500; }
        
        /* Submit Button */
        .submit-section { text-align: center; padding: 30px; }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
            border: none;
            padding: 18px 60px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16,185,129,0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert.error {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }
        
        /* Success Page */
        .success-page { text-align: center; padding: 60px 20px; }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--success);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 30px;
        }
        
        .success-page h1 { color: var(--success); font-size: 2rem; margin-bottom: 15px; }
        .success-page p { color: var(--gray-500); font-size: 1.1rem; }
        
        .btn-whatsapp-success {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #25d366;
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-whatsapp-success:hover {
            background: #1da851;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(37, 211, 102, 0.3);
        }
        
        @media (max-width: 768px) {
            .turno-table, .viagem-table { font-size: 0.8rem; }
            .turno-table input, .viagem-table input { padding: 6px; }
            .turno-table .time-group input { width: 60px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($sucesso): 
            $numero_exibir = $numero_rat ?? ($rat['numero'] ?? 'RAT');
            $tecnico_exibir = $tecnico_rat ?? ($rat['tecnico_nome'] ?? 'Técnico');
            $texto_whatsapp = "✅ RAT Enviado!\n\nNúmero: {$numero_exibir}\nTécnico: {$tecnico_exibir}\n\nO relatório foi preenchido e está pronto para visualização.";
            $whatsapp_link = formatWhatsAppLink(WHATSAPP_NOTIFY, $texto_whatsapp);
        ?>
            <div class="section success-page">
                <div class="success-icon"><i class="fas fa-check"></i></div>
                <h1>RAT Enviado com Sucesso!</h1>
                <p>Seu relatório de assistência técnica foi enviado e será processado pela equipe Madetech.</p>
                
                <div style="margin-top: 30px;">
                    <a href="<?= $whatsapp_link ?>" target="_blank" class="btn-whatsapp-success">
                        <i class="fab fa-whatsapp"></i> Avisar que enviei
                    </a>
                </div>
                <p style="margin-top: 15px; font-size: 0.9rem; color: #666;">Clique acima para notificar a equipe via WhatsApp</p>
            </div>
        <?php else: ?>
            <div class="header">
                <div style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 10px;">
                    <img src="https://madetech.com.br/wp-content/uploads/2026/05/Logo-Madetech-Final.webp" alt="Madetech" style="height: 60px;">
                    <img src="https://madetech.com.br/wp-content/uploads/2026/07/Logo-Madeparts-Final.png" alt="Madeparts" style="height: 60px;">
                </div>
                <div class="subtitle">MADETECH E MADEPARTS</div>
                <h1>Relatório de Assistência Técnica</h1>
                <span class="rat-number"><?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?></span>
            </div>
            
            <?php if (isset($erro)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="formRAT">
                <!-- DADOS DO CLIENTE -->
                <div class="section">
                    <h2><i class="fas fa-building"></i> Dados do Cliente</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Cliente (Empresa) <span class="required">*</span></label>
                            <input type="text" name="cliente_empresa" value="<?= htmlspecialchars($rat['cliente_empresa'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Solicitante (Responsável) <span class="required">*</span></label>
                            <input type="text" name="cliente_responsavel" value="<?= htmlspecialchars($rat['cliente_responsavel'] ?? '') ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Endereço <span class="required">*</span></label>
                            <input type="text" name="endereco" value="<?= htmlspecialchars($rat['endereco'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Cidade</label>
                            <input type="text" name="cidade" value="<?= htmlspecialchars($rat['cidade'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="estado">
                                <option value="">Selecione...</option>
                                <?php
                                $estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                                foreach ($estados as $uf) {
                                    $sel = ($rat['estado'] ?? '') === $uf ? 'selected' : '';
                                    echo "<option value=\"$uf\" $sel>$uf</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- DADOS DO EQUIPAMENTO -->
                <div class="section">
                    <h2><i class="fas fa-cog"></i> Dados do Equipamento</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Técnico Responsável <span class="required">*</span></label>
                            <input type="text" value="<?= htmlspecialchars($rat['tecnico_nome'] ?? '') ?>" readonly style="background: var(--gray-100);">
                        </div>
                        <div class="form-group">
                            <label>Equipamento <span class="required">*</span></label>
                            <input type="text" value="<?= htmlspecialchars($rat['equipamento'] ?? '') ?>" readonly style="background: var(--gray-100);">
                        </div>
                        <div class="form-group">
                            <label>Modelo da máquina <span class="required">*</span></label>
                            <input type="text" name="modelo_maquina" value="<?= htmlspecialchars($rat['modelo_maquina'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Matrícula <span class="required">*</span></label>
                            <input type="text" name="matricula" value="<?= htmlspecialchars($rat['matricula'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Máquina na garantia? <span class="required">*</span></label>
                            <select name="garantia" required>
                                <option value="">Selecione...</option>
                                <option value="Sim" <?= ($rat['garantia'] ?? '') === 'Sim' ? 'selected' : '' ?>>Sim</option>
                                <option value="Não" <?= ($rat['garantia'] ?? '') === 'Não' ? 'selected' : '' ?>>Não</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- SERVIÇO -->
                <div class="section">
                    <h2><i class="fas fa-tools"></i> Serviço Executado</h2>
                    <?php 
                    $tipos = array_map('trim', explode(',', $rat['tipo_servico'] ?? ''));
                    $esconder_defeito = in_array('Treinamento', $tipos) && in_array('Instalação', $tipos);
                    if (!$esconder_defeito): 
                    ?>
                    <div class="form-group">
                        <label>Defeito constatado <span class="required">*</span></label>
                        <textarea name="defeito_constatado" required placeholder="Descreva o defeito encontrado..."><?= htmlspecialchars($rat['defeito_constatado'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Trabalho executado <span class="required">*</span></label>
                        <textarea name="trabalho_executado" required placeholder="Descreva os serviços realizados..."><?= htmlspecialchars($rat['trabalho_executado'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group" style="max-width: 300px;">
                        <label>Horas de serviço</label>
                        <input type="text" name="horas_servico" placeholder="Ex: 8h30">
                    </div>
                </div>
                
                <!-- TURNOS -->
                <div class="section">
                    <h2><i class="fas fa-clock"></i> Registro de Turnos</h2>
                    <div style="overflow-x: auto;">
                        <table class="turno-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Data <span class="required">*</span></th>
                                    <th colspan="2">Turno Manhã <span class="required">*</span></th>
                                    <th colspan="2">Turno Tarde <span class="required">*</span></th>
                                </tr>
                                <tr>
                                    <th>DD-MM-YYYY</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <tr>
                                    <td><input type="date" name="turno<?= $i ?>_data" <?= $i === 1 ? 'required' : '' ?>></td>
                                    <td><input type="time" name="turno<?= $i ?>_manha_inicio" <?= $i === 1 ? 'required' : '' ?>></td>
                                    <td><input type="time" name="turno<?= $i ?>_manha_fim" <?= $i === 1 ? 'required' : '' ?>></td>
                                    <td><input type="time" name="turno<?= $i ?>_tarde_inicio" <?= $i === 1 ? 'required' : '' ?>></td>
                                    <td><input type="time" name="turno<?= $i ?>_tarde_fim" <?= $i === 1 ? 'required' : '' ?>></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="total-field">
                        <label>Total dos turnos <span class="required">*</span></label>
                        <input type="text" name="total_turnos" placeholder="Ex: 40h00" required style="max-width: 200px; margin-top: 8px;">
                    </div>
                </div>
                
                <!-- HORAS VIAJADAS -->
                <div class="section">
                    <h2><i class="fas fa-road"></i> Horas Viajadas <span class="required">*</span></h2>
                    <div style="overflow-x: auto;">
                        <table class="viagem-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Data</th>
                                    <th>Ida (formato xxhyy)</th>
                                    <th>Volta (formato xxhyy)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <tr>
                                    <td class="row-num"><?= $i ?>.</td>
                                    <td><input type="date" name="viagem<?= $i ?>_data"></td>
                                    <td><input type="text" name="viagem<?= $i ?>_ida" placeholder="00h00"></td>
                                    <td><input type="text" name="viagem<?= $i ?>_volta" placeholder="00h00"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="total-field">
                        <label>Total horas viajadas (favor calcular a soma e inserir aqui) <span class="required">*</span></label>
                        <input type="text" name="total_horas_viajadas" placeholder="Ex: 12h30" required style="max-width: 200px; margin-top: 8px;">
                    </div>
                </div>
                
                <!-- KMS RODADOS -->
                <div class="section">
                    <h2><i class="fas fa-car"></i> KMs Rodados <span class="required">*</span></h2>
                    <div style="overflow-x: auto;">
                        <table class="viagem-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Data</th>
                                    <th>Ida</th>
                                    <th>Volta</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <tr>
                                    <td class="row-num"><?= $i ?>.</td>
                                    <td><input type="date" name="km<?= $i ?>_data"></td>
                                    <td><input type="text" name="km<?= $i ?>_ida" placeholder="0"></td>
                                    <td><input type="text" name="km<?= $i ?>_volta" placeholder="0"></td>
                                    <td><input type="text" name="km<?= $i ?>_total" placeholder="0"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="total-field">
                        <label>Total KMs rodados <span class="required">*</span></label>
                        <input type="text" name="total_kms" placeholder="0" required style="max-width: 200px; margin-top: 8px;">
                    </div>
                </div>
                
                <!-- ADICIONAIS -->
                <div class="section">
                    <h2><i class="fas fa-receipt"></i> Adicionais (inserir valores e somatória total)</h2>
                    <div style="overflow-x: auto;">
                        <table class="adicionais-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Pedágio</th>
                                    <th>Hospedagem</th>
                                    <th>Alimentação</th>
                                    <th class="total-cell">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="row-num" style="background: var(--gray-50); font-weight: 500;">R$</td>
                                    <td><input type="text" name="pedagio" placeholder="0,00" oninput="calcularTotalAdicionais()"></td>
                                    <td><input type="text" name="hospedagem" placeholder="0,00" oninput="calcularTotalAdicionais()"></td>
                                    <td><input type="text" name="alimentacao" placeholder="0,00" oninput="calcularTotalAdicionais()"></td>
                                    <td class="total-cell"><input type="text" name="total_adicionais" id="totalAdicionais" placeholder="0,00" readonly></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ASSINATURAS -->
                <div class="section">
                    <h2><i class="fas fa-signature"></i> Assinaturas</h2>
                    
                    <div class="form-grid">
                        <!-- Assinatura Cliente -->
                        <div class="form-group">
                            <div class="aceite-box">
                                <p>Atesto que os serviços foram prestados pelo técnico responsável em nome das empresas Madetech e Madeparts.</p>
                            </div>
                            <label>Assinatura do Cliente <span class="required">*</span></label>
                            <div class="signature-container">
                                <canvas id="canvasCliente"></canvas>
                                <input type="hidden" name="assinatura_cliente" id="assinaturaClienteInput">
                                <div class="signature-actions">
                                    <button type="button" onclick="limparAssinatura('Cliente')">
                                        <i class="fas fa-eraser"></i> Limpar
                                    </button>
                                </div>
                            </div>
                            <div style="margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div style="grid-column: span 2;">
                                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 4px; display: block;">Nome do Responsável</label>
                                    <input type="text" name="nome_assinatura" value="<?= htmlspecialchars($rat['nome_assinatura'] ?? $rat['cliente_responsavel'] ?? '') ?>" placeholder="Nome de quem está assinando" style="width: 100%; padding: 8px 12px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 14px;">
                                </div>
                                <div>
                                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 4px; display: block;">Cargo</label>
                                    <input type="text" name="cargo_assinatura" value="<?= htmlspecialchars($rat['cargo_assinatura'] ?? '') ?>" placeholder="Ex: Gerente" style="width: 100%; padding: 8px 12px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 14px;">
                                </div>
                                <div>
                                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 4px; display: block;">CPF</label>
                                    <input type="text" name="cpf_assinatura" value="<?= htmlspecialchars($rat['cpf_assinatura'] ?? '') ?>" placeholder="000.000.000-00" oninput="this.value = this.value.replace(/\D/g, '').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')" maxlength="14" style="width: 100%; padding: 8px 12px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 14px;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assinatura Técnico -->
                        <div class="form-group">
                            <div class="aceite-box">
                                <p>Atesto que os serviços foram realizados em nome das empresas Madetech e Madeparts conforme solicitação do cliente.</p>
                            </div>
                            <label>Assinatura do Técnico <span class="required">*</span></label>
                            <div class="signature-container">
                                <canvas id="canvasTecnico"></canvas>
                                <input type="hidden" name="assinatura_tecnico" id="assinaturaTecnicoInput">
                                <div class="signature-actions">
                                    <button type="button" onclick="limparAssinatura('Tecnico')">
                                        <i class="fas fa-eraser"></i> Limpar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SUBMIT -->
                <div class="submit-section">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        Enviar
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // ============ ASSINATURAS CANVAS ============
        function setupSignature(canvasId, inputId) {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');
            const input = document.getElementById(inputId);
            
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width;
            canvas.height = rect.height;
            
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;
            
            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                if (e.touches) {
                    return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
                }
                return { x: e.clientX - rect.left, y: e.clientY - rect.top };
            }
            
            function startDrawing(e) {
                isDrawing = true;
                const pos = getPos(e);
                lastX = pos.x;
                lastY = pos.y;
            }
            
            function draw(e) {
                if (!isDrawing) return;
                e.preventDefault();
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                lastX = pos.x;
                lastY = pos.y;
            }
            
            function stopDrawing() {
                if (isDrawing) {
                    isDrawing = false;
                    input.value = canvas.toDataURL('image/png');
                }
            }
            
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);
            canvas.addEventListener('touchstart', startDrawing);
            canvas.addEventListener('touchmove', draw);
            canvas.addEventListener('touchend', stopDrawing);
            
            return function clear() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                input.value = '';
            };
        }
        
        let limparCliente, limparTecnico;
        window.addEventListener('load', function() {
            limparCliente = setupSignature('canvasCliente', 'assinaturaClienteInput');
            limparTecnico = setupSignature('canvasTecnico', 'assinaturaTecnicoInput');
        });
        
        function limparAssinatura(tipo) {
            if (tipo === 'Cliente') limparCliente();
            if (tipo === 'Tecnico') limparTecnico();
        }
        
        // ============ CÁLCULO ADICIONAIS ============
        function calcularTotalAdicionais() {
            const pedagio = parseFloat(document.querySelector('[name="pedagio"]').value.replace(',', '.')) || 0;
            const hospedagem = parseFloat(document.querySelector('[name="hospedagem"]').value.replace(',', '.')) || 0;
            const alimentacao = parseFloat(document.querySelector('[name="alimentacao"]').value.replace(',', '.')) || 0;
            const total = pedagio + hospedagem + alimentacao;
            document.getElementById('totalAdicionais').value = total.toFixed(2).replace('.', ',');
        }
        
        // ============ VALIDAÇÃO ============
        document.getElementById('formRAT').addEventListener('submit', function(e) {
            const assinaturaCliente = document.getElementById('assinaturaClienteInput').value;
            const assinaturaTecnico = document.getElementById('assinaturaTecnicoInput').value;
            
            if (!assinaturaCliente || !assinaturaTecnico) {
                e.preventDefault();
                alert('Por favor, assine os campos de assinatura.');
                return;
            }
        });
    </script>
</body>
</html>
