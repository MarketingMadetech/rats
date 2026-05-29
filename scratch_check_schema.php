<?php
require_once 'api/config.php';
$db = getDB();
$result = $db->query("PRAGMA table_info(rats)");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['name'] . " (" . $row['type'] . ")\n";
}
?>
