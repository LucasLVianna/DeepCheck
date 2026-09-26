<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/checklist.php';

exigir_login_api();
exigir_post_com_csrf();

$projetoId = filter_var($_POST['projeto_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$nome = is_string($_POST['nome'] ?? null) ? trim($_POST['nome']) : '';
if ($projetoId === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto inválido.'], 400);
}
if ($nome === '' || mb_strlen($nome) > CHECKLIST_NOME_MAX) {
    responder_json(['status' => 'nok', 'mensagem' => 'Informe o nome do checklist (até ' . CHECKLIST_NOME_MAX . ' caracteres).'], 400);
}

require_once __DIR__ . '/../../config/conexao.php';
// Qualquer membro do projeto edita o checklist.
if (projeto_do_membro($conexao, $projetoId, (int) $_SESSION['usuario']['id']) === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto não encontrado.'], 404);
}
checklist_renomear($conexao, $projetoId, $nome);
$conexao->close();

responder_json(['status' => 'ok', 'mensagem' => 'Checklist renomeado.', 'nome' => $nome]);
