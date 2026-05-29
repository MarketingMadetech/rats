<?php
/**
 * Cadastrar Técnicos - Sistema RAT
 */

require_once __DIR__ . '/api/config.php';

// Lista de técnicos
$tecnicos = [
    ['nome' => 'Flavio', 'email' => 'gustiflan.tecnologia@gmail.com'],
    ['nome' => 'André Neri Paraguay', 'email' => 'anpparaguay@gmail.com'],
    ['nome' => 'Fabio Leite', 'email' => 'fabio.rpleite@gmail.com'],
    ['nome' => 'Francisco Prestes', 'email' => 'prestes.eletrica@gmail.com'],
    ['nome' => 'Rogério Brito', 'email' => 'rogeriopbrito@yahoo.com.br'],
    ['nome' => 'Rogério Teodoro', 'email' => 'rogerio.manutencao@yahoo.com.br'],
    ['nome' => 'Valtencir Ribeiro da Silva', 'email' => 'valtencirribeiro11@gmail.com'],
    ['nome' => 'Auri Legramante', 'email' => 'auri@armaqservice.com.br'],
    ['nome' => 'José Clemir Foiato', 'email' => 'clemirfoiato@hotmail.com'],
    ['nome' => 'Givanilton Santiago', 'email' => 'eletrix@eletrix.net.br'],
    ['nome' => 'Jonatas Oliveira', 'email' => 'solvertechserv@gmail.com'],
    ['nome' => 'Marcos Kleber Inácio de Oliveira', 'email' => 'Tecnobimar@gmail.com'],
    ['nome' => 'Gentil Tavares', 'email' => 'Gentiltavarestech@gmail.com'],
    ['nome' => 'Wilton Manoel de Lara', 'email' => 'wiltonlara.assistencia@gmail.com'],
    ['nome' => 'Bruno Vieira Junior', 'email' => 'bruno.eletrica@hotmail.com'],
    ['nome' => 'Marcelo de Souza Vieira', 'email' => 'msv.tecsouza@gmail.com'],
    ['nome' => 'Gabriel Guilherme Vianna do Espirito Santo', 'email' => 'gabrielviannaes@gmail.com'],
    ['nome' => 'Allan', 'email' => 'assistencia@madetech.com.br'],
];

echo "<h1>Cadastro de Técnicos - Sistema RAT</h1>";
echo "<hr>";
echo "<p>Cadastrando " . count($tecnicos) . " técnicos...</p>";
echo "<table border='1' cellpadding='10' style='width:100%; margin-top: 20px;'>";
echo "<tr>";
echo "<th>#</th>";
echo "<th>Nome</th>";
echo "<th>Email</th>";
echo "<th>Senha Gerada</th>";
echo "<th>Status</th>";
echo "</tr>";

$db = getDB();
$cadastrados = 0;
$erros = 0;

foreach ($tecnicos as $idx => $tecnico) {
    $numero = $idx + 1;
    
    // Gerar senha: primeiras 4 letras do nome + número iniciado em 2026
    $primeiroNome = strtolower(explode(' ', $tecnico['nome'])[0]);
    $senha = $primeiroNome . '2026';
    $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
    
    echo "<tr>";
    echo "<td>$numero</td>";
    echo "<td>" . htmlspecialchars($tecnico['nome']) . "</td>";
    echo "<td>" . htmlspecialchars($tecnico['email']) . "</td>";
    echo "<td><code>$senha</code></td>";
    
    try {
        // Verificar se já existe
        $stmt = $db->prepare("SELECT id FROM tecnicos WHERE email = ?");
        $stmt->execute([$tecnico['email']]);
        
        if ($stmt->fetch()) {
            echo "<td style='background-color: #fff3cd;'><span style='color: orange;'>⚠ JÁ EXISTE</span></td>";
        } else {
            // Inserir novo técnico
            $stmt = $db->prepare("
                INSERT INTO tecnicos (nome, email, senha, telefone)
                VALUES (?, ?, ?, '')
            ");
            $stmt->execute([$tecnico['nome'], $tecnico['email'], $senhaHash]);
            
            echo "<td style='background-color: #d4edda;'><span style='color: green;'>✓ CADASTRADO</span></td>";
            $cadastrados++;
        }
    } catch (Exception $e) {
        echo "<td style='background-color: #f8d7da;'><span style='color: red;'>✗ ERRO: " . $e->getMessage() . "</span></td>";
        $erros++;
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h2>Resumo:</h2>";
echo "<ul>";
echo "<li><strong>Cadastrados:</strong> $cadastrados</li>";
echo "<li><strong>Já existentes:</strong> " . (count($tecnicos) - $cadastrados - $erros) . "</li>";
echo "<li><strong>Erros:</strong> $erros</li>";
echo "</ul>";

echo "<h2>Credenciais dos Técnicos:</h2>";
echo "<p>Imprima ou salve isto para distribuir as senhas:</p>";
echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;'>";

foreach ($tecnicos as $tecnico) {
    $primeiroNome = strtolower(explode(' ', $tecnico['nome'])[0]);
    $senha = $primeiroNome . '2026';
    echo htmlspecialchars($tecnico['email']) . " / $senha\n";
}

echo "</pre>";

echo "<hr>";
echo "<p>";
echo "<a href='admin/login.php' style='padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Ir para Login Admin</a>";
echo "</p>";
?>
