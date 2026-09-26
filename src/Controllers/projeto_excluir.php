<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';

exigir_login_api();
exigir_post_com_csrf();

$projetoId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($projetoId === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto inválido.'], 400);
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
    responder_json(['status' => 'nok', 'mensagem' => 'Apenas o dono do projeto pode excluí-lo.'], 403);
}

try {
    projeto_excluir($conexao, $projetoId);
} catch (mysqli_sql_exception $e) {
    // 1451: alguma tabela filha (fases seguintes) com ON DELETE RESTRICT ainda referencia o projeto.
    error_log('DeepCheck: falha ao excluir projeto ' . $projetoId . ': ' . $e->getMessage());
    responder_json([
        'status'   => 'nok',
        'mensagem' => $e->getCode() == 1451
            ? 'O projeto ainda possui registros vinculados e não pode ser excluído.'
            : 'Não foi possível excluir o projeto. Tente novamente.'
    ], $e->getCode() == 1451 ? 409 : 500);
} finally {
    $conexao->close();
}

responder_json(['status' => 'ok', 'mensagem' => 'Projeto excluído com sucesso']);
