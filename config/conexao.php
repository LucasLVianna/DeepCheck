<?php
require_once __DIR__ . '/env.php';

try {
    $conexao = new mysqli(
        env('DB_HOST', 'db'),
        env('DB_USERNAME', ''),
        env('DB_PASSWORD', ''),
        env('DB_NAME', '')
    );
    $conexao->set_charset('utf8mb4');
    // Mesmo fuso do PHP (date.timezone), para que CURRENT_TIMESTAMP/NOW() gravem horário de Brasília.
    $conexao->query("SET time_zone = '" . date('P') . "'");
} catch (mysqli_sql_exception $e) {
    // O detalhe vai para o log do servidor; o cliente recebe só uma mensagem genérica.
    error_log('DeepCheck: falha ao conectar no MySQL: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'   => 'nok',
        'mensagem' => 'Serviço indisponível no momento. Tente novamente mais tarde.',
        'data'     => []
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
