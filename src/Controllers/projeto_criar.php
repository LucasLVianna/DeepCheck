<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';

exigir_login_api();
exigir_post_com_csrf();

$nome = trim((string) ($_POST['nome'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');
$confirmacao = (string) ($_POST['confirmar_senha'] ?? '');

$erro = projeto_validar_nome($nome) ?? projeto_validar_senha($senha);
if ($erro === null && $senha !== $confirmacao) {
    $erro = 'A confirmação não confere com a senha do projeto.';
}
if ($erro !== null) {
    responder_json(['status' => 'nok', 'mensagem' => $erro], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

try {
    $projeto = projeto_criar($conexao, $nome, $senha, (int) $_SESSION['usuario']['id']);
} catch (mysqli_sql_exception $e) {
    error_log('DeepCheck: falha ao criar projeto: ' . $e->getMessage());
    responder_json(['status' => 'nok', 'mensagem' => 'Não foi possível criar o projeto. Tente novamente.'], 500);
} finally {
    $conexao->close();
}

responder_json([
    'status'   => 'ok',
    'mensagem' => 'Projeto criado com sucesso',
    'projeto'  => $projeto
], 201);
