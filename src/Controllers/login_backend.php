<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/rate_limit.php';

const LOGIN_MAX_FALHAS_CONTA = 5;  // por IP + e-mail, na janela do rate limit
const LOGIN_MAX_FALHAS_IP    = 20; // por IP, qualquer e-mail

exigir_post_com_csrf();

$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$senha = (string) ($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    responder_json(['status' => 'nok', 'mensagem' => 'Informe e-mail e senha.'], 400);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$chaveConta = 'login|' . $ip . '|' . $email;
$chaveIp    = 'login-ip|' . $ip;

$espera = max(
    rate_limit_segundos_restantes($chaveConta, LOGIN_MAX_FALHAS_CONTA),
    rate_limit_segundos_restantes($chaveIp, LOGIN_MAX_FALHAS_IP)
);
if ($espera > 0) {
    header('Retry-After: ' . $espera);
    responder_json([
        'status'   => 'nok',
        'mensagem' => 'Muitas tentativas de login. Tente novamente em ' . (int) ceil($espera / 60) . ' minuto(s).'
    ], 429);
}

require_once __DIR__ . '/../../config/conexao.php';

$stmt = $conexao->prepare("SELECT id, nome_usuario, senha, conta_ativa FROM usuario WHERE email_usuario = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

$senhaCorreta = password_verify($senha, $usuario['senha'] ?? HASH_FICTICIO) && $usuario !== null;

if (!$senhaCorreta) {
    rate_limit_registrar_falha($chaveConta);
    rate_limit_registrar_falha($chaveIp);
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Email ou senha incorretos'], 401);
}

rate_limit_limpar($chaveConta);

if (!$usuario['conta_ativa']) {
    $conexao->close();
    responder_json(['status' => 'nok', 'mensagem' => 'Conta desativada. Entre em contato com o suporte.'], 403);
}

// Atualiza o hash se o algoritmo/custo padrão do PHP tiver mudado desde o cadastro.
if (password_needs_rehash($usuario['senha'], PASSWORD_DEFAULT)) {
    $novoHash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conexao->prepare("UPDATE usuario SET senha = ? WHERE id = ?");
    $stmt->bind_param("si", $novoHash, $usuario['id']);
    $stmt->execute();
    $stmt->close();
}
$conexao->close();

autenticar_usuario((int) $usuario['id'], $usuario['nome_usuario']);

responder_json([
    'status'   => 'ok',
    'mensagem' => 'Login realizado com sucesso',
    'redirect' => '/src/Views/menu.php'
]);
