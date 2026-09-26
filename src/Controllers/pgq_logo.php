<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/pgq.php';

// Logo do projeto na capa do PGQ (qualquer membro, como o restante do PGQ).
//   acao=enviar  → arquivo "logo" (PNG ou JPG, até 1 MB)
//   acao=remover
exigir_login_api();
exigir_post_com_csrf();

$projetoId = filter_var($_POST['projeto_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';
if ($projetoId === false || !in_array($acao, ['enviar', 'remover'], true)) {
    responder_json(['status' => 'nok', 'mensagem' => 'Requisição inválida.'], 400);
}

$logo = null;
if ($acao === 'enviar') {
    $logo = pgq_ler_logo($_FILES['logo'] ?? null);
    if (isset($logo['erro'])) {
        responder_json(['status' => 'nok', 'mensagem' => $logo['erro']], 400);
    }
}

require_once __DIR__ . '/../../config/conexao.php';
if (projeto_do_membro($conexao, $projetoId, (int) $_SESSION['usuario']['id']) === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Projeto não encontrado.'], 404);
}
$versao = pgq_salvar_logo($conexao, $projetoId, $logo);
$conexao->close();

responder_json([
    'status'   => 'ok',
    'mensagem' => $logo ? 'Logo atualizado. Ele aparece na capa do PDF do plano.' : 'Logo removido.',
    'versao'   => $versao,
    'tem_logo' => $logo !== null,
]);
