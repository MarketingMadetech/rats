<?php
function exibirNumeroRAT($numero, $numero_sequencial = null) {
    if (!empty($numero_sequencial)) {
        return (string)$numero_sequencial;
    }
    if (preg_match('/^RAT-\d{2}-(\d{4})$/', $numero, $matches)) {
        return (string)intval($matches[1]);
    }
    return $numero;
}

// Test cases
$tests = [
    ['RAT-18-0028', null],
    ['RAT-18-0028', 28],
    ['35', 35],
    ['1', 1],
    ['RASCUNHO-1779127806-5848', null],
    ['TEMP-abc123xyz', null],
];

foreach ($tests as $t) {
    $num = $t[0];
    $seq = $t[1];
    $formatted = exibirNumeroRAT($num, $seq);
    echo "Input: [num='$num', seq=" . ($seq ?? 'null') . "] -> Output: '$formatted'\n";
}
?>
