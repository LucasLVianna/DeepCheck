<?php
// Acesso a dados e regras do Checklist de Qualidade (tabelas checklists e checklist_itens).
// Um checklist por projeto, criado no primeiro item. Colunas seguem o template
// "Modelo Checklist - Processo de Qualidade"; status e fórmula de aderência seguem
// as "Regras de Negócio" do CLAUDE.md.

const CHECKLIST_NOME_PADRAO = 'Checklist de Qualidade';
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
    i.data_escalonamento, i.data_conclusao_nc, i.status_nc,
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

// Data prevista de resolução ('aaaa-mm-dd hh:mm:ss') sem contar sábados e domingos.
// Dias: conta a partir do dia seguinte à identificação, só dias úteis; vale até o fim do
// dia (23:59:59). Horas: conta a partir da identificação, sem as horas de fim de semana.
function checklist_calcular_prevista(string $identificacao, array $classificacao): string
{
    $quantidade = (int) $classificacao['prazo_valor'];
    $data = new DateTimeImmutable($identificacao);
    $fimDeSemana = fn(DateTimeImmutable $d) => (int) $d->format('N') >= 6;

    if ($classificacao['prazo_unidade'] === 'dias') {
        $data = $data->setTime(0, 0);
        while ($quantidade > 0) {
            $data = $data->modify('+1 day');
            if (!$fimDeSemana($data)) {
                $quantidade--;
            }
        }
        return $data->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    }

    $restante = $quantidade * 3600;
    while ($fimDeSemana($data)) {
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
        while ($fimDeSemana($data)) {
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

// Cria o checklist do projeto (se ainda não existir) e adiciona o item com o próximo número.
// Retorna o item criado ou ['erro' => ...] se o limite de itens for atingido.
function checklist_adicionar_item(mysqli $conexao, int $projetoId, string $descricao): array
{
    $conexao->begin_transaction();
    try {
        $nome = CHECKLIST_NOME_PADRAO;
        $stmt = $conexao->prepare(
            "INSERT INTO checklists (projeto_id, nome) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $stmt->bind_param("is", $projetoId, $nome);
        $stmt->execute();
        $stmt->close();
        $checklistId = $conexao->insert_id;

        // Trava o checklist para que adições simultâneas não calculem o mesmo número.
        $stmt = $conexao->prepare("SELECT id FROM checklists WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $checklistId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conexao->prepare("SELECT COUNT(*), COALESCE(MAX(numero_item), 0) FROM checklist_itens WHERE checklist_id = ?");
        $stmt->bind_param("i", $checklistId);
        $stmt->execute();
        [$quantidade, $ultimoNumero] = $stmt->get_result()->fetch_row();
        $stmt->close();

        if ($quantidade >= CHECKLIST_ITENS_MAX) {
            $conexao->rollback();
            return ['erro' => 'O checklist atingiu o limite de ' . CHECKLIST_ITENS_MAX . ' itens.'];
        }

        $numero = $ultimoNumero + 1;
        $stmt = $conexao->prepare("INSERT INTO checklist_itens (checklist_id, numero_item, descricao) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $checklistId, $numero, $descricao);
        $stmt->execute();
        $itemId = $conexao->insert_id;
        $stmt->close();

        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }

    $stmt = $conexao->prepare("SELECT " . CHECKLIST_COLUNAS_ITEM . " FROM " . CHECKLIST_FROM_ITEM . " WHERE i.id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $item;
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
    ];
}
