<?php
// Suporte dual: admin e técnico
if (session_status() === PHP_SESSION_NONE) session_start();
$_is_admin_mode = isset($_SESSION['admin_id']) && isset($_SESSION['_admin_edit_mode']);

if (!$_is_admin_mode) {
    require_once __DIR__ . '/../api/config.php';
    require_once __DIR__ . '/../api/auth-tecnico.php';
    verificarTecnico();
}

$db = getDB();
$rat_id = $_GET['id'] ?? '';

if (!$rat_id) {
    header('Location: index.php');
    exit;
}

// Buscar RAT
if ($_is_admin_mode) {
    // Admin pode ver/editar qualquer RAT
    $stmt = $db->prepare("SELECT r.*, t.nome as _tecnico_nome, t.email as _tecnico_email FROM rats r LEFT JOIN tecnicos t ON r.id_tecnico = t.id WHERE r.id = ?");
    $stmt->execute([$rat_id]);
    $rat = $stmt->fetch();
    $tecnico_id = $rat['id_tecnico'] ?? 0;
    $tecnico_nome = $rat['_tecnico_nome'] ?? 'Técnico';
    $tecnico_email = $rat['_tecnico_email'] ?? '';
} else {
    $tecnico_id = $_SESSION['tecnico_id'];
    $tecnico_nome = $_SESSION['tecnico_nome'] ?? 'Técnico';
    $tecnico_email = $_SESSION['tecnico_email'] ?? '';
    $stmt = $db->prepare("SELECT * FROM rats WHERE id = ? AND id_tecnico = ?");
    $stmt->execute([$rat_id, $tecnico_id]);
    $rat = $stmt->fetch();
}

if (!$rat) {
    die("RAT não encontrado ou acesso negado!");
}

$erro = '';
$sucesso = '';

function normalizarHoraViagemNumero($valor) {
    $valor = trim((string)$valor);
    if ($valor === '') {
        return '';
    }

    $valor = str_replace(',', '.', $valor);

    if (is_numeric($valor)) {
        return (string)(float)$valor;
    }

    if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $valor, $m)) {
        $horas = intval($m[1]);
        $min = intval($m[2]);
        return (string)($horas + ($min / 60));
    }

    if (preg_match('/^(\d+)h\s*(\d{1,2})?\s*(min)?$/i', $valor, $m)) {
        $horas = intval($m[1]);
        $min = intval($m[2] ?? 0);
        return (string)($horas + ($min / 60));
    }

    if (preg_match('/^(\d+)h(\d{1,2})$/i', $valor, $m)) {
        $horas = intval($m[1]);
        $min = intval($m[2]);
        return (string)($horas + ($min / 60));
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Detectar post_max_size excedido (PHP descarta tudo silenciosamente)
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max = ini_get('post_max_size');
        $erro = "❌ Arquivo(s) muito grande(s)! O limite total é {$max}. Tente enviar fotos menores ou uma de cada vez.";
    }
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'atualizar' || $acao === 'enviar' || $acao === 'autosave') {
        try {
            $equipamento = $_POST['equipamento'] ?? '';
            $modelo_maquina = $_POST['modelo_maquina'] ?? '';
            $matricula = $_POST['matricula'] ?? '';
            $garantia = $_POST['garantia'] ?? 'Não';
            $tipo_servico_arr = $_POST['tipo_servico'] ?? [];
            if (is_array($tipo_servico_arr)) {
                $tipo_servico = implode(', ', array_slice($tipo_servico_arr, 0, 2));
            } else {
                $tipo_servico = $tipo_servico_arr;
            }
            $defeito_constatado = $_POST['defeito_constatado'] ?? '';
            $trabalho_executado = $_POST['trabalho_executado'] ?? '';
            
            // Turnos - sempre salva 5 elementos (mantendo índices consistentes)
            $turnos = [];
            for ($i = 1; $i <= 5; $i++) {
                $dia = $_POST["turno_dia_$i"] ?? '';
                $mi = $_POST["turno_manha_inicio_$i"] ?? '';
                $mf = $_POST["turno_manha_fim_$i"] ?? '';
                $ti = $_POST["turno_tarde_inicio_$i"] ?? '';
                $tf = $_POST["turno_tarde_fim_$i"] ?? '';
                $turnos[] = [
                    'dia' => $dia,
                    'manha' => ['inicio' => $mi, 'fim' => $mf],
                    'tarde' => ['inicio' => $ti, 'fim' => $tf]
                ];
            }
            $turnos_json = json_encode($turnos);
            
            // Viagens - sempre salva 4 elementos
            $horas_viajadas = [];
            for ($i = 1; $i <= 4; $i++) {
                $data = $_POST["hora_viagem_data_$i"] ?? '';
                $ida = normalizarHoraViagemNumero($_POST["hora_viagem_ida_$i"] ?? '');
                $volta = normalizarHoraViagemNumero($_POST["hora_viagem_volta_$i"] ?? '');
                $horas_viajadas[] = ['data' => $data, 'ida' => $ida, 'volta' => $volta];
            }
            $horas_viajadas_json = json_encode($horas_viajadas);
            
            // KMs - sempre salva 4 elementos
            $kms_rodados = [];
            for ($i = 1; $i <= 4; $i++) {
                $data = $_POST["km_data_$i"] ?? '';
                $ida = $_POST["km_ida_$i"] ?? '';
                $volta = $_POST["km_volta_$i"] ?? '';
                $total = $_POST["km_total_$i"] ?? '';
                $kms_rodados[] = ['data' => $data, 'ida' => $ida, 'volta' => $volta, 'total' => $total];
            }
            $kms_rodados_json = json_encode($kms_rodados);
            
            // Adicionais
            $pedagio = floatval($_POST['pedagio'] ?? 0);
            $pedagio_qtd = intval($_POST['pedagio_qtd'] ?? 0);
            $hospedagem = floatval($_POST['hospedagem'] ?? 0);
            $hospedagem_dias = intval($_POST['hospedagem_dias'] ?? 0);
            $alimentacao = floatval($_POST['alimentacao'] ?? 0);
            $alimentacao_qtd = intval($_POST['alimentacao_qtd'] ?? 0);
            $outros_despesas = floatval($_POST['outros_despesas'] ?? 0);
            $outros_qtd = intval($_POST['outros_qtd'] ?? 0);
            $outros_despesas_desc = $_POST['outros_despesas_desc'] ?? '';
            $total_adicionais = ($pedagio * max($pedagio_qtd,0)) + ($hospedagem * max($hospedagem_dias,0)) + ($alimentacao * max($alimentacao_qtd,0)) + ($outros_despesas * max($outros_qtd,0));
            
            // Assinaturas
            $assinatura_cliente = $_POST['assinatura_cliente'] ?? '';
            $assinatura_tecnico = $_POST['assinatura_tecnico'] ?? '';
            $aceite_cliente = isset($_POST['aceite_cliente']) ? 1 : 0;
            $aceite_tecnico = isset($_POST['aceite_tecnico']) ? 1 : 0;
            $nome_assinatura = $_POST['nome_assinatura'] ?? '';
            $cargo_assinatura = $_POST['cargo_assinatura'] ?? '';
            $cpf_assinatura = $_POST['cpf_assinatura'] ?? '';
            
            // Atualizar
            $total_horas_trabalhadas = $_POST['total_horas_trabalhadas'] ?? '';
            $total_horas_viajadas_calc = $_POST['total_horas_viajadas_calc'] ?? '';
            
            // Orçamento
            $orcamento_nomes = $_POST['orcamento_nome'] ?? [];
            $orcamento_qtds = $_POST['orcamento_qtd'] ?? [];
            $orcamento_items = [];
            if (is_array($orcamento_nomes)) {
                for ($i = 0; $i < count($orcamento_nomes); $i++) {
                    $nome = trim($orcamento_nomes[$i] ?? '');
                    $qtd = trim($orcamento_qtds[$i] ?? '');
                    if ($nome !== '') {
                        $orcamento_items[] = ['nome' => $nome, 'quantidade' => $qtd];
                    }
                }
            }
            $orcamento_json = json_encode($orcamento_items);
            
            // Técnicos Adicionais
            $tecnicos_adicionais = [];
            if (isset($_POST['tecnico_extra_nome']) && is_array($_POST['tecnico_extra_nome'])) {
                foreach ($_POST['tecnico_extra_nome'] as $index => $nome) {
                    $nome = trim($nome);
                    if (!$nome) continue;
                    
                    $tec = [
                        'nome' => $nome,
                        'turnos' => [],
                        'horas_viajadas' => [],
                        'kms_rodados' => [],
                        'despesas' => [
                            'pedagio' => floatval($_POST['tecnico_extra_pedagio'][$index] ?? 0),
                            'pedagio_qtd' => intval($_POST['tecnico_extra_pedagio_qtd'][$index] ?? 0),
                            'hospedagem' => floatval($_POST['tecnico_extra_hospedagem'][$index] ?? 0),
                            'hospedagem_dias' => intval($_POST['tecnico_extra_hospedagem_dias'][$index] ?? 0),
                            'alimentacao' => floatval($_POST['tecnico_extra_alimentacao'][$index] ?? 0),
                            'alimentacao_qtd' => intval($_POST['tecnico_extra_alimentacao_qtd'][$index] ?? 0),
                            'outros_despesas' => floatval($_POST['tecnico_extra_outros_despesas'][$index] ?? 0),
                            'outros_qtd' => intval($_POST['tecnico_extra_outros_qtd'][$index] ?? 0),
                            'outros_despesas_desc' => $_POST['tecnico_extra_outros_despesas_desc'][$index] ?? ''
                        ],
                        'assinatura' => $_POST['tecnico_extra_assinatura'][$index] ?? '',
                        'aceite' => isset($_POST['tecnico_extra_aceite'][$index]) ? 1 : 0,
                        'total_horas_trabalhadas' => $_POST['tecnico_extra_total_horas_trabalhadas'][$index] ?? '',
                        'total_horas_viajadas_calc' => $_POST['tecnico_extra_total_horas_viajadas_calc'][$index] ?? ''
                    ];
                    
                    for ($i = 1; $i <= 5; $i++) {
                        $tec['turnos'][] = [
                            'dia' => $_POST["tecnico_extra_turno_dia_{$i}"][$index] ?? '',
                            'manha' => ['inicio' => $_POST["tecnico_extra_turno_manha_inicio_{$i}"][$index] ?? '', 'fim' => $_POST["tecnico_extra_turno_manha_fim_{$i}"][$index] ?? ''],
                            'tarde' => ['inicio' => $_POST["tecnico_extra_turno_tarde_inicio_{$i}"][$index] ?? '', 'fim' => $_POST["tecnico_extra_turno_tarde_fim_{$i}"][$index] ?? '']
                        ];
                    }
                    for ($i = 1; $i <= 4; $i++) {
                        $tec['horas_viajadas'][] = [
                            'data' => $_POST["tecnico_extra_hora_viagem_data_{$i}"][$index] ?? '',
                            'ida' => normalizarHoraViagemNumero($_POST["tecnico_extra_hora_viagem_ida_{$i}"][$index] ?? ''),
                            'volta' => normalizarHoraViagemNumero($_POST["tecnico_extra_hora_viagem_volta_{$i}"][$index] ?? '')
                        ];
                    }
                    for ($i = 1; $i <= 4; $i++) {
                        $tec['kms_rodados'][] = [
                            'data' => $_POST["tecnico_extra_km_data_{$i}"][$index] ?? '',
                            'ida' => $_POST["tecnico_extra_km_ida_{$i}"][$index] ?? '',
                            'volta' => $_POST["tecnico_extra_km_volta_{$i}"][$index] ?? '',
                            'total' => $_POST["tecnico_extra_km_total_{$i}"][$index] ?? ''
                        ];
                    }
                    $tecnicos_adicionais[] = $tec;
                }
            }
            $tecnicos_adicionais_json = json_encode($tecnicos_adicionais);
            
            $_update_where = $_is_admin_mode ? 'WHERE id = ?' : 'WHERE id = ? AND id_tecnico = ?';
            $stmt = $db->prepare("
                UPDATE rats SET
                    equipamento = ?, modelo_maquina = ?, matricula = ?, garantia = ?, tipo_servico = ?,
                    defeito_constatado = ?, trabalho_executado = ?,
                    turnos_json = ?, horas_viajadas_json = ?, kms_rodados_json = ?,
                    pedagio = ?, pedagio_qtd = ?, hospedagem = ?, hospedagem_dias = ?, alimentacao = ?, alimentacao_qtd = ?, outros_despesas = ?, outros_qtd = ?, outros_despesas_desc = ?, total_adicionais = ?,
                    assinatura_cliente = ?, assinatura_tecnico = ?,
                    aceite_cliente = ?, aceite_tecnico = ?,
                    nome_assinatura = ?, cargo_assinatura = ?, cpf_assinatura = ?,
                    total_horas_trabalhadas = ?, total_horas_viajadas_calc = ?,
                    orcamento_json = ?, tecnicos_adicionais_json = ?,
                    atualizado_em = CURRENT_TIMESTAMP
                {$_update_where}
            ");
            
            $_params = [
                $equipamento, $modelo_maquina, $matricula, $garantia, $tipo_servico,
                $defeito_constatado, $trabalho_executado,
                $turnos_json, $horas_viajadas_json, $kms_rodados_json,
                $pedagio, $pedagio_qtd, $hospedagem, $hospedagem_dias, $alimentacao, $alimentacao_qtd, $outros_despesas, $outros_qtd, $outros_despesas_desc, $total_adicionais,
                $assinatura_cliente, $assinatura_tecnico,
                $aceite_cliente, $aceite_tecnico,
                $nome_assinatura, $cargo_assinatura, $cpf_assinatura,
                $total_horas_trabalhadas, $total_horas_viajadas_calc,
                $orcamento_json, $tecnicos_adicionais_json,
                $rat_id
            ];
            if (!$_is_admin_mode) $_params[] = $tecnico_id;
            $stmt->execute($_params);
            
            // Debug: log dos JSONs salvos
            error_log("[RAT SAVE DEBUG] RAT $rat_id | Turnos: $turnos_json | Viagens: $horas_viajadas_json | KMs: $kms_rodados_json");
            
            if ($acao === 'autosave') {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success']);
                exit;
            }
            
            if ($acao === 'enviar') {
                // Se ainda for rascunho temporário, gera o número sequencial final
                if (strpos($rat['numero'], 'RASCUNHO') !== false) {
                    $proximo_seq = obterProximoSequencialRAT($tecnico_id);
                    $numero_display = gerarNumeroRATporTecnico($tecnico_id, $proximo_seq);
                    $stmt_seq = $db->prepare("UPDATE rats SET numero = ?, numero_sequencial = ? WHERE id = ?");
                    $stmt_seq->execute([$numero_display, $proximo_seq, $rat_id]);
                    $rat['numero'] = $numero_display;
                    $rat['numero_sequencial'] = $proximo_seq;
                }

                if ($_is_admin_mode) {
                    $stmt = $db->prepare("UPDATE rats SET status = 'enviado', enviado_em = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$rat_id]);
                } else {
                    $stmt = $db->prepare("UPDATE rats SET status = 'enviado', enviado_em = CURRENT_TIMESTAMP WHERE id = ? AND id_tecnico = ?");
                    $stmt->execute([$rat_id, $tecnico_id]);
                }
                
                // Enviar emails com RAT anexo
                require_once __DIR__ . '/../api/enviar-email.php';
                $emailOk = enviarPDFsPorEmail($rat_id, $db);
                
                if ($emailOk) {
                    $sucesso = "RAT enviado com sucesso! Emails disparados para técnico, suporte e marketing.";
                } else {
                    $sucesso = "RAT enviado com sucesso! (Aviso: falha ao enviar alguns emails — verifique o log)";
                }
            } else {
                $sucesso = "RAT salvo em rascunho!";
            }
            
            // Recarregar
            if ($_is_admin_mode) {
                $stmt = $db->prepare("SELECT * FROM rats WHERE id = ?");
                $stmt->execute([$rat_id]);
            } else {
                $stmt = $db->prepare("SELECT * FROM rats WHERE id = ? AND id_tecnico = ?");
                $stmt->execute([$rat_id, $tecnico_id]);
            }
            $rat_novo = $stmt->fetch();
            if ($rat_novo) $rat = $rat_novo; // só sobrescreve se encontrou
            
        } catch (Exception $e) {
            $erro = "Erro: " . $e->getMessage();
        }
    }

    // ── Upload de Notas Fiscais
    if ($acao === 'upload_notas') {
        // Detectar se post_max_size foi excedido (PHP descarta $_POST e $_FILES silenciosamente)
        if (empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
            $max = ini_get('post_max_size');
            $erro = "Arquivo(s) muito grande(s)! O limite total é {$max}. Tente enviar fotos menores ou uma de cada vez.";
        } elseif (empty($_FILES['notas_fiscais']['name'][0])) {
            $erro = 'Nenhum arquivo selecionado.';
        } else {
            $upload_dir = __DIR__ . '/../uploads/notas/' . $rat_id . '/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
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
                $fname = 'nota_' . time() . '_' . $idx . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_dir . $fname)) {
                    $existing[] = $fname;
                    $uploaded++;
                } else {
                    $erros_arquivo[] = "{$nome_original}: Falha ao mover arquivo";
                }
            }
            if ($_is_admin_mode) {
                $stmt2 = $db->prepare("UPDATE rats SET notas_fiscais_json = ? WHERE id = ?");
                $stmt2->execute([json_encode($existing), $rat_id]);
            } else {
                $stmt2 = $db->prepare("UPDATE rats SET notas_fiscais_json = ? WHERE id = ? AND id_tecnico = ?");
                $stmt2->execute([json_encode($existing), $rat_id, $tecnico_id]);
            }
            if ($uploaded > 0) {
                $sucesso = "✅ {$uploaded} nota(s) fiscal(is) enviada(s) com sucesso!";
            }
            if (!empty($erros_arquivo)) {
                $erro = ($uploaded > 0 ? '' : '❌ ') . 'Problemas: ' . implode(' | ', $erros_arquivo);
            } elseif ($uploaded === 0) {
                $erro = '❌ Nenhum arquivo foi enviado. Tente novamente.';
            }
            $rat = array_merge($rat, ['notas_fiscais_json' => json_encode($existing)]);
        }
    }

    // ── Apagar Nota Fiscal
    if ($acao === 'apagar_nota') {
        $arquivo = basename($_POST['nota_arquivo'] ?? '');
        if ($arquivo) {
            $existing = json_decode($rat['notas_fiscais_json'] ?? '[]', true) ?: [];
            $existing = array_values(array_filter($existing, function($f) use ($arquivo) { return basename($f) !== $arquivo; }));
            $fpath = __DIR__ . '/../uploads/notas/' . $rat_id . '/' . $arquivo;
            if (file_exists($fpath)) unlink($fpath);
            if ($_is_admin_mode) {
                $stmt2 = $db->prepare("UPDATE rats SET notas_fiscais_json = ? WHERE id = ?");
                $stmt2->execute([json_encode($existing), $rat_id]);
            } else {
                $stmt2 = $db->prepare("UPDATE rats SET notas_fiscais_json = ? WHERE id = ? AND id_tecnico = ?");
                $stmt2->execute([json_encode($existing), $rat_id, $tecnico_id]);
            }
            $sucesso = 'Nota removida.';
            $rat = array_merge($rat, ['notas_fiscais_json' => json_encode($existing)]);
        }
    }
}

// Decodificar JSON e normalizar arrays
$turnos = !empty($rat['turnos_json']) ? json_decode($rat['turnos_json'], true) : [];
// Garantir 5 elementos vazios se necessário
while (count($turnos) < 5) {
    $turnos[] = ['dia' => '', 'manha' => ['inicio' => '', 'fim' => ''], 'tarde' => ['inicio' => '', 'fim' => '']];
}

$horas_viajadas = !empty($rat['horas_viajadas_json']) ? json_decode($rat['horas_viajadas_json'], true) : [];
// Garantir 4 elementos vazios se necessário
while (count($horas_viajadas) < 4) {
    $horas_viajadas[] = ['data' => '', 'ida' => '', 'volta' => ''];
}

$kms_rodados = !empty($rat['kms_rodados_json']) ? json_decode($rat['kms_rodados_json'], true) : [];
// Garantir 4 elementos vazios se necessário
while (count($kms_rodados) < 4) {
    $kms_rodados[] = ['data' => '', 'ida' => '', 'volta' => '', 'total' => ''];
}

$orcamento_items = !empty($rat['orcamento_json']) ? json_decode($rat['orcamento_json'], true) : [];
if (!is_array($orcamento_items)) $orcamento_items = [];

$iniciais = strtoupper(substr($tecnico_nome, 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Editar RAT <?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?> | Sistema RAT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #034c8c;
            --primary-light: #0466c8;
            --primary-dark: #022d54;
            --secondary: #f58220;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
            --teal: #14b8a6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --sidebar-width: 260px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            color: var(--gray-900);
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            position: fixed;
            left: 0; top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
            z-index: 200;
            display: flex;
            flex-direction: column;
            animation: sidebarSlide 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            overflow: hidden;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }

        @keyframes sidebarSlide {
            from { opacity: 0; transform: translateX(-100%); }
            to { opacity: 1; transform: translateX(0); }
        }

        .sidebar-brand {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand .logo-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .sidebar-brand img {
            height: 36px;
            filter: brightness(0) invert(1);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .sidebar-brand img:hover { transform: scale(1.1) rotate(-3deg); }

        .sidebar-brand h2 { color: white; font-size: 18px; font-weight: 700; }

        .version-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.9);
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
        }

        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-section { margin-bottom: 24px; }

        .nav-section-title {
            font-size: 10px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 0 12px 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            margin-bottom: 4px;
        }

        .nav-item i { width: 20px; text-align: center; font-size: 16px; transition: transform 0.3s; }

        .nav-item:hover {
            background: rgba(255,255,255,0.12);
            color: white;
            transform: translateX(4px);
        }

        .nav-item:hover i { transform: scale(1.2); }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 600;
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 50%;
            transform: translateY(-50%);
            width: 4px; height: 24px;
            background: white;
            border-radius: 0 4px 4px 0;
        }

        .sidebar-cta {
            margin: 0 12px 16px;
            padding: 14px;
            background: linear-gradient(135deg, var(--secondary), #ff9940);
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: white;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s;
            box-shadow: 0 4px 16px rgba(245, 130, 32, 0.4);
        }

        .sidebar-cta:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(245, 130, 32, 0.5); }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }

        .sidebar-user { display: flex; align-items: center; gap: 12px; }

        .sidebar-user-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0d9488);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            font-weight: 700;
        }

        .sidebar-user-info { flex: 1; }
        .sidebar-user-info strong { display: block; color: white; font-size: 13px; }
        .sidebar-user-info small { color: rgba(255,255,255,0.5); font-size: 11px; }

        .sidebar-logout {
            color: rgba(255,255,255,0.5);
            font-size: 16px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .sidebar-logout:hover { color: var(--danger); transform: scale(1.2); }

        /* ========== MAIN ========== */
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; }

        .topbar {
            position: sticky;
            top: 0;
            height: 70px;
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            animation: fadeInDown 0.6s ease-out 0.3s both;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .topbar-left h1 { font-size: 18px; font-weight: 700; color: var(--gray-900); }
        .topbar-breadcrumb { font-size: 13px; color: var(--gray-400); }
        .topbar-breadcrumb a { color: var(--primary); text-decoration: none; }

        .topbar-right { display: flex; align-items: center; gap: 12px; }

        .topbar-btn {
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            padding: 0 14px;
            font-size: 13px;
            font-weight: 600;
            gap: 6px;
        }

        .topbar-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.15);
        }

        .rat-badge-top {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        /* ========== CONTENT ========== */
        .content { padding: 32px; max-width: 960px; }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: cardPop 0.5s ease both;
        }

        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }

        /* Section cards */
        .section {
            background: white;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 20px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .section:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border-color: rgba(3, 76, 140, 0.2);
        }

        .section:nth-child(1) { animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.1s both; }
        .section:nth-child(2) { animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.15s both; }
        .section:nth-child(3) { animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.2s both; }
        .section:nth-child(4) { animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.25s both; }
        .section:nth-child(5) { animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.3s both; }
        .section:nth-child(6) { animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.35s both; }
        .section:nth-child(7) { animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.4s both; }
        .section:nth-child(8) { animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.45s both; }
        .section:nth-child(9) { animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.5s both; }

        @keyframes cardPop {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-100);
        }

        .section-icon {
            width: 44px; height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 12px;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .section-title { font-size: 17px; font-weight: 700; color: var(--gray-900); }
        .section-description { font-size: 13px; color: var(--gray-400); margin-top: 2px; }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .form-grid.cols-3 { grid-template-columns: repeat(3, 1fr); }
        .form-grid.cols-4 { grid-template-columns: repeat(4, 1fr); }
        .form-grid.full { grid-template-columns: 1fr; }

        .form-group { display: flex; flex-direction: column; }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 14px;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s;
            background: var(--gray-50);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(3, 76, 140, 0.08);
        }

        .form-group textarea { resize: vertical; min-height: 100px; }

        /* Tipo Serviço radio cards */
        .service-type-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 8px;
        }

        .service-type-card {
            position: relative;
        }

        .service-type-card input[type="radio"],
        .service-type-card input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .service-type-card label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 18px 12px;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-transform: none;
            letter-spacing: 0;
            text-align: center;
            background: var(--gray-50);
        }

        .service-type-card label:hover {
            border-color: var(--primary);
            background: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(3, 76, 140, 0.1);
        }

        .service-type-card input:checked + label {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(3,76,140,0.05), rgba(4,102,200,0.08));
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.15);
        }

        .service-type-card input:checked + label .st-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }

        .st-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            background: var(--gray-100);
            color: var(--gray-500);
            transition: all 0.3s;
        }

        .st-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
        }

        /* Compact Table Layout for Jornadas/Viagens/KM */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }

        .data-table thead th {
            background: var(--gray-50);
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-500);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--gray-200);
        }

        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }

        .data-table tbody td {
            padding: 8px 6px;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        .data-table tbody tr:hover { background: rgba(3, 76, 140, 0.02); }

        .data-table .row-num {
            font-weight: 700;
            color: var(--primary);
            font-size: 12px;
            width: 40px;
            text-align: center;
        }

        .data-table input {
            width: 100%;
            padding: 8px 10px;
            border: 1.5px solid var(--gray-200);
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            background: var(--gray-50);
            transition: all 0.2s;
        }

        .data-table input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 2px rgba(3, 76, 140, 0.08);
        }

        .table-wrapper {
            overflow-x: auto;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
        }

        /* Info box */
        .info-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #eff6ff, #eef2ff);
            border: 1px solid rgba(3, 76, 140, 0.1);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            color: var(--primary);
            margin-bottom: 16px;
        }

        .info-box i { font-size: 16px; }

        /* Despesas grid */
        .despesas-total {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            margin-top: 16px;
            font-weight: 700;
        }

        .despesas-total .total-value { font-size: 24px; letter-spacing: -0.5px; }

        .despesa-row {
            display: grid;
            grid-template-columns: 1fr 90px;
            gap: 10px;
            align-items: end;
        }
        .despesa-row .form-group { margin-bottom: 0; }
        .qty-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; color: var(--gray-500); }

        /* Canvas */
        .canvas-container {
            border: 2px dashed var(--gray-200);
            border-radius: 12px;
            padding: 16px;
            background: var(--gray-50);
            margin-bottom: 20px;
        }

        .canvas-container canvas {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            display: block;
            width: 100%;
            height: 180px;
            background: white;
            cursor: crosshair;
            margin-bottom: 12px;
            touch-action: none;
            -ms-touch-action: none;
        }

        .canvas-buttons { display: flex; gap: 8px; }

        .btn-canvas {
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-canvas-clear {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .btn-canvas-clear:hover { background: #fecaca; transform: translateY(-2px); }

        .signature-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .signature-label:not(:first-of-type) { margin-top: 24px; }

        /* Checkboxes */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: var(--gray-50);
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }

        .checkbox-group:hover {
            background: white;
            border-color: var(--primary);
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px; height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .checkbox-group label {
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            padding-top: 8px;
        }

        .btn {
            padding: 12px 28px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(3, 76, 140, 0.35);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.35);
        }

        .btn-secondary {
            background: white;
            color: var(--primary);
            border: 2px solid var(--gray-200);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        /* Voice Dictation Styles */
        .label-with-voice {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            gap: 8px;
        }

        .label-with-voice label {
            margin-bottom: 0 !important;
        }

        .btn-voice-dictation {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            color: var(--primary, #034c8c);
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            user-select: none;
            line-height: 1.2;
        }

        .btn-voice-dictation:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: #02386e;
            transform: translateY(-1px);
        }

        .btn-voice-dictation.btn-voice-sm {
            padding: 3px 8px;
            font-size: 11px;
        }

        .btn-voice-dictation i {
            font-size: 12px;
            transition: transform 0.2s ease;
        }

        .btn-voice-dictation.is-listening {
            background: linear-gradient(135deg, #ef4444, #f97316);
            border-color: #dc2626;
            color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25);
            animation: voice-pulse 1.5s infinite;
        }

        .btn-voice-dictation.is-listening i {
            animation: mic-bounce 0.8s infinite alternate ease-in-out;
        }

        @keyframes voice-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        @keyframes mic-bounce {
            0% { transform: scale(1); }
            100% { transform: scale(1.25); }
        }

        .field-listening {
            border-color: #f97316 !important;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2) !important;
            background-color: #fffaf5 !important;
        }

        .voice-live-preview {
            display: none;
            font-size: 11px;
            color: #c2410c;
            background: #fff7ed;
            border: 1px dashed #fdba74;
            border-radius: 6px;
            padding: 4px 8px;
            margin-top: 4px;
            font-style: italic;
        }

        .voice-live-preview.active {
            display: block;
        }

        /* Mobile */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px; left: 14px;
            z-index: 300;
            width: 40px; height: 40px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.3);
            -webkit-tap-highlight-color: transparent;
        }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 199;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        .sidebar-backdrop.active { display: block; }

        /* ===== TABLET (max 1024px) ===== */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; animation: none !important; }
            .sidebar.open { transform: translateX(0); }
            .mobile-toggle { display: flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; }
            .content { padding: 24px 20px; }
            .topbar { padding: 0 20px 0 68px; }
        }

        /* ===== PHONE (max 768px) ===== */
        @media (max-width: 768px) {
            .content { padding: 16px 12px; max-width: 100%; }
            .topbar { height: 60px; padding: 0 12px 0 68px; }
            .mobile-toggle { top: 10px; }
            .topbar-left h1 { font-size: 15px; }
            .topbar-breadcrumb { font-size: 11px; }
            .topbar-right { gap: 8px; }
            .topbar-btn { height: 32px; padding: 0 10px; font-size: 12px; }
            .rat-badge-top { padding: 4px 10px; font-size: 11px; }

            .section { padding: 20px 16px; margin-bottom: 14px; border-radius: 12px; }
            .section-header { gap: 10px; margin-bottom: 18px; padding-bottom: 12px; }
            .section-icon { width: 38px; height: 38px; font-size: 15px; border-radius: 10px; }
            .section-title { font-size: 15px; }
            .section-description { font-size: 12px; }

            .form-grid,
            .form-grid.cols-3,
            .form-grid.cols-4 { grid-template-columns: 1fr; gap: 12px; }

            .form-group label { font-size: 11px; margin-bottom: 5px; }
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 12px 14px;
                font-size: 16px; /* prevent iOS zoom */
                border-radius: 10px;
            }

            /* Service type cards: stack vertically */
            .service-type-group { grid-template-columns: 1fr; gap: 10px; }
            .service-type-card label { flex-direction: row; padding: 14px 16px; gap: 12px; }
            .st-icon { width: 38px; height: 38px; font-size: 16px; flex-shrink: 0; }
            .st-label { font-size: 14px; }

            /* Despesas qty/dias */
            .despesa-row { grid-template-columns: 1fr 80px; gap: 8px; }

            /* Tables: horizontal scroll with bigger inputs */
            .table-wrapper { border-radius: 8px; -webkit-overflow-scrolling: touch; }
            .data-table { min-width: 580px; }
            .data-table thead th { padding: 8px 8px; font-size: 10px; white-space: nowrap; }
            .data-table tbody td { padding: 6px 4px; }
            .data-table input {
                padding: 10px 8px;
                font-size: 16px; /* prevent iOS zoom */
                min-width: 80px;
            }

            /* Despesas */
            .despesas-total { flex-direction: column; text-align: center; gap: 8px; padding: 14px; }
            .despesas-total .total-value { font-size: 22px; }

            /* Info box */
            .info-box { font-size: 12px; padding: 10px 12px; }

            /* Canvas signatures - taller for finger drawing */
            .canvas-container { padding: 12px; }
            .canvas-container canvas { height: 200px; touch-action: none; }
            .signature-label { font-size: 13px; }

            /* Checkboxes - bigger tap targets */
            .checkbox-group { padding: 16px 14px; }
            .checkbox-group input[type="checkbox"] { width: 22px; height: 22px; }
            .checkbox-group label { font-size: 13px; }

            /* Buttons - full width, bigger */
            .button-group { flex-direction: column; gap: 10px; padding-top: 4px; }
            .btn { width: 100%; justify-content: center; padding: 14px 20px; font-size: 15px; border-radius: 12px; }
        }

        /* ===== SMALL PHONE (max 380px) ===== */
        @media (max-width: 380px) {
            .content { padding: 12px 8px; }
            .section { padding: 16px 12px; }
            .topbar { padding: 0 8px 0 54px; }
            .topbar-left h1 { font-size: 14px; }
            .mobile-toggle { width: 34px; height: 34px; top: 13px; left: 8px; font-size: 15px; border-radius: 8px; }
            .data-table { min-width: 520px; }
        }

        @media print {
            .sidebar, .topbar, .mobile-toggle, .button-group, .btn-canvas-clear, .sidebar-cta, .sidebar-backdrop { display: none !important; }
            .main-content { margin-left: 0; }
            .section { box-shadow: none; border: 1px solid #ddd; page-break-inside: avoid; }
            .content { padding: 0; max-width: 100%; }
            body { background: white; }
        }
    </style>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>
    <button class="mobile-toggle" id="mobileToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo-row">
                <img src="https://madetech.com.br/wp-content/uploads/2026/05/Logo-Madetech-Final.webp" alt="Madetech">
                <h2>Sistema RAT</h2>
            </div>
            <span class="version-badge"><?= $_is_admin_mode ? 'Admin v1.0' : 'Técnico v1.0' ?></span>
        </div>

        <nav class="sidebar-nav">
            <?php if ($_is_admin_mode): ?>
            <div class="nav-section">
                <div class="nav-section-title">Administração</div>
                <a href="<?= BASE_URL ?>admin/index.php" class="nav-item"><i class="fas fa-chart-pie"></i> Dashboard</a>
                <a href="<?= BASE_URL ?>admin/tecnicos.php" class="nav-item"><i class="fas fa-users"></i> Técnicos</a>
                <a href="<?= BASE_URL ?>admin/criar-rat.php" class="nav-item"><i class="fas fa-plus-circle"></i> Criar RAT</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">RAT Atual</div>
                <a href="<?= BASE_URL ?>admin/editar-rat.php?id=<?= $rat_id ?>" class="nav-item active">
                    <i class="fas fa-file-edit"></i> <?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Acesso</div>
                <a href="<?= BASE_URL ?>tecnico/login.php" class="nav-item">
                    <i class="fas fa-exchange-alt"></i>
                    Painel Técnico
                </a>
                <a href="https://madetech.com.br/checklist/" target="_blank" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    Checklist
                </a>
                <a href="https://madetech.com.br/checklist-maquina/" target="_blank" class="nav-item">
                    <i class="fas fa-cogs"></i>
                    Máquinas Checklist
                </a>
            </div>
            <?php else: ?>
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="index.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i> Meu Painel
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">RAT Atual</div>
                <a href="editar-rat.php?id=<?= $rat_id ?>" class="nav-item active">
                    <i class="fas fa-file-edit"></i> <?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?>
                </a>
                <a href="criar-rat.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i> Criar Novo
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <?php if (!$_is_admin_mode): ?>
        <a href="criar-rat.php" class="sidebar-cta">
            <i class="fas fa-plus-circle"></i> Novo RAT
        </a>
        <?php endif; ?>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar"><?= $_is_admin_mode ? 'A' : $iniciais ?></div>
                <div class="sidebar-user-info">
                    <strong><?= $_is_admin_mode ? 'Administrador' : htmlspecialchars($tecnico_nome) ?></strong>
                    <small><?= $_is_admin_mode ? 'admin@madetech.com.br' : htmlspecialchars($tecnico_email) ?></small>
                </div>
                <a href="../api/logout.php" class="sidebar-logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <div>
                    <h1>Editar RAT</h1>
                    <div class="topbar-breadcrumb">
                        <?php if ($_is_admin_mode): ?>
                            <a href="<?= BASE_URL ?>admin/index.php">Admin</a> / Editar RAT (Técnico: <?= htmlspecialchars($tecnico_nome) ?>)
                        <?php else: ?>
                            <a href="index.php">Painel</a> / Editar RAT
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="topbar-right">
                <span class="rat-badge-top"><i class="fas fa-file-alt"></i> <?= htmlspecialchars(exibirNumeroRAT($rat['numero'], $rat['numero_sequencial'] ?? null)) ?></span>
                <?php if ($_is_admin_mode): ?>
                    <a href="<?= BASE_URL ?>admin/index.php" class="topbar-btn"><i class="fas fa-arrow-left"></i> Voltar</a>
                <?php else: ?>
                    <a href="index.php" class="topbar-btn"><i class="fas fa-arrow-left"></i> Voltar</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <?php if ($erro): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="acao" value="atualizar">

                <!-- 1 EQUIPAMENTO -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-cogs"></i></div>
                        <div>
                            <div class="section-title">Informações do Equipamento</div>
                            <div class="section-description">Dados técnicos da máquina</div>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label>Tipo de Equipamento</label>
                                <button type="button" class="btn-voice-dictation btn-voice-sm" data-voice-target="equipamento" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar</span>
                                </button>
                            </div>
                            <input type="text" id="equipamento" name="equipamento" value="<?= htmlspecialchars($rat['equipamento'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label>Modelo da Máquina</label>
                                <button type="button" class="btn-voice-dictation btn-voice-sm" data-voice-target="modelo_maquina" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar</span>
                                </button>
                            </div>
                            <input type="text" id="modelo_maquina" name="modelo_maquina" value="<?= htmlspecialchars($rat['modelo_maquina'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label>Matrícula/Série</label>
                                <button type="button" class="btn-voice-dictation btn-voice-sm" data-voice-target="matricula" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar</span>
                                </button>
                            </div>
                            <input type="text" id="matricula" name="matricula" value="<?= htmlspecialchars($rat['matricula'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Em Garantia?</label>
                            <select name="garantia">
                                <option value="Não" <?= ($rat['garantia'] ?? 'Não') === 'Não' ? 'selected' : '' ?>>Não</option>
                                <option value="Sim" <?= ($rat['garantia'] ?? '') === 'Sim' ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 2 TIPO DE SERVIÇO -->
                <?php $tipos_selecionados = array_map('trim', explode(',', $rat['tipo_servico'] ?? '')); ?>
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-wrench"></i></div>
                        <div>
                            <div class="section-title">Tipo de Serviço</div>
                            <div class="section-description">Selecione até 2 tipos de atendimento realizado</div>
                        </div>
                    </div>
                    <div class="service-type-group">
                        <div class="service-type-card">
                            <input type="checkbox" name="tipo_servico[]" id="tipo_manutencao" value="Manutenção" class="tipo-servico-check" <?= in_array('Manutenção', $tipos_selecionados) ? 'checked' : '' ?>>
                            <label for="tipo_manutencao">
                                <div class="st-icon"><i class="fas fa-tools"></i></div>
                                <span class="st-label">Manutenção</span>
                            </label>
                        </div>
                        <div class="service-type-card">
                            <input type="checkbox" name="tipo_servico[]" id="tipo_treinamento" value="Treinamento" class="tipo-servico-check" <?= in_array('Treinamento', $tipos_selecionados) ? 'checked' : '' ?>>
                            <label for="tipo_treinamento">
                                <div class="st-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                <span class="st-label">Treinamento</span>
                            </label>
                        </div>
                        <div class="service-type-card">
                            <input type="checkbox" name="tipo_servico[]" id="tipo_instalacao" value="Instalação" class="tipo-servico-check" <?= in_array('Instalação', $tipos_selecionados) ? 'checked' : '' ?>>
                            <label for="tipo_instalacao">
                                <div class="st-icon"><i class="fas fa-truck-loading"></i></div>
                                <span class="st-label">Instalação</span>
                            </label>
                        </div>
                    </div>
                    <small style="color: #64748b; margin-top: 4px; display: block;">Máximo de 2 opções</small>
                </div>

                <!-- 3 DEFEITO -->
                <div class="section" id="section_defeito">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div>
                            <div class="section-title">Problema Constatado</div>
                            <div class="section-description">Descreva o problema encontrado</div>
                        </div>
                    </div>
                    <div class="form-grid full">
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label>Descrição do Defeito</label>
                                <button type="button" class="btn-voice-dictation" data-voice-target="defeito_constatado" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar por Voz</span>
                                </button>
                            </div>
                            <textarea id="defeito_constatado" name="defeito_constatado" required><?= htmlspecialchars($rat['defeito_constatado'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 4 TRABALHO EXECUTADO -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-check-double"></i></div>
                        <div>
                            <div class="section-title">Serviço Executado</div>
                            <div class="section-description">Descreva a solução implementada</div>
                        </div>
                    </div>
                    <div class="form-grid full">
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label>Descrição do Trabalho</label>
                                <button type="button" class="btn-voice-dictation" data-voice-target="trabalho_executado" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar por Voz</span>
                                </button>
                            </div>
                            <textarea id="trabalho_executado" name="trabalho_executado" required><?= htmlspecialchars($rat['trabalho_executado'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ORÇAMENTO -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div>
                            <div class="section-title">Orçamento</div>
                            <div class="section-description">Itens do orçamento para o cliente</div>
                        </div>
                    </div>
                    <div id="orcamento-container">
                        <?php if (!empty($orcamento_items)): ?>
                            <?php foreach ($orcamento_items as $idx => $item): ?>
                            <div class="form-grid" style="align-items:end; margin-bottom:8px;" data-orcamento-row>
                                <div class="form-group" style="flex:3;">
                                    <label>Nome do Item</label>
                                    <input type="text" name="orcamento_nome[]" value="<?= htmlspecialchars($item['nome'] ?? '') ?>" placeholder="Ex: Peça de reposição">
                                </div>
                                <div class="form-group" style="flex:1; max-width:100px;">
                                    <label>Qtd.</label>
                                    <input type="number" name="orcamento_qtd[]" value="<?= htmlspecialchars($item['quantidade'] ?? '1') ?>" min="1">
                                </div>
                                <div class="form-group" style="flex:0; min-width:40px;">
                                    <button type="button" class="btn-canvas btn-canvas-clear" onclick="this.closest('[data-orcamento-row]').remove()" style="margin-bottom:0; padding:10px 12px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="addOrcamentoRow()" class="btn btn-secondary" style="margin-top:12px; width: auto; padding: 10px 20px;">
                        <i class="fas fa-plus"></i> Adicionar Item
                    </button>
                </div>

                <!-- 5 JORNADAS + VIAGENS + KM  (tabelas compactas) -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div>
                            <div class="section-title">Jornadas de Trabalho</div>
                            <div class="section-description">Horários de trabalho (até 5 dias)</div>
                        </div>
                    </div>
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        Preencha apenas os dias trabalhados. Deixe em branco os demais.
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Dia</th>
                                    <th>Data</th>
                                    <th>Manhã Início</th>
                                    <th>Manhã Fim</th>
                                    <th>Tarde Início</th>
                                    <th>Tarde Fim</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <tr>
                                    <td class="row-num"><?= $i ?></td>
                                    <td><input type="date" name="turno_dia_<?= $i ?>" value="<?= isset($turnos[$i-1]) ? $turnos[$i-1]['dia'] : '' ?>"></td>
                                    <td><input type="time" name="turno_manha_inicio_<?= $i ?>" value="<?= isset($turnos[$i-1]) ? $turnos[$i-1]['manha']['inicio'] : '' ?>" onchange="calcTotalHorasTrabalhadas()"></td>
                                    <td><input type="time" name="turno_manha_fim_<?= $i ?>" value="<?= isset($turnos[$i-1]) ? $turnos[$i-1]['manha']['fim'] : '' ?>" onchange="calcTotalHorasTrabalhadas()"></td>
                                    <td><input type="time" name="turno_tarde_inicio_<?= $i ?>" value="<?= isset($turnos[$i-1]) ? $turnos[$i-1]['tarde']['inicio'] : '' ?>" onchange="calcTotalHorasTrabalhadas()"></td>
                                    <td><input type="time" name="turno_tarde_fim_<?= $i ?>" value="<?= isset($turnos[$i-1]) ? $turnos[$i-1]['tarde']['fim'] : '' ?>" onchange="calcTotalHorasTrabalhadas()"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="total-row" style="display:flex; align-items:center; gap:12px; margin-top:10px; padding:10px 16px; background:var(--bg-light,#f0f4f8); border-radius:8px; font-weight:600;">
                        <i class="fas fa-clock" style="color:var(--primary,#0078D4);"></i>
                        <span>Total Horas Trabalhadas:</span>
                        <span id="total_horas_trabalhadas_display" style="color:var(--primary,#0078D4); font-size:1.1em;">0h 00min</span>
                        <input type="hidden" name="total_horas_trabalhadas" id="total_horas_trabalhadas" value="<?= htmlspecialchars($rat['total_horas_trabalhadas'] ?? '') ?>">
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-plane"></i></div>
                        <div>
                            <div class="section-title">Horas de Viagem</div>
                            <div class="section-description">Tempo de deslocamento ida e volta</div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Data</th>
                                    <th>Horas Ida</th>
                                    <th>Horas Volta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <tr>
                                    <td class="row-num"><?= $i ?></td>
                                    <td><input type="date" name="hora_viagem_data_<?= $i ?>" value="<?= isset($horas_viajadas[$i-1]) ? $horas_viajadas[$i-1]['data'] : '' ?>"></td>
                                    <td><input type="text" inputmode="decimal" name="hora_viagem_ida_<?= $i ?>" value="<?= isset($horas_viajadas[$i-1]) ? normalizarHoraViagemNumero($horas_viajadas[$i-1]['ida']) : '' ?>" placeholder="Ex: 0h30, 1:30 ou 1,5" title="Aceita 0h30, 1:30, 2h 15min ou 1,5" oninput="calcTotalHorasViajadas()" onchange="calcTotalHorasViajadas()"></td>
                                    <td><input type="text" inputmode="decimal" name="hora_viagem_volta_<?= $i ?>" value="<?= isset($horas_viajadas[$i-1]) ? normalizarHoraViagemNumero($horas_viajadas[$i-1]['volta']) : '' ?>" placeholder="Ex: 0h30, 1:30 ou 1,5" title="Aceita 0h30, 1:30, 2h 15min ou 1,5" oninput="calcTotalHorasViajadas()" onchange="calcTotalHorasViajadas()"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="total-row" style="display:flex; align-items:center; gap:12px; margin-top:10px; padding:10px 16px; background:var(--bg-light,#f0f4f8); border-radius:8px; font-weight:600;">
                        <i class="fas fa-route" style="color:var(--primary,#0078D4);"></i>
                        <span>Total Horas Viajadas:</span>
                        <span id="total_horas_viajadas_display" style="color:var(--primary,#0078D4); font-size:1.1em;">0.0h</span>
                        <input type="hidden" name="total_horas_viajadas_calc" id="total_horas_viajadas_calc" value="<?= htmlspecialchars($rat['total_horas_viajadas_calc'] ?? '') ?>">
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-road"></i></div>
                        <div>
                            <div class="section-title">Quilometragem</div>
                            <div class="section-description">Distância percorrida nos trajetos</div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table class="data-table">
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
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <tr>
                                    <td class="row-num"><?= $i ?></td>
                                    <td><input type="date" name="km_data_<?= $i ?>" value="<?= isset($kms_rodados[$i-1]) ? $kms_rodados[$i-1]['data'] : '' ?>"></td>
                                    <td><input type="number" step="0.1" name="km_ida_<?= $i ?>" value="<?= isset($kms_rodados[$i-1]) ? $kms_rodados[$i-1]['ida'] : '' ?>" placeholder="0.0" onchange="calcKmTotal(<?= $i ?>)"></td>
                                    <td><input type="number" step="0.1" name="km_volta_<?= $i ?>" value="<?= isset($kms_rodados[$i-1]) ? $kms_rodados[$i-1]['volta'] : '' ?>" placeholder="0.0" onchange="calcKmTotal(<?= $i ?>)"></td>
                                    <td><input type="number" step="0.1" name="km_total_<?= $i ?>" value="<?= isset($kms_rodados[$i-1]) ? $kms_rodados[$i-1]['total'] : '' ?>" placeholder="0.0" id="km_total_<?= $i ?>"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 8 DESPESAS -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-wallet"></i></div>
                        <div>
                            <div class="section-title">Despesas Adicionais</div>
                            <div class="section-description">Custos extras do atendimento</div>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="despesa-row">
                            <div class="form-group">
                                <label><i class="fas fa-receipt"></i> Pedágio (R$)</label>
                                <input type="number" step="0.01" name="pedagio" value="<?= htmlspecialchars($rat['pedagio'] ?? 0) ?>" onchange="calcTotal()">
                            </div>
                            <div class="form-group">
                                <label class="qty-label"><i class="fas fa-hashtag"></i> Qtd.</label>
                                <input type="number" min="0" name="pedagio_qtd" value="<?= htmlspecialchars($rat['pedagio_qtd'] ?? 0) ?>" onchange="calcTotal()">
                            </div>
                        </div>
                        <div class="despesa-row">
                            <div class="form-group">
                                <label><i class="fas fa-hotel"></i> Hospedagem (R$)</label>
                                <input type="number" step="0.01" name="hospedagem" value="<?= htmlspecialchars($rat['hospedagem'] ?? 0) ?>" onchange="calcTotal()">
                            </div>
                            <div class="form-group">
                                <label class="qty-label"><i class="fas fa-calendar-day"></i> Dias</label>
                                <input type="number" min="0" name="hospedagem_dias" value="<?= htmlspecialchars($rat['hospedagem_dias'] ?? 0) ?>" onchange="calcTotal()">
                            </div>
                        </div>
                        <div class="despesa-row">
                            <div class="form-group">
                                <label><i class="fas fa-utensils"></i> Alimentação (R$)</label>
                                <input type="number" step="0.01" name="alimentacao" value="<?= htmlspecialchars($rat['alimentacao'] ?? 0) ?>" onchange="calcTotal()">
                            </div>
                            <div class="form-group">
                                <label class="qty-label"><i class="fas fa-hashtag"></i> Qtd.</label>
                                <input type="number" min="0" name="alimentacao_qtd" value="<?= htmlspecialchars($rat['alimentacao_qtd'] ?? 0) ?>" onchange="calcTotal()">
                            </div>
                        </div>
                        <div class="despesa-row">
                            <div class="form-group">
                                <label><i class="fas fa-ellipsis-h"></i> Outros (R$)</label>
                                <input type="number" step="0.01" name="outros_despesas" value="<?= htmlspecialchars($rat['outros_despesas'] ?? 0) ?>" onchange="calcTotal()">
                            </div>
                            <div class="form-group">
                                <label class="qty-label"><i class="fas fa-hashtag"></i> Qtd.</label>
                                <input type="number" min="0" name="outros_qtd" value="<?= htmlspecialchars($rat['outros_qtd'] ?? 0) ?>" onchange="calcTotal()">
                            </div>
                        </div>
                    </div>
                    <div class="form-grid full" style="margin-top: 12px;">
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label><i class="fas fa-pen"></i> Especificar "Outros"</label>
                                <button type="button" class="btn-voice-dictation btn-voice-sm" data-voice-target="outros_despesas_desc" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar</span>
                                </button>
                            </div>
                            <input type="text" id="outros_despesas_desc" name="outros_despesas_desc" value="<?= htmlspecialchars($rat['outros_despesas_desc'] ?? '') ?>" placeholder="Ex: Combustível extra, peças, táxi...">
                        </div>
                    </div>
                    <div class="despesas-total">
                        <span><i class="fas fa-coins"></i> Total de Despesas</span>
                        <span class="total-value" id="total_display">R$ <?= number_format(
                            (($rat['pedagio'] ?? 0) * max(($rat['pedagio_qtd'] ?? 0),0)) + (($rat['hospedagem'] ?? 0) * max(($rat['hospedagem_dias'] ?? 0),0)) + (($rat['alimentacao'] ?? 0) * max(($rat['alimentacao_qtd'] ?? 0),0)) + (($rat['outros_despesas'] ?? 0) * max(($rat['outros_qtd'] ?? 0),0)),
                            2, ',', '.'
                        ) ?></span>
                    </div>
                </div>

                <!-- TÉCNICOS ADICIONAIS -->
                <div id="tecnicos_adicionais_container">
                    <?php 
                    $tecnicos_adicionais = json_decode($rat['tecnicos_adicionais_json'] ?? '[]', true) ?: [];
                    foreach ($tecnicos_adicionais as $index => $tec): 
                    ?>
                    <div class="section tecnico-adicional-block" data-index="<?= $index ?>">
                        <div class="section-header" style="justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <div class="section-icon" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="fas fa-user-plus"></i></div>
                                <div>
                                    <div class="section-title">Técnico Adicional: <?= htmlspecialchars($tec['nome'] ?? '') ?></div>
                                    <div class="section-description">Preencha os dados extras para este técnico</div>
                                </div>
                            </div>
                            <button type="button" class="btn-canvas btn-canvas-clear" onclick="removerTecnicoAdicional(this)" style="color: #ef4444; border-color: #ef4444;"><i class="fas fa-trash"></i> Remover</button>
                        </div>

                        <!-- Nome -->
                        <div class="form-grid full">
                            <div class="form-group">
                                <label>Nome do Técnico Adicional *</label>
                                <input type="text" name="tecnico_extra_nome[]" value="<?= htmlspecialchars($tec['nome'] ?? '') ?>" required>
                            </div>
                        </div>

                        <!-- Turnos Extra -->
                        <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-calendar-alt"></i> Jornadas de Trabalho</h4>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Dia</th>
                                        <th>Data</th>
                                        <th>Manhã Início</th>
                                        <th>Manhã Fim</th>
                                        <th>Tarde Início</th>
                                        <th>Tarde Fim</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <tr>
                                        <td class="row-num"><?= $i ?></td>
                                        <td><input type="date" name="tecnico_extra_turno_dia_<?= $i ?>[]" value="<?= htmlspecialchars($tec['turnos'][$i-1]['dia'] ?? '') ?>"></td>
                                        <td><input type="time" name="tecnico_extra_turno_manha_inicio_<?= $i ?>[]" value="<?= htmlspecialchars($tec['turnos'][$i-1]['manha']['inicio'] ?? '') ?>"></td>
                                        <td><input type="time" name="tecnico_extra_turno_manha_fim_<?= $i ?>[]" value="<?= htmlspecialchars($tec['turnos'][$i-1]['manha']['fim'] ?? '') ?>"></td>
                                        <td><input type="time" name="tecnico_extra_turno_tarde_inicio_<?= $i ?>[]" value="<?= htmlspecialchars($tec['turnos'][$i-1]['tarde']['inicio'] ?? '') ?>"></td>
                                        <td><input type="time" name="tecnico_extra_turno_tarde_fim_<?= $i ?>[]" value="<?= htmlspecialchars($tec['turnos'][$i-1]['tarde']['fim'] ?? '') ?>"></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Viagens Extra -->
                        <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-plane"></i> Horas de Viagem</h4>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Data</th>
                                        <th>Horas Ida</th>
                                        <th>Horas Volta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <tr>
                                        <td class="row-num"><?= $i ?></td>
                                        <td><input type="date" name="tecnico_extra_hora_viagem_data_<?= $i ?>[]" value="<?= htmlspecialchars($tec['horas_viajadas'][$i-1]['data'] ?? '') ?>"></td>
                                        <td><input type="text" inputmode="decimal" name="tecnico_extra_hora_viagem_ida_<?= $i ?>[]" value="<?= htmlspecialchars($tec['horas_viajadas'][$i-1]['ida'] ?? '') ?>"></td>
                                        <td><input type="text" inputmode="decimal" name="tecnico_extra_hora_viagem_volta_<?= $i ?>[]" value="<?= htmlspecialchars($tec['horas_viajadas'][$i-1]['volta'] ?? '') ?>"></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- KMs Extra -->
                        <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-road"></i> Quilometragem</h4>
                        <div class="table-wrapper">
                            <table class="data-table">
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
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <tr>
                                        <td class="row-num"><?= $i ?></td>
                                        <td><input type="date" name="tecnico_extra_km_data_<?= $i ?>[]" value="<?= htmlspecialchars($tec['kms_rodados'][$i-1]['data'] ?? '') ?>"></td>
                                        <td><input type="number" step="0.1" name="tecnico_extra_km_ida_<?= $i ?>[]" value="<?= htmlspecialchars($tec['kms_rodados'][$i-1]['ida'] ?? '') ?>"></td>
                                        <td><input type="number" step="0.1" name="tecnico_extra_km_volta_<?= $i ?>[]" value="<?= htmlspecialchars($tec['kms_rodados'][$i-1]['volta'] ?? '') ?>"></td>
                                        <td><input type="number" step="0.1" name="tecnico_extra_km_total_<?= $i ?>[]" value="<?= htmlspecialchars($tec['kms_rodados'][$i-1]['total'] ?? '') ?>"></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Despesas Extra -->
                        <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-wallet"></i> Despesas Adicionais</h4>
                        <div class="form-grid">
                            <div class="despesa-row">
                                <div class="form-group"><label><i class="fas fa-receipt"></i> Pedágio (R$)</label><input type="number" step="0.01" name="tecnico_extra_pedagio[]" value="<?= htmlspecialchars($tec['despesas']['pedagio'] ?? 0) ?>"></div>
                                <div class="form-group"><label class="qty-label"><i class="fas fa-hashtag"></i> Qtd.</label><input type="number" min="0" name="tecnico_extra_pedagio_qtd[]" value="<?= htmlspecialchars($tec['despesas']['pedagio_qtd'] ?? 0) ?>"></div>
                            </div>
                            <div class="despesa-row">
                                <div class="form-group"><label><i class="fas fa-hotel"></i> Hospedagem (R$)</label><input type="number" step="0.01" name="tecnico_extra_hospedagem[]" value="<?= htmlspecialchars($tec['despesas']['hospedagem'] ?? 0) ?>"></div>
                                <div class="form-group"><label class="qty-label"><i class="fas fa-calendar-day"></i> Dias</label><input type="number" min="0" name="tecnico_extra_hospedagem_dias[]" value="<?= htmlspecialchars($tec['despesas']['hospedagem_dias'] ?? 0) ?>"></div>
                            </div>
                            <div class="despesa-row">
                                <div class="form-group"><label><i class="fas fa-utensils"></i> Alimentação (R$)</label><input type="number" step="0.01" name="tecnico_extra_alimentacao[]" value="<?= htmlspecialchars($tec['despesas']['alimentacao'] ?? 0) ?>"></div>
                                <div class="form-group"><label class="qty-label"><i class="fas fa-hashtag"></i> Qtd.</label><input type="number" min="0" name="tecnico_extra_alimentacao_qtd[]" value="<?= htmlspecialchars($tec['despesas']['alimentacao_qtd'] ?? 0) ?>"></div>
                            </div>
                            <div class="despesa-row">
                                <div class="form-group"><label><i class="fas fa-ellipsis-h"></i> Outros (R$)</label><input type="number" step="0.01" name="tecnico_extra_outros_despesas[]" value="<?= htmlspecialchars($tec['despesas']['outros_despesas'] ?? 0) ?>"></div>
                                <div class="form-group"><label class="qty-label"><i class="fas fa-hashtag"></i> Qtd.</label><input type="number" min="0" name="tecnico_extra_outros_qtd[]" value="<?= htmlspecialchars($tec['despesas']['outros_qtd'] ?? 0) ?>"></div>
                            </div>
                        </div>
                        <div class="form-grid full" style="margin-top: 12px;">
                            <div class="form-group"><label><i class="fas fa-pen"></i> Especificar "Outros"</label><input type="text" name="tecnico_extra_outros_despesas_desc[]" value="<?= htmlspecialchars($tec['despesas']['outros_despesas_desc'] ?? '') ?>"></div>
                        </div>

                        <!-- Assinatura Extra -->
                        <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-signature"></i> Assinatura deste Técnico</h4>
                        <div class="canvas-container">
                            <canvas id="canvas_extra_<?= $index ?>" class="canvas_extra"></canvas>
                            <div class="canvas-buttons">
                                <button type="button" class="btn-canvas btn-canvas-clear" onclick="limparAssinatura('canvas_extra_<?= $index ?>')"><i class="fas fa-eraser"></i> Limpar</button>
                            </div>
                            <input type="hidden" id="assinatura_extra_<?= $index ?>" name="tecnico_extra_assinatura[]" value="<?= htmlspecialchars($tec['assinatura'] ?? '') ?>">
                        </div>
                        <div class="checkbox-group" style="margin-top:10px;">
                            <input type="checkbox" id="aceite_extra_<?= $index ?>" name="tecnico_extra_aceite[]" value="1" <?= ($tec['aceite'] ?? 0) ? 'checked' : '' ?>>
                            <label for="aceite_extra_<?= $index ?>">Este técnico confirma a conclusão do atendimento</label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="section" style="text-align: center; background: transparent; box-shadow: none; border: none; padding: 0;">
                    <button type="button" class="btn btn-secondary" onclick="addTecnicoAdicional()" style="width: 100%; border-style: dashed; border-width: 2px; padding: 15px; font-size: 15px;">
                        <i class="fas fa-user-plus"></i> Adicionar Mais Um Técnico na RAT
                    </button>
                </div>

                <!-- 9 ASSINATURAS PRINCIPAIS -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-signature"></i></div>
                        <div>
                            <div class="section-title">Assinaturas Digitais</div>
                            <div class="section-description">Confirme a conclusão do atendimento</div>
                        </div>
                    </div>

                    <div class="info-box">
                        <i class="fas fa-exclamation-circle"></i>
                        Ambas as assinaturas são obrigatórias para enviar o RAT.
                    </div>

                    <div class="signature-label"><i class="fas fa-user"></i> Assinatura do Cliente</div>
                    <div class="canvas-container">
                        <canvas id="canvas_cliente"></canvas>
                        <div class="canvas-buttons">
                            <button type="button" class="btn-canvas btn-canvas-clear" onclick="limparAssinatura('canvas_cliente')"><i class="fas fa-eraser"></i> Limpar</button>
                        </div>
                        <input type="hidden" id="assinatura_cliente" name="assinatura_cliente" value="<?= htmlspecialchars($rat['assinatura_cliente'] ?? '') ?>">
                    </div>

                    <div class="form-grid" style="margin-bottom: 24px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Nome do Responsável *</label>
                            <input type="text" name="nome_assinatura" value="<?= htmlspecialchars($rat['nome_assinatura'] ?? '') ?>" required placeholder="Nome completo de quem assinou">
                        </div>
                        <div class="form-group">
                            <label>Cargo *</label>
                            <input type="text" name="cargo_assinatura" value="<?= htmlspecialchars($rat['cargo_assinatura'] ?? '') ?>" required placeholder="Ex: Gerente">
                        </div>
                        <div class="form-group">
                            <label>CPF *</label>
                            <input type="text" name="cpf_assinatura" value="<?= htmlspecialchars($rat['cpf_assinatura'] ?? '') ?>" required placeholder="000.000.000-00" oninput="this.value = this.value.replace(/\D/g, '').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')" maxlength="14">
                        </div>
                    </div>

                    <div class="signature-label"><i class="fas fa-hard-hat"></i> Assinatura do Técnico</div>
                    <div class="canvas-container">
                        <canvas id="canvas_tecnico"></canvas>
                        <div class="canvas-buttons">
                            <button type="button" class="btn-canvas btn-canvas-clear" onclick="limparAssinatura('canvas_tecnico')"><i class="fas fa-eraser"></i> Limpar</button>
                        </div>
                        <input type="hidden" id="assinatura_tecnico" name="assinatura_tecnico" value="<?= htmlspecialchars($rat['assinatura_tecnico'] ?? '') ?>">
                    </div>
                </div>

                <!-- 10 ACEITES -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-clipboard-check"></i></div>
                        <div>
                            <div class="section-title">Confirmação</div>
                            <div class="section-description">Confirme que os dados estão corretos</div>
                        </div>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="aceite_cliente" name="aceite_cliente" value="1" <?= ($rat['aceite_cliente'] ?? 0) ? 'checked' : '' ?>>
                        <label for="aceite_cliente">Cliente confirma os dados do atendimento</label>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="aceite_tecnico" name="aceite_tecnico" value="1" <?= ($rat['aceite_tecnico'] ?? 0) ? 'checked' : '' ?>>
                        <label for="aceite_tecnico">Técnico confirma a conclusão do atendimento</label>
                    </div>
                </div>

                <!-- BOTÕES -->
                <div class="section">
                    <div class="button-group">
                        <button type="submit" name="acao" value="atualizar" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Rascunho</button>
                        <button type="submit" name="acao" value="enviar" class="btn btn-success"><i class="fas fa-paper-plane"></i> Enviar RAT</button>
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                    </div>
                </div>
            </form>

            <!-- ══════════════════════════════════════════════
                 NOTAS FISCAIS — formulário separado
            ══════════════════════════════════════════════ -->
            <span id="notas-fiscais"></span>
            <div class="section" style="margin-top:24px;">
                <div class="section-header">
                    <div class="section-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fas fa-receipt"></i></div>
                    <div>
                        <div class="section-title">Notas Fiscais &amp; Comprovantes de Despesas</div>
                        <div class="section-description">Anexe aqui os comprovantes das despesas do atendimento para reembolso.</div>
                    </div>
                </div>

                <!-- Bloco explicativo -->
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px 18px;margin-bottom:20px;">
                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <i class="fas fa-info-circle" style="color:#d97706;font-size:20px;margin-top:2px;flex-shrink:0;"></i>
                        <div style="font-size:13px;color:#78350f;line-height:1.7;">
                            <strong style="display:block;margin-bottom:6px;font-size:14px;">Como funciona esta seção?</strong>
                            Aqui você deve anexar os <strong>comprovantes das despesas pagas durante o atendimento</strong>, como:
                            pedágio, alimentação, hospedagem, combustível ou peças compradas.
                            <br><br>
                            <strong>⚠️ Atenção — dois passos separados:</strong>
                            <ol style="margin:6px 0 0 16px;padding:0;">
                                <li>Preencha o RAT normalmente e clique em <strong>"Salvar Rascunho"</strong> para salvar os dados.</li>
                                <li>Depois, escolha os arquivos abaixo e clique em <strong>"Enviar Notas"</strong> para salvar os comprovantes.</li>
                            </ol>
                            <br>
                            Os arquivos ficam armazenados no sistema e serão conferidos pelo financeiro da Madetech para liberar o reembolso.
                            <strong>Eles não são incluídos no e-mail enviado ao cliente.</strong>
                        </div>
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
                        <div style="position:relative;border:1px solid var(--gray-200);border-radius:10px;overflow:hidden;width:130px;background:var(--gray-50);box-shadow:0 2px 6px rgba(0,0,0,.06);">
                            <?php if ($is_pdf): ?>
                            <a href="<?= $nota_url ?>" target="_blank" style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:110px;text-decoration:none;color:var(--gray-700);font-size:12px;font-weight:600;gap:6px;padding:8px;">
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
                    <p style="color:var(--gray-400);font-size:13px;margin-bottom:16px;">Nenhuma nota fiscal anexada ainda.</p>
                    <?php endif; ?>

                    <form id="formUploadNotas" method="POST" enctype="multipart/form-data" style="margin:0;">
                        <input type="hidden" name="acao" value="upload_notas">
                        <label style="display:block;font-weight:600;color:var(--gray-700);margin-bottom:8px;">Adicionar Notas Fiscais</label>
                        <input type="file" id="inputNotas" accept="image/*,application/pdf" multiple
                            style="display:block;width:100%;padding:12px 14px;border:2px dashed var(--gray-300);border-radius:10px;background:var(--gray-50);font-size:13px;cursor:pointer;color:var(--gray-600);">
                        <p style="margin-top:6px;font-size:12px;color:var(--gray-400);">Aceita fotos (JPG, PNG, WebP) e PDFs. Fotos são comprimidas automaticamente.</p>
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
    </main>

    <script>
        // Canvas signatures
        let isDrawing = false;

        function inicializarCanvas(canvasId) {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth;
            canvas.height = 180;
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Load existing
            const hidden = document.getElementById(canvasId.replace('canvas_', 'assinatura_'));
            if (hidden && hidden.value) {
                const img = new Image();
                img.onload = () => ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                img.src = hidden.value;
            }

            const getPos = (e) => {
                const rect = canvas.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : e;
                return { x: t.clientX - rect.left, y: t.clientY - rect.top };
            };

            const start = (e) => {
                e.preventDefault();
                isDrawing = true;
                const p = getPos(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
            };

            const move = (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                const p = getPos(e);
                ctx.lineTo(p.x, p.y);
                ctx.lineWidth = 2;
                ctx.strokeStyle = '#034c8c';
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.stroke();
            };

            const end = () => {
                isDrawing = false;
                if(hidden) {
                    hidden.value = canvas.toDataURL();
                    hidden.dispatchEvent(new Event('input', { bubbles: true }));
                }
            };

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            canvas.addEventListener('mouseup', end);
            canvas.addEventListener('mouseleave', end);
            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', move, { passive: false });
            canvas.addEventListener('touchend', end);
        }

        function limparAssinatura(canvasId) {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            const hidden = document.getElementById(canvasId.replace('canvas_', 'assinatura_'));
            if(hidden) {
                hidden.value = '';
                hidden.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        // Orçamento - adicionar nova linha
        function addOrcamentoRow() {
            const container = document.getElementById('orcamento-container');
            const row = document.createElement('div');
            row.className = 'form-grid';
            row.style.cssText = 'align-items:end; margin-bottom:8px;';
            row.setAttribute('data-orcamento-row', '');
            row.innerHTML = `
                <div class="form-group" style="flex:3;">
                    <label>Nome do Item</label>
                    <input type="text" name="orcamento_nome[]" placeholder="Ex: Peça de reposição">
                </div>
                <div class="form-group" style="flex:1; max-width:100px;">
                    <label>Qtd.</label>
                    <input type="number" name="orcamento_qtd[]" value="1" min="1">
                </div>
                <div class="form-group" style="flex:0; min-width:40px;">
                    <button type="button" class="btn-canvas btn-canvas-clear" onclick="this.closest('[data-orcamento-row]').remove()" style="margin-bottom:0; padding:10px 12px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(row);
            // Focus the new name input
            row.querySelector('input[name="orcamento_nome[]"]').focus();
        }

        // KM auto-calc
        function calcKmTotal(i) {
            const ida = parseFloat(document.querySelector('[name="km_ida_' + i + '"]').value) || 0;
            const volta = parseFloat(document.querySelector('[name="km_volta_' + i + '"]').value) || 0;
            document.getElementById('km_total_' + i).value = (ida + volta).toFixed(1);
        }

        // Total Horas Trabalhadas auto-calc
        function timeToMinutes(timeStr) {
            if (!timeStr) return 0;
            const parts = timeStr.split(':');
            return parseInt(parts[0]) * 60 + parseInt(parts[1]);
        }

        function calcTotalHorasTrabalhadas() {
            let totalMin = 0;
            for (let i = 1; i <= 5; i++) {
                const mi = document.querySelector('[name="turno_manha_inicio_' + i + '"]').value;
                const mf = document.querySelector('[name="turno_manha_fim_' + i + '"]').value;
                const ti = document.querySelector('[name="turno_tarde_inicio_' + i + '"]').value;
                const tf = document.querySelector('[name="turno_tarde_fim_' + i + '"]').value;
                if (mi && mf) {
                    const diff = timeToMinutes(mf) - timeToMinutes(mi);
                    if (diff > 0) totalMin += diff;
                }
                if (ti && tf) {
                    const diff = timeToMinutes(tf) - timeToMinutes(ti);
                    if (diff > 0) totalMin += diff;
                }
            }
            const h = Math.floor(totalMin / 60);
            const m = totalMin % 60;
            document.getElementById('total_horas_trabalhadas_display').textContent = h + 'h ' + String(m).padStart(2, '0') + 'min';
            document.getElementById('total_horas_trabalhadas').value = h + ':' + String(m).padStart(2, '0');
        }

        function horaViagemParaDecimal(valor) {
            const texto = (valor || '').toString().trim().toLowerCase();
            if (!texto) return 0;

            const normalizado = texto.replace(',', '.');
            if (!Number.isNaN(Number(normalizado))) {
                return Number(normalizado);
            }

            const hhmm = normalizado.match(/^(\d{1,2}):(\d{1,2})$/);
            if (hhmm) {
                return parseInt(hhmm[1], 10) + (parseInt(hhmm[2], 10) / 60);
            }

            const hMin = normalizado.match(/^(\d+)h\s*(\d{1,2})?\s*(min)?$/);
            if (hMin) {
                return parseInt(hMin[1], 10) + (parseInt(hMin[2] || '0', 10) / 60);
            }

            const hColado = normalizado.match(/^(\d+)h(\d{1,2})$/);
            if (hColado) {
                return parseInt(hColado[1], 10) + (parseInt(hColado[2], 10) / 60);
            }

            return 0;
        }

        // Total Horas Viajadas auto-calc
        function calcTotalHorasViajadas() {
            let total = 0;
            for (let i = 1; i <= 4; i++) {
                const ida = horaViagemParaDecimal(document.querySelector('[name="hora_viagem_ida_' + i + '"]').value);
                const volta = horaViagemParaDecimal(document.querySelector('[name="hora_viagem_volta_' + i + '"]').value);
                total += ida + volta;
            }
            document.getElementById('total_horas_viajadas_display').textContent = total.toFixed(1) + 'h';
            document.getElementById('total_horas_viajadas_calc').value = total.toFixed(1);
        }

        // Despesas auto-calc
        function calcTotal() {
            const p = parseFloat(document.querySelector('[name="pedagio"]').value) || 0;
            const pq = Math.max(parseInt(document.querySelector('[name="pedagio_qtd"]').value) || 0, 0);
            const h = parseFloat(document.querySelector('[name="hospedagem"]').value) || 0;
            const hd = Math.max(parseInt(document.querySelector('[name="hospedagem_dias"]').value) || 0, 0);
            const a = parseFloat(document.querySelector('[name="alimentacao"]').value) || 0;
            const aq = Math.max(parseInt(document.querySelector('[name="alimentacao_qtd"]').value) || 0, 0);
            const o = parseFloat(document.querySelector('[name="outros_despesas"]').value) || 0;
            const oq = Math.max(parseInt(document.querySelector('[name="outros_qtd"]').value) || 0, 0);
            const total = (p * pq) + (h * hd) + (a * aq) + (o * oq);
            document.getElementById('total_display').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
        }

        // Sidebar toggle with backdrop
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('open');
            backdrop.classList.toggle('active');
            // Change hamburger to X
            const icon = document.querySelector('#mobileToggle i');
            if (sidebar.classList.contains('open')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        }

        // Close sidebar on nav click (mobile)
        document.querySelectorAll('.sidebar .nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 1024) toggleSidebar();
            });
        });

        // Resize canvas on orientation change
        function resizeCanvases() {
            ['canvas_cliente', 'canvas_tecnico'].forEach(id => {
                const canvas = document.getElementById(id);
                const hidden = document.getElementById('assinatura_' + id.split('_')[1]);
                const data = hidden.value;
                canvas.width = canvas.offsetWidth;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = 'white';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                if (data) {
                    const img = new Image();
                    img.onload = () => ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    img.src = data;
                }
            });
        }
        window.addEventListener('orientationchange', () => setTimeout(resizeCanvases, 300));

        document.addEventListener('DOMContentLoaded', () => {
            const mainForm = document.querySelector('form');
            const ratId = "<?= htmlspecialchars($rat_id) ?>";
            const storageKey = 'rat_draft_' + ratId;

            function serializeForm(form) {
                const obj = {};
                const elements = form.querySelectorAll('input, select, textarea');
                elements.forEach(el => {
                    if (!el.name) return;
                    if (el.type === 'file') return;
                    
                    if (el.type === 'checkbox') {
                        if (!obj[el.name]) obj[el.name] = [];
                        if (el.checked) obj[el.name].push(el.value);
                    } else if (el.type === 'radio') {
                        if (el.checked) obj[el.name] = el.value;
                    } else {
                        obj[el.name] = el.value;
                    }
                });
                return obj;
            }

            function restoreForm(form, data) {
                for (let name in data) {
                    const value = data[name];
                    const elements = form.querySelectorAll(`[name="${name}"]`);
                    if (!elements.length) continue;
                    
                    if (elements[0].type === 'checkbox') {
                        elements.forEach(el => {
                            el.checked = Array.isArray(value) ? value.includes(el.value) : (el.value === value);
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    } else if (elements[0].type === 'radio') {
                        elements.forEach(el => {
                            el.checked = (el.value === value);
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    } else {
                        elements.forEach(el => {
                            el.value = value;
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    }
                }
            }

            // Check draft in local storage
            const draftStr = localStorage.getItem(storageKey);
            if (draftStr) {
                try {
                    const draft = JSON.parse(draftStr);
                    if (Object.keys(draft).length > 2) {
                        restoreForm(mainForm, draft);
                        
                        // Show a banner notifying user that a draft was restored
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-success';
                        alertDiv.style.margin = '20px 0';
                        alertDiv.innerHTML = `
                            <div style="display:flex; justify-content:space-between; align-items:center; width:100%; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <i class="fas fa-check-circle"></i>
                                    <strong>Rascunho recuperado!</strong> Restauramos automaticamente o que você digitou neste aparelho.
                                </div>
                                <button type="button" id="btnDiscardDraft" style="background:#ef4444; color:#fff; border:none; padding:6px 12px; border-radius:6px; font-weight:600; cursor:pointer; font-size:12px;">Descartar e Recarregar original</button>
                            </div>
                        `;
                        const contentDiv = document.querySelector('.content');
                        if (contentDiv) {
                            contentDiv.insertBefore(alertDiv, contentDiv.firstChild);
                        }

                        document.getElementById('btnDiscardDraft').addEventListener('click', () => {
                            localStorage.removeItem(storageKey);
                            window.location.reload();
                        });
                    }
                } catch(e) {
                    console.error('Erro ao restaurar rascunho local:', e);
                }
            }

            function saveDraftLocal() {
                const data = serializeForm(mainForm);
                localStorage.setItem(storageKey, JSON.stringify(data));
            }

            // Save draft locally on input and change
            mainForm.addEventListener('input', saveDraftLocal);
            mainForm.addEventListener('change', saveDraftLocal);

            // Clean localStorage when form is submitted successfully (either updating or sending)
            mainForm.addEventListener('submit', () => {
                localStorage.removeItem(storageKey);
            });

            inicializarCanvas('canvas_cliente');
            inicializarCanvas('canvas_tecnico');
            calcTotalHorasTrabalhadas();
            calcTotalHorasViajadas();

            // Auto-Save background logic (evita perda de dados ao trocar de app)
            let isAutoSaving = false;
            let lastSaveTime = Date.now();
            let autoSaveTimer = null;

            function autoSaveRascunho() {
                if (isAutoSaving) return;
                isAutoSaving = true;

                const formData = new FormData(mainForm);
                formData.set('acao', 'autosave');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(r => r.json()).then(data => {
                    isAutoSaving = false;
                    lastSaveTime = Date.now();
                }).catch(err => {
                    isAutoSaving = false;
                });
            }

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    const formData = new FormData(mainForm);
                    formData.set('acao', 'autosave');
                    navigator.sendBeacon(window.location.href, formData);
                }
            });

            mainForm.addEventListener('input', () => {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(autoSaveRascunho, 3000);
            });
            mainForm.addEventListener('change', () => {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(autoSaveRascunho, 1000);
            });
            
            setInterval(() => {
                if (Date.now() - lastSaveTime > 60000) autoSaveRascunho();
            }, 60000);

            // Limitar seleção de Tipo de Serviço a no máximo 2 e verificar regra de ocultar Defeito
            const tipoChecks = document.querySelectorAll('.tipo-servico-check');
            const sectionDefeito = document.getElementById('section_defeito');
            const textareaDefeito = document.querySelector('textarea[name="defeito_constatado"]');
            
            function atualizarVisibilidadeDefeito() {
                if (!sectionDefeito || !textareaDefeito) return;
                const marcados = Array.from(document.querySelectorAll('.tipo-servico-check:checked')).map(cb => cb.value);
                const isTreinamento = marcados.includes('Treinamento');
                const isInstalacao = marcados.includes('Instalação');
                
                if (isTreinamento && isInstalacao) {
                    sectionDefeito.style.display = 'none';
                    textareaDefeito.required = false;
                } else {
                    sectionDefeito.style.display = '';
                    textareaDefeito.required = true;
                }
            }

            tipoChecks.forEach(cb => {
                cb.addEventListener('change', () => {
                    const marcados = document.querySelectorAll('.tipo-servico-check:checked');
                    if (marcados.length > 2) {
                        cb.checked = false;
                        alert('Você pode selecionar no máximo 2 tipos de serviço.');
                    }
                    atualizarVisibilidadeDefeito();
                });
            });
            atualizarVisibilidadeDefeito();
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
    // ── Funções para Técnicos Adicionais ────────────────────────
    let tecnicoExtraIndex = document.querySelectorAll('.tecnico-adicional-block').length;

    function addTecnicoAdicional() {
        const container = document.getElementById('tecnicos_adicionais_container');
        const template = document.getElementById('template_tecnico_adicional').content.cloneNode(true);
        const currentIndex = tecnicoExtraIndex++;
        
        // Setup canvas ID dynamically
        const canvas = template.querySelector('.canvas_extra');
        const inputAssinatura = template.querySelector('input[type="hidden"]');
        const btnLimpar = template.querySelector('.btn-canvas-clear:not([onclick="removerTecnicoAdicional(this)"])');
        const checkboxAceite = template.querySelector('input[type="checkbox"]');
        const labelAceite = template.querySelector('.checkbox-group label');
        
        canvas.id = 'canvas_extra_' + currentIndex;
        inputAssinatura.id = 'assinatura_extra_' + currentIndex;
        btnLimpar.setAttribute('onclick', 'limparAssinatura("canvas_extra_' + currentIndex + '")');
        checkboxAceite.id = 'aceite_extra_' + currentIndex;
        if(labelAceite) labelAceite.setAttribute('for', 'aceite_extra_' + currentIndex);
        
        container.appendChild(template);
        inicializarCanvas('canvas_extra_' + currentIndex);
    }

    function removerTecnicoAdicional(btn) {
        if(confirm('Tem certeza que deseja remover este técnico adicional?')) {
            btn.closest('.tecnico-adicional-block').remove();
        }
    }

    // Inicializar os canvases dos técnicos adicionais que vieram do banco de dados
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.canvas_extra').forEach(canvas => {
            inicializarCanvas(canvas.id);
        });

        // ── Rascunho Inteligente Offline (localStorage) ────────────────────────
        const ratId = <?= json_encode($rat_id) ?>;
        const localDraftKey = 'rat_draft_offline_' + ratId;

        function saveOfflineDraft() {
            if (!mainForm) return;
            const formData = new FormData(mainForm);
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) continue;
                if (key === 'acao') continue;
                
                if (data[key] !== undefined) {
                    if (!Array.isArray(data[key])) {
                        data[key] = [data[key]];
                    }
                    data[key].push(value);
                } else {
                    data[key] = value;
                }
            }
            
            data['_timestamp'] = Date.now();
            localStorage.setItem(localDraftKey, JSON.stringify(data));
            
            let notif = document.getElementById('offline-save-notif');
            if (!notif) {
                notif = document.createElement('div');
                notif.id = 'offline-save-notif';
                notif.style.cssText = 'position:fixed; bottom:20px; right:20px; background:rgba(16, 185, 129, 0.9); color:white; padding:8px 12px; border-radius:8px; font-size:12px; z-index:9999; box-shadow:0 4px 6px rgba(0,0,0,0.1); opacity:0; transition:opacity 0.3s; pointer-events:none;';
                notif.innerHTML = '<i class="fas fa-check-circle"></i> Salvo no aparelho (Rascunho Inteligente)';
                document.body.appendChild(notif);
            }
            notif.style.opacity = '1';
            setTimeout(() => notif.style.opacity = '0', 2500);
        }

        // Auto-save a cada 10 segundos
        setInterval(saveOfflineDraft, 10000);

        // Limpar rascunho local se for salvo no servidor (Rascunho ou Envio final)
        mainForm.addEventListener('submit', () => {
            localStorage.removeItem(localDraftKey);
        });

        // Verificar rascunho ao carregar
        const savedDraftStr = localStorage.getItem(localDraftKey);
        if (savedDraftStr) {
            try {
                const savedDraft = JSON.parse(savedDraftStr);
                // Verifica se o rascunho tem menos de 24 horas (opcional, mas bom pra limpar lixo antigo)
                if (Date.now() - savedDraft['_timestamp'] < 24 * 60 * 60 * 1000) {
                    const banner = document.createElement('div');
                    banner.style.cssText = 'background:#fef3c7; border-bottom:1px solid #f59e0b; color:#92400e; padding:12px 20px; display:flex; justify-content:space-between; align-items:center; font-weight:500; font-size:14px; flex-wrap:wrap; gap:10px; margin-bottom:15px; border-radius:6px;';
                    banner.innerHTML = `
                        <div><i class="fas fa-exclamation-triangle"></i> <strong>Rascunho Inteligente:</strong> Você possui um preenchimento offline não salvo no servidor.</div>
                        <div style="display:flex; gap:10px;">
                            <button id="btn_restore_draft" type="button" style="background:#f59e0b; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold;"><i class="fas fa-undo"></i> Restaurar Dados</button>
                            <button id="btn_discard_draft" type="button" style="background:transparent; color:#92400e; border:1px solid #92400e; padding:6px 12px; border-radius:4px; cursor:pointer;"><i class="fas fa-trash"></i> Descartar</button>
                        </div>
                    `;
                    document.querySelector('.topbar').insertAdjacentElement('afterend', banner);
                    
                    document.getElementById('btn_discard_draft').addEventListener('click', (e) => {
                        e.preventDefault();
                        localStorage.removeItem(localDraftKey);
                        banner.remove();
                    });
                    
                    document.getElementById('btn_restore_draft').addEventListener('click', (e) => {
                        e.preventDefault();
                        restoreOfflineDraft(savedDraft);
                        banner.remove();
                    });
                } else {
                    localStorage.removeItem(localDraftKey);
                }
            } catch(e) {}
        }

        function restoreOfflineDraft(data) {
            // Lidar com técnicos adicionais se houver no rascunho
            if (data['tecnico_extra_nome[]']) {
                const extraCount = Array.isArray(data['tecnico_extra_nome[]']) ? data['tecnico_extra_nome[]'].length : 1;
                while (document.querySelectorAll('.tecnico-adicional-block').length < extraCount) {
                    addTecnicoAdicional(); 
                }
            }

            for (const key in data) {
                if (key === '_timestamp') continue;
                const values = Array.isArray(data[key]) ? data[key] : [data[key]];
                const inputs = document.querySelectorAll(`[name="${key}"]`);
                
                inputs.forEach((input, index) => {
                    if (values[index] !== undefined) {
                        if (input.type === 'checkbox' || input.type === 'radio') {
                            input.checked = (input.value === values[index]);
                            // Disparar evento change
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        } else {
                            input.value = values[index];
                            
                            // Restaurar canvases das assinaturas
                            if (key.includes('assinatura') && values[index].length > 100) {
                                const canvasId = input.id.replace('assinatura', 'canvas');
                                const canvas = document.getElementById(canvasId);
                                if (canvas) {
                                    const ctx = canvas.getContext('2d');
                                    const img = new Image();
                                    img.onload = () => {
                                        ctx.clearRect(0,0,canvas.width,canvas.height);
                                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                                    };
                                    img.src = values[index];
                                }
                            }
                        }
                    }
                });
            }
            alert('Rascunho offline restaurado com sucesso!');
        }

        // ==========================================
        // DITADO POR VOZ INTELIGENTE (Web Speech API)
        // ==========================================
        (function initVoiceDictation() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            
            if (!SpeechRecognition) {
                console.warn('Reconhecimento de fala não suportado nativamente neste navegador.');
                document.querySelectorAll('.btn-voice-dictation').forEach(btn => {
                    btn.title = 'Ditado por voz não suportado neste navegador (use Chrome, Edge ou Safari)';
                    btn.style.opacity = '0.7';
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        alert('O reconhecimento de voz direto não é suportado pelo seu navegador atual. Recomendamos utilizar o Google Chrome, Microsoft Edge ou Safari.');
                    });
                });
                return;
            }

            let recognition = null;
            let currentActiveBtn = null;
            let currentTargetInput = null;
            let isListening = false;
            let restartTimeout = null;

            function formatSpeechText(text) {
                if (!text) return '';
                let formatted = text
                    .replace(/\b(ponto final|ponto)\b/gi, '.')
                    .replace(/\b(vírgula)\b/gi, ',')
                    .replace(/\b(dois pontos)\b/gi, ':')
                    .replace(/\b(ponto e vírgula)\b/gi, ';')
                    .replace(/\b(interrogação|ponto de interrogação)\b/gi, '?')
                    .replace(/\b(exclamação|ponto de exclamação)\b/gi, '!')
                    .replace(/\b(nova linha|novo parágrafo|parágrafo)\b/gi, '\n');

                // Limpar espaços antes de pontuações
                formatted = formatted.replace(/\s+([.,!?:;])/g, '$1');
                // Limpar múltiplos espaços
                formatted = formatted.replace(/[ \t]+/g, ' ').trim();

                if (!formatted) return '';
                return formatted.charAt(0).toUpperCase() + formatted.slice(1);
            }

            function startRecognitionSession() {
                if (!isListening || !currentTargetInput) return;

                try {
                    recognition = new SpeechRecognition();
                    recognition.lang = 'pt-BR';
                    recognition.continuous = false; // Impede duplicação em cascata no Android/Chrome Mobile
                    recognition.interimResults = true;
                    recognition.maxAlternatives = 1;

                    let phraseResult = '';

                    recognition.onstart = function() {
                        if (currentActiveBtn && currentTargetInput) {
                            currentActiveBtn.classList.add('is-listening');
                            const span = currentActiveBtn.querySelector('span');
                            if (span) span.textContent = 'Ouvindo...';
                            currentTargetInput.classList.add('field-listening');
                            
                            let preview = currentTargetInput.parentNode.querySelector('.voice-live-preview');
                            if (!preview) {
                                preview = document.createElement('div');
                                preview.className = 'voice-live-preview';
                                currentTargetInput.parentNode.appendChild(preview);
                            }
                            preview.textContent = '🎙️ Ouvindo... fale agora (toque no botão para encerrar)';
                            preview.classList.add('active');
                        }
                    };

                    recognition.onresult = function(event) {
                        if (!currentTargetInput || !isListening) return;

                        let interim = '';
                        for (let i = 0; i < event.results.length; ++i) {
                            if (event.results[i].isFinal) {
                                phraseResult = event.results[i][0].transcript;
                            } else {
                                interim = event.results[i][0].transcript;
                            }
                        }

                        const preview = currentTargetInput.parentNode.querySelector('.voice-live-preview');
                        if (preview && interim) {
                            preview.textContent = '🎙️ Ouvindo: "' + interim + '"';
                        }
                    };

                    recognition.onerror = function(event) {
                        console.warn('SpeechRecognition error:', event.error);
                        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                            alert('Permissão de microfone negada. Permita o microfone no navegador para ditar.');
                            stopDictation();
                        }
                    };

                    recognition.onend = function() {
                        if (phraseResult && currentTargetInput) {
                            const formatted = formatSpeechText(phraseResult);
                            if (formatted) {
                                const currentVal = (currentTargetInput.value || '').trim();
                                if (currentVal) {
                                    const lastChar = currentVal.slice(-1);
                                    const separator = (lastChar === '\n') ? '' : ' ';
                                    currentTargetInput.value = currentVal + separator + formatted;
                                } else {
                                    currentTargetInput.value = formatted;
                                }

                                currentTargetInput.dispatchEvent(new Event('input', { bubbles: true }));
                                currentTargetInput.dispatchEvent(new Event('change', { bubbles: true }));

                                if (currentTargetInput.scrollHeight) {
                                    currentTargetInput.scrollTop = currentTargetInput.scrollHeight;
                                }

                                const preview = currentTargetInput.parentNode.querySelector('.voice-live-preview');
                                if (preview) {
                                    preview.textContent = '✅ Trecho gravado!';
                                }
                            }
                            phraseResult = '';
                        }

                        // Se o técnico ainda não clicou em parar, continua ouvindo a próxima frase
                        if (isListening) {
                            clearTimeout(restartTimeout);
                            restartTimeout = setTimeout(() => {
                                if (isListening) startRecognitionSession();
                            }, 200);
                        } else {
                            stopDictation();
                        }
                    };

                    recognition.start();
                } catch(err) {
                    console.error('Erro ao iniciar reconhecimento de fala:', err);
                    stopDictation();
                }
            }

            function stopDictation() {
                isListening = false;
                clearTimeout(restartTimeout);

                if (recognition) {
                    try { recognition.stop(); } catch(e) {}
                    recognition = null;
                }

                if (currentActiveBtn) {
                    currentActiveBtn.classList.remove('is-listening');
                    const span = currentActiveBtn.querySelector('span');
                    if (span) {
                        const isSm = currentActiveBtn.classList.contains('btn-voice-sm');
                        span.textContent = isSm ? 'Ditar' : (currentActiveBtn.getAttribute('data-voice-target')?.includes('defeito') || currentActiveBtn.getAttribute('data-voice-target')?.includes('trabalho') ? 'Ditar por Voz' : 'Ditar');
                    }
                }
                if (currentTargetInput) {
                    currentTargetInput.classList.remove('field-listening');
                    const preview = currentTargetInput.parentNode.querySelector('.voice-live-preview');
                    if (preview) {
                        preview.classList.remove('active');
                    }
                }
                currentActiveBtn = null;
                currentTargetInput = null;
            }

            function toggleDictation(btn, targetInput) {
                if (!targetInput) return;

                if (isListening && currentActiveBtn === btn) {
                    stopDictation();
                    return;
                }

                if (isListening) {
                    stopDictation();
                }

                currentActiveBtn = btn;
                currentTargetInput = targetInput;
                isListening = true;

                startRecognitionSession();
            }

            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-voice-dictation');
                if (!btn) return;
                
                e.preventDefault();
                e.stopPropagation();

                const targetId = btn.getAttribute('data-voice-target');
                let targetInput = null;

                if (targetId) {
                    targetInput = document.getElementById(targetId) || document.querySelector(`[name="${targetId}"]`);
                }
                
                if (!targetInput) {
                    const formGroup = btn.closest('.form-group') || btn.closest('.section') || btn.parentNode;
                    if (formGroup) {
                        targetInput = formGroup.querySelector('textarea, input[type="text"]');
                    }
                }

                if (targetInput) {
                    toggleDictation(btn, targetInput);
                }
            });
        })();
    });

    </script>

    <!-- Template for new Técnicos Adicionais -->
    <template id="template_tecnico_adicional">
        <div class="section tecnico-adicional-block">
            <div class="section-header" style="justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div class="section-icon" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="fas fa-user-plus"></i></div>
                    <div>
                        <div class="section-title">Técnico Adicional (Novo)</div>
                        <div class="section-description">Preencha os dados extras para este técnico</div>
                    </div>
                </div>
                <button type="button" class="btn-canvas btn-canvas-clear" onclick="removerTecnicoAdicional(this)" style="color: #ef4444; border-color: #ef4444;"><i class="fas fa-trash"></i> Remover</button>
            </div>

            <!-- Nome -->
            <div class="form-grid full">
                <div class="form-group">
                    <label>Nome do Técnico Adicional *</label>
                    <input type="text" name="tecnico_extra_nome[]" required>
                </div>
            </div>

            <!-- Turnos Extra -->
            <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-calendar-alt"></i> Jornadas de Trabalho</h4>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Dia</th><th>Data</th><th>Manhã Início</th><th>Manhã Fim</th><th>Tarde Início</th><th>Tarde Fim</th></tr></thead>
                    <tbody>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <tr>
                            <td class="row-num"><?= $i ?></td>
                            <td><input type="date" name="tecnico_extra_turno_dia_<?= $i ?>[]"></td>
                            <td><input type="time" name="tecnico_extra_turno_manha_inicio_<?= $i ?>[]"></td>
                            <td><input type="time" name="tecnico_extra_turno_manha_fim_<?= $i ?>[]"></td>
                            <td><input type="time" name="tecnico_extra_turno_tarde_inicio_<?= $i ?>[]"></td>
                            <td><input type="time" name="tecnico_extra_turno_tarde_fim_<?= $i ?>[]"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <!-- Viagens Extra -->
            <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-plane"></i> Horas de Viagem</h4>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Data</th><th>Horas Ida</th><th>Horas Volta</th></tr></thead>
                    <tbody>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <tr>
                            <td class="row-num"><?= $i ?></td>
                            <td><input type="date" name="tecnico_extra_hora_viagem_data_<?= $i ?>[]"></td>
                            <td><input type="text" inputmode="decimal" name="tecnico_extra_hora_viagem_ida_<?= $i ?>[]"></td>
                            <td><input type="text" inputmode="decimal" name="tecnico_extra_hora_viagem_volta_<?= $i ?>[]"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <!-- KMs Extra -->
            <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-road"></i> Quilometragem</h4>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Data</th><th>KM Ida</th><th>KM Volta</th><th>KM Total</th></tr></thead>
                    <tbody>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <tr>
                            <td class="row-num"><?= $i ?></td>
                            <td><input type="date" name="tecnico_extra_km_data_<?= $i ?>[]"></td>
                            <td><input type="number" step="0.1" name="tecnico_extra_km_ida_<?= $i ?>[]"></td>
                            <td><input type="number" step="0.1" name="tecnico_extra_km_volta_<?= $i ?>[]"></td>
                            <td><input type="number" step="0.1" name="tecnico_extra_km_total_<?= $i ?>[]"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <!-- Despesas Extra -->
            <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-wallet"></i> Despesas Adicionais</h4>
            <div class="form-grid">
                <div class="despesa-row">
                    <div class="form-group"><label><i class="fas fa-receipt"></i> Pedágio (R$)</label><input type="number" step="0.01" name="tecnico_extra_pedagio[]" value="0"></div>
                    <div class="form-group"><label class="qty-label"><i class="fas fa-hashtag"></i> Qtd.</label><input type="number" min="0" name="tecnico_extra_pedagio_qtd[]" value="0"></div>
                </div>
                <div class="despesa-row">
                    <div class="form-group"><label><i class="fas fa-hotel"></i> Hospedagem (R$)</label><input type="number" step="0.01" name="tecnico_extra_hospedagem[]" value="0"></div>
                    <div class="form-group"><label class="qty-label"><i class="fas fa-calendar-day"></i> Dias</label><input type="number" min="0" name="tecnico_extra_hospedagem_dias[]" value="0"></div>
                </div>
                <div class="despesa-row">
                    <div class="form-group"><label><i class="fas fa-utensils"></i> Alimentação (R$)</label><input type="number" step="0.01" name="tecnico_extra_alimentacao[]" value="0"></div>
                    <div class="form-group"><label class="qty-label"><i class="fas fa-hashtag"></i> Qtd.</label><input type="number" min="0" name="tecnico_extra_alimentacao_qtd[]" value="0"></div>
                </div>
                <div class="despesa-row">
                    <div class="form-group"><label><i class="fas fa-ellipsis-h"></i> Outros (R$)</label><input type="number" step="0.01" name="tecnico_extra_outros_despesas[]" value="0"></div>
                    <div class="form-group"><label class="qty-label"><i class="fas fa-hashtag"></i> Qtd.</label><input type="number" min="0" name="tecnico_extra_outros_qtd[]" value="0"></div>
                </div>
            </div>
            <div class="form-grid full" style="margin-top: 12px;">
                <div class="form-group"><label><i class="fas fa-pen"></i> Especificar "Outros"</label><input type="text" name="tecnico_extra_outros_despesas_desc[]" placeholder="Ex: Combustível extra, peças, táxi..."></div>
            </div>

            <!-- Assinatura Extra -->
            <h4 style="margin: 20px 0 10px; color: var(--primary);"><i class="fas fa-signature"></i> Assinatura deste Técnico</h4>
            <div class="canvas-container">
                <canvas class="canvas_extra"></canvas>
                <div class="canvas-buttons">
                    <button type="button" class="btn-canvas btn-canvas-clear"><i class="fas fa-eraser"></i> Limpar</button>
                </div>
                <input type="hidden" name="tecnico_extra_assinatura[]" value="">
            </div>
            <div class="checkbox-group" style="margin-top:10px;">
                <input type="checkbox" name="tecnico_extra_aceite[]" value="1">
                <label>Este técnico confirma a conclusão do atendimento</label>
            </div>
        </div>
    </template>
</body>
</html>
