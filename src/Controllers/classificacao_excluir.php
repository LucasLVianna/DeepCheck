<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/classificacoes_nc.php';

exigir_login_api();
exigir_post_com_csrf();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$substitutaId = ($_POST['substituta_id'] ?? '') === ''
    ? null
    : filter_var($_POST['substituta_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $substitutaId === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Requisição inválida.'], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

$usuarioId = (int) $_SESSION['usuario']['id'];
$classificacao = classificacao_do_membro($conexao, $id, $usuarioId);
if ($classificacao === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Classificação não encontrada.'], 404);
}
$projetoId = (int) $classificacao['projeto_id'];

$classificacoesDoProjeto = array_column(classificacoes_com_uso($conexao, $projetoId), null, 'id');
// Checagem antecipada só para a mensagem; a regra é garantida dentro da transação do Model.
if (count($classificacoesDoProjeto) <= 1) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'O projeto precisa ter pelo menos uma classificação.'], 409);
}
$uso = $classificacoesDoProjeto[$id];
$emUso = $uso['itens_em_uso'] > 0 || $uso['ncs_em_uso'] > 0;

$substituta = null;
if ($emUso) {
    // Em uso: exige outra classificação do mesmo projeto para substituir nos itens e NCs.
    if ($substitutaId === null) {
        $conexao->close();
        responder_json([
            'status'   => 'nok',
            'mensagem' => 'Esta classificação está em uso: escolha outra para substituí-la.',
            'em_uso'   => ['itens' => (int) $uso['itens_em_uso'], 'ncs' => (int) $uso['ncs_em_uso']],
        ], 409);
    }
    $substituta = classificacao_do_membro($conexao, $substitutaId, $usuarioId);
    if ($substituta === null || (int) $substituta['projeto_id'] !== $projetoId || $substitutaId === $id) {
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => 'Classificação substituta inválida.'], 400);
    }
}

try {
    $resultado = classificacao_excluir($conexao, $classificacao, $substituta, $usuarioId);
} catch (mysqli_sql_exception $e) {
    $conexao->close();
    error_log('DeepCheck: falha ao excluir classificação ' . $id . ': ' . $e->getMessage());
    responder_json([
        'status'   => 'nok',
        'mensagem' => $e->getCode() == 1451
            ? 'A classificação passou a ser usada enquanto era excluída. Recarregue a página e tente de novo.'
            : 'Não foi possível excluir a classificação. Tente novamente.'
    ], $e->getCode() == 1451 ? 409 : 500);
}

if (isset($resultado['erro'])) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => $resultado['erro']], 409);
}

$lista = classificacoes_com_uso($conexao, $projetoId);
$historico = classificacoes_historico($conexao, $projetoId);
$conexao->close();

$mensagem = "Classificação \"{$classificacao['nome']}\" excluída.";
if ($substituta !== null) {
    $mensagem .= " Substituída por \"{$substituta['nome']}\" em {$resultado['itens']} item(ns) do checklist e {$resultado['ncs']} NC(s).";
}
responder_json(['status' => 'ok', 'mensagem' => $mensagem, 'classificacoes' => $lista, 'historico' => $historico]);
