<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/rate_limit.php';
require_once __DIR__ . '/../../config/email.php';
require_once __DIR__ . '/../Models/classificacoes_nc.php';
require_once __DIR__ . '/../Models/nao_conformidades.php';

// Mesmo limite (e mesma chave) do envio da NC: protege a conta de e-mail do sistema.
const NC_ENVIOS_MAX_POR_USUARIO = 20;

exigir_login_api();
exigir_post_com_csrf();

$ncId = filter_var($_POST['nc_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
// Nº de escalonamento que o usuário via na tela: evita escalonar duas vezes com um clique duplo.
$numeroVisto = filter_var($_POST['numero_escalonamento'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($ncId === false || $numeroVisto === false) {
    responder_json(['status' => 'nok', 'mensagem' => 'Requisição inválida.'], 400);
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

$nc = nc_do_membro($conexao, $ncId, $usuarioId);
if ($nc === null) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Não conformidade não encontrada.'], 404);
}
if (!in_array($nc['status'], NC_STATUS_ABERTOS, true)) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Só é possível escalonar uma NC aberta (pendente, não resolvida ou escalonada).'], 409);
}

$agora = date('Y-m-d H:i:s');
$escalonamento = nc_ler_escalonamento($_POST, $nc['prazo_unidade'], $agora);
if (isset($escalonamento['erro'])) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => $escalonamento['erro']], 400);
}

$usuario = nc_usuario_remetente($conexao, $usuarioId);
$primeiroEnvio = nc_primeiro_email($conexao, $ncId);
set_time_limit(60);
rate_limit_registrar_falha($chaveEnvio); // conta toda tentativa de envio, com ou sem sucesso

// Escalonamento e e-mail na mesma transação: se o envio falhar, nada fica gravado.
$conexao->begin_transaction();
try {
    $stmt = $conexao->prepare("SELECT numero_escalonamento FROM nao_conformidades WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $ncId);
    $stmt->execute();
    $numeroAtual = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    if ($numeroAtual !== $numeroVisto || $numeroAtual !== (int) $nc['numero_escalonamento']) {
        $conexao->rollback();
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => 'Esta NC acabou de ser escalonada por outra ação. Recarregue a página.'], 409);
    }

    $escalonamentoId = nc_registrar_escalonamento($conexao, $nc, $escalonamento, $usuarioId, $agora);
    $nc['numero_escalonamento'] = $numeroAtual + 1;
    $nc['responsavel_resolucao'] = $escalonamento['responsavel_resolucao'];

    $texto = nc_texto_escalonamento($nc['projeto_nome'], $escalonamento['superior_nome'], $nc['responsavel_qa'], $nc['numero_escalonamento']);
    $cc = nc_lista_cc(
        array_merge([$nc['responsavel_email']], nc_envolvidos($conexao, $ncId), [$usuario['email_usuario']]),
        $escalonamento['superior_email']
    );
    $anexoNome = nc_nome_anexo((int) $nc['numero_item'], $nc['numero_escalonamento']);
    $extras = $primeiroEnvio
        ? [['nome' => $primeiroEnvio['anexo_nome'], 'base64' => base64_encode($primeiroEnvio['anexo_pdf'])]]
        : [];

    $resultado = email_executar_script(nc_entrada_script(
        $usuario,
        $escalonamento['superior_email'],
        $escalonamento['superior_nome'],
        $cc,
        $texto,
        nc_documento($nc, nc_historico($conexao, $ncId), $escalonamento['observacoes']),
        $anexoNome,
        $extras
    ));
    if (!$resultado['ok']) {
        $conexao->rollback();
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => $resultado['erro']], 502);
    }

    nc_registrar_email($conexao, [
        'nao_conformidade_id' => $ncId,
        'escalonamento_id'    => $escalonamentoId,
        'enviado_por'         => $usuarioId,
        'destinatario'        => $escalonamento['superior_email'],
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
    error_log('DeepCheck: falha ao registrar escalonamento da NC ' . $ncId . ': ' . $e->getMessage());
    responder_json([
        'status'   => 'nok',
        'mensagem' => $e->getCode() == 1062
            ? 'Esta NC acabou de ser escalonada por outra ação. Recarregue a página.'
            : 'Não foi possível registrar o escalonamento. Tente novamente.'
    ], $e->getCode() == 1062 ? 409 : 500);
}
$conexao->close();

responder_json([
    'status'   => 'ok',
    'mensagem' => "Escalonamento Nº {$nc['numero_escalonamento']} enviado para {$escalonamento['superior_email']}.",
], 201);
