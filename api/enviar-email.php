<?php
/**
 * Enviar PDFs por Email
 * Dispara automaticamente ao técnico enviar RAT
 */

// Verifica se foi incluído pelo editar-rat.php
function enviarPDFsPorEmail($rat_id, $db) {
    try {
        // Buscar dados do RAT
        $stmt = $db->prepare("SELECT r.*, t.nome as tecnico_nome, t.email as tecnico_email FROM rats r LEFT JOIN tecnicos t ON r.id_tecnico = t.id WHERE r.id = ?");
        $stmt->execute([$rat_id]);
        $rat = $stmt->fetch();
        
        if (!$rat) {
            throw new Exception("RAT não encontrado");
        }
        
        // Gerar os 4 PDFs (HTML em string)
        $pdf_completo = gerarPDFHTML($rat, 'completo');
        $pdf_suporte = gerarPDFHTML($rat, 'suporte');
        $pdf_marketing = gerarPDFHTML($rat, 'marketing');
        $pdf_cliente = gerarPDFHTML($rat, 'cliente');
        
        // Nome do arquivo
        $nome_arquivo = gerarNomeArquivo($rat);
        
        $data_rat = date('d/m/Y', strtotime($rat['data_criacao'] ?? 'now'));
        
        // Coletar arquivos de notas fiscais do disco para anexar nos e-mails internos
        $notas_fiscais_caminhos = [];
        $notas_json = json_decode($rat['notas_fiscais_json'] ?? '[]', true) ?: [];
        $upload_dir_notas = __DIR__ . '/../uploads/notas/' . $rat_id . '/';
        foreach ($notas_json as $fname) {
            $fpath = $upload_dir_notas . basename($fname);
            if (file_exists($fpath)) {
                $notas_fiscais_caminhos[] = $fpath;
            }
        }
        
        // Preparar emails
        $emails = [
            [
                'para' => $rat['tecnico_email'],
                'assunto' => "{$data_rat} - {$rat['numero']} - {$rat['cliente_empresa']} - {$rat['tecnico_nome']}",
                'corpo' => prepararCorpoEmail($rat, 'tecnico'),
                'pdf' => $pdf_completo,
                'pdf_nome' => $nome_arquivo,
                'extras' => $notas_fiscais_caminhos
            ],
            [
                'para' => EMAIL_SUPORTE_CENTRAL,
                'assunto' => "{$data_rat} - {$rat['numero']} - {$rat['cliente_empresa']} - {$rat['tecnico_nome']}",
                'corpo' => prepararCorpoEmail($rat, 'suporte'),
                'pdf' => $pdf_suporte,
                'pdf_nome' => $nome_arquivo,
                'extras' => $notas_fiscais_caminhos
            ],
            [
                'para' => EMAIL_MARKETING_CENTRAL,
                'assunto' => "{$data_rat} - {$rat['numero']} - {$rat['cliente_empresa']} - {$rat['tecnico_nome']}",
                'corpo' => prepararCorpoEmail($rat, 'marketing'),
                'pdf' => $pdf_marketing,
                'pdf_nome' => $nome_arquivo,
                'extras' => $notas_fiscais_caminhos
            ],
            [
                'para' => $rat['email_cliente'],
                'assunto' => "{$data_rat} - Relatório de Assistência Técnica - {$rat['numero']}",
                'corpo' => prepararCorpoEmail($rat, 'cliente'),
                'pdf' => $pdf_cliente,
                'pdf_nome' => $nome_arquivo,
                'extras' => [] // cliente não recebe notas fiscais internas
            ]
        ];
        
        // Enviar cada email
        $total_tentados = 0;
        $total_enviados = 0;
        $falhas = [];
        foreach ($emails as $email) {
            if ($email['para']) { // Verificar se email não está vazio
                $total_tentados++;
                $ok = enviarEmailComPDF(
                    $email['para'],
                    $email['assunto'],
                    $email['corpo'],
                    $email['pdf'],
                    $email['pdf_nome'],
                    $email['extras'] ?? []
                );
                if ($ok) {
                    $total_enviados++;
                } else {
                    $falhas[] = $email['para'];
                }
            }
        }

        if ($total_tentados === 0) {
            error_log("[RAT EMAIL AVISO] Nenhum destinatário válido para RAT {$rat_id}");
            return false;
        }

        if ($total_enviados === 0) {
            error_log("[RAT EMAIL ERRO] Nenhum email enviado para RAT {$rat_id}");
            return false;
        }

        if (!empty($falhas)) {
            error_log("[RAT EMAIL PARCIAL] RAT {$rat_id} | Enviados {$total_enviados}/{$total_tentados} | Falhas: " . implode(', ', $falhas));
        } else {
            error_log("[RAT EMAIL OK] RAT {$rat_id} | Enviados {$total_enviados}/{$total_tentados}");
        }

        return true;
        
    } catch (Exception $e) {
        // Log do erro
        error_log("Erro ao enviar PDFs por email: " . $e->getMessage());
        return false;
    }
}

function gerarNomeArquivo($rat) {
    $nome_tecnico = substr(explode(' ', $rat['tecnico_nome'])[0] ?? '', 0, 10);
    $data = date('d-m-Y', strtotime($rat['data_criacao']));
    $empresa = substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $rat['cliente_empresa']), 0, 20);
    
    return "{$data} - {$empresa} - {$nome_tecnico}.pdf";
}

function prepararCorpoEmail($rat, $tipo) {
    $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>RAT " . htmlspecialchars($rat['numero']) . "</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10b981; color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 8px; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #10b981; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Relatório de Assistência Técnica</h1>
            <p>RAT " . htmlspecialchars($rat['numero']) . "</p>
        </div>
        
        <div class='content'>";
    
    if ($tipo === 'tecnico') {
        $html .= "
            <p>Olá <strong>" . htmlspecialchars($rat['tecnico_nome']) . "</strong>,</p>
            <p>Seu RAT foi criado com sucesso! O PDF completo está anexado para sua documentação.</p>
            ";
    } elseif ($tipo === 'suporte') {
        $link_rat = SITE_URL . BASE_URL . "admin/rat-view.php?id=" . $rat['id'];
        $html .= "
            <div style='background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 20px; border-radius: 4px;'>
                <strong style='color: #d97706; font-size: 16px;'>Lembrete Importante!</strong><br>
                <p style='margin-top: 8px; color: #92400e; margin-bottom: 0;'>Entre em contato com o cliente perguntando sobre como foi o atendimento e anote o feedback no painel.</p>
                <a href='{$link_rat}' style='display: inline-block; margin-top: 15px; background-color: #f59e0b; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold;'>Registrar Feedback na RAT</a>
            </div>
            <p>Novo RAT recebido!</p>
            <div class='info-box'>
                <strong>Técnico:</strong> " . htmlspecialchars($rat['tecnico_nome']) . "<br>
                <strong>Cliente:</strong> " . htmlspecialchars($rat['cliente_empresa']) . "<br>
                <strong>Equipamento:</strong> " . htmlspecialchars($rat['modelo_maquina']) . "<br>
            </div>
            ";
    } elseif ($tipo === 'marketing') {
        $html .= "
            <p>Novo relatório de atendimento para registro.</p>
            <div class='info-box'>
                <strong>Cliente:</strong> " . htmlspecialchars($rat['cliente_empresa']) . "<br>
                <strong>Data de Criação:</strong> " . date('d/m/Y H:i', strtotime($rat['data_criacao'])) . "<br>
            </div>
            ";
    } elseif ($tipo === 'cliente') {
        $html .= "
            <p>Prezado(a) <strong>" . htmlspecialchars($rat['cliente_responsavel']) . "</strong>,</p>
            <p>Segue em anexo o Relatório de Assistência Técnica referente ao atendimento realizado.</p>
            <div class='info-box'>
                <strong>Empresa Responsável:</strong> Madetech<br>
                <strong>Data do Atendimento:</strong> " . date('d/m/Y', strtotime($rat['data_criacao'])) . "<br>
            </div>
            <p>Caso tenha dúvidas, entre em contato conosco.</p>
            ";
    }
    
    $html .= "
        </div>
        <div class='footer'>
            <p>Este é um email automático gerado pelo Sistema RAT - Madetech</p>
        </div>
    </div>
</body>
</html>";
    
    return $html;
}

function gerarPDFHTML($rat, $versao) {
    // Função que gera o HTML do RAT para ser salvo como PDF
    // Esta função é chamada para gerar os PDFs de diferentes versões
    
    $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>RAT " . htmlspecialchars($rat['numero']) . "</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            color: #2c3e50; 
            line-height: 1.6; 
            background: #ffffff;
        }
        .container { 
            max-width: 21cm; 
            margin: 0 auto; 
            padding: 15mm 18mm; 
            position: relative; 
        }
        
        @page { size: A4; margin: 12mm; }
        
        /* Cabeçalho Premium */
        .header { 
            text-align: center; 
            margin-bottom: 25px; 
            padding: 20px;
            background-color: #034c8c;
            border-radius: 8px;
            color: white;
            border: 3px solid #0366b5;
        }
        .header h1 { 
            color: white; 
            font-size: 26px; 
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .header p { 
            color: #e8f4fd; 
            font-size: 13px; 
            font-weight: 500;
        }
        
        /* Badge do Número RAT */
        .rat-number { 
            background-color: #10b981;
            color: white; 
            padding: 14px 20px; 
            text-align: center; 
            border-radius: 6px; 
            margin: 15px 0 25px 0; 
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 1.2px;
            border: 3px solid #059669;
        }
        
        /* Seções com Visual Moderno */
        .section { 
            margin: 18px 0; 
            page-break-inside: avoid;
        }
        .section-title { 
            background-color: #e2e8f0;
            padding: 12px 16px; 
            font-weight: 700; 
            border-left: 6px solid #034c8c; 
            margin: 20px 0 12px 0;
            font-size: 13px;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 0 4px 4px 0;
        }
        
        /* Linha de informação completa */
        .info-row-full {
            display: block;
            padding: 10px 12px; 
            background-color: #f8fafc;
            border-radius: 4px;
            border-left: 3px solid #cbd5e1;
            margin: 8px 0;
        }
        .info-label { 
            font-weight: 700; 
            color: #475569; 
            font-size: 11px; 
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }
        .info-value { 
            color: #1e293b; 
            font-size: 13px; 
            font-weight: 500;
        }
        
        /* Tabelas Zebradas e Modernas */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 12px 0; 
            font-size: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }
        th { 
            background-color: #034c8c;
            color: white; 
            padding: 10px 12px; 
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #0366b5;
        }
        td { 
            padding: 10px 12px; 
            border-bottom: 1px solid #e2e8f0;
            background-color: white;
        }
        tr:nth-child(even) td { 
            background-color: #f8fafc; 
        }
        tr:last-child td {
            border-bottom: none;
        }
        
        /* Total Box Destacado */
        .total-box {
            background-color: #fef3c7;
            padding: 12px 16px;
            border-radius: 6px;
            font-weight: 700;
            margin-top: 10px;
            border-left: 5px solid #f59e0b;
            border: 2px solid #fcd34d;
            color: #92400e;
            font-size: 13px;
        }
        
        /* Área de Assinaturas Premium */
        .signature-area { 
            display: inline-block; 
            width: 45%; 
            text-align: center; 
            margin: 15px 2%; 
            vertical-align: top;
            padding: 15px;
            background-color: #f8fafc;
            border-radius: 8px;
            border: 2px dashed #94a3b8;
        }
        .signature-title {
            font-weight: 700;
            color: #475569;
            margin-bottom: 10px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .signature-line { 
            border-top: 2px solid #334155; 
            margin: 50px 20px 8px 20px; 
            padding-top: 8px;
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
        }
        .img-signature { 
            max-width: 200px; 
            max-height: 80px; 
            margin: 10px auto; 
            display: block;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 5px;
            background-color: white;
        }
        
        /* Rodapé */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #cbd5e1;
            text-align: center;
            font-size: 10px;
            color: #64748b;
            background-color: #f8fafc;
            padding: 12px;
            border-radius: 4px;
        }
        
        /* Lógica de visibilidade */
        .visible-for-cliente .hidden-for-cliente { display: none; }
        .visible-for-suporte .hidden-for-suporte { display: none; }
        .visible-for-marketing .hidden-for-marketing { display: none; }
        .visible-for-completo .hidden-for-completo { display: none; }
    </style>
</head>
<body>
    <div class='container visible-for-" . htmlspecialchars($versao) . "'>
        <div class='header'>
            <h1>Relatório de Assistência Técnica</h1>
            <p>Madetech Assistência Técnica</p>
        </div>
        
        <div class='rat-number'>" . htmlspecialchars($rat['numero']) . "</div>
        
        <!-- Dados do Cliente -->
        <div class='section'>
            <div class='section-title'>DADOS DO CLIENTE</div>
            <table style='border: none;'>
                <tr>
                    <td style='width: 50%; border: none; background-color: #f8fafc; border-left: 3px solid #cbd5e1; border-radius: 4px;'>
                        <div class='info-label'>Empresa:</div>
                        <div class='info-value'>" . htmlspecialchars($rat['cliente_empresa'] ?? '') . "</div>
                    </td>
                    <td style='width: 50%; border: none; background-color: #f8fafc; border-left: 3px solid #cbd5e1; border-radius: 4px;'>
                        <div class='info-label'>Responsável:</div>
                        <div class='info-value'>" . htmlspecialchars($rat['cliente_responsavel'] ?? '') . "</div>
                    </td>
                </tr>
            </table>
            <div class='info-row-full'>
                <div class='info-label'>Endereço:</div>
                <div class='info-value'>" . htmlspecialchars($rat['endereco'] ?? '') . ", " . htmlspecialchars($rat['cidade'] ?? '') . " - " . htmlspecialchars($rat['estado'] ?? '') . "</div>
            </div>
        </div>
        
        <!-- Dados do Equipamento -->
        <div class='section'>
            <div class='section-title'>DADOS DO EQUIPAMENTO</div>
            <table style='border: none;'>
                <tr>
                    <td style='width: 50%; border: none; background-color: #f8fafc; border-left: 3px solid #cbd5e1; border-radius: 4px;'>
                        <div class='info-label'>Equipamento:</div>
                        <div class='info-value'>" . htmlspecialchars($rat['equipamento'] ?? '') . "</div>
                    </td>
                    <td style='width: 50%; border: none; background-color: #f8fafc; border-left: 3px solid #cbd5e1; border-radius: 4px;'>
                        <div class='info-label'>Modelo:</div>
                        <div class='info-value'>" . htmlspecialchars($rat['modelo_maquina'] ?? '') . "</div>
                    </td>
                </tr>
                <tr>
                    <td style='width: 50%; border: none; background-color: #f8fafc; border-left: 3px solid #cbd5e1; border-radius: 4px;'>
                        <div class='info-label'>Matrícula:</div>
                        <div class='info-value'>" . htmlspecialchars($rat['matricula'] ?? '-') . "</div>
                    </td>
                    <td style='width: 50%; border: none; background-color: #f8fafc; border-left: 3px solid #cbd5e1; border-radius: 4px;'>
                        <div class='info-label'>Garantia:</div>
                        <div class='info-value'>" . htmlspecialchars($rat['garantia'] ?? '-') . "</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Serviço Realizado -->
        <div class='section'>
            <div class='section-title'>SERVIÇO REALIZADO</div>
            <div class='info-row-full'>
                <div class='info-label'>Tipo de Serviço:</div>
                <div class='info-value'>" . htmlspecialchars($rat['tipo_servico'] ?? '-') . "</div>
            </div>
            <div class='info-row-full'>
                <div class='info-label'>Defeito Constatado:</div>
                <div class='info-value'>" . nl2br(htmlspecialchars($rat['defeito_constatado'] ?? '')) . "</div>
            </div>
            <div class='info-row-full'>
                <div class='info-label'>Trabalho Executado:</div>
                <div class='info-value'>" . nl2br(htmlspecialchars($rat['trabalho_executado'] ?? '')) . "</div>
            </div>
        </div>
        
        <!-- Turnos -->
        <div class='section'>
            <div class='section-title'>TURNOS DE TRABALHO</div>
            <table>
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Manhã</th>
                        <th>Tarde</th>
                    </tr>
                </thead>
                <tbody>";
        
        $turnos = json_decode($rat['turnos_json'] ?? '[]', true);
        foreach ($turnos as $turno) {
            // Pula linhas vazias
            if (empty($turno['dia']) && empty($turno['manha']['inicio']) && empty($turno['manha']['fim']) && empty($turno['tarde']['inicio']) && empty($turno['tarde']['fim'])) continue;
            $dia_fmt = (!empty($turno['dia']) && strpos($turno['dia'], '-') !== false) ? date('d/m/Y', strtotime($turno['dia'])) : ($turno['dia'] ?? '');
            $html .= "<tr>
                        <td>" . htmlspecialchars($dia_fmt) . "</td>
                        <td>" . htmlspecialchars($turno['manha']['inicio'] ?? '') . " - " . htmlspecialchars($turno['manha']['fim'] ?? '') . "</td>
                        <td>" . htmlspecialchars($turno['tarde']['inicio'] ?? '') . " - " . htmlspecialchars($turno['tarde']['fim'] ?? '') . "</td>
                    </tr>";
        }
        
        $total_ht = $rat['total_horas_trabalhadas'] ?? '';
        
        // Calcular total em minutos para verificar mínimo de 3h
        $total_minutos_trabalhados = 0;
        if (!empty($total_ht)) {
            $partes_ht = explode(':', $total_ht);
            if (count($partes_ht) === 2) {
                $total_minutos_trabalhados = (intval($partes_ht[0]) * 60) + intval($partes_ht[1]);
            }
        }
        
        $html .= "
                </tbody>
            </table>
            <div class='total-box'>
                Total Horas Trabalhadas: " . htmlspecialchars($total_ht) . "
            </div>";
        
        // Aviso de mínimo 3h - visível APENAS para o cliente
        if ($total_minutos_trabalhados > 0 && $total_minutos_trabalhados < 180) {
            $html .= "
            <div class='hidden-for-completo hidden-for-suporte hidden-for-marketing' style='margin-top: 10px; padding: 12px 16px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px; font-size: 11px; color: #92400e;'>
                <strong>Observação:</strong> Para cobrir a despesa do técnico, é cobrado o período mínimo de 3 horas de serviço.
            </div>";
        }
        
        $html .= "
        </div>
        
        <!-- Horas Viajadas -->
        <div class='section'>
            <div class='section-title'>HORAS DE VIAGEM</div>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Ida</th>
                        <th>Volta</th>
                    </tr>
                </thead>
                <tbody>";
        
        $viagens = json_decode($rat['horas_viajadas_json'] ?? '[]', true);
        foreach ($viagens as $viagem) {
            // Pula linhas vazias
            if (empty($viagem['data']) && empty($viagem['ida']) && empty($viagem['volta'])) continue;
            $data_viagem_fmt = (!empty($viagem['data']) && strpos($viagem['data'], '-') !== false) ? date('d/m/Y', strtotime($viagem['data'])) : ($viagem['data'] ?? '');
            $html .= "<tr>
                        <td>" . htmlspecialchars($data_viagem_fmt) . "</td>
                        <td>" . htmlspecialchars($viagem['ida'] ?? '') . "</td>
                        <td>" . htmlspecialchars($viagem['volta'] ?? '') . "</td>
                    </tr>";
        }
        
        $total_hv = $rat['total_horas_viajadas_calc'] ?? '';
        $html .= "
                </tbody>
            </table>
            <div class='total-box'>
                Total Horas Viajadas: " . htmlspecialchars($total_hv) . "h
            </div>
        </div>
        
        <!-- KMs Rodados -->
        <div class='section'>
            <div class='section-title'>QUILOMETRAGEM</div>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Ida</th>
                        <th>Volta</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>";
        
        $kms = json_decode($rat['kms_rodados_json'] ?? '[]', true);
        $total_kms = 0;
        foreach ($kms as $km) {
            // Pula linhas vazias
            if (empty($km['data']) && empty($km['ida']) && empty($km['volta']) && empty($km['total'])) continue;
            $data_km_fmt = (!empty($km['data']) && strpos($km['data'], '-') !== false) ? date('d/m/Y', strtotime($km['data'])) : ($km['data'] ?? '');
            $total_kms += floatval($km['total'] ?? 0);
            $html .= "<tr>
                        <td>" . htmlspecialchars($data_km_fmt) . "</td>
                        <td>" . htmlspecialchars($km['ida'] ?? '') . "</td>
                        <td>" . htmlspecialchars($km['volta'] ?? '') . "</td>
                        <td>" . htmlspecialchars($km['total'] ?? '') . " km</td>
                    </tr>";
        }
        
        $html .= "
                </tbody>
            </table>
            <div class='total-box'>
                Total Quilometragem: " . number_format($total_kms, 1, ',', '.') . " km
            </div>
        </div>
        
        <!-- Adicionais -->
        <div class='section hidden-for-cliente'>
            <div class='section-title'>DESPESAS ADICIONAIS</div>
            <table>
                <thead>
                    <tr>
                        <th>Despesa</th>
                        <th>Valor Unit.</th>
                        <th>Qtd/Dias</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>";
        
        $pedagio_val = floatval($rat['pedagio'] ?? 0);
        $pedagio_qtd = max(intval($rat['pedagio_qtd'] ?? 1), 1);
        $hospedagem_val = floatval($rat['hospedagem'] ?? 0);
        $hospedagem_dias = max(intval($rat['hospedagem_dias'] ?? 1), 1);
        $alimentacao_val = floatval($rat['alimentacao'] ?? 0);
        $alimentacao_qtd = max(intval($rat['alimentacao_qtd'] ?? 1), 1);
        $outros_val = floatval($rat['outros_despesas'] ?? 0);
        $outros_qtd = max(intval($rat['outros_qtd'] ?? 1), 1);
        $outros_desc = $rat['outros_despesas_desc'] ?? '';
        
        if ($pedagio_val > 0 && intval($rat['pedagio_qtd'] ?? 0) >= 1) {
            $html .= "
                <tr>
                    <td><strong>Pedágio</strong></td>
                    <td>R\$ " . number_format($pedagio_val, 2, ',', '.') . "</td>
                    <td>" . $pedagio_qtd . "</td>
                    <td>R\$ " . number_format($pedagio_val * $pedagio_qtd, 2, ',', '.') . "</td>
                </tr>";
        }
        
        if ($hospedagem_val > 0 && intval($rat['hospedagem_dias'] ?? 0) >= 1) {
            $html .= "
                <tr>
                    <td><strong>Hospedagem</strong></td>
                    <td>R\$ " . number_format($hospedagem_val, 2, ',', '.') . "</td>
                    <td>" . ($hospedagem_dias > 0 ? $hospedagem_dias . ' dia(s)' : '—') . "</td>
                    <td>R\$ " . number_format($hospedagem_val * $hospedagem_dias, 2, ',', '.') . "</td>
                </tr>";
        }
        
        if ($alimentacao_val > 0 && intval($rat['alimentacao_qtd'] ?? 0) >= 1) {
            $html .= "
                <tr>
                    <td><strong>Alimentação</strong></td>
                    <td>R\$ " . number_format($alimentacao_val, 2, ',', '.') . "</td>
                    <td>" . $alimentacao_qtd . "</td>
                    <td>R\$ " . number_format($alimentacao_val * $alimentacao_qtd, 2, ',', '.') . "</td>
                </tr>";
        }
        
        if ($outros_val > 0 && intval($rat['outros_qtd'] ?? 0) >= 1) {
            $html .= "
                <tr>
                    <td><strong>Outros</strong>" . (!empty($outros_desc) ? "<br><small style='color:#64748b;'>" . htmlspecialchars($outros_desc) . "</small>" : "") . "</td>
                    <td>R\$ " . number_format($outros_val, 2, ',', '.') . "</td>
                    <td>" . $outros_qtd . "</td>
                    <td>R\$ " . number_format($outros_val * $outros_qtd, 2, ',', '.') . "</td>
                </tr>";
        }
        
        $html .= "
                </tbody>
                <tfoot>
                    <tr style='background-color: #f0fdf4;'>
                        <td colspan='3'><strong>Total Geral:</strong></td>
                        <td><strong>R\$ " . number_format($rat['total_adicionais'] ?? 0, 2, ',', '.') . "</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Assinaturas -->
        <div class='section'>
            <div class='section-title'>ASSINATURAS</div>
            <div style='display: flex; justify-content: space-between;'>
                <div class='signature-area'>
                    <div class='signature-title'>Cliente</div>";
        
        if ($rat['assinatura_cliente']) {
            $html .= "<img src='" . $rat['assinatura_cliente'] . "' class='img-signature' alt='Assinatura Cliente'>";
        }
        
        $html .= "
                    <div class='signature-line'>";
        
        if (!empty($rat['nome_assinatura'])) {
            $html .= htmlspecialchars($rat['nome_assinatura']) . "<br>";
            $html .= "<span style='font-size: 9px; color: #64748b; font-weight: normal;'>";
            $html .= "Cargo: " . htmlspecialchars($rat['cargo_assinatura']) . " | CPF: " . htmlspecialchars($rat['cpf_assinatura']);
            $html .= "</span>";
        } else {
            $html .= htmlspecialchars($rat['cliente_responsavel'] ?? '');
        }
        
        $html .= "</div>
                </div>
                
                <div class='signature-area'>
                    <div class='signature-title'>Técnico</div>";
        
        if ($rat['assinatura_tecnico']) {
            $html .= "<img src='" . $rat['assinatura_tecnico'] . "' class='img-signature' alt='Assinatura Técnico'>";
        }
        
        $data_geracao = date('d/m/Y H:i');
        $html .= "
                    <div class='signature-line'>" . htmlspecialchars($rat['tecnico_nome'] ?? 'Técnico Responsável') . "</div>
                </div>
            </div>
        </div>
        
        <!-- Rodapé -->
        <div class='footer'>
            <p><strong>Madetech</strong> - Assistência Técnica Especializada</p>
            <p>Documento gerado em: " . $data_geracao . " | RAT: " . htmlspecialchars($rat['numero']) . "</p>
        </div>
    </div>
</body>
</html>";
    
    return $html;
}

function enviarEmailComPDF($para, $assunto, $corpo_html, $pdf_html, $nome_arquivo, $arquivos_extras = []) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Gerar PDF real com DOMPDF
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $options->set('chroot', __DIR__ . '/..');
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($pdf_html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf_content = $dompdf->output();
        
        // SMTP config
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE === 'tls' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        
        // Remetente
        $mail->setFrom(SMTP_USER, EMAIL_FROM_NAME);
        $mail->addReplyTo(SMTP_USER, EMAIL_FROM_NAME);
        
        // Destinatário
        $mail->addAddress($para);
        
        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpo_html;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $corpo_html));
        
        // Anexar PDF real
        $mail->addStringAttachment($pdf_content, $nome_arquivo, 'base64', 'application/pdf');
        
        // Anexar notas fiscais extras (ex: enviadas pelo técnico)
        foreach ($arquivos_extras as $caminho_extra) {
            if (file_exists($caminho_extra)) {
                $mail->addAttachment($caminho_extra);
            }
        }
        
        $mail->send();
        error_log("[RAT EMAIL OK] Para: $para | Assunto: $assunto");
        return true;
        
    } catch (\Exception $e) {
        error_log("[RAT EMAIL ERRO] Para: $para | Erro: " . ($mail->ErrorInfo ?? $e->getMessage()));
        return false;
    }
}

/**
 * Reenvia a versão CLIENTE para qualquer e-mail informado pelo admin
 */
function reenviarEmailCliente($rat_id, $email_destino, $db) {
    try {
        $stmt = $db->prepare("SELECT r.*, t.nome as tecnico_nome, t.email as tecnico_email FROM rats r LEFT JOIN tecnicos t ON r.id_tecnico = t.id WHERE r.id = ?");
        $stmt->execute([$rat_id]);
        $rat = $stmt->fetch();
        if (!$rat) throw new Exception('RAT nao encontrado');

        $data_rat     = date('d/m/Y', strtotime($rat['data_criacao'] ?? 'now'));
        $nome_arquivo = gerarNomeArquivo($rat);
        $pdf_html     = gerarPDFHTML($rat, 'cliente');
        $corpo        = prepararCorpoEmail($rat, 'cliente');
        $assunto      = "{$data_rat} - Relatorio de Assistencia Tecnica - {$rat['numero']}";

        return enviarEmailComPDF($email_destino, $assunto, $corpo, $pdf_html, $nome_arquivo);
    } catch (Exception $e) {
        error_log('[REENVIO CLIENTE] Erro: ' . $e->getMessage());
        return false;
    }
}
?>
