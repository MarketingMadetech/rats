<?php
/**
 * Cadastro de Técnicos - Produção
 * Acesse uma vez para popular o banco, depois DELETE este arquivo do servidor!
 */

require_once __DIR__ . '/api/config.php';

$db = getDB();

// Garantir que a tabela existe
initDB();

$tecnicos = [
    ['Flavio', 'gustiflan.tecnologia@gmail.com', 'flavio2026'],
    ['André Neri Paraguay', 'anpparaguay@gmail.com', 'andre2026'],
    ['Fabio Leite', 'fabio.rpleite@gmail.com', 'fabio2026'],
    ['Francisco Prestes', 'prestes.eletrica@gmail.com', 'francisco2026'],
    ['Rogério Brito', 'rogeriopbrito@yahoo.com.br', 'rogeriob2026'],
    ['Rogério Teodoro', 'rogerio.manutencao@yahoo.com.br', 'rogeriot2026'],
    ['Valtencir Ribeiro da Silva', 'valtencirribeiro11@gmail.com', 'valtencir2026'],
    ['Auri Legramante', 'auri@armaqservice.com.br', 'auri2026'],
    ['José Clemir Foiato', 'clemirfoiato@hotmail.com', 'clemir2026'],
    ['Givanilton Santiago', 'eletrix@eletrix.net.br', 'givanilton2026'],
    ['Jonatas Oliveira', 'jonatas.oliveira@leroymerlin.com.br', 'jonatas2026'],
    ['Marcos Kleber Inácio de Oliveira', 'tecnobimar@gmail.com', 'marcos2026'],
    ['Gentil Tavares', 'gentiltavarestech@gmail.com', 'gentil2026'],
    ['Wilton Manoel de Lara', 'wiltonlara.assistencia@gmail.com', 'wilton2026'],
    ['Bruno Vieira Junior', 'bruno.eletrica@hotmail.com', 'bruno2026'],
    ['Marcelo de Souza Vieira', 'msv.tecsouza@gmail.com', 'marcelo2026'],
    ['Gabriel Guilherme Vianna do Espirito Santo', 'gabrielviannaes@gmail.com', 'gabriel2026'],
    ['Allan', 'assistencia@madetech.com.br', 'allan2026'],
];

echo "<h2>Cadastro de Técnicos</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse;font-family:Arial;'>";
echo "<tr style='background:#025fb1;color:white;'><th>#</th><th>Nome</th><th>Email</th><th>Senha</th><th>Status</th></tr>";

$inseridos = 0;
$existentes = 0;

foreach ($tecnicos as $i => $t) {
    $nome = $t[0];
    $email = strtolower(trim($t[1]));
    $senha = $t[2];
    
    // Verificar se já existe
    $stmt = $db->prepare("SELECT id FROM tecnicos WHERE email = ?");
    $stmt->execute([$email]);
    $existe = $stmt->fetch();
    
    if ($existe) {
        $status = "<span style='color:orange;'>Já existe (ID: {$existe['id']})</span>";
        $existentes++;
    } else {
        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO tecnicos (nome, email, senha) VALUES (?, ?, ?)");
        if ($stmt->execute([$nome, $email, $senha_hash])) {
            $id = $db->lastInsertId();
            $status = "<span style='color:green;'>Cadastrado (ID: $id)</span>";
            $inseridos++;
        } else {
            $status = "<span style='color:red;'>ERRO ao cadastrar</span>";
        }
    }
    
    $num = $i + 1;
    echo "<tr><td>$num</td><td>$nome</td><td>$email</td><td>$senha</td><td>$status</td></tr>";
}

echo "</table>";
echo "<br><p><b>Resumo:</b> $inseridos cadastrados, $existentes já existiam.</p>";

// Verificar admin padrão
$stmt = $db->query("SELECT COUNT(*) as total FROM administradores");
$result = $stmt->fetch();
if ($result['total'] == 0) {
    $senha_hash = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO administradores (nome, email, senha) VALUES (?, ?, ?)");
    $stmt->execute(['Administrador', 'admin@madetech.com.br', $senha_hash]);
    echo "<p style='color:green;'><b>Admin criado:</b> admin@madetech.com.br / admin123</p>";
} else {
    echo "<p>Admin já existe.</p>";
}

echo "<hr><p style='color:red;'><b>IMPORTANTE:</b> Delete este arquivo do servidor após o cadastro!</p>";
echo "<p><a href='admin/login.php'>Ir para Login Admin</a> | <a href='tecnico/login.php'>Ir para Login Técnico</a></p>";
?>
