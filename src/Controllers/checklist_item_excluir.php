<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/checklist.php';

exigir_login_api();
exigir_post_com_csrf();

$itemId = filter_var($_POST['item_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($itemId === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Item inválido.'], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

$item = checklist_item_do_membro($conexao, $itemId, (int) $_SESSION['usuario']['id']);
if ($item === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Item não encontrado.'], 404);
}
if (!empty($item['nc_id'])) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Este item tem uma NC enviada por e-mail e não pode ser excluído.'], 409);
}

try {
    checklist_excluir_item($conexao, $itemId);
} catch (mysqli_sql_exception $e) {
    // 1451: NC enviada criada entre a verificação acima e o DELETE (FK RESTRICT).
    error_log('DeepCheck: falha ao excluir item ' . $itemId . ' do checklist: ' . $e->getMessage());
    $conexao->close();
    responder_json([
        'status'   => 'nok',
        'mensagem' => $e->getCode() == 1451
            ? 'O item possui uma não conformidade registrada e não pode ser excluído.'
            : 'Não foi possível excluir o item. Tente novamente.'
    ], $e->getCode() == 1451 ? 409 : 500);
}

$indicadores = checklist_indicadores(checklist_itens($conexao, (int) $item['checklist_id']));
$conexao->close();

responder_json(['status' => 'ok', 'mensagem' => 'Item excluído.', 'indicadores' => $indicadores]);
