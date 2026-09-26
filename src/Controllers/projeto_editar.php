<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';

exigir_login_api();
exigir_post_com_csrf();

$projetoId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$nome = trim((string) ($_POST['nome'] ?? ''));

if ($projetoId === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto inválido.'], 400);
}
if (($erro = projeto_validar_nome($nome)) !== null) {
    responder_json(['status' => 'nok', 'mensagem' => $erro], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

$usuarioId = (int) $_SESSION['usuario']['id'];
$projeto = projeto_do_membro($conexao, $projetoId, $usuarioId);

if ($projeto === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto não encontrado.'], 404);
}
if ((int) $projeto['criado_por'] !== $usuarioId) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Apenas quem criou o projeto pode renomeá-lo.'], 403);
}

projeto_renomear($conexao, $projetoId, $nome);
$conexao->close();

responder_json(['status' => 'ok', 'mensagem' => 'Projeto renomeado com sucesso']);
