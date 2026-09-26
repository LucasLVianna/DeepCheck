<?php
// Contas de usuário (tabela usuario): regras de nome, e-mail, CEP e senha usadas no
// cadastro, no perfil e na redefinição de senha.

const USUARIO_NOME_MAX = 100;
const USUARIO_EMAIL_MAX = 255;
const USUARIO_SENHA_MIN = 8;
const USUARIO_SENHA_MAX = 72; // limite do bcrypt: acima disso o restante seria ignorado

// Cada função retorna a mensagem de erro, ou null se o valor for válido.
function usuario_validar_nome(string $nome): ?string
{
    if ($nome === '') {
        return 'Informe o nome.';
    }
    return mb_strlen($nome) > USUARIO_NOME_MAX ? 'O nome pode ter no máximo ' . USUARIO_NOME_MAX . ' caracteres.' : null;
}

function usuario_validar_email(string $email): ?string
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= USUARIO_EMAIL_MAX ? null : 'Digite um e-mail válido.';
}

function usuario_validar_cep(string $cepNumerico): ?string
{
    return strlen($cepNumerico) === 8 ? null : 'Digite um CEP válido no formato 00000-000.';
}

// Mesma regra de public/js/autenticacao.js (REQUISITOS_SENHA).
function usuario_validar_senha(string $senha): ?string
{
    if (strlen($senha) < USUARIO_SENHA_MIN || strlen($senha) > USUARIO_SENHA_MAX
        || !preg_match('/[A-Z]/', $senha) || !preg_match('/[a-z]/', $senha)
        || !preg_match('/[0-9]/', $senha) || !preg_match('/[@$!%*?&]/', $senha)) {
        return 'A senha deve ter entre 8 e 72 caracteres, com letras maiúsculas, minúsculas, números e caracteres especiais (@$!%*?&).';
    }
    return null;
}

function usuario_normalizar_email(string $email): string
{
    return mb_strtolower(trim($email));
}

function usuario_normalizar_cep(string $cep): string
{
    return preg_replace('/\D/', '', $cep);
}

function usuario_por_id(mysqli $conexao, int $id): ?array
{
    $stmt = $conexao->prepare("SELECT id, nome_usuario, email_usuario, senha, cep, criado_em FROM usuario WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $usuario ?: null;
}

function usuario_atualizar_dados(mysqli $conexao, int $id, string $nome, string $cep): void
{
    $stmt = $conexao->prepare("UPDATE usuario SET nome_usuario = ?, cep = ? WHERE id = ?");
    $stmt->bind_param("ssi", $nome, $cep, $id);
    $stmt->execute();
    $stmt->close();
}

// Lança mysqli_sql_exception com código 1062 se o e-mail já pertencer a outra conta.
function usuario_atualizar_email(mysqli $conexao, int $id, string $email): void
{
    $stmt = $conexao->prepare("UPDATE usuario SET email_usuario = ? WHERE id = ?");
    $stmt->bind_param("si", $email, $id);
    $stmt->execute();
    $stmt->close();
}

function usuario_atualizar_senha(mysqli $conexao, int $id, string $senha): void
{
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conexao->prepare("UPDATE usuario SET senha = ? WHERE id = ?");
    $stmt->bind_param("si", $hash, $id);
    $stmt->execute();
    $stmt->close();
}

// --- Redefinição de senha ("Esqueceu a senha?") ---
// O token vai por e-mail (link); no banco fica só o hash SHA-256 dele. Vale 1 hora e é
// de uso único; pedir um novo invalida os anteriores do mesmo usuário.

const SENHA_REDEFINICAO_VALIDADE = 3600; // segundos

function usuario_por_email(mysqli $conexao, string $email): ?array
{
    $stmt = $conexao->prepare("SELECT id, nome_usuario, email_usuario, conta_ativa FROM usuario WHERE email_usuario = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $usuario ?: null;
}

// Invalida os pedidos ainda não usados do usuário (novo pedido, senha trocada no perfil ou redefinida).
function senha_redefinicao_invalidar(mysqli $conexao, int $usuarioId): void
{
    $stmt = $conexao->prepare("UPDATE senha_redefinicoes SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL");
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $stmt->close();
}

// Cria um pedido e retorna o token em claro (só vai no link do e-mail).
function senha_redefinicao_criar(mysqli $conexao, int $usuarioId): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expira = date('Y-m-d H:i:s', time() + SENHA_REDEFINICAO_VALIDADE);

    $conexao->begin_transaction();
    try {
        senha_redefinicao_invalidar($conexao, $usuarioId);
        $stmt = $conexao->prepare("INSERT INTO senha_redefinicoes (usuario_id, token_hash, expira_em) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $usuarioId, $hash, $expira);
        $stmt->execute();
        $stmt->close();
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }
    return $token;
}

// Pedido válido (não usado, não expirado, conta ativa) para o token; null caso contrário.
function senha_redefinicao_valida(mysqli $conexao, string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = $conexao->prepare(
        "SELECT r.id, r.usuario_id, u.nome_usuario, u.email_usuario
           FROM senha_redefinicoes r
           JOIN usuario u ON u.id = r.usuario_id
          WHERE r.token_hash = ? AND r.usado_em IS NULL AND r.expira_em > NOW() AND u.conta_ativa = 1"
    );
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $pedido ?: null;
}

// Grava a nova senha e marca o pedido (e qualquer outro do usuário) como usado.
function senha_redefinicao_concluir(mysqli $conexao, array $pedido, string $novaSenha): void
{
    $conexao->begin_transaction();
    try {
        usuario_atualizar_senha($conexao, (int) $pedido['usuario_id'], $novaSenha);
        senha_redefinicao_invalidar($conexao, (int) $pedido['usuario_id']);
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }
}
