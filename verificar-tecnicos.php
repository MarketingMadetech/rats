<?php
/**
 * Verificar Tecnicos Cadastrados
 */

require_once __DIR__ . '/api/config.php';

$db = getDB();

echo "<h1>Verificação de Técnicos Cadastrados</h1>";
echo "<hr>";

// Contar total
$stmt = $db->query("SELECT COUNT(*) as total FROM tecnicos");
$total = $stmt->fetch()['total'];

echo "<h2>Resumo</h2>";
echo "<p><strong>Total de técnicos cadastrados:</strong> <span style='font-size: 24px; color: #034c8c;'>$total</span></p>";

echo "<hr>";
echo "<h2>Lista de Técnicos</h2>";
echo "<table border='1' cellpadding='10' style='width:100%;'>";
echo "<tr>";
echo "<th>#</th>";
echo "<th>Nome</th>";
echo "<th>Email</th>";
echo "<th>Data de Criação</th>";
echo "</tr>";

$stmt = $db->query("SELECT id, nome, email, criado_em FROM tecnicos ORDER BY id");
$tecnicos = $stmt->fetchAll();

foreach ($tecnicos as $t) {
    $data = date('d/m/Y H:i', strtotime($t['criado_em']));
    echo "<tr>";
    echo "<td>" . $t['id'] . "</td>";
    echo "<td>" . htmlspecialchars($t['nome']) . "</td>";
    echo "<td>" . htmlspecialchars($t['email']) . "</td>";
    echo "<td>$data</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h2>Próximas Ações:</h2>";
echo "<ul>";
echo "<li><a href='admin/login.php'>Acessar Painel Admin</a></li>";
echo "<li><a href='tecnico/login.php'>Acessar Painel Técnico</a></li>";
echo "<li><a href='setup.php'>Voltar ao Setup</a></li>";
echo "</ul>";
?>
