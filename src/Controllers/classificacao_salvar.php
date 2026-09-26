<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/classificacoes_nc.php';

exigir_login_api();
exigir_post_com_csrf();

// Sem "id": cria uma classificação no projeto. Com "id": edita (nome, prazo e unidade).
$projetoId = filter_var($_POST['projeto_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$id = ($_POST['id'] ?? '') === '' ? null : filter_var($_POST['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($projetoId === false || $id === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Requisição inválida.'], 400);
}
$dados = classificacao_ler_entrada($_POST);
if (isset($dados['erro'])) {
    responder_json(['status' => 'nok', 'mensagem' => $dados['erro']], 400);
}

require_once __DIR__ . '/../../config/conexao.php';

$usuarioId = (int) $_SESSION['usuario']['id'];
// Qualquer membro do projeto edita as classificações.
if (projeto_do_membro($conexao, $projetoId, $usuarioId) === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto não encontrado.'], 404);
}
$atual = null;
if ($id !== null) {
    $atual = classificacao_do_membro($conexao, $id, $usuarioId);
    if ($atual === null || (int) $atual['projeto_id'] !== $projetoId) {
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => 'Classificação não encontrada.'], 404);
    }
}

try {
    $resultado = classificacao_salvar($conexao, $projetoId, $atual, $dados, $usuarioId);
} catch (mysqli_sql_exception $e) {
    $conexao->close();
    if ($e->getCode() == 1062) {
        responder_json(['status' => 'nok', 'mensagem' => "Já existe uma classificação chamada \"{$dados['nome']}\" neste projeto."], 409);
    }
    error_log('DeepCheck: falha ao salvar classificação: ' . $e->getMessage());
    responder_json(['status' => 'nok', 'mensagem' => 'Não foi possível salvar a classificação. Tente novamente.'], 500);
}

$lista = classificacoes_com_uso($conexao, $projetoId);
$historico = classificacoes_historico($conexao, $projetoId);
$conexao->close();

$mensagem = $atual === null ? "Classificação \"{$dados['nome']}\" criada." : "Classificação \"{$dados['nome']}\" salva.";
if ($resultado['recalculados'] > 0) {
    $mensagem .= " Data prevista recalculada em {$resultado['recalculados']} item(ns) do checklist com NC ainda não enviada.";
}
responder_json(['status' => 'ok', 'mensagem' => $mensagem, 'classificacoes' => $lista, 'historico' => $historico], $atual === null ? 201 : 200);
