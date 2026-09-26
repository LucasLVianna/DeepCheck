<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/checklist.php';
require_once __DIR__ . '/../Models/classificacoes_nc.php';
require_once __DIR__ . '/../Views/abas/checklist_linha.php';

exigir_login_api();
exigir_post_com_csrf();

$projetoId = filter_var($_POST['projeto_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$descricao = is_string($_POST['descricao'] ?? null) ? trim($_POST['descricao']) : '';

if ($projetoId === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto inválido.'], 400);
}
if (($erro = checklist_validar_descricao($descricao)) !== null) {
    responder_json(['status' => 'nok', 'mensagem' => $erro], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

if (projeto_do_membro($conexao, $projetoId, (int) $_SESSION['usuario']['id']) === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto não encontrado.'], 404);
}

try {
    $item = checklist_adicionar_item($conexao, $projetoId, $descricao);
} catch (mysqli_sql_exception $e) {
    error_log('DeepCheck: falha ao adicionar item ao checklist do projeto ' . $projetoId . ': ' . $e->getMessage());
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Não foi possível adicionar o item. Tente novamente.'], 500);
}
if (isset($item['erro'])) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => $item['erro']], 409);
}

$classificacoes = classificacoes_do_projeto($conexao, $projetoId);
$indicadores = checklist_indicadores(checklist_itens($conexao, (int) $item['checklist_id']));
$conexao->close();

responder_json([
    'status'      => 'ok',
    'mensagem'    => 'Item adicionado.',
    'item_html'   => checklist_linha_html($item, $classificacoes),
    'indicadores' => $indicadores
], 201);
