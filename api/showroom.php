<?php
/**
 * API - Máquinas Showroom (Sistema RAT Madetech)
 * Armazena os dados na tabela `showroom_maquinas` do banco SQLite (data/rats.db).
 *
 * GET                                     -> { ok, machines: [...] }
 * POST { action: "save",   machine: {...} } -> { ok, machine }
 * POST { action: "delete", id: "..." }      -> { ok }
 */

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function showroomFail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function showroomClean($v, $max = 2000) {
    $str = trim((string)($v ?? ''));
    return function_exists('mb_substr') ? mb_substr($str, 0, $max, 'UTF-8') : substr($str, 0, $max);
}

// Exigir autenticação (Técnico ou Administrador)
$is_admin = !empty($_SESSION['admin_id']);
$is_tecnico = !empty($_SESSION['tecnico_id']);

if (!$is_admin && !$is_tecnico) {
    showroomFail('Não autenticado. Faça login novamente.', 401);
}

try {
    $db = getDB();
    inicializarShowroom($db);
} catch (Exception $e) {
    showroomFail('Erro ao conectar ao banco de dados', 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        $stmt = $db->query("
            SELECT id, nome, modelo, serie, tipo, COALESCE(quantidade, 1) as quantidade, estado, obs, COALESCE(info_extra, '') as info_extra, atualizado_em, atualizado_por
            FROM showroom_maquinas
            ORDER BY nome COLLATE NOCASE ASC
        ");
        $machines = $stmt->fetchAll();
        foreach ($machines as &$item) {
            $item['tipo'] = normalizarTipoShowroom($item['tipo'] ?? '');
            $item['quantidade'] = max(1, (int)($item['quantidade'] ?? 1));
        }
        unset($item);
        echo json_encode(['ok' => true, 'machines' => $machines], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        showroomFail('Erro ao listar máquinas do showroom', 500);
    }
}

if ($method !== 'POST') {
    showroomFail('Método não permitido', 405);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    showroomFail('JSON inválido');
}

$estados = ['operacional', 'restricao', 'manutencao', 'aguardando', 'parada', 'sem_info'];

// Identificar usuário autenticado conforme o painel utilizado
$context = $in['context'] ?? '';
if ($context === 'admin' && $is_admin) {
    $user = $_SESSION['admin_nome'] ?? 'Administrador';
} elseif ($context === 'tecnico' && $is_tecnico) {
    $user = $_SESSION['tecnico_nome'] ?? 'Técnico';
} elseif ($is_tecnico) {
    $user = $_SESSION['tecnico_nome'] ?? 'Técnico';
} elseif ($is_admin) {
    $user = $_SESSION['admin_nome'] ?? 'Administrador';
} else {
    $user = showroomClean($in['user'] ?? '', 120);
}

switch ($in['action'] ?? '') {
    case 'save':
        $m = $in['machine'] ?? null;
        if (!is_array($m)) {
            showroomFail('Dados da máquina inválidos');
        }

        $tipoLimpo = normalizarTipoShowroom(showroomClean($m['tipo'] ?? '', 100));
        $modeloLimpo = showroomClean($m['modelo'] ?? '', 150);
        $nomeLimpo = showroomClean($m['nome'] ?? '', 150);

        // Se o nome vier vazio mas houver tipo/modelo, monta automaticamente
        if ($nomeLimpo === '' && ($tipoLimpo !== '' || $modeloLimpo !== '')) {
            $nomeLimpo = trim($tipoLimpo . ($modeloLimpo !== '' ? ' ' . $modeloLimpo : ''));
        }
        if ($nomeLimpo === '') {
            showroomFail('Informe o nome ou o tipo/modelo da máquina');
        }

        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($m['id'] ?? ''));
        if ($id === '') {
            $id = 'm' . time() . mt_rand(100, 999);
        }

        $quantidade = max(1, min(9999, (int)($m['quantidade'] ?? 1)));

        $row = [
            'id'             => $id,
            'nome'           => $nomeLimpo,
            'modelo'         => $modeloLimpo,
            'serie'          => showroomClean($m['serie'] ?? '', 80),
            'tipo'           => $tipoLimpo,
            'quantidade'     => $quantidade,
            'estado'         => in_array($m['estado'] ?? '', $estados, true) ? $m['estado'] : 'sem_info',
            'obs'            => showroomClean($m['obs'] ?? '', 4000),
            'info_extra'     => showroomClean($m['info_extra'] ?? '', 1500),
            'atualizado_em'  => date('c'),
            'atualizado_por' => $user,
        ];

        try {
            $check = $db->prepare("SELECT COUNT(*) FROM showroom_maquinas WHERE id = ?");
            $check->execute([$id]);
            $exists = ((int)$check->fetchColumn()) > 0;

            if ($exists) {
                $stmt = $db->prepare("
                    UPDATE showroom_maquinas
                    SET nome = ?, modelo = ?, serie = ?, tipo = ?, quantidade = ?, estado = ?, obs = ?, info_extra = ?, atualizado_em = ?, atualizado_por = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $row['nome'],
                    $row['modelo'],
                    $row['serie'],
                    $row['tipo'],
                    $row['quantidade'],
                    $row['estado'],
                    $row['obs'],
                    $row['info_extra'],
                    $row['atualizado_em'],
                    $row['atualizado_por'],
                    $row['id'],
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO showroom_maquinas (id, nome, modelo, serie, tipo, quantidade, estado, obs, info_extra, atualizado_em, atualizado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $row['id'],
                    $row['nome'],
                    $row['modelo'],
                    $row['serie'],
                    $row['tipo'],
                    $row['quantidade'],
                    $row['estado'],
                    $row['obs'],
                    $row['info_extra'],
                    $row['atualizado_em'],
                    $row['atualizado_por'],
                ]);
            }

            echo json_encode(['ok' => true, 'machine' => $row], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            showroomFail('Erro ao salvar máquina: ' . $e->getMessage(), 500);
        }

    case 'delete':
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($in['id'] ?? ''));
        if ($id === '') {
            showroomFail('ID inválido');
        }

        try {
            $stmt = $db->prepare("DELETE FROM showroom_maquinas WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            showroomFail('Erro ao excluir máquina', 500);
        }

    default:
        showroomFail('Ação inválida');
}

