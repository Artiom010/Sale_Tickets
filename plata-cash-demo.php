<?php
// Simulare "marcare ca plătit" în fișier
$code = $_GET['code'] ?? '';
if ($code) {
    $paidCodes = file_exists('paid_cash_codes.txt')
        ? file('paid_cash_codes.txt', FILE_IGNORE_NEW_LINES)
        : [];
    if (!in_array($code, $paidCodes)) {
        file_put_contents('paid_cash_codes.txt', $code . PHP_EOL, FILE_APPEND);
    }
    echo "Plata DEMO pentru codul $code marcată ca plătită!";
} else {
    echo "Lipsește codul!";
}
?>