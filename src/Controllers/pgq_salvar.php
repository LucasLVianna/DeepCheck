<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/pgq.php';

exigir_login_api();
exigir_post_com_csrf();

$projetoId = filter_var($_POST['projeto_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($projetoId === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto inválido.'], 400);
}

$entrada = pgq_ler_entrada($_POST);
if (isset($entrada['erro'])) {
    responder_json(['status' => 'nok', 'mensagem' => $entrada['erro']], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

// Qualquer membro do projeto pode editar o PGQ.
if (projeto_do_membro($conexao, $projetoId, (int) $_SESSION['usuario']['id']) === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto não encontrado.'], 404);
}

try {
    $versaoVista = is_string($_POST['versao'] ?? null) ? $_POST['versao'] : '';
    $resultado = pgq_salvar($conexao, $projetoId, $entrada['campos'], $entrada['linhas'], $versaoVista, ($_POST['sobrescrever'] ?? '') === '1');
} catch (mysqli_sql_exception $e) {
    error_log('DeepCheck: falha ao salvar PGQ do projeto ' . $projetoId . ': ' . $e->getMessage());
    responder_json(['status' => 'nok', 'mensagem' => 'Não foi possível salvar o plano. Tente novamente.'], 500);
} finally {
    $conexao->close();
}

if (isset($resultado['conflito'])) {
    $quando = $resultado['conflito'] === '' ? '' : ' em ' . date('d/m/Y H:i', strtotime($resultado['conflito']));
    responder_json([
        'status'   => 'nok',
        'conflito' => true,
        'mensagem' => "Outra pessoa salvou o plano{$quando}, depois que você abriu esta página. Se salvar agora, as alterações dela serão substituídas pelas suas."
    ], 409);
}

responder_json([
    'status'        => 'ok',
    'mensagem'      => 'Plano de Garantia da Qualidade salvo.',
    'atualizado_em' => date('d/m/Y H:i', strtotime($resultado['atualizado_em'])),
    'versao'        => $resultado['atualizado_em']
]);
