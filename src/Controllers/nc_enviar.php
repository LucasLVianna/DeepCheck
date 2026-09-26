<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/rate_limit.php';
require_once __DIR__ . '/../../config/email.php';
require_once __DIR__ . '/../Models/projetos.php';
require_once __DIR__ . '/../Models/checklist.php';
require_once __DIR__ . '/../Models/classificacoes_nc.php';
require_once __DIR__ . '/../Models/nao_conformidades.php';

// Envios por usuário na janela do rate limit (protege a conta de e-mail do sistema).
const NC_ENVIOS_MAX_POR_USUARIO = 20;

exigir_login_api();
exigir_post_com_csrf();

$itemId = filter_var($_POST['item_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($itemId === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Item inválido.'], 400);
}
$envio = nc_ler_envio($_POST);
if (isset($envio['erro'])) {
    responder_json(['status' => 'nok', 'mensagem' => $envio['erro']], 400);
}

$usuarioId = (int) $_SESSION['usuario']['id'];
$chaveEnvio = 'nc-envio|' . $usuarioId;
$espera = rate_limit_segundos_restantes($chaveEnvio, NC_ENVIOS_MAX_POR_USUARIO);
if ($espera > 0) {
    header('Retry-After: ' . $espera);
    responder_json([
        'status'   => 'nok',
        'mensagem' => 'Limite de envios atingido. Tente novamente em ' . (int) ceil($espera / 60) . ' minuto(s).'
    ], 429);
}

require_once __DIR__ . '/../../config/conexao.php';

$item = checklist_item_do_membro($conexao, $itemId, $usuarioId);
if ($item === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Item não encontrado.'], 404);
}
if (!empty($item['nc_id'])) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'A solicitação desta NC já foi enviada.'], 409);
}
if (!checklist_item_pode_enviar($item)) {
    $conexao->close();
    responder_json([
        'status'   => 'nok',
        'mensagem' => 'Para enviar, o item precisa estar como não conformidade, com classificação e responsável pela resolução preenchidos.'
    ], 400);
}

$projetoId = (int) $item['projeto_id'];
$projeto = projeto_do_membro($conexao, $projetoId, $usuarioId);
$classificacao = array_column(classificacoes_do_projeto($conexao, $projetoId), null, 'id')[$item['classificacao_nc_id']];

$usuario = nc_usuario_remetente($conexao, $usuarioId);
$agora = date('Y-m-d H:i:s');
$texto = nc_texto_email($projeto['nome'], $item['responsavel_resolucao'], $envio['responsavel_qa']);
$cc = nc_lista_cc(array_merge([$usuario['email_usuario']], $envio['cc']), $envio['email_responsavel']);
$anexoNome = nc_nome_anexo((int) $item['numero_item']);

$entradaScript = nc_entrada_script(
    $usuario,
    $envio['email_responsavel'],
    $item['responsavel_resolucao'],
    $cc,
    $texto,
    [
        'projeto'                   => $projeto['nome'],
        'responsavel_resolucao'     => $item['responsavel_resolucao'],
        'responsavel_qa'            => $envio['responsavel_qa'],
        'data_primeira_solicitacao' => nc_formatar_data($agora),
        'prazo_resolucao'           => nc_formatar_data($item['data_prevista_resolucao']),
        'numero_escalonamento'      => 0,
        'descricao'                 => $item['descricao'],
        'classificacao'             => $classificacao['nome'] . ' | ' . classificacao_prazo_texto($classificacao),
        'acao_corretiva'            => $item['acao_corretiva_indicada'],
        'historico'                 => [],
        'observacoes'               => $envio['observacoes'],
    ],
    $anexoNome
);

// O envio leva alguns segundos (SMTP); o padrão de 30 s do PHP é apertado.
set_time_limit(60);
rate_limit_registrar_falha($chaveEnvio); // conta toda tentativa de envio, com ou sem sucesso

// A NC é criada na mesma transação do envio: se o e-mail falhar, nada fica gravado.
// O UNIQUE em nao_conformidades.checklist_item_id impede dois envios simultâneos do mesmo item.
$conexao->begin_transaction();
try {
    $ncId = nc_criar($conexao, [
        'projeto_id'                => $projetoId,
        'checklist_item_id'         => $itemId,
        'descricao'                 => $item['descricao'],
        'classificacao_nc_id'       => (int) $item['classificacao_nc_id'],
        'acao_corretiva_indicada'   => $item['acao_corretiva_indicada'],
        'responsavel_resolucao'     => $item['responsavel_resolucao'],
        'responsavel_email'         => $envio['email_responsavel'],
        'responsavel_qa'            => $envio['responsavel_qa'],
        'data_primeira_solicitacao' => $agora,
        'prazo_resolucao'           => $item['data_prevista_resolucao'],
        'status'                    => $item['status_nc'],
        'observacoes'               => $envio['observacoes'],
    ]);

    $resultado = email_executar_script($entradaScript);
    if (!$resultado['ok']) {
        $conexao->rollback();
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => $resultado['erro']], 502);
    }

    nc_registrar_email($conexao, [
        'nao_conformidade_id' => $ncId,
        'enviado_por'         => $usuarioId,
        'destinatario'        => $envio['email_responsavel'],
        'cc'                  => implode(', ', $cc),
        'responder_para'      => $usuario['email_usuario'],
        'assunto'             => $texto['assunto'],
        'corpo'               => $texto['corpo'],
        'anexo_nome'          => $anexoNome,
        'anexo_pdf'           => $resultado['pdf'],
        'status_envio'        => 'sucesso',
        'erro_envio'          => null,
    ]);
    $conexao->commit();
} catch (mysqli_sql_exception $e) {
    $conexao->rollback();
    $conexao->close();
    error_log('DeepCheck: falha ao registrar envio da NC do item ' . $itemId . ': ' . $e->getMessage());
    responder_json([
        'status'   => 'nok',
        'mensagem' => $e->getCode() == 1062
            ? 'A solicitação desta NC já foi enviada.'
            : 'Não foi possível registrar o envio. Tente novamente.'
    ], $e->getCode() == 1062 ? 409 : 500);
}

$item['nc_id'] = $ncId;
$item['nc_enviada_em'] = $agora;
$conexao->close();

responder_json([
    'status'   => 'ok',
    'mensagem' => "Solicitação enviada para {$envio['email_responsavel']}.",
    'item'     => checklist_item_para_json($item),
], 201);
