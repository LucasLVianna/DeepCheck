<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/checklist.php';

// Importa itens para o fim do checklist do projeto (entram como "não avaliado"):
//   origem=texto   → texto: um item por linha
//   origem=projeto → projeto_origem_id: copia as descrições do checklist de outro projeto
//                    do qual o usuário também é membro
exigir_login_api();
exigir_post_com_csrf();

$projetoId = filter_var($_POST['projeto_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$origem = is_string($_POST['origem'] ?? null) ? $_POST['origem'] : '';
if ($projetoId === false || !in_array($origem, ['texto', 'projeto'], true)) {
    responder_json(['status' => 'nok', 'mensagem' => 'Requisição inválida.'], 400);
}

require_once __DIR__ . '/../../config/conexao.php';
$usuarioId = (int) $_SESSION['usuario']['id'];
if (projeto_do_membro($conexao, $projetoId, $usuarioId) === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto não encontrado.'], 404);
}

if ($origem === 'texto') {
    $descricoes = checklist_ler_lista_itens(is_string($_POST['texto'] ?? null) ? $_POST['texto'] : '');
} else {
    $origemId = filter_var($_POST['projeto_origem_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($origemId === false || $origemId === $projetoId || projeto_do_membro($conexao, $origemId, $usuarioId) === null) {
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => 'Projeto de origem não encontrado.'], 404);
    }
    $descricoes = checklist_descricoes_do_projeto($conexao, $origemId);
    if (!$descricoes) {
        $descricoes = ['erro' => 'O checklist do projeto de origem não tem itens.'];
    }
}
if (isset($descricoes['erro'])) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => $descricoes['erro']], 400);
}

try {
    $resultado = checklist_importar_itens($conexao, $projetoId, $descricoes);
} catch (mysqli_sql_exception $e) {
    $conexao->close();
    error_log('DeepCheck: falha ao importar itens no checklist do projeto ' . $projetoId . ': ' . $e->getMessage());
    responder_json(['status' => 'nok', 'mensagem' => 'Não foi possível importar os itens. Tente novamente.'], 500);
}
$conexao->close();

if (isset($resultado['erro'])) {
    responder_json(['status' => 'nok', 'mensagem' => $resultado['erro']], 409);
}
responder_json(['status' => 'ok', 'mensagem' => "{$resultado['importados']} item(ns) importado(s)."], 201);
