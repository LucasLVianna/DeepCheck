<?php
// lendo o .env manualmente (PHP puro, sem Laravel)
$env = parse_ini_file(__DIR__ . '/../.env');

$conexao = new mysqli(
    $env['DB_HOST'],
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    $env['DB_NAME']
);

if($conexao->connect_error){
    header("Content-type: application/json; charset=utf-8");
    echo json_encode([
        'status' => 'nok',
        'mensagem' => $conexao->connect_error,
        'data' => []
    ]);
    exit();
}