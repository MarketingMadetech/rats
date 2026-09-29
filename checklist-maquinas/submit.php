<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, ['ok'=>false,'error'=>'Método não permitido.']);
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) respond(415, ['ok'=>false,'error'=>'Envie dados JSON.']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 5000000) respond(413, ['ok'=>false,'error'=>'Arquivo muito grande.']);

try {
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 5000000) respond(413, ['ok'=>false,'error'=>'Arquivo muito grande.']);
    $request = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($request) || !preg_match('/^[a-f0-9]{24}$/', (string)($request['id'] ?? ''))) respond(400, ['ok'=>false,'error'=>'Envio inválido.']);
    $configFile = __DIR__ . '/config.local.php';
    $config = is_file($configFile) ? require $configFile : [];
    $url = (string)($config['webhook_url'] ?? getenv('CHECKLIST_MAQUINAS_WEBHOOK_URL') ?: '');
    $token = (string)($config['webhook_token'] ?? getenv('CHECKLIST_MAQUINAS_WEBHOOK_TOKEN') ?: '');
    if (!preg_match('~^https://script\.google\.com/macros/s/[^/]+/exec$~', $url) || strlen($token) < 24 || str_contains($url, 'SEU_DEPLOYMENT_ID') || str_contains($token, 'COLOQUE_AQUI')) {
        respond(503, ['ok'=>false,'error'=>'A conexão com a planilha ainda não está configurada.']);
    }
    $request['token'] = $token;
    $curl = curl_init($url);
    if ($curl === false) throw new RuntimeException('Falha ao iniciar conexão.');
    curl_setopt_array($curl, [
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($request, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_MAXREDIRS=>3,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>90,
        CURLOPT_SSL_VERIFYPEER=>true,
    ]);
    $response = curl_exec($curl);
    $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === false || $http !== 200) throw new RuntimeException('Sem confirmação do Google Sheets.');
    $result = json_decode((string)$response, true);
    if (!is_array($result) || !isset($result['ok']) || ($result['id'] ?? '') !== $request['id']) {
        if (!is_array($result) || ($result['ok'] ?? null) !== false) throw new RuntimeException('Resposta inesperada do Google Sheets.');
    }
    if (($result['ok'] ?? false) !== true) respond(422, ['ok'=>false,'error'=>substr((string)($result['error'] ?? 'Falha na gravação.'), 0, 300)]);
    respond(200, ['ok'=>true,'id'=>$request['id']]);
} catch (Throwable $error) {
    error_log('Checklist de Saída de Máquina: ' . $error->getMessage());
    respond(502, ['ok'=>false,'error'=>'Não foi possível confirmar a gravação na planilha. Tente novamente.']);
}
