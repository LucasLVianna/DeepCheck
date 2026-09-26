<?php
// Acesso a dados e regras do Checklist de Qualidade (tabelas checklists e checklist_itens).
// Um checklist por projeto, criado no primeiro item. Colunas seguem o template
// "Modelo Checklist - Processo de Qualidade"; status e fórmula de aderência seguem
// as "Regras de Negócio" do CLAUDE.md.

const CHECKLIST_NOME_PADRAO = 'Checklist de Qualidade';
const CHECKLIST_NOME_MAX = 150;
const CHECKLIST_ITENS_MAX = 500;
const CHECKLIST_DESCRICAO_MAX = 1000;
const CHECKLIST_RESPONSAVEL_MAX = 150;
const CHECKLIST_ACAO_MAX = 1000;

// Valores armazenados => rótulos exibidos.
const CHECKLIST_RESULTADOS = [
    'conforme'         => 'Conforme',
    'nao_conformidade' => 'Não conformidade',
    'nao_se_aplica'    => 'Não se aplica',
];
const CHECKLIST_STATUS_NC = [
    'pendente'            => 'Pendente',
    'resolvida'           => 'Resolvida',
    'nao_resolvida'       => 'Não resolvida',
    'escalonada'          => 'Escalonada',
    'fechada_por_excecao' => 'Fechada por exceção',
];
// Status que encerram a NC: gravam data_conclusao_nc.
const CHECKLIST_STATUS_NC_ENCERRADOS = ['resolvida', 'fechada_por_excecao'];

// Campos que o usuário pode alterar; os campos de NC só valem em item "nao_conformidade".
const CHECKLIST_CAMPOS_EDITAVEIS = ['descricao', 'resultado', 'responsavel_resolucao', 'classificacao_nc_id', 'acao_corretiva_indicada', 'status_nc'];
const CHECKLIST_CAMPOS_NC = ['responsavel_resolucao', 'classificacao_nc_id', 'acao_corretiva_indicada', 'status_nc'];

// Depois que a NC é enviada por e-mail, só o status continua editável no checklist.
const CHECKLIST_CAMPOS_TRAVADOS_APOS_ENVIO = ['descricao', 'resultado', 'responsavel_resolucao', 'classificacao_nc_id', 'acao_corretiva_indicada'];

// Colunas do item + a NC enviada (nao_conformidades), se houver. Usar com CHECKLIST_FROM_ITEM.
const CHECKLIST_COLUNAS_ITEM = 'i.id, i.checklist_id, i.numero_item, i.descricao, i.resultado, i.data_identificacao_nc,
    i.responsavel_resolucao, i.classificacao_nc_id, i.acao_corretiva_indicada, i.data_prevista_resolucao,
    i.data_escalonamento, i.data_conclusao_nc, i.status_nc, i.atualizado_em,
    nc.id AS nc_id, nc.data_primeira_solicitacao AS nc_enviada_em';
const CHECKLIST_FROM_ITEM = 'checklist_itens i LEFT JOIN nao_conformidades nc ON nc.checklist_item_id = i.id';

// Como o item entra no cálculo de aderência:
// 'na' não avaliado | 'nna' não aplicável | 'conforme' | 'nnc' não conformidade em aberto.
function checklist_categoria_item(?string $resultado, ?string $statusNc): string
{
    if ($resultado === null) {
        return 'na';
    }
    if ($resultado === 'nao_se_aplica') {
        return 'nna';
    }
    if ($resultado === 'conforme') {
        return 'conforme';
    }
    return match ($statusNc) {
        'resolvida'           => 'conforme',
        'fechada_por_excecao' => 'nna',
        default               => 'nnc', // pendente, escalonada, nao_resolvida
    };
}

// NT, NA, NNA, NTA, NNC, NC e % de aderência (null quando NTA = 0).
function checklist_indicadores(array $itens): array
{
    $total = ['na' => 0, 'nna' => 0, 'conforme' => 0, 'nnc' => 0];
    foreach ($itens as $item) {
        $total[checklist_categoria_item($item['resultado'], $item['status_nc'])]++;
    }

    $nt = count($itens);
    $nta = $nt - $total['na'] - $total['nna'];
    $nc = $nta - $total['nnc'];

    return [
        'nt'        => $nt,
        'na'        => $total['na'],
        'nna'       => $total['nna'],
        'nta'       => $nta,
        'nnc'       => $total['nnc'],
        'nc'        => $nc,
        'aderencia' => $nta > 0 ? round($nc / $nta * 100, 2) : null,
    ];
}

// Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher; a extensão calendar do PHP não
// está instalada no container).
function checklist_pascoa(int $ano): DateTimeImmutable
{
    $a = $ano % 19;
    $b = intdiv($ano, 100);
    $c = $ano % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $mes = intdiv($h + $l - 7 * $m + 114, 31);
    $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano, $mes, $dia));
}

// Feriados nacionais oficiais ('mm-dd' => nome) do ano: fixos + Sexta-feira Santa.
// Carnaval e Corpus Christi são pontos facultativos e não entram (decisão do desenvolvedor);
// feriados estaduais e municipais também não.
function checklist_feriados(int $ano): array
{
    static $cache = [];
    if (!isset($cache[$ano])) {
        $cache[$ano] = [
            '01-01' => 'Confraternização Universal',
            checklist_pascoa($ano)->modify('-2 days')->format('m-d') => 'Sexta-feira Santa',
            '04-21' => 'Tiradentes',
            '05-01' => 'Dia do Trabalho',
            '09-07' => 'Independência do Brasil',
            '10-12' => 'Nossa Senhora Aparecida',
            '11-02' => 'Finados',
            '11-15' => 'Proclamação da República',
            '11-20' => 'Dia Nacional de Zumbi e da Consciência Negra',
            '12-25' => 'Natal',
        ];
    }
    return $cache[$ano];
}

// Dia útil = segunda a sexta que não é feriado nacional.
function checklist_dia_util(DateTimeImmutable $data): bool
{
    return (int) $data->format('N') < 6 && !isset(checklist_feriados((int) $data->format('Y'))[$data->format('m-d')]);
}

// Data prevista de resolução ('aaaa-mm-dd hh:mm:ss') só em dias úteis (sem sábados,
// domingos e feriados nacionais). Dias: conta a partir do dia seguinte à identificação;
// vale até o fim do dia (23:59:59). Horas: conta a partir da identificação, sem as horas
// de dias não úteis.
function checklist_calcular_prevista(string $identificacao, array $classificacao): string
{
    $quantidade = (int) $classificacao['prazo_valor'];
    $data = new DateTimeImmutable($identificacao);
    $naoUtil = fn(DateTimeImmutable $d) => !checklist_dia_util($d);

    if ($classificacao['prazo_unidade'] === 'dias') {
        $data = $data->setTime(0, 0);
        while ($quantidade > 0) {
            $data = $data->modify('+1 day');
            if (!$naoUtil($data)) {
                $quantidade--;
            }
        }
        return $data->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    }

    $restante = $quantidade * 3600;
    while ($naoUtil($data)) {
        $data = $data->setTime(0, 0)->modify('+1 day');
    }
    while (true) {
        $proximoDia = $data->setTime(0, 0)->modify('+1 day');
        $disponivel = $proximoDia->getTimestamp() - $data->getTimestamp();
        if ($restante <= $disponivel) {
            return $data->modify("+{$restante} seconds")->format('Y-m-d H:i:s');
        }
        $restante -= $disponivel;
        $data = $proximoDia;
        while ($naoUtil($data)) {
            $data = $data->modify('+1 day');
        }
    }
}

// Prazo em dias vale até o fim do dia (23:59:59) e é exibido só com a data.
function checklist_formatar_prazo(?string $data): string
{
    if ($data === null) {
        return '';
    }
    return date(str_ends_with($data, '23:59:59') ? 'd/m/Y' : 'd/m/Y H:i', strtotime($data));
}

// Item pronto para enviar a Solicitação de Resolução: NC ainda não enviada, com
// classificação (e portanto data prevista) e responsável preenchidos.
function checklist_item_pode_enviar(array $item): bool
{
    return $item['resultado'] === 'nao_conformidade'
        && empty($item['nc_id'])
        && $item['classificacao_nc_id'] !== null
        && $item['data_prevista_resolucao'] !== null
        && trim((string) $item['responsavel_resolucao']) !== '';
}

// NC aberta com o prazo de resolução vencido.
function checklist_item_atrasado(array $item): bool
{
    return $item['data_prevista_resolucao'] !== null
        && checklist_categoria_item($item['resultado'], $item['status_nc']) === 'nnc'
        && strtotime($item['data_prevista_resolucao']) < time();
}

function checklist_do_projeto(mysqli $conexao, int $projetoId): ?array
{
    $stmt = $conexao->prepare("SELECT id, projeto_id, nome FROM checklists WHERE projeto_id = ?");
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    $checklist = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $checklist ?: null;
}

// Itens do checklist em ordem de número, com o nome e o prazo da classificação.
function checklist_itens(mysqli $conexao, int $checklistId): array
{
    $stmt = $conexao->prepare(
        "SELECT " . CHECKLIST_COLUNAS_ITEM . "
           FROM " . CHECKLIST_FROM_ITEM . "
          WHERE i.checklist_id = ?
          ORDER BY i.numero_item"
    );
    $stmt->bind_param("i", $checklistId);
    $stmt->execute();
    $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $itens;
}

// O item com o projeto dele, se o usuário for membro do projeto; null caso contrário.
function checklist_item_do_membro(mysqli $conexao, int $itemId, int $usuarioId): ?array
{
    $stmt = $conexao->prepare(
        "SELECT " . CHECKLIST_COLUNAS_ITEM . ", c.projeto_id
           FROM " . CHECKLIST_FROM_ITEM . "
           JOIN checklists c ON c.id = i.checklist_id
           JOIN projeto_membros m ON m.projeto_id = c.projeto_id AND m.usuario_id = ?
          WHERE i.id = ?"
    );
    $stmt->bind_param("ii", $usuarioId, $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $item ?: null;
}

// Retorna a mensagem de erro, ou null se a descrição for válida.
function checklist_validar_descricao(string $descricao): ?string
{
    if ($descricao === '') {
        return 'Informe a descrição do item.';
    }
    if (mb_strlen($descricao) > CHECKLIST_DESCRICAO_MAX) {
        return 'A descrição pode ter no máximo ' . CHECKLIST_DESCRICAO_MAX . ' caracteres.';
    }
    return null;
}

// Id do checklist do projeto, criando-o (com o nome padrão) se ainda não existir.
function checklist_obter_ou_criar(mysqli $conexao, int $projetoId): int
{
    $nome = CHECKLIST_NOME_PADRAO;
    $stmt = $conexao->prepare(
        "INSERT INTO checklists (projeto_id, nome) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
    );
    $stmt->bind_param("is", $projetoId, $nome);
    $stmt->execute();
    $stmt->close();
    return $conexao->insert_id;
}

// Insere itens no fim do checklist, numerando em sequência a partir do contador
// checklists.ultimo_numero_item — números nunca são reaproveitados, nem depois de excluir
// o último item (usa o maior entre o contador e o maior número existente, por segurança).
// Deve rodar dentro de uma transação: trava o checklist (SELECT ... FOR UPDATE) para que
// inserções simultâneas não calculem o mesmo número.
// Retorna os ids criados ou ['erro' => ...] se passar do limite.
function checklist_inserir_itens(mysqli $conexao, int $checklistId, array $descricoes): array
{
    $stmt = $conexao->prepare("SELECT ultimo_numero_item FROM checklists WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $checklistId);
    $stmt->execute();
    $contador = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $stmt = $conexao->prepare("SELECT COUNT(*), COALESCE(MAX(numero_item), 0) FROM checklist_itens WHERE checklist_id = ?");
    $stmt->bind_param("i", $checklistId);
    $stmt->execute();
    [$quantidade, $maiorNumero] = $stmt->get_result()->fetch_row();
    $stmt->close();
    $ultimoNumero = max($contador, (int) $maiorNumero);

    if ($quantidade + count($descricoes) > CHECKLIST_ITENS_MAX) {
        $disponivel = max(0, CHECKLIST_ITENS_MAX - $quantidade);
        return ['erro' => 'O checklist pode ter no máximo ' . CHECKLIST_ITENS_MAX . " itens (ainda cabem {$disponivel})."];
    }

    $ids = [];
    $stmt = $conexao->prepare("INSERT INTO checklist_itens (checklist_id, numero_item, descricao) VALUES (?, ?, ?)");
    foreach ($descricoes as $descricao) {
        $numero = ++$ultimoNumero;
        $stmt->bind_param("iis", $checklistId, $numero, $descricao);
        $stmt->execute();
        $ids[] = $conexao->insert_id;
    }
    $stmt->close();

    $stmt = $conexao->prepare("UPDATE checklists SET ultimo_numero_item = ? WHERE id = ?");
    $stmt->bind_param("ii", $ultimoNumero, $checklistId);
    $stmt->execute();
    $stmt->close();
    return $ids;
}

// Cria o checklist do projeto (se ainda não existir) e adiciona o item com o próximo número.
// Retorna o item criado ou ['erro' => ...] se o limite de itens for atingido.
function checklist_adicionar_item(mysqli $conexao, int $projetoId, string $descricao): array
{
    $conexao->begin_transaction();
    try {
        $ids = checklist_inserir_itens($conexao, checklist_obter_ou_criar($conexao, $projetoId), [$descricao]);
        if (isset($ids['erro'])) {
            $conexao->rollback();
            return $ids;
        }
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }

    $stmt = $conexao->prepare("SELECT " . CHECKLIST_COLUNAS_ITEM . " FROM " . CHECKLIST_FROM_ITEM . " WHERE i.id = ?");
    $stmt->bind_param("i", $ids[0]);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $item;
}

// Importa itens (só as descrições; entram como "não avaliado") no fim do checklist do
// projeto. Retorna ['importados' => n] ou ['erro' => ...].
function checklist_importar_itens(mysqli $conexao, int $projetoId, array $descricoes): array
{
    $conexao->begin_transaction();
    try {
        $ids = checklist_inserir_itens($conexao, checklist_obter_ou_criar($conexao, $projetoId), $descricoes);
        if (isset($ids['erro'])) {
            $conexao->rollback();
            return $ids;
        }
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }
    return ['importados' => count($ids)];
}

// Lista "um item por linha" → descrições válidas (linhas vazias ignoradas; numeração do
// início da linha, como "12." ou "12 -", removida). Retorna ['erro' => ...] ou a lista.
function checklist_ler_lista_itens(string $texto): array
{
    $descricoes = [];
    foreach (preg_split('/\R/u', $texto) as $numeroLinha => $linha) {
        $linha = trim(preg_replace('/^\s*\d+\s*[.)\-–:]?\s+/u', '', $linha));
        if ($linha === '') {
            continue;
        }
        if (($erro = checklist_validar_descricao($linha)) !== null) {
            return ['erro' => 'Linha ' . ($numeroLinha + 1) . ": {$erro}"];
        }
        $descricoes[] = $linha;
    }
    return $descricoes ?: ['erro' => 'Nenhum item encontrado: escreva um item por linha.'];
}

// Descrições dos itens do checklist de um projeto, na ordem.
function checklist_descricoes_do_projeto(mysqli $conexao, int $projetoId): array
{
    $stmt = $conexao->prepare(
        "SELECT i.descricao FROM checklist_itens i JOIN checklists c ON c.id = i.checklist_id
          WHERE c.projeto_id = ? ORDER BY i.numero_item"
    );
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    $descricoes = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'descricao');
    $stmt->close();
    return $descricoes;
}

// Outros projetos do usuário que têm itens no checklist (origem para copiar itens).
function checklist_projetos_para_copiar(mysqli $conexao, int $usuarioId, int $projetoAtualId): array
{
    $stmt = $conexao->prepare(
        "SELECT p.id, p.nome, COUNT(i.id) AS itens
           FROM projeto_membros m
           JOIN projetos p ON p.id = m.projeto_id
           JOIN checklists c ON c.projeto_id = p.id
           JOIN checklist_itens i ON i.checklist_id = c.id
          WHERE m.usuario_id = ? AND p.id <> ?
          GROUP BY p.id, p.nome
          ORDER BY p.nome"
    );
    $stmt->bind_param("ii", $usuarioId, $projetoAtualId);
    $stmt->execute();
    $projetos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $projetos;
}

function checklist_renomear(mysqli $conexao, int $projetoId, string $nome): void
{
    $checklistId = checklist_obter_ou_criar($conexao, $projetoId);
    $stmt = $conexao->prepare("UPDATE checklists SET nome = ? WHERE id = ?");
    $stmt->bind_param("si", $nome, $checklistId);
    $stmt->execute();
    $stmt->close();
}

// Aplica a alteração de um campo às regras de negócio, sem gravar.
// $classificacoes: classificações do projeto indexadas por id. $agora: 'aaaa-mm-dd hh:mm:ss'.
// Retorna ['item' => item alterado] ou ['erro' => mensagem].
function checklist_aplicar_alteracao(array $item, string $campo, string $valor, array $classificacoes, string $agora): array
{
    $valor = trim($valor);
    $ehNc = $item['resultado'] === 'nao_conformidade';

    if (!empty($item['nc_id']) && in_array($campo, CHECKLIST_CAMPOS_TRAVADOS_APOS_ENVIO, true)) {
        return ['erro' => 'Esta NC já foi enviada por e-mail: só o status pode ser alterado.'];
    }

    if (in_array($campo, CHECKLIST_CAMPOS_NC, true) && !$ehNc) {
        return ['erro' => 'Marque o item como não conformidade para preencher os dados da NC.'];
    }

    switch ($campo) {
        case 'descricao':
            if (($erro = checklist_validar_descricao($valor)) !== null) {
                return ['erro' => $erro];
            }
            $item['descricao'] = $valor;
            break;

        case 'resultado':
            $novo = $valor === '' ? null : $valor;
            if ($novo !== null && !isset(CHECKLIST_RESULTADOS[$novo])) {
                return ['erro' => 'Resultado inválido.'];
            }
            if ($novo === 'nao_conformidade' && !$ehNc) {
                $item['data_identificacao_nc'] = $agora;
                $item['status_nc'] = 'pendente';
            } elseif ($novo !== 'nao_conformidade' && $ehNc) {
                // Deixou de ser NC: os dados da NC são descartados.
                foreach (['data_identificacao_nc', 'responsavel_resolucao', 'classificacao_nc_id', 'acao_corretiva_indicada',
                          'data_prevista_resolucao', 'data_escalonamento', 'data_conclusao_nc', 'status_nc'] as $campoNc) {
                    $item[$campoNc] = null;
                }
            }
            $item['resultado'] = $novo;
            break;

        case 'responsavel_resolucao':
            if (mb_strlen($valor) > CHECKLIST_RESPONSAVEL_MAX) {
                return ['erro' => 'O responsável pode ter no máximo ' . CHECKLIST_RESPONSAVEL_MAX . ' caracteres.'];
            }
            $item['responsavel_resolucao'] = $valor === '' ? null : $valor;
            break;

        case 'acao_corretiva_indicada':
            if (mb_strlen($valor) > CHECKLIST_ACAO_MAX) {
                return ['erro' => 'A ação corretiva pode ter no máximo ' . CHECKLIST_ACAO_MAX . ' caracteres.'];
            }
            $item['acao_corretiva_indicada'] = $valor === '' ? null : $valor;
            break;

        case 'classificacao_nc_id':
            if ($valor === '') {
                $item['classificacao_nc_id'] = null;
                $item['data_prevista_resolucao'] = null;
                break;
            }
            $classificacao = $classificacoes[(int) $valor] ?? null;
            if ($classificacao === null || (string) (int) $valor !== $valor) {
                return ['erro' => 'Classificação inválida.'];
            }
            $item['classificacao_nc_id'] = (int) $valor;
            $item['data_prevista_resolucao'] = checklist_calcular_prevista($item['data_identificacao_nc'], $classificacao);
            break;

        case 'status_nc':
            if (!isset(CHECKLIST_STATUS_NC[$valor])) {
                return ['erro' => 'Status da NC inválido.'];
            }
            // 'escalonada' só é definido pelo fluxo de escalonamento (aba Não Conformidades),
            // para não existir NC escalonada sem escalonamento registrado.
            if ($valor === 'escalonada' && $item['status_nc'] !== 'escalonada') {
                return ['erro' => 'Para escalonar, use o botão "Escalonar" na aba Não Conformidades.'];
            }
            $encerrado = in_array($valor, CHECKLIST_STATUS_NC_ENCERRADOS, true);
            if (!$encerrado) {
                $item['data_conclusao_nc'] = null;
            } elseif ($item['data_conclusao_nc'] === null) {
                $item['data_conclusao_nc'] = $agora;
            }
            $item['status_nc'] = $valor;
            break;

        default:
            return ['erro' => 'Campo inválido.'];
    }

    return ['item' => $item];
}

// Grava o item e, se a NC já foi enviada, replica o status em nao_conformidades.
function checklist_salvar_item(mysqli $conexao, array $item): void
{
    $conexao->begin_transaction();
    try {
        checklist_gravar_item($conexao, $item);
        if (!empty($item['nc_id'])) {
            $stmt = $conexao->prepare("UPDATE nao_conformidades SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $item['status_nc'], $item['nc_id']);
            $stmt->execute();
            $stmt->close();
        }
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }
}

function checklist_gravar_item(mysqli $conexao, array $item): void
{
    $stmt = $conexao->prepare(
        "UPDATE checklist_itens
            SET descricao = ?, resultado = ?, data_identificacao_nc = ?, responsavel_resolucao = ?,
                classificacao_nc_id = ?, acao_corretiva_indicada = ?, data_prevista_resolucao = ?,
                data_escalonamento = ?, data_conclusao_nc = ?, status_nc = ?
          WHERE id = ?"
    );
    $stmt->bind_param(
        "ssssisssssi",
        $item['descricao'],
        $item['resultado'],
        $item['data_identificacao_nc'],
        $item['responsavel_resolucao'],
        $item['classificacao_nc_id'],
        $item['acao_corretiva_indicada'],
        $item['data_prevista_resolucao'],
        $item['data_escalonamento'],
        $item['data_conclusao_nc'],
        $item['status_nc'],
        $item['id']
    );
    $stmt->execute();
    $stmt->close();
}

function checklist_excluir_item(mysqli $conexao, int $itemId): void
{
    $stmt = $conexao->prepare("DELETE FROM checklist_itens WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $stmt->close();
}

// Dados do item prontos para o front: datas formatadas e flag de atraso.
function checklist_item_para_json(array $item): array
{
    $formatar = fn(?string $data) => $data === null ? '' : date('d/m/Y H:i', strtotime($data));
    return [
        'id'                      => (int) $item['id'],
        'numero_item'             => (int) $item['numero_item'],
        'descricao'               => $item['descricao'],
        'resultado'               => $item['resultado'] ?? '',
        'responsavel_resolucao'   => $item['responsavel_resolucao'] ?? '',
        'classificacao_nc_id'     => $item['classificacao_nc_id'] === null ? '' : (string) $item['classificacao_nc_id'],
        'acao_corretiva_indicada' => $item['acao_corretiva_indicada'] ?? '',
        'status_nc'               => $item['status_nc'] ?? '',
        'data_identificacao_nc'   => $formatar($item['data_identificacao_nc']),
        'data_prevista_resolucao' => checklist_formatar_prazo($item['data_prevista_resolucao']),
        'data_escalonamento'      => $formatar($item['data_escalonamento']),
        'data_conclusao_nc'       => $formatar($item['data_conclusao_nc']),
        'atrasado'                => checklist_item_atrasado($item),
        'nc_enviada'              => !empty($item['nc_id']),
        'nc_enviada_em'           => $formatar($item['nc_enviada_em'] ?? null),
        'pode_enviar'             => checklist_item_pode_enviar($item),
        // Versão do item (edição simultânea): enviada de volta a cada alteração.
        'versao'                  => $item['atualizado_em'] ?? '',
    ];
}
