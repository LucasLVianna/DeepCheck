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
if ($ncId === false) {
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
    responder_json(['status' => 'nok', 'mensagem' => 'Só é possível reenviar uma NC aberta (pendente, não resolvida ou escalonada).'], 409);
}

// Reenvio: documento atual (com o histórico, se houver) ao responsável pela resolução
// atual (o do último escalonamento), com cópia para o usuário e para as cópias do 1º envio.
$usuario = nc_usuario_remetente($conexao, $usuarioId);
$primeiroEnvio = nc_primeiro_email($conexao, $ncId);
$historico = nc_historico($conexao, $ncId);
$ultimoResponsavel = $nc['responsavel_resolucao'];

$texto = nc_texto_reenvio($nc['projeto_nome'], $ultimoResponsavel, $nc['responsavel_qa']);
$cc = nc_lista_cc(
    array_merge([$usuario['email_usuario']], explode(',', (string) ($primeiroEnvio['cc'] ?? ''))),
    $nc['responsavel_email']
);
$anexoNome = nc_nome_anexo((int) $nc['numero_item'], (int) $nc['numero_escalonamento']);

set_time_limit(60);
rate_limit_registrar_falha($chaveEnvio);

$resultado = email_executar_script(nc_entrada_script(
    $usuario,
    $nc['responsavel_email'],
    $ultimoResponsavel,
    $cc,
    $texto,
    nc_documento($nc, $historico, $nc['observacoes']),
    $anexoNome
));
if (!$resultado['ok']) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => $resultado['erro']], 502);
}

try {
    nc_registrar_email($conexao, [
        'nao_conformidade_id' => $ncId,
        'escalonamento_id'    => null,
        'enviado_por'         => $usuarioId,
        'destinatario'        => $nc['responsavel_email'],
        'cc'                  => implode(', ', $cc),
        'responder_para'      => $usuario['email_usuario'],
        'assunto'             => $texto['assunto'],
        'corpo'               => $texto['corpo'],
        'anexo_nome'          => $anexoNome,
        'anexo_pdf'           => $resultado['pdf'],
        'status_envio'        => 'sucesso',
        'erro_envio'          => null,
    ]);
} catch (mysqli_sql_exception $e) {
    // O e-mail já saiu; só o registro falhou.
    error_log('DeepCheck: reenvio da NC ' . $ncId . ' enviado mas não registrado: ' . $e->getMessage());
}
$conexao->close();

responder_json(['status' => 'ok', 'mensagem' => "Solicitação reenviada para {$nc['responsavel_email']}."]);
