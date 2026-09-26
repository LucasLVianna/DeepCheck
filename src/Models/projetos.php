<?php
// Acesso a dados de projetos (tabelas projetos, projeto_membros e classificacoes_nc).
// O acesso de um usuário a um projeto (nível 2) é a existência da linha em
// projeto_membros: ela é criada ao criar o projeto ou ao entrar com código + senha.

// Sem caracteres ambíguos (0/O, 1/I/L) para facilitar a digitação do código.
const PROJETO_CODIGO_ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
const PROJETO_CODIGO_TAMANHO = 8;

const PROJETO_NOME_MAX = 150;
const PROJETO_SENHA_MIN = 8;
const PROJETO_SENHA_MAX = 72; // limite do bcrypt

// Classificações de NC criadas junto com todo projeto novo (editáveis na Fase 8).
const PROJETO_CLASSIFICACOES_PADRAO = [
    ['nome' => 'Alta',    'prazo_valor' => 24, 'prazo_unidade' => 'horas'],
    ['nome' => 'Média',   'prazo_valor' => 3,  'prazo_unidade' => 'dias'],
    ['nome' => 'Simples', 'prazo_valor' => 5,  'prazo_unidade' => 'dias'],
];

function projeto_gerar_codigo(): string
{
    $codigo = '';
    $ultimo = strlen(PROJETO_CODIGO_ALFABETO) - 1;
    for ($i = 0; $i < PROJETO_CODIGO_TAMANHO; $i++) {
        $codigo .= PROJETO_CODIGO_ALFABETO[random_int(0, $ultimo)];
    }
    return $codigo;
}

// Aceita o código digitado com espaços, hífens ou minúsculas.
function projeto_normalizar_codigo(string $codigo): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
}

// Retorna a mensagem de erro, ou null se o nome for válido.
function projeto_validar_nome(string $nome): ?string
{
    if ($nome === '') {
        return 'Informe o nome do projeto.';
    }
    if (mb_strlen($nome) > PROJETO_NOME_MAX) {
        return 'O nome do projeto pode ter no máximo ' . PROJETO_NOME_MAX . ' caracteres.';
    }
    return null;
}

// Retorna a mensagem de erro, ou null se a senha for válida.
function projeto_validar_senha(string $senha): ?string
{
    if (strlen($senha) < PROJETO_SENHA_MIN || strlen($senha) > PROJETO_SENHA_MAX) {
        return 'A senha do projeto deve ter entre ' . PROJETO_SENHA_MIN . ' e ' . PROJETO_SENHA_MAX . ' caracteres.';
    }
    return null;
}

// Projetos em que o usuário é membro, do acesso mais recente para o mais antigo, com a
// quantidade de NCs abertas (itens do checklist em não conformidade com status pendente,
// não resolvida ou escalonada) e quantas delas estão com o prazo vencido.
function projetos_do_usuario(mysqli $conexao, int $usuarioId): array
{
    $ncsAbertas = "FROM checklist_itens i JOIN checklists c ON c.id = i.checklist_id
                   WHERE c.projeto_id = p.id AND i.resultado = 'nao_conformidade'
                     AND i.status_nc IN ('pendente', 'nao_resolvida', 'escalonada')";
    $stmt = $conexao->prepare(
        "SELECT p.id, p.nome, p.projeto_codigo_acesso, p.criado_por,
                u.nome_usuario AS criador_nome, m.acesso_em,
                (SELECT COUNT(*) {$ncsAbertas}) AS ncs_abertas,
                (SELECT COUNT(*) {$ncsAbertas} AND i.data_prevista_resolucao < NOW()) AS ncs_vencidas
           FROM projeto_membros m
           JOIN projetos p ON p.id = m.projeto_id
           JOIN usuario u ON u.id = p.criado_por
          WHERE m.usuario_id = ?
          ORDER BY m.acesso_em DESC, p.id DESC"
    );
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $projetos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $projetos;
}

// O projeto, se o usuário for membro dele; null se não existir ou se ele não tiver acesso.
function projeto_do_membro(mysqli $conexao, int $projetoId, int $usuarioId): ?array
{
    $stmt = $conexao->prepare(
        "SELECT p.id, p.nome, p.projeto_codigo_acesso, p.criado_por
           FROM projetos p
           JOIN projeto_membros m ON m.projeto_id = p.id AND m.usuario_id = ?
          WHERE p.id = ?"
    );
    $stmt->bind_param("ii", $usuarioId, $projetoId);
    $stmt->execute();
    $projeto = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $projeto ?: null;
}

function projeto_por_codigo(mysqli $conexao, string $codigo): ?array
{
    $stmt = $conexao->prepare("SELECT id, projeto_senha_hash FROM projetos WHERE projeto_codigo_acesso = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $projeto = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $projeto ?: null;
}

// Vincula o usuário ao projeto ou, se já for membro, atualiza o último acesso.
function projeto_registrar_acesso(mysqli $conexao, int $projetoId, int $usuarioId): void
{
    $stmt = $conexao->prepare(
        "INSERT INTO projeto_membros (projeto_id, usuario_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE acesso_em = CURRENT_TIMESTAMP"
    );
    $stmt->bind_param("ii", $projetoId, $usuarioId);
    $stmt->execute();
    $stmt->close();
}

// Cria o projeto, vincula o criador e insere as classificações padrão numa única
// transação. Retorna ['id' => ..., 'codigo' => ...].
function projeto_criar(mysqli $conexao, string $nome, string $senha, int $usuarioId): array
{
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $conexao->begin_transaction();
    try {
        $stmt = $conexao->prepare(
            "INSERT INTO projetos (nome, projeto_codigo_acesso, projeto_senha_hash, criado_por) VALUES (?, ?, ?, ?)"
        );
        // Colisão de código é improvável (31^8 combinações), mas o UNIQUE garante; tenta de novo.
        for ($tentativa = 1; ; $tentativa++) {
            $codigo = projeto_gerar_codigo();
            $stmt->bind_param("sssi", $nome, $codigo, $senhaHash, $usuarioId);
            try {
                $stmt->execute();
                break;
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1062 || $tentativa >= 5) {
                    throw $e;
                }
            }
        }
        $projetoId = $conexao->insert_id;
        $stmt->close();

        projeto_registrar_acesso($conexao, $projetoId, $usuarioId);

        $stmt = $conexao->prepare(
            "INSERT INTO classificacoes_nc (projeto_id, nome, prazo_valor, prazo_unidade) VALUES (?, ?, ?, ?)"
        );
        foreach (PROJETO_CLASSIFICACOES_PADRAO as $classificacao) {
            $stmt->bind_param(
                "isis",
                $projetoId,
                $classificacao['nome'],
                $classificacao['prazo_valor'],
                $classificacao['prazo_unidade']
            );
            $stmt->execute();
        }
        $stmt->close();

        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }

    return ['id' => $projetoId, 'codigo' => $codigo];
}

function projeto_renomear(mysqli $conexao, int $projetoId, string $nome): void
{
    $stmt = $conexao->prepare("UPDATE projetos SET nome = ? WHERE id = ?");
    $stmt->bind_param("si", $nome, $projetoId);
    $stmt->execute();
    $stmt->close();
}

// Apaga o projeto e tudo o que depende dele (ON DELETE CASCADE). As NCs enviadas são
// apagadas antes, na mesma transação: nao_conformidades.checklist_item_id é RESTRICT
// (impede excluir item com NC enviada) e bloquearia a cascata projetos → checklist_itens.
function projeto_excluir(mysqli $conexao, int $projetoId): void
{
    $conexao->begin_transaction();
    try {
        foreach (["DELETE FROM nao_conformidades WHERE projeto_id = ?", "DELETE FROM projetos WHERE id = ?"] as $sql) {
            $stmt = $conexao->prepare($sql);
            $stmt->bind_param("i", $projetoId);
            $stmt->execute();
            $stmt->close();
        }
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }
}

// Membros do projeto (nome, e-mail, último acesso), com o dono (criado_por) primeiro.
function projeto_membros(mysqli $conexao, int $projetoId): array
{
    $stmt = $conexao->prepare(
        "SELECT u.id, u.nome_usuario, u.email_usuario, m.acesso_em, (u.id = p.criado_por) AS dono
           FROM projeto_membros m
           JOIN usuario u ON u.id = m.usuario_id
           JOIN projetos p ON p.id = m.projeto_id
          WHERE m.projeto_id = ?
          ORDER BY dono DESC, u.nome_usuario"
    );
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    $membros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $membros;
}

// Remove o vínculo do usuário (sair do projeto ou ser removido pelo dono).
// O acesso volta a exigir código + senha do projeto.
function projeto_remover_membro(mysqli $conexao, int $projetoId, int $usuarioId): void
{
    $stmt = $conexao->prepare("DELETE FROM projeto_membros WHERE projeto_id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $projetoId, $usuarioId);
    $stmt->execute();
    $stmt->close();
}

function projeto_trocar_senha(mysqli $conexao, int $projetoId, string $senha): void
{
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conexao->prepare("UPDATE projetos SET projeto_senha_hash = ? WHERE id = ?");
    $stmt->bind_param("si", $hash, $projetoId);
    $stmt->execute();
    $stmt->close();
}

// Passa a posse (criado_por) para outro membro; o dono anterior continua membro.
function projeto_transferir_posse(mysqli $conexao, int $projetoId, int $novoDonoId): void
{
    $stmt = $conexao->prepare("UPDATE projetos SET criado_por = ? WHERE id = ?");
    $stmt->bind_param("ii", $novoDonoId, $projetoId);
    $stmt->execute();
    $stmt->close();
}
