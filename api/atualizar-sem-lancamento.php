<?php
/**
 * API: Atualizar Sem Lançamento do RAT
 */
require_once __DIR__ . '/auth-admin.php'; // handles session_start and DB config
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id = intval($data['id'] ?? 0);
$sem_lancamento = isset($data['sem_lancamento']) ? intval($data['sem_lancamento']) : null;

if (!$id || ($sem_lancamento !== 0 && $sem_lancamento !== 1)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("UPDATE rats SET sem_lancamento = ? WHERE id = ?");
    $stmt->execute([$sem_lancamento, $id]);
    
    echo json_encode(['success' => true, 'sem_lancamento' => $sem_lancamento]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
