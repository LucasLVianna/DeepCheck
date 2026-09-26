<?php
// Classificações de NC de cada projeto (tabela classificacoes_nc): gravidade + prazo de
// resolução. Editadas na seção 6 do PGQ por qualquer membro do projeto.
require_once __DIR__ . '/checklist.php';

const CLASSIFICACAO_NOME_MAX = 50;
const CLASSIFICACAO_PRAZO_MAX = 999;
const CLASSIFICACAO_UNIDADES = ['horas' => 'horas', 'dias' => 'dias úteis'];

// Classificações do projeto, do menor prazo para o maior.
function classificacoes_do_projeto(mysqli $conexao, int $projetoId): array
{
    $stmt = $conexao->prepare(
        "SELECT id, nome, prazo_valor, prazo_unidade
           FROM classificacoes_nc
          WHERE projeto_id = ?
          ORDER BY prazo_valor * IF(prazo_unidade = 'dias', 24, 1), nome"
    );
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    $classificacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $classificacoes;
}

// "24 horas", "1 dia", "3 dias"...
function classificacao_prazo_texto(array $classificacao): string
{
    $valor = (int) $classificacao['prazo_valor'];
    $unidade = $classificacao['prazo_unidade'] === 'dias' ? 'dia' : 'hora';
    return $valor . ' ' . $unidade . ($valor === 1 ? '' : 's');
}

// Classificações do projeto com quantos itens do checklist e quantas NCs enviadas usam cada uma.
function classificacoes_com_uso(mysqli $conexao, int $projetoId): array
{
    $stmt = $conexao->prepare(
        "SELECT cl.id, cl.nome, cl.prazo_valor, cl.prazo_unidade,
                (SELECT COUNT(*) FROM checklist_itens i WHERE i.classificacao_nc_id = cl.id) AS itens_em_uso,
                (SELECT COUNT(*) FROM nao_conformidades n WHERE n.classificacao_nc_id = cl.id) AS ncs_em_uso
           FROM classificacoes_nc cl
          WHERE cl.projeto_id = ?
          ORDER BY cl.prazo_valor * IF(cl.prazo_unidade = 'dias', 24, 1), cl.nome"
    );
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    $classificacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($c) => $c + ['prazo_texto' => classificacao_prazo_texto($c)], $classificacoes);
}

// A classificação, se o usuário for membro do projeto dela; null caso contrário.
function classificacao_do_membro(mysqli $conexao, int $id, int $usuarioId): ?array
{
    $stmt = $conexao->prepare(
        "SELECT cl.id, cl.projeto_id, cl.nome, cl.prazo_valor, cl.prazo_unidade
           FROM classificacoes_nc cl
           JOIN projeto_membros m ON m.projeto_id = cl.projeto_id AND m.usuario_id = ?
          WHERE cl.id = ?"
    );
    $stmt->bind_param("ii", $usuarioId, $id);
    $stmt->execute();
    $classificacao = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $classificacao ?: null;
}

// Valida nome, prazo e unidade. Retorna ['erro' => ...] ou os dados normalizados.
function classificacao_ler_entrada(array $post): array
{
    $texto = fn($chave) => is_string($post[$chave] ?? null) ? trim($post[$chave]) : '';

    $nome = $texto('nome');
    if ($nome === '' || mb_strlen($nome) > CLASSIFICACAO_NOME_MAX) {
        return ['erro' => 'Informe o nome da classificação (até ' . CLASSIFICACAO_NOME_MAX . ' caracteres).'];
    }
    $prazo = filter_var($texto('prazo_valor'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => CLASSIFICACAO_PRAZO_MAX]]);
    if ($prazo === false) {
        return ['erro' => 'O prazo deve ser um número inteiro de 1 a ' . CLASSIFICACAO_PRAZO_MAX . '.'];
    }
    $unidade = $texto('prazo_unidade');
    if (!isset(CLASSIFICACAO_UNIDADES[$unidade])) {
        return ['erro' => 'Unidade de prazo inválida.'];
    }
    return ['nome' => $nome, 'prazo_valor' => $prazo, 'prazo_unidade' => $unidade];
}

// Recalcula a data prevista dos itens do checklist com NC ainda NÃO enviada que usam a
// classificação (a partir da identificação, em dias úteis). NCs já enviadas mantêm o
// prazo comunicado. Retorna quantos itens foram recalculados.
function classificacao_recalcular_itens(mysqli $conexao, array $classificacao): int
{
    $stmt = $conexao->prepare(
        "SELECT i.id, i.data_identificacao_nc
           FROM checklist_itens i
           LEFT JOIN nao_conformidades nc ON nc.checklist_item_id = i.id
          WHERE i.classificacao_nc_id = ? AND i.resultado = 'nao_conformidade' AND nc.id IS NULL"
    );
    $stmt->bind_param("i", $classificacao['id']);
    $stmt->execute();
    $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conexao->prepare("UPDATE checklist_itens SET data_prevista_resolucao = ? WHERE id = ?");
    foreach ($itens as $item) {
        $prevista = checklist_calcular_prevista($item['data_identificacao_nc'], $classificacao);
        $stmt->bind_param("si", $prevista, $item['id']);
        $stmt->execute();
    }
    $stmt->close();
    return count($itens);
}

// Histórico de alterações das classificações do projeto (mais recentes primeiro).
function classificacoes_historico(mysqli $conexao, int $projetoId, int $limite = 30): array
{
    $stmt = $conexao->prepare(
        "SELECT h.acao, h.descricao, h.criado_em, u.nome_usuario
           FROM classificacoes_nc_historico h
           JOIN usuario u ON u.id = h.usuario_id
          WHERE h.projeto_id = ?
          ORDER BY h.id DESC
          LIMIT ?"
    );
    $stmt->bind_param("ii", $projetoId, $limite);
    $stmt->execute();
    $historico = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($h) => $h + ['quando' => date('d/m/Y H:i', strtotime($h['criado_em']))], $historico);
}

function classificacao_registrar_historico(mysqli $conexao, int $projetoId, int $usuarioId, string $acao, string $descricao): void
{
    $descricao = mb_substr($descricao, 0, 500);
    $stmt = $conexao->prepare("INSERT INTO classificacoes_nc_historico (projeto_id, usuario_id, acao, descricao) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $projetoId, $usuarioId, $acao, $descricao);
    $stmt->execute();
    $stmt->close();
}

// Cria ($atual null) ou atualiza a classificação e registra no histórico. Ao atualizar
// com prazo diferente, recalcula os itens com NC não enviada.
// Retorna ['id' => ..., 'recalculados' => n].
function classificacao_salvar(mysqli $conexao, int $projetoId, ?array $atual, array $dados, int $usuarioId): array
{
    $conexao->begin_transaction();
    try {
        if ($atual === null) {
            $stmt = $conexao->prepare("INSERT INTO classificacoes_nc (projeto_id, nome, prazo_valor, prazo_unidade) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isis", $projetoId, $dados['nome'], $dados['prazo_valor'], $dados['prazo_unidade']);
            $stmt->execute();
            $id = $conexao->insert_id;
            $stmt->close();
            $recalculados = 0;
            classificacao_registrar_historico($conexao, $projetoId, $usuarioId, 'criada',
                "\"{$dados['nome']}\" criada com prazo de " . classificacao_prazo_texto($dados) . '.');
        } else {
            $id = (int) $atual['id'];
            $stmt = $conexao->prepare("UPDATE classificacoes_nc SET nome = ?, prazo_valor = ?, prazo_unidade = ? WHERE id = ?");
            $stmt->bind_param("sisi", $dados['nome'], $dados['prazo_valor'], $dados['prazo_unidade'], $id);
            $stmt->execute();
            $stmt->close();
            $prazoMudou = (int) $atual['prazo_valor'] !== $dados['prazo_valor'] || $atual['prazo_unidade'] !== $dados['prazo_unidade'];
            $recalculados = $prazoMudou ? classificacao_recalcular_itens($conexao, ['id' => $id] + $dados) : 0;

            $mudancas = [];
            if ($atual['nome'] !== $dados['nome']) {
                $mudancas[] = "nome \"{$atual['nome']}\" → \"{$dados['nome']}\"";
            }
            if ($prazoMudou) {
                $mudancas[] = 'prazo ' . classificacao_prazo_texto($atual) . ' → ' . classificacao_prazo_texto($dados)
                    . ($recalculados ? " ({$recalculados} item(ns) não enviado(s) recalculado(s))" : '');
            }
            if ($mudancas) {
                classificacao_registrar_historico($conexao, $projetoId, $usuarioId, 'alterada',
                    "\"{$dados['nome']}\": " . implode('; ', $mudancas) . '.');
            }
        }
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }
    return ['id' => $id, 'recalculados' => $recalculados];
}


// Exclui a classificação e registra no histórico. Se estiver em uso, $substituta (outra
// classificação do mesmo projeto) passa a ser usada nos itens do checklist e nas NCs;
// itens com NC não enviada têm a data prevista recalculada com a substituta. Tudo numa
// transação que trava as classificações do projeto, para duas exclusões simultâneas não
// deixarem o projeto sem nenhuma.
// Retorna ['erro' => ...] ou ['itens' => n, 'ncs' => n, 'recalculados' => n].
function classificacao_excluir(mysqli $conexao, array $classificacao, ?array $substituta, int $usuarioId): array
{
    $projetoId = (int) $classificacao['projeto_id'];
    $conexao->begin_transaction();
    try {
        $stmt = $conexao->prepare("SELECT id FROM classificacoes_nc WHERE projeto_id = ? FOR UPDATE");
        $stmt->bind_param("i", $projetoId);
        $stmt->execute();
        $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $stmt->close();
        if (!in_array($classificacao['id'], $ids) || ($substituta !== null && !in_array($substituta['id'], $ids))) {
            $conexao->rollback();
            return ['erro' => 'A classificação foi alterada por outra pessoa. Recarregue a página.'];
        }
        if (count($ids) <= 1) {
            $conexao->rollback();
            return ['erro' => 'O projeto precisa ter pelo menos uma classificação.'];
        }

        $itens = $ncs = $recalculados = 0;
        if ($substituta !== null) {
            $stmt = $conexao->prepare("UPDATE checklist_itens SET classificacao_nc_id = ? WHERE classificacao_nc_id = ?");
            $stmt->bind_param("ii", $substituta['id'], $classificacao['id']);
            $stmt->execute();
            $itens = $stmt->affected_rows;
            $stmt->close();

            $stmt = $conexao->prepare("UPDATE nao_conformidades SET classificacao_nc_id = ? WHERE classificacao_nc_id = ?");
            $stmt->bind_param("ii", $substituta['id'], $classificacao['id']);
            $stmt->execute();
            $ncs = $stmt->affected_rows;
            $stmt->close();

            $recalculados = classificacao_recalcular_itens($conexao, $substituta);
        }

        $stmt = $conexao->prepare("DELETE FROM classificacoes_nc WHERE id = ?");
        $stmt->bind_param("i", $classificacao['id']);
        $stmt->execute();
        $stmt->close();

        $descricao = "\"{$classificacao['nome']}\" (" . classificacao_prazo_texto($classificacao) . ') excluída';
        if ($substituta !== null) {
            $descricao .= "; substituída por \"{$substituta['nome']}\" em {$itens} item(ns) do checklist e {$ncs} NC(s)";
        }
        classificacao_registrar_historico($conexao, $projetoId, $usuarioId, 'excluida', $descricao . '.');
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }
    return ['itens' => $itens, 'ncs' => $ncs, 'recalculados' => $recalculados];
}
