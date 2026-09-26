<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/usuarios.php';

// Grava a nova senha a partir do link de redefinição (token de uso único, 1 hora).
exigir_post_com_csrf();

$texto = fn($chave) => is_string($_POST[$chave] ?? null) ? $_POST[$chave] : '';
$nova = $texto('nova_senha');
if (($erro = usuario_validar_senha($nova)) !== null) {
    responder_json(['status' => 'nok', 'mensagem' => $erro], 400);
}
if ($nova !== $texto('confirmar_senha')) {
    responder_json(['status' => 'nok', 'mensagem' => 'A confirmação não confere com a nova senha.'], 400);
}

require_once __DIR__ . '/../../config/conexao.php';
$pedido = senha_redefinicao_valida($conexao, $texto('token'));
if ($pedido === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Este link de redefinição é inválido, já foi usado ou expirou. Peça um novo.'], 410);
}
senha_redefinicao_concluir($conexao, $pedido, $nova);
$conexao->close();

responder_json(['status' => 'ok', 'mensagem' => 'Senha redefinida.', 'redirect' => '/src/Views/login.php?motivo=senha_redefinida']);
