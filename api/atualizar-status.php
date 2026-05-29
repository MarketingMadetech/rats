<?php
/**
 * API: Atualizar Status do RAT
 */
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id = intval($data['id'] ?? 0);
$status = $data['status'] ?? '';

if (!$id || !in_array($status, ['pendente', 'enviado', 'preenchido'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

try {
    $db = getDB();
    
    $sql = "UPDATE rats SET status = ?";
    $params = [$status];
    
    if ($status === 'enviado') {
        $sql .= ", data_envio = datetime('now')";
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $id;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'status' => $status]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
