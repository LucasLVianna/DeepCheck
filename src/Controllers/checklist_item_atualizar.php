<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/checklist.php';
require_once __DIR__ . '/../Models/classificacoes_nc.php';

exigir_login_api();
exigir_post_com_csrf();

// Salvamento automático: um campo por requisição.
$itemId = filter_var($_POST['item_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$campo = is_string($_POST['campo'] ?? null) ? $_POST['campo'] : '';
$valor = is_string($_POST['valor'] ?? null) ? $_POST['valor'] : '';

if ($itemId === false || !in_array($campo, CHECKLIST_CAMPOS_EDITAVEIS, true)) {
    responder_json(['status' => 'nok', 'mensagem' => 'Requisição inválida.'], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

$item = checklist_item_do_membro($conexao, $itemId, (int) $_SESSION['usuario']['id']);
if ($item === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Item não encontrado.'], 404);
}

$classificacoes = array_column(classificacoes_do_projeto($conexao, (int) $item['projeto_id']), null, 'id');
$resultado = checklist_aplicar_alteracao($item, $campo, $valor, $classificacoes, date('Y-m-d H:i:s'));
if (isset($resultado['erro'])) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => $resultado['erro']], 400);
}

try {
    checklist_salvar_item($conexao, $resultado['item']);
} catch (mysqli_sql_exception $e) {
    error_log('DeepCheck: falha ao salvar item ' . $itemId . ' do checklist: ' . $e->getMessage());
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Não foi possível salvar a alteração. Tente novamente.'], 500);
}

$indicadores = checklist_indicadores(checklist_itens($conexao, (int) $item['checklist_id']));
$conexao->close();

responder_json([
    'status'      => 'ok',
    'mensagem'    => 'Alteração salva.',
    'item'        => checklist_item_para_json($resultado['item']),
    'indicadores' => $indicadores
]);
