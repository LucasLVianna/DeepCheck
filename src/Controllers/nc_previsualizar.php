<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/email.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/classificacoes_nc.php';
require_once __DIR__ . '/../Models/nao_conformidades.php';

// Pré-visualização do PDF da Solicitação antes de enviar (mesmos dados do formulário).
//   tipo=envio          → item_id + campos do formulário "Enviar NC"
//   tipo=escalonamento  → nc_id + campos do formulário "Escalonar"
// Responde o PDF (application/pdf); em erro, JSON como os demais endpoints.
exigir_login_api();
exigir_post_com_csrf();

$usuarioId = (int) $_SESSION['usuario']['id'];
$agora = date('Y-m-d H:i:s');
$tipo = is_string($_POST['tipo'] ?? null) ? $_POST['tipo'] : '';

require_once __DIR__ . '/../../config/conexao.php';

if ($tipo === 'envio') {
    $itemId = filter_var($_POST['item_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $envio = nc_ler_envio($_POST);
    $item = $itemId ? checklist_item_do_membro($conexao, $itemId, $usuarioId) : null;
    if ($item === null) {
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => 'Item não encontrado.'], 404);
    }
    if (isset($envio['erro'])) {
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => $envio['erro']], 400);
    }
    if (!checklist_item_pode_enviar($item)) {
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => 'O item precisa estar como não conformidade, com classificação e responsável preenchidos.'], 400);
    }
    $projeto = projeto_do_membro($conexao, (int) $item['projeto_id'], $usuarioId);
    $classificacao = array_column(classificacoes_do_projeto($conexao, (int) $item['projeto_id']), null, 'id')[$item['classificacao_nc_id']];
    $documento = nc_documento_envio($projeto['nome'], $item, $classificacao, $envio, $agora);
    $anexoNome = nc_nome_anexo((int) $item['numero_item']);
} elseif ($tipo === 'escalonamento') {
    $ncId = filter_var($_POST['nc_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $nc = $ncId ? nc_do_membro($conexao, $ncId, $usuarioId) : null;
    if ($nc === null) {
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => 'Não conformidade não encontrada.'], 404);
    }
    $escalonamento = nc_ler_escalonamento($_POST, $nc['prazo_unidade'], $agora);
    if (isset($escalonamento['erro'])) {
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => $escalonamento['erro']], 400);
    }
    // Histórico com o ciclo que ainda vai ser criado.
    $historico = nc_historico($conexao, $ncId);
    $historico[] = [
        'superior_nome'         => $escalonamento['superior_nome'],
        'responsavel_resolucao' => $escalonamento['responsavel_resolucao'],
        'novo_prazo_resolucao'  => $escalonamento['novo_prazo'],
    ];
    $nc['numero_escalonamento'] = (int) $nc['numero_escalonamento'] + 1;
    $nc['responsavel_resolucao'] = $escalonamento['responsavel_resolucao'];
    $documento = nc_documento($nc, $historico, $escalonamento['observacoes']);
    $anexoNome = nc_nome_anexo((int) $nc['numero_item'], $nc['numero_escalonamento']);
} else {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Requisição inválida.'], 400);
}
$conexao->close();

$resultado = email_executar_script(['apenas_pdf' => true, 'anexo_nome' => $anexoNome, 'documento' => $documento]);
if (!$resultado['ok']) {
    responder_json(['status' => 'nok', 'mensagem' => $resultado['erro']], 500);
}

header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($resultado['pdf']));
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9 ._-]/', '_', 'Previa - ' . $anexoNome) . '"');
header('Cache-Control: private, no-store');
echo $resultado['pdf'];
