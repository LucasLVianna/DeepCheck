<?php
// Sessão, autenticação e CSRF. Toda página e todo controller devem incluir
// este arquivo antes de qualquer saída: ele envia os headers de segurança
// e inicia a sessão com cookie endurecido.

require_once __DIR__ . '/headers.php';

const SESSION_TIMEOUT = 1800; // 30 minutos de inatividade

function iniciar_sessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('DEEPCHECKSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => requisicao_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function encerrar_sessao(): void
{
    session_unset();
    $_SESSION = [];

    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);

    session_destroy();
}

// Retorna 'ativa', 'expirada' ou 'anonima', aplicando o timeout por inatividade.
function estado_sessao(): string
{
    static $estado = null;
    if ($estado !== null) {
        return $estado;
    }

    if (empty($_SESSION['logado']) || empty($_SESSION['usuario']['id'])) {
        return $estado = 'anonima';
    }

    if (time() - ($_SESSION['ultima_atividade'] ?? 0) > SESSION_TIMEOUT) {
        encerrar_sessao();
        return $estado = 'expirada';
    }

    $_SESSION['ultima_atividade'] = time();
    return $estado = 'ativa';
}

// Chamado após validar as credenciais. Gera um novo ID de sessão para
// evitar session fixation e descarta qualquer dado da sessão anônima.
function autenticar_usuario(int $id, string $nome): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'logado'           => true,
        'usuario'          => ['id' => $id, 'nome' => $nome],
        'ultima_atividade' => time(),
    ];
}

// Para páginas: redireciona ao login se não houver sessão ativa.
function exigir_login(): void
{
    $estado = estado_sessao();
    if ($estado !== 'ativa') {
        $motivo = $estado === 'expirada' ? '?motivo=expirado' : '';
        header('Location: /src/Views/login.php' . $motivo);
        exit;
    }
}

// Para endpoints JSON: responde 401 se não houver sessão ativa.
function exigir_login_api(): void
{
    $estado = estado_sessao();
    if ($estado !== 'ativa') {
        responder_json([
            'status'   => 'nok',
            'expirado' => $estado === 'expirada',
            'mensagem' => $estado === 'expirada' ? 'Sessão expirada' : 'Não autenticado'
        ], 401);
    }
}

// Para login/cadastro: quem já está logado vai direto ao menu.
function redirecionar_se_logado(): void
{
    if (estado_sessao() === 'ativa') {
        header('Location: /src/Views/menu.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Aceita o token pelo header X-CSRF-Token (fetch) ou pelo campo csrf_token (form).
function csrf_valido(): bool
{
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    return is_string($enviado)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $enviado);
}

// Para endpoints JSON que alteram estado: exige POST e token CSRF válido.
function exigir_post_com_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        responder_json(['status' => 'nok', 'mensagem' => 'Método não permitido'], 405);
    }
    if (!csrf_valido()) {
        responder_json([
            'status'   => 'nok',
            'mensagem' => 'Sessão inválida ou expirada. Recarregue a página e tente novamente.'
        ], 403);
    }
}

function responder_json(array $dados, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

iniciar_sessao();
