<?php
// =====================
// LÓGICA ORIGINAL DO ARQUIVO
// =====================

require_once '../api/config.php';
require_once '../api/auth-tecnico.php';

verificarTecnico();

$db = getDB();
$tecnico_id = $_SESSION['tecnico_id'];
$rat_id = $_GET['id'] ?? '';

if (!$rat_id) {
    header('Location: index.php');
    exit;
}

// Buscar RAT
$stmt = $db->prepare("SELECT * FROM rats WHERE id = ? AND id_tecnico = ?");
$stmt->execute([$rat_id, $tecnico_id]);
$rat = $stmt->fetch();

if (!$rat) {
    die("RAT não encontrado ou acesso negado!");
}

// Helper: parse stored hora (decimal "2.5" or "2h 30min") → ['h'=>2,'m'=>30]
function parseHoraViagem($val) {
    if (empty($val)) return ['h' => 0, 'm' => 0];
    if (strpos($val, 'h') !== false) {
        preg_match('/(\d+)h\s*(\d*)/', $val, $m);
        $mins = intval($m[2] ?? 0);
        // round to nearest 5 so dropdown matches
        $mins = (int)(round($mins / 5) * 5);
        return ['h' => intval($m[1] ?? 0), 'm' => min($mins, 55)];
    }
    // decimal format (e.g. 2.5 or 1,30)
    $dec = floatval(str_replace(',', '.', $val));
    $h = intval($dec);
    $m = (int)round(($dec - $h) * 60);
    $m = (int)(round($m / 5) * 5);
    return ['h' => $h, 'm' => min($m, 55)];
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Detectar post_max_size excedido (PHP descarta tudo silenciosamente)
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max = ini_get('post_max_size');
        $erro = "❌ Arquivo(s) muito grande(s)! O limite total é {$max}. Tente enviar fotos menores ou uma de cada vez.";
    }
    $acao = $_POST['acao'] ?? '';
    $is_enviar = isset($_POST['enviar']);
    
    if ($acao === 'atualizar') {
        try {
            $equipamento = $_POST['equipamento'] ?? '';
            $modelo_maquina = $_POST['modelo_maquina'] ?? '';
            $matricula = $_POST['matricula'] ?? '';
            $garantia = $_POST['garantia'] ?? 'Não';
            $defeito_constatado = $_POST['defeito_constatado'] ?? '';
            $trabalho_executado = $_POST['trabalho_executado'] ?? '';
            
            // Turnos
            $turnos = [];
            for ($i = 1; $i <= 5; $i++) {
                $dia = $_POST["turno_dia_$i"] ?? '';
                if ($dia) {
                    $turnos[] = [
                        'dia' => $dia,
                        'manha' => ['inicio' => $_POST["turno_manha_inicio_$i"] ?? '', 'fim' => $_POST["turno_manha_fim_$i"] ?? ''],
                        'tarde' => ['inicio' => $_POST["turno_tarde_inicio_$i"] ?? '', 'fim' => $_POST["turno_tarde_fim_$i"] ?? '']
                    ];
                }
            }
            $turnos_json = json_encode($turnos);
            
            // Viagens — horas e minutos separados
            $horas_viajadas = [];
            for ($i = 1; $i <= 4; $i++) {
                $data = $_POST["hora_viagem_data_$i"] ?? '';
                if ($data) {
                    $ida_h  = intval($_POST["hora_viagem_ida_h_$i"]  ?? 0);
                    $ida_m  = intval($_POST["hora_viagem_ida_m_$i"]  ?? 0);
                    $vlt_h  = intval($_POST["hora_viagem_volta_h_$i"] ?? 0);
                    $vlt_m  = intval($_POST["hora_viagem_volta_m_$i"] ?? 0);
                    $horas_viajadas[] = [
                        'data'  => $data,
                        'ida'   => "{$ida_h}h {$ida_m}min",
                        'volta' => "{$vlt_h}h {$vlt_m}min"
                    ];
                }
            }
            $horas_viajadas_json = json_encode($horas_viajadas);
            
            // KMs
            $kms_rodados = [];
            for ($i = 1; $i <= 4; $i++) {
                $data = $_POST["km_data_$i"] ?? '';
                if ($data) {
                    $km_ida   = floatval($_POST["km_ida_$i"]   ?? 0);
                    $km_volta = floatval($_POST["km_volta_$i"] ?? 0);
                    $kms_rodados[] = ['data' => $data, 'ida' => $km_ida, 'volta' => $km_volta, 'total' => $km_ida + $km_volta];
                }
            }
            $kms_rodados_json = json_encode($kms_rodados);
            
            // Adicionais
            $pedagio     = floatval($_POST['pedagio']    ?? 0);
            $hospedagem  = floatval($_POST['hospedagem'] ?? 0);
            $alimentacao = floatval($_POST['alimentacao']?? 0);
            $total_adicionais = $pedagio + $hospedagem + $alimentacao;
            
            // Assinaturas
            $assinatura_cliente = $_POST['assinatura_cliente'] ?? '';
            $assinatura_tecnico = $_POST['assinatura_tecnico'] ?? '';
            $aceite_cliente     = isset($_POST['aceite_cliente']) ? 1 : 0;
            $aceite_tecnico     = isset($_POST['aceite_tecnico']) ? 1 : 0;
            
            // Validações
            if (!$equipamento || !$modelo_maquina || !$defeito_constatado || !$trabalho_executado) {
                throw new Exception("Preencha todos os campos obrigatórios (equipamento, modelo, defeito e trabalho executado)!");
            }
            
            // Assinaturas/aceites só obrigatórios no envio final
            if ($is_enviar) {
                if (!$assinatura_cliente || !$assinatura_tecnico) {
                    throw new Exception("As assinaturas são obrigatórias para enviar o RAT!");
                }
                if (!$aceite_cliente || !$aceite_tecnico) {
                    throw new Exception("Ambas as confirmações são obrigatórias para enviar o RAT!");
                }
            }
            
            // Atualizar
            $novo_status = $is_enviar ? 'preenchido' : 'rascunho';
            $stmt = $db->prepare("
                UPDATE rats SET
                    equipamento = ?, modelo_maquina = ?, matricula = ?, garantia = ?,
                    defeito_constatado = ?, trabalho_executado = ?,
                    turnos_json = ?, horas_viajadas_json = ?, kms_rodados_json = ?,
                    pedagio = ?, hospedagem = ?, alimentacao = ?, total_adicionais = ?,
                    assinatura_cliente = ?, assinatura_tecnico = ?,
                    aceite_cliente = ?, aceite_tecnico = ?,
                    status = ?, data_preenchimento = CURRENT_TIMESTAMP
                WHERE id = ? AND id_tecnico = ?
            ");
            
            $stmt->execute([$equipamento, $modelo_maquina, $matricula, $garantia, $defeito_constatado, $trabalho_executado, $turnos_json, $horas_viajadas_json, $kms_rodados_json, $pedagio, $hospedagem, $alimentacao, $total_adicionais, $assinatura_cliente, $assinatura_tecnico, $aceite_cliente, $aceite_tecnico, $novo_status, $rat_id, $tecnico_id]);
            
            if ($is_enviar) {
                require_once '../api/enviar-email.php';
                enviarPDFsPorEmail($rat_id, $db);
                $stmt = $db->prepare("UPDATE rats SET status = 'enviado', data_envio = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$rat_id]);
                $sucesso = "✅ RAT enviado com sucesso! PDFs enviados para Técnico, Suporte, Marketing e Cliente.";
            } else {
                $sucesso = "✅ RAT salvo como rascunho!";
            }
            
            $stmt = $db->prepare("SELECT * FROM rats WHERE id = ? AND id_tecnico = ?");
            $stmt->execute([$rat_id, $tecnico_id]);
            $rat = $stmt->fetch();
            
        } catch (Exception $e) {
            $erro = "Erro: " . $e->getMessage();
        }
    }

    // ── Upload de Notas Fiscais
    if ($acao === 'upload_notas') {
        try {
            // Detectar se post_max_size foi excedido (PHP descarta $_POST e $_FILES silenciosamente)
            if (empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
                $max = ini_get('post_max_size');
                throw new Exception("Arquivo(s) muito grande(s)! O limite total é {$max}. Tente enviar fotos menores ou uma de cada vez.");
            }
            if (empty($_FILES['notas_fiscais']['name'][0])) {
                throw new Exception("Nenhum arquivo selecionado.");
            }
            $upload_dir = __DIR__ . '/../uploads/notas/' . $rat_id . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $existing = json_decode($rat['notas_fiscais_json'] ?? '[]', true) ?: [];
            $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp','image/heic','image/heif','application/pdf'];
            $allowed_ext  = ['jpg','jpeg','png','gif','webp','heic','heif','pdf'];
            $uploaded = 0;
            $erros_arquivo = [];
            foreach ($_FILES['notas_fiscais']['tmp_name'] as $idx => $tmp) {
                $nome_original = $_FILES['notas_fiscais']['name'][$idx] ?? 'desconhecido';
                $err_code = $_FILES['notas_fiscais']['error'][$idx];
                if ($err_code !== UPLOAD_ERR_OK) {
                    $err_msgs = [1=>'Arquivo muito grande (limite do servidor)',2=>'Arquivo muito grande',3=>'Upload parcial',4=>'Nenhum arquivo',6=>'Pasta temporária ausente',7=>'Falha ao gravar'];
                    $erros_arquivo[] = "{$nome_original}: " . ($err_msgs[$err_code] ?? "Erro {$err_code}");
                    continue;
                }
                $ext  = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
                $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : '';
                if (!in_array($mime, $allowed_mime) && !in_array($ext, $allowed_ext)) {
                    $erros_arquivo[] = "{$nome_original}: Tipo não permitido ({$ext}|{$mime})";
                    continue;
                }
                $filename = 'nota_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_dir . $filename)) {
                    $existing[] = $filename;
                    $uploaded++;
                } else {
                    $erros_arquivo[] = "{$nome_original}: Falha ao mover arquivo";
                }
            }
            $stmt2 = $db->prepare("UPDATE rats SET notas_fiscais_json = ? WHERE id = ? AND id_tecnico = ?");
            $stmt2->execute([json_encode($existing), $rat_id, $tecnico_id]);
            if ($uploaded > 0) {
                $sucesso = "✅ {$uploaded} nota(s) fiscal(is) enviada(s) com sucesso!";
            }
            if (!empty($erros_arquivo)) {
                $erro = ($uploaded > 0 ? '' : '❌ ') . 'Problemas: ' . implode(' | ', $erros_arquivo);
            } elseif ($uploaded === 0) {
                $erro = '❌ Nenhum arquivo foi enviado. Tente novamente.';
            }
            $rat = array_merge($rat, ['notas_fiscais_json' => json_encode($existing)]);
        } catch (Exception $e) {
            $erro = "Erro ao enviar notas: " . $e->getMessage();
        }
    }

    // ── Apagar uma Nota Fiscal
    if ($acao === 'apagar_nota') {
        $nota_arquivo = $_POST['nota_arquivo'] ?? '';
        if ($nota_arquivo) {
            $existing = json_decode($rat['notas_fiscais_json'] ?? '[]', true) ?: [];
            $existing = array_values(array_filter($existing, fn($n) => $n !== $nota_arquivo));
            $stmt2 = $db->prepare("UPDATE rats SET notas_fiscais_json = ? WHERE id = ? AND id_tecnico = ?");
            $stmt2->execute([json_encode($existing), $rat_id, $tecnico_id]);
            // Remove physical file
            $filepath = __DIR__ . '/../uploads/notas/' . $rat_id . '/' . basename($nota_arquivo);
            if (file_exists($filepath)) unlink($filepath);
            $sucesso = "Nota fiscal removida.";
            $rat = array_merge($rat, ['notas_fiscais_json' => json_encode($existing)]);
        }
    }
}

$turnos_lista = json_decode($rat['turnos_json'] ?? '[]', true);
$horas_viajadas_lista = json_decode($rat['horas_viajadas_json'] ?? '[]', true);
$kms_rodados_lista = json_decode($rat['kms_rodados_json'] ?? '[]', true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preencher RAT - <?php echo htmlspecialchars($rat['numero']); ?> | Sistema RAT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
            padding: 20px 40px;
            box-shadow: 0 12px 40px rgba(16, 185, 129, 0.2);
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .header-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            cursor: pointer;
        }
        
        .header-btn:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-1px);
        }
        
        /* CONTAINER */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        /* BREADCRUMB & TITLE */
        .page-intro {
            margin-bottom: 35px;
        }
        
        .breadcrumb {
            display: flex;
            gap: 10px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        
        .breadcrumb a {
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .page-title {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            font-size: 15px;
            color: #718096;
        }
        
        .rat-badge {
            display: inline-block;
            background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
            color: #0369a1;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        /* ALERTS */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-icon {
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            color: #7f1d1d;
            border-left: 4px solid #dc2626;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #86efac 0%, #65ff87 100%);
            color: #15803d;
            border-left: 4px solid #22c55e;
        }
        
        /* FORM CARD */
        .form-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-section {
            padding: 32px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e0e7ff;
        }
        
        .section-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(16,185,129,0.2);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
        }
        
        .section-desc {
            font-size: 13px;
            color: #718096;
            margin-left: 52px;
            margin-top: -8px;
        }
        
        /* FORM GRID */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 12px;
        }
        
        .form-grid-full {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .required { color: #ef4444; }
        
        input, textarea, select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            background: #f7fafc;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #10b981;
            background: white;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
            transform: translateY(-2px);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* BUTTONS */
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px solid #e0e7ff;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
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
        
        /* INFO BOX */
        .info-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            font-size: 13px;
            color: #065f46;
            line-height: 1.6;
        }
        
        .info-box strong { color: #10b981; display: block; margin-bottom: 6px; }
        
        /* CANVAS SIGNATURE */
        .canvas-container {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8fafc;
        }
        
        canvas {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            display: block;
            margin: 0 auto 12px;
            background: white;
            cursor: crosshair;
        }
        
        .canvas-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .canvas-buttons button {
            padding: 8px 16px;
            background: #e2e8f0;
            color: #2d3748;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .canvas-buttons button:hover {
            background: #cbd5e1;
        }
        
        /* CHECKBOX */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f7fafc;
            border-radius: 6px;
        }
        
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-label {
            font-size: 14px;
            color: #2d3748;
            cursor: pointer;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            header { padding: 16px 20px; }
            .container { padding: 20px; }
            .form-section { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .page-title { font-size: 28px; }
            .button-group { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }

        /* TABLES */
        .table-wrapper { overflow-x: auto; margin-top: 16px; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white; padding: 10px 12px; text-align: left; font-size: 12px;
            font-weight: 600; letter-spacing: 0.4px; white-space: nowrap;
        }
        .data-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .data-table tbody tr:hover { background: rgba(16,185,129,0.04); }
        .data-table td.row-num { color: #aaa; font-size: 12px; text-align: center; width: 30px; font-weight: 600; }
        .data-table input[type="date"],
        .data-table input[type="time"],
        .data-table input[type="number"] {
            padding: 7px 10px; border: 1.5px solid #e2e8f0; border-radius: 6px;
            font-family: 'Inter',sans-serif; font-size: 13px; background: #f7fafc;
            width: 100%; transition: border-color 0.2s;
        }
        .data-table input:focus { outline: none; border-color: #10b981; background: white; }

        /* H+M dropdowns for horas viagem */
        .hm-group { display: flex; align-items: center; gap: 4px; }
        .hm-group select {
            padding: 7px 6px; border: 1.5px solid #e2e8f0; border-radius: 6px;
            font-family: 'Inter',sans-serif; font-size: 13px; background: #f7fafc;
            transition: border-color 0.2s; cursor: pointer;
        }
        .hm-group select:focus { outline: none; border-color: #10b981; background: white; }
        .hm-label { font-size: 11px; color: #718096; font-weight: 600; }

        /* TOTAL ROW */
        .total-row {
            display: flex; align-items: center; gap: 12px;
            margin-top: 10px; padding: 10px 16px;
            background: linear-gradient(135deg, #f0fdf4, #e0f2fe);
            border-radius: 8px; font-weight: 600; font-size: 14px; color: #065f46;
            flex-wrap: wrap;
        }
        .total-row i { color: #10b981; }
        .total-val { color: #059669; font-size: 1.05em; }

        /* DESPESAS */
        .despesas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap: 16px; }
        .despesa-total {
            display: flex; align-items: center; justify-content: space-between;
            background: linear-gradient(135deg, #10b981,#059669); color: white;
            padding: 14px 20px; border-radius: 10px; margin-top: 16px; font-weight: 700; font-size: 16px;
        }

        /* CANVAS SIGNATURE */
        .sig-label { font-size: 13px; font-weight: 700; color: #4a5568; letter-spacing: 0.5px;
            text-transform: uppercase; margin: 20px 0 8px; display: flex; align-items: center; gap: 6px; }
        .canvas-wrap { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 16px;
            background: #f8fafc; text-align: center; }
        .canvas-wrap canvas { border: 1px solid #e2e8f0; border-radius: 6px; display: block;
            margin: 0 auto 10px; background: white; cursor: crosshair; width: 100%; max-width: 500px; }
        .canvas-btns { display: flex; gap: 8px; justify-content: center; }
        .canvas-btns button { padding: 7px 14px; background: #e2e8f0; color: #2d3748; border: none;
            border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: background 0.2s; }
        .canvas-btns button:hover { background: #10b981; color: white; }

        /* CHECKBOX */
        .check-group { display: flex; align-items: center; gap: 12px; padding: 14px;
            background: #f7fafc; border-radius: 8px; margin-top: 12px;
            border: 1.5px solid #e2e8f0; }
        .check-group input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #10b981; }
        .check-group label { font-size: 14px; color: #2d3748; cursor: pointer; }

        .info-box { background: linear-gradient(135deg,#f0fdf4,#e0f2fe); border: 1px solid #86efac;
            border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; font-size: 13px; color: #065f46; }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">⚙️ SISTEMA RAT</div>
            <div>
                <a href="index.php" class="header-btn"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="page-intro">
            <div class="breadcrumb">
                <a href="index.php">Meu Painel</a>
                <span>/</span>
                <span>Preencher RAT</span>
            </div>
            <div class="rat-badge">📋 <?php echo htmlspecialchars($rat['numero']); ?></div>
            <h1 class="page-title">Preencher Relatório Técnico</h1>
            <p class="page-subtitle">Complete todos os campos para finalizar o RAT</p>
        </div>
        
        <?php if ($erro): ?>
            <div class="alert alert-error">
                <div class="alert-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div><?php echo $erro; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
                <div><?php echo $sucesso; ?></div>
            </div>
        <?php endif; ?>
        
        <!-- FORM START -->
        <form method="POST" class="form-card">
            <input type="hidden" name="acao" value="atualizar">
            
            <!-- EQUIPAMENTO -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">🖥️</div>
                    <div>
                        <div class="section-title">Dados do Equipamento</div>
                        <div class="section-desc">Informações sobre a máquina atendida</div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="equipamento"><i class="fas fa-cube"></i> Tipo de Equipamento <span class="required">*</span></label>
                        <input type="text" id="equipamento" name="equipamento" placeholder="Ex: Coladeira de Bordo" value="<?php echo htmlspecialchars($rat['equipamento'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="modelo_maquina"><i class="fas fa-microchip"></i> Modelo <span class="required">*</span></label>
                        <input type="text" id="modelo_maquina" name="modelo_maquina" placeholder="Ex: CB-2000 Pro" value="<?php echo htmlspecialchars($rat['modelo_maquina'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="matricula"><i class="fas fa-hashtag"></i> Matrícula/Série</label>
                        <input type="text" id="matricula" name="matricula" placeholder="Ex: MTD-2024-001" value="<?php echo htmlspecialchars($rat['matricula'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="garantia"><i class="fas fa-shield"></i> Garantia Válida?</label>
                        <select id="garantia" name="garantia">
                            <option value="Não" <?php echo ($rat['garantia'] === 'Não') ? 'selected' : ''; ?>>Não</option>
                            <option value="Sim" <?php echo ($rat['garantia'] === 'Sim') ? 'selected' : ''; ?>>Sim</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- DEFEITO -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">⚠️</div>
                    <div>
                        <div class="section-title">Problema Constatado</div>
                        <div class="section-desc">Descreva o problema encontrado</div>
                    </div>
                </div>
                
                <div class="form-grid form-grid-full">
                    <div class="form-group">
                        <label for="defeito_constatado"><i class="fas fa-clipboard"></i> Defeito <span class="required">*</span></label>
                        <textarea id="defeito_constatado" name="defeito_constatado" placeholder="Descrição detalhada do problema..." required><?php echo htmlspecialchars($rat['defeito_constatado'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- TRABALHO EXECUTADO -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">✅</div>
                    <div>
                        <div class="section-title">Trabalho Executado</div>
                        <div class="section-desc">Descreva a solução aplicada</div>
                    </div>
                </div>
                
                <div class="form-grid form-grid-full">
                    <div class="form-group">
                        <label for="trabalho_executado"><i class="fas fa-tools"></i> Solução <span class="required">*</span></label>
                        <textarea id="trabalho_executado" name="trabalho_executado" placeholder="O que foi feito para resolver o problema..." required><?php echo htmlspecialchars($rat['trabalho_executado'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- JORNADAS DE TRABALHO -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">📅</div>
                    <div>
                        <div class="section-title">Jornadas de Trabalho</div>
                        <div class="section-desc">Horários de trabalho — até 5 dias. Deixe em branco os não utilizados.</div>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Data</th><th>Manhã Início</th><th>Manhã Fim</th><th>Tarde Início</th><th>Tarde Fim</th></tr></thead>
                        <tbody>
                            <?php for ($i=1;$i<=5;$i++): ?>
                            <tr>
                                <td class="row-num"><?= $i ?></td>
                                <td><input type="date" name="turno_dia_<?= $i ?>" value="<?= isset($turnos_lista[$i-1]) ? htmlspecialchars($turnos_lista[$i-1]['dia']) : '' ?>"></td>
                                <td><input type="time" name="turno_manha_inicio_<?= $i ?>" value="<?= isset($turnos_lista[$i-1]) ? htmlspecialchars($turnos_lista[$i-1]['manha']['inicio']) : '' ?>" onchange="calcTotalHoras()"></td>
                                <td><input type="time" name="turno_manha_fim_<?= $i ?>" value="<?= isset($turnos_lista[$i-1]) ? htmlspecialchars($turnos_lista[$i-1]['manha']['fim']) : '' ?>" onchange="calcTotalHoras()"></td>
                                <td><input type="time" name="turno_tarde_inicio_<?= $i ?>" value="<?= isset($turnos_lista[$i-1]) ? htmlspecialchars($turnos_lista[$i-1]['tarde']['inicio']) : '' ?>" onchange="calcTotalHoras()"></td>
                                <td><input type="time" name="turno_tarde_fim_<?= $i ?>" value="<?= isset($turnos_lista[$i-1]) ? htmlspecialchars($turnos_lista[$i-1]['tarde']['fim']) : '' ?>" onchange="calcTotalHoras()"></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="total-row">
                    <i class="fas fa-clock"></i>
                    <span>Total Horas Trabalhadas:</span>
                    <span class="total-val" id="totalHorasDisplay">0h 00min</span>
                </div>
            </div>

            <!-- HORAS DE VIAGEM -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">✈️</div>
                    <div>
                        <div class="section-title">Horas de Viagem</div>
                        <div class="section-desc">Tempo de deslocamento ida e volta — selecione horas e minutos</div>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Data</th><th>Tempo Ida</th><th>Tempo Volta</th></tr></thead>
                        <tbody>
                            <?php for ($i=1;$i<=4;$i++):
                                $hv   = $horas_viajadas_lista[$i-1] ?? null;
                                $idaP = parseHoraViagem($hv['ida']   ?? '');
                                $vltP = parseHoraViagem($hv['volta'] ?? '');
                            ?>
                            <tr>
                                <td class="row-num"><?= $i ?></td>
                                <td><input type="date" name="hora_viagem_data_<?= $i ?>" value="<?= $hv ? htmlspecialchars($hv['data']) : '' ?>"></td>
                                <td>
                                    <div class="hm-group">
                                        <select name="hora_viagem_ida_h_<?= $i ?>" onchange="calcTotalViagem()">
                                            <?php for($h=0;$h<=12;$h++): ?><option value="<?=$h?>" <?=$idaP['h']==$h?'selected':''?>><?=$h?>h</option><?php endfor; ?>
                                        </select>
                                        <select name="hora_viagem_ida_m_<?= $i ?>" onchange="calcTotalViagem()">
                                            <?php for($m=0;$m<=55;$m+=5): ?><option value="<?=$m?>" <?=$idaP['m']==$m?'selected':''?>><?=str_pad($m,2,'0',STR_PAD_LEFT)?>min</option><?php endfor; ?>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div class="hm-group">
                                        <select name="hora_viagem_volta_h_<?= $i ?>" onchange="calcTotalViagem()">
                                            <?php for($h=0;$h<=12;$h++): ?><option value="<?=$h?>" <?=$vltP['h']==$h?'selected':''?>><?=$h?>h</option><?php endfor; ?>
                                        </select>
                                        <select name="hora_viagem_volta_m_<?= $i ?>" onchange="calcTotalViagem()">
                                            <?php for($m=0;$m<=55;$m+=5): ?><option value="<?=$m?>" <?=$vltP['m']==$m?'selected':''?>><?=str_pad($m,2,'0',STR_PAD_LEFT)?>min</option><?php endfor; ?>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="total-row">
                    <i class="fas fa-route"></i>
                    <span>Total Tempo de Viagem:</span>
                    <span class="total-val" id="totalViagemDisplay">0h 00min</span>
                </div>
            </div>

            <!-- QUILOMETRAGEM -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">🛣️</div>
                    <div>
                        <div class="section-title">Quilometragem</div>
                        <div class="section-desc">KMs percorridos nos trajetos</div>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Data</th><th>KM Ida</th><th>KM Volta</th><th>KM Total</th></tr></thead>
                        <tbody>
                            <?php for($i=1;$i<=4;$i++):
                                $km = $kms_rodados_lista[$i-1] ?? null;
                            ?>
                            <tr>
                                <td class="row-num"><?= $i ?></td>
                                <td><input type="date" name="km_data_<?= $i ?>" value="<?= $km ? htmlspecialchars($km['data']) : '' ?>"></td>
                                <td><input type="number" step="0.1" name="km_ida_<?= $i ?>" value="<?= $km ? htmlspecialchars($km['ida']) : '' ?>" placeholder="0.0" onchange="calcKm(<?= $i ?>)"></td>
                                <td><input type="number" step="0.1" name="km_volta_<?= $i ?>" value="<?= $km ? htmlspecialchars($km['volta']) : '' ?>" placeholder="0.0" onchange="calcKm(<?= $i ?>)"></td>
                                <td><input type="number" step="0.1" name="km_total_<?= $i ?>" id="km_total_<?= $i ?>" value="<?= $km ? htmlspecialchars($km['total']) : '' ?>" placeholder="0.0" readonly style="background:#f0fdf4;color:#059669;font-weight:700;"></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DESPESAS ADICIONAIS -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">💰</div>
                    <div>
                        <div class="section-title">Despesas Adicionais</div>
                        <div class="section-desc">Custos extras — não exibidos ao cliente no PDF</div>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-receipt"></i> Pedágio (R$)</label>
                        <input type="number" step="0.01" name="pedagio" value="<?= htmlspecialchars($rat['pedagio'] ?? 0) ?>" onchange="calcAdicionais()">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hotel"></i> Hospedagem (R$)</label>
                        <input type="number" step="0.01" name="hospedagem" value="<?= htmlspecialchars($rat['hospedagem'] ?? 0) ?>" onchange="calcAdicionais()">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-utensils"></i> Alimentação (R$)</label>
                        <input type="number" step="0.01" name="alimentacao" value="<?= htmlspecialchars($rat['alimentacao'] ?? 0) ?>" onchange="calcAdicionais()">
                    </div>
                </div>
                <div class="despesa-total">
                    <span><i class="fas fa-coins"></i> Total Despesas</span>
                    <span id="totalAdicionaisDisplay">R$ <?= number_format(($rat['total_adicionais'] ?? 0),2,',','.') ?></span>
                </div>
            </div>

            <!-- ASSINATURAS + CONFIRMAÇÃO -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">✍️</div>
                    <div>
                        <div class="section-title">Assinaturas &amp; Confirmação</div>
                        <div class="section-desc">Obrigatório apenas ao <strong>Enviar</strong> o RAT. Rascunho pode ser salvo sem assinar.</div>
                    </div>
                </div>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    Você pode <strong>salvar rascunho</strong> a qualquer momento sem assinaturas. Elas são exigidas somente para <strong>Enviar</strong>.
                </div>

                <div class="sig-label"><i class="fas fa-user"></i> Assinatura do Cliente</div>
                <div class="canvas-wrap">
                    <canvas id="canvas_cliente" height="160"></canvas>
                    <div class="canvas-btns">
                        <button type="button" onclick="limparSig('canvas_cliente')"><i class="fas fa-eraser"></i> Limpar</button>
                    </div>
                    <input type="hidden" id="assinatura_cliente" name="assinatura_cliente" value="<?= htmlspecialchars($rat['assinatura_cliente'] ?? '') ?>">
                </div>

                <div class="sig-label" style="margin-top:22px;"><i class="fas fa-hard-hat"></i> Assinatura do Técnico</div>
                <div class="canvas-wrap">
                    <canvas id="canvas_tecnico" height="160"></canvas>
                    <div class="canvas-btns">
                        <button type="button" onclick="limparSig('canvas_tecnico')"><i class="fas fa-eraser"></i> Limpar</button>
                    </div>
                    <input type="hidden" id="assinatura_tecnico" name="assinatura_tecnico" value="<?= htmlspecialchars($rat['assinatura_tecnico'] ?? '') ?>">
                </div>

                <div style="margin-top:20px;">
                    <div class="check-group">
                        <input type="checkbox" id="aceite_cliente" name="aceite_cliente" value="1" <?= ($rat['aceite_cliente'] ?? 0) ? 'checked' : '' ?>>
                        <label for="aceite_cliente">Cliente confirma os dados do atendimento</label>
                    </div>
                    <div class="check-group">
                        <input type="checkbox" id="aceite_tecnico" name="aceite_tecnico" value="1" <?= ($rat['aceite_tecnico'] ?? 0) ? 'checked' : '' ?>>
                        <label for="aceite_tecnico">Técnico confirma a conclusão do atendimento</label>
                    </div>
                </div>
            </div>

            <!-- SUBMIT BUTTONS -->
            <div class="form-section">
                <div class="button-group">
                    <button type="submit" name="salvar" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar como Rascunho
                    </button>
                    <button type="submit" name="enviar" class="btn btn-primary" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                        <i class="fas fa-paper-plane"></i> Enviar RAT
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>

        <!-- ══════════════════════════════════════════════
             NOTAS FISCAIS — formulário separado
        ══════════════════════════════════════════════ -->
        <span id="notas-fiscais"></span>
        <div class="form-card" style="margin-top:24px;">
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fas fa-receipt"></i></div>
                    <div>
                        <div class="section-title">Notas Fiscais</div>
                        <div class="section-desc">Anexe fotos ou PDFs de notas fiscais. <strong>Não são enviadas por e-mail.</strong></div>
                    </div>
                </div>

                <?php
                $notas_existentes = json_decode($rat['notas_fiscais_json'] ?? '[]', true) ?: [];
                if (!empty($notas_existentes)): ?>
                <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                    <?php foreach ($notas_existentes as $nota):
                        $nota_url = '../uploads/notas/' . $rat_id . '/' . rawurlencode(basename($nota));
                        $is_pdf = strtolower(pathinfo($nota, PATHINFO_EXTENSION)) === 'pdf';
                    ?>
                    <div style="position:relative;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;width:130px;background:#f8fafc;box-shadow:0 2px 6px rgba(0,0,0,.06);">
                        <?php if ($is_pdf): ?>
                        <a href="<?= $nota_url ?>" target="_blank" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:110px;text-decoration:none;color:#334155;font-size:12px;font-weight:600;gap:6px;padding:8px;">
                            <i class="fas fa-file-pdf" style="font-size:36px;color:#ef4444;"></i>
                            PDF
                        </a>
                        <?php else: ?>
                        <a href="<?= $nota_url ?>" target="_blank">
                            <img src="<?= $nota_url ?>" alt="Nota Fiscal" style="width:130px;height:110px;object-fit:cover;display:block;">
                        </a>
                        <?php endif; ?>
                        <form method="POST" style="position:absolute;top:5px;right:5px;margin:0;">
                            <input type="hidden" name="acao" value="apagar_nota">
                            <input type="hidden" name="nota_arquivo" value="<?= htmlspecialchars(basename($nota)) ?>">
                            <button type="submit" title="Remover nota" onclick="return confirm('Remover esta nota fiscal?')"
                                style="width:24px;height:24px;border-radius:50%;background:#ef4444;color:white;border:none;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;padding:0;box-shadow:0 2px 6px rgba(0,0,0,.25);">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:#94a3b8;font-size:13px;margin-bottom:16px;">Nenhuma nota fiscal anexada ainda.</p>
                <?php endif; ?>

                <form id="formUploadNotas" method="POST" enctype="multipart/form-data" style="margin:0;">
                    <input type="hidden" name="acao" value="upload_notas">
                    <label style="display:block;font-weight:600;color:#334155;margin-bottom:8px;">Adicionar Notas Fiscais</label>
                    <input type="file" id="inputNotas" accept="image/*,application/pdf" multiple
                        style="display:block;width:100%;padding:12px 14px;border:2px dashed #cbd5e1;border-radius:10px;background:#f8fafc;font-size:13px;cursor:pointer;color:#475569;">
                    <p style="margin-top:6px;font-size:12px;color:#94a3b8;">Aceita fotos (JPG, PNG, WebP) e PDFs. Fotos são comprimidas automaticamente.</p>
                    <div id="notaProgress" style="display:none;margin-top:10px;padding:10px;background:#eff6ff;border-radius:8px;font-size:13px;color:#1e40af;">
                        <i class="fas fa-spinner fa-spin"></i> <span id="notaProgressText">Comprimindo fotos...</span>
                    </div>
                    <div style="margin-top:14px;">
                        <button type="submit" id="btnEnviarNotas" style="padding:10px 24px;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border:none;border-radius:8px;font-weight:600;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
                            <i class="fas fa-cloud-upload-alt"></i> Enviar Notas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // ── Canvas signatures ────────────────────────────────────────────
    let drawing = false;
    function initCanvas(id) {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        canvas.width = canvas.offsetWidth || 480;
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        const key = id.replace('canvas_', '');
        const hidden = document.getElementById('assinatura_' + key);
        if (hidden && hidden.value) {
            const img = new Image();
            img.onload = () => ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            img.src = hidden.value;
        }
        const pos = e => { const r = canvas.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return {x:t.clientX-r.left, y:t.clientY-r.top}; };
        const save = () => { if(hidden) hidden.value = canvas.toDataURL(); };
        canvas.addEventListener('mousedown',  e => { drawing=true; const p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); });
        canvas.addEventListener('mousemove',  e => { if(!drawing)return; const p=pos(e); ctx.lineTo(p.x,p.y); ctx.lineWidth=2; ctx.strokeStyle='#034c8c'; ctx.lineCap='round'; ctx.stroke(); });
        canvas.addEventListener('mouseup',    () => { drawing=false; save(); });
        canvas.addEventListener('mouseleave', () => { drawing=false; save(); });
        canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing=true; const p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); }, {passive:false});
        canvas.addEventListener('touchmove',  e => { if(!drawing)return; e.preventDefault(); const p=pos(e); ctx.lineTo(p.x,p.y); ctx.lineWidth=2; ctx.strokeStyle='#034c8c'; ctx.lineCap='round'; ctx.stroke(); }, {passive:false});
        canvas.addEventListener('touchend',   () => { drawing=false; save(); });
    }
    function limparSig(id) {
        const canvas = document.getElementById(id);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        const hidden = document.getElementById('assinatura_' + id.replace('canvas_',''));
        if (hidden) hidden.value = '';
    }

    // ── Horas trabalhadas ────────────────────────────────────────────
    function calcTotalHoras() {
        let tot = 0;
        const diff = (a,b) => { if(!a||!b)return 0; const [ah,am]=a.split(':').map(Number),[bh,bm]=b.split(':').map(Number),d=(bh*60+bm)-(ah*60+am); return d>0?d:0; };
        for (let i=1;i<=5;i++) {
            const v = n => document.querySelector('[name="'+n+i+'"]')?.value||'';
            tot += diff(v('turno_manha_inicio_'),v('turno_manha_fim_')) + diff(v('turno_tarde_inicio_'),v('turno_tarde_fim_'));
        }
        document.getElementById('totalHorasDisplay').textContent = Math.floor(tot/60)+'h '+String(tot%60).padStart(2,'0')+'min';
    }

    // ── Horas viagem ─────────────────────────────────────────────────
    function calcTotalViagem() {
        let tot = 0;
        for (let i=1;i<=4;i++) {
            if (!document.querySelector('[name="hora_viagem_data_'+i+'"]')?.value) continue;
            const g = n => parseInt(document.querySelector('[name="'+n+i+'"]')?.value||0);
            tot += g('hora_viagem_ida_h_')*60 + g('hora_viagem_ida_m_') + g('hora_viagem_volta_h_')*60 + g('hora_viagem_volta_m_');
        }
        document.getElementById('totalViagemDisplay').textContent = Math.floor(tot/60)+'h '+String(tot%60).padStart(2,'0')+'min';
    }

    // ── KM ───────────────────────────────────────────────────────────
    function calcKm(i) {
        const a = parseFloat(document.querySelector('[name="km_ida_'+i+'"]')?.value)||0;
        const b = parseFloat(document.querySelector('[name="km_volta_'+i+'"]')?.value)||0;
        document.getElementById('km_total_'+i).value = (a+b).toFixed(1);
    }

    // ── Adicionais ───────────────────────────────────────────────────
    function calcAdicionais() {
        const f = n => parseFloat(document.querySelector('[name="'+n+'"]')?.value)||0;
        const t = f('pedagio')+f('hospedagem')+f('alimentacao');
        document.getElementById('totalAdicionaisDisplay').textContent = 'R$ '+t.toFixed(2).replace('.',',');
    }

    // ── Init ─────────────────────────────────────────────────────────
    window.addEventListener('load', () => {
        initCanvas('canvas_cliente');
        initCanvas('canvas_tecnico');
        calcTotalHoras();
        calcTotalViagem();
        calcAdicionais();
        for(let i=1;i<=4;i++) calcKm(i);
    });

    // ── Compressão de imagens antes do upload ────────────────────────
    function comprimirImagem(file, maxW, maxH, quality) {
        return new Promise((resolve) => {
            if (file.type === 'application/pdf') { resolve(file); return; }
            if (!file.type.startsWith('image/')) { resolve(file); return; }
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    let w = img.width, h = img.height;
                    if (w > maxW || h > maxH) {
                        const ratio = Math.min(maxW / w, maxH / h);
                        w = Math.round(w * ratio);
                        h = Math.round(h * ratio);
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = w; canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, w, h);
                    canvas.toBlob(function(blob) {
                        const nome = file.name.replace(/\.[^.]+$/, '.jpg');
                        resolve(new File([blob], nome, { type: 'image/jpeg' }));
                    }, 'image/jpeg', quality);
                };
                img.onerror = function() { resolve(file); };
                img.src = e.target.result;
            };
            reader.onerror = function() { resolve(file); };
            reader.readAsDataURL(file);
        });
    }

    (function() {
        const form = document.getElementById('formUploadNotas');
        if (!form) return;
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const input = document.getElementById('inputNotas');
            const files = input.files;
            if (!files || files.length === 0) { alert('Selecione pelo menos um arquivo.'); return; }
            const btn = document.getElementById('btnEnviarNotas');
            const prog = document.getElementById('notaProgress');
            const progText = document.getElementById('notaProgressText');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            prog.style.display = 'block';
            const formData = new FormData();
            formData.append('acao', 'upload_notas');
            for (let i = 0; i < files.length; i++) {
                progText.textContent = 'Comprimindo ' + (i+1) + ' de ' + files.length + '...';
                const compressed = await comprimirImagem(files[i], 1600, 1600, 0.75);
                formData.append('notas_fiscais[]', compressed, compressed.name);
            }
            progText.textContent = 'Enviando ao servidor...';
            try {
                const resp = await fetch(window.location.href, { method: 'POST', body: formData });
                if (resp.ok) { window.location.reload(); }
                else { alert('Erro no envio. Código: ' + resp.status); btn.disabled = false; btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Enviar Notas'; prog.style.display = 'none'; }
            } catch(err) { alert('Erro de conexão: ' + err.message); btn.disabled = false; btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Enviar Notas'; prog.style.display = 'none'; }
        });
    })();
    </script>
</body>
</html>
