<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/rate_limit.php';
require_once __DIR__ . '/../Models/usuarios.php';

// Senha atual errada ao trocar e-mail/senha: limite por usuário (impede força bruta
// a partir de uma sessão aberta).
const PERFIL_MAX_FALHAS_SENHA = 5;

exigir_login_api();
exigir_post_com_csrf();

$texto = fn($chave) => is_string($_POST[$chave] ?? null) ? $_POST[$chave] : '';
$acao = $texto('acao');
$usuarioId = (int) $_SESSION['usuario']['id'];

require_once __DIR__ . '/../../config/conexao.php';
$usuario = usuario_por_id($conexao, $usuarioId);

// Confere a senha atual (para trocar e-mail ou senha), com rate limit.
$confirmarSenhaAtual = function () use ($texto, $usuario, $usuarioId, $conexao): void {
    $chave = 'perfil-senha|' . $usuarioId;
    $espera = rate_limit_segundos_restantes($chave, PERFIL_MAX_FALHAS_SENHA);
    if ($espera > 0) {
        $conexao->close();
        header('Retry-After: ' . $espera);
        responder_json(['status' => 'nok', 'mensagem' => 'Muitas tentativas com a senha atual errada. Tente novamente em ' . (int) ceil($espera / 60) . ' minuto(s).'], 429);
    }
    if (!password_verify($texto('senha_atual'), $usuario['senha'])) {
        rate_limit_registrar_falha($chave);
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => 'A senha atual está incorreta.'], 403);
    }
    rate_limit_limpar($chave);
};

switch ($acao) {
    case 'dados':
        $nome = trim($texto('nome'));
        $cep = usuario_normalizar_cep($texto('cep'));
        if (($erro = usuario_validar_nome($nome) ?? usuario_validar_cep($cep)) !== null) {
            $conexao->close();
            responder_json(['status' => 'nok', 'mensagem' => $erro], 400);
        }
        usuario_atualizar_dados($conexao, $usuarioId, $nome, $cep);
        $_SESSION['usuario']['nome'] = $nome;
        $conexao->close();
        responder_json(['status' => 'ok', 'mensagem' => 'Dados atualizados.', 'nome' => $nome]);

    case 'email':
        $email = usuario_normalizar_email($texto('email'));
        if (($erro = usuario_validar_email($email)) !== null) {
            $conexao->close();
            responder_json(['status' => 'nok', 'mensagem' => $erro], 400);
        }
        if ($email === $usuario['email_usuario']) {
            $conexao->close();
            responder_json(['status' => 'nok', 'mensagem' => 'Este já é o seu e-mail atual.'], 400);
        }
        $confirmarSenhaAtual();
        try {
            usuario_atualizar_email($conexao, $usuarioId, $email);
        } catch (mysqli_sql_exception $e) {
            $conexao->close();
            if ($e->getCode() == 1062) {
                responder_json(['status' => 'nok', 'mensagem' => 'Este e-mail já está cadastrado em outra conta.'], 409);
            }
            error_log('DeepCheck: falha ao trocar e-mail do usuário ' . $usuarioId . ': ' . $e->getMessage());
            responder_json(['status' => 'nok', 'mensagem' => 'Não foi possível alterar o e-mail. Tente novamente.'], 500);
        }
        $conexao->close();
        responder_json(['status' => 'ok', 'mensagem' => 'E-mail alterado. Use o novo e-mail no próximo login.', 'email' => $email]);

    case 'senha':
        $nova = $texto('nova_senha');
        if (($erro = usuario_validar_senha($nova)) !== null) {
            $conexao->close();
            responder_json(['status' => 'nok', 'mensagem' => $erro], 400);
        }
        if ($nova !== $texto('confirmar_senha')) {
            $conexao->close();
            responder_json(['status' => 'nok', 'mensagem' => 'A confirmação não confere com a nova senha.'], 400);
        }
        $confirmarSenhaAtual();
        usuario_atualizar_senha($conexao, $usuarioId, $nova);
        senha_redefinicao_invalidar($conexao, $usuarioId); // links de "esqueci a senha" pendentes deixam de valer
        $conexao->close();
        session_regenerate_id(true);
        responder_json(['status' => 'ok', 'mensagem' => 'Senha alterada.']);

    default:
        $conexao->close();
        responder_json(['status' => 'nok', 'mensagem' => 'Ação inválida.'], 400);
}
