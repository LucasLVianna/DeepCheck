<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/rate_limit.php';
require_once __DIR__ . '/../../config/email.php';
require_once __DIR__ . '/../Models/usuarios.php';

// "Esqueceu a senha?": envia um link de redefinição (válido por 1 hora) para o e-mail,
// se ele pertencer a uma conta ativa. A resposta é sempre a mesma, exista a conta ou não,
// para não revelar quais e-mails estão cadastrados.
const ESQUECI_MAX_POR_EMAIL = 3;  // pedidos por e-mail na janela do rate limit
const ESQUECI_MAX_POR_IP = 10;    // pedidos por IP

exigir_post_com_csrf();

$email = usuario_normalizar_email(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
if (($erro = usuario_validar_email($email)) !== null) {
    responder_json(['status' => 'nok', 'mensagem' => $erro], 400);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$chaveEmail = 'esqueci-senha|' . $email;
$chaveIp = 'esqueci-senha-ip|' . $ip;
$espera = max(
    rate_limit_segundos_restantes($chaveEmail, ESQUECI_MAX_POR_EMAIL),
    rate_limit_segundos_restantes($chaveIp, ESQUECI_MAX_POR_IP)
);
if ($espera > 0) {
    header('Retry-After: ' . $espera);
    responder_json(['status' => 'nok', 'mensagem' => 'Muitos pedidos de redefinição. Tente novamente em ' . (int) ceil($espera / 60) . ' minuto(s).'], 429);
}
rate_limit_registrar_falha($chaveEmail);
rate_limit_registrar_falha($chaveIp);

$mensagemPadrao = 'Se este e-mail estiver cadastrado, você vai receber em instantes um link para criar uma nova senha. O link vale por 1 hora. Confira também a caixa de spam.';

require_once __DIR__ . '/../../config/conexao.php';
$usuario = usuario_por_email($conexao, $email);
if ($usuario === null || !$usuario['conta_ativa']) {
    $conexao->close();
    responder_json(['status' => 'ok', 'mensagem' => $mensagemPadrao]);
}

$token = senha_redefinicao_criar($conexao, (int) $usuario['id']);
$conexao->close();

$link = rtrim(env('APP_URL', 'http://localhost:8080'), '/') . '/src/Views/redefinir_senha.php?token=' . $token;
set_time_limit(60);
$envio = email_enviar_texto(
    $usuario['email_usuario'],
    $usuario['nome_usuario'],
    'Redefinição de senha - DeepCheck',
    "Olá, {$usuario['nome_usuario']},\n\n"
    . "Recebemos um pedido para redefinir a senha da sua conta no DeepCheck.\n\n"
    . "Para criar uma nova senha, acesse o link abaixo (válido por 1 hora, uso único):\n{$link}\n\n"
    . "Se você não fez esse pedido, ignore este e-mail: sua senha continua a mesma.\n\n"
    . "Equipe DeepCheck\n"
);
if (!$envio['ok']) {
    // O detalhe já foi para o log; para o usuário a resposta continua genérica.
    error_log('DeepCheck: falha ao enviar e-mail de redefinição de senha para o usuário ' . $usuario['id']);
}

responder_json(['status' => 'ok', 'mensagem' => $mensagemPadrao]);
