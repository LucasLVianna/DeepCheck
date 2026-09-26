<?php
// Acesso a dados do Plano de Garantia da Qualidade: tabela pgq (capa, comprometimento
// e seções 1, 5, 6 e 7) e as sub-tabelas das seções 2, 3 e 4. Campos e seções seguem
// o template "Plano de Garantia da Qualidade".

const PGQ_TEXTO_LONGO_MAX = 10000; // colunas TEXT
const PGQ_LINHAS_MAX = 100;        // por sub-tabela

// Campos de texto da tabela pgq.
const PGQ_CAMPOS_TEXTO = [
    'autor_gqa'                    => ['rotulo' => 'Autor/Responsável por GQA', 'max' => 150],
    'versao_documento'             => ['rotulo' => 'Versão', 'max' => 30],
    'cidade'                       => ['rotulo' => 'Cidade', 'max' => 100],
    'responsavel_projeto'          => ['rotulo' => 'Nome do Responsável pelo Projeto', 'max' => 150],
    'rq_nome'                      => ['rotulo' => 'Nome do RQ', 'max' => 150],
    'objetivo'                     => ['rotulo' => '1.1 Objetivo', 'max' => PGQ_TEXTO_LONGO_MAX],
    'visao_geral'                  => ['rotulo' => '1.2 Visão Geral', 'max' => PGQ_TEXTO_LONGO_MAX],
    'registros_qualidade_local'    => ['rotulo' => '5. Registros de Qualidade', 'max' => 500],
    'definicao_nc_texto'           => ['rotulo' => '6. Definição das Não-Conformidades', 'max' => PGQ_TEXTO_LONGO_MAX],
    'processo_escalonamento_texto' => ['rotulo' => '7. Processo de escalonamento', 'max' => PGQ_TEXTO_LONGO_MAX],
];

// Campos de data (dia) da tabela pgq.
const PGQ_CAMPOS_DATA = [
    'data_comprometimento_responsavel' => 'Data do Responsável pelo Projeto',
    'data_comprometimento_rq'          => 'Data do RQ',
];
// data_documento (capa) é mês/ano: chega em duas listas (data_documento_mes e
// data_documento_ano) e é gravada como o dia 1 do mês.
const PGQ_MESES = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
    7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
];

// Sub-tabelas: chave usada no formulário => tabela e colunas (na ordem do template).
const PGQ_SUBTABELAS = [
    'documentos' => [
        'tabela'  => 'pgq_documentos',
        'secao'   => '2. Documentação, Padrões e Diretrizes',
        'colunas' => [
            'documento' => ['rotulo' => 'Documento', 'max' => 255, 'obrigatorio' => true],
            'versao'    => ['rotulo' => 'Versão', 'max' => 30],
        ],
    ],
    'itens_avaliados' => [
        'tabela'  => 'pgq_itens_avaliados',
        'secao'   => '3. Itens a Serem Avaliados',
        'colunas' => [
            'documento'           => ['rotulo' => 'Documento', 'max' => 255, 'obrigatorio' => true],
            'local_armazenamento' => ['rotulo' => 'Local de Armazenamento', 'max' => 500],
            'versao'              => ['rotulo' => 'Versão', 'max' => 30],
        ],
    ],
    'plano_avaliacoes' => [
        'tabela'  => 'pgq_plano_avaliacoes',
        'secao'   => '4. Plano de Avaliações',
        'colunas' => [
            'artefato_avaliado' => ['rotulo' => 'Artefatos Avaliados', 'max' => 255, 'obrigatorio' => true],
            'data_avaliacao'    => ['rotulo' => 'Data da Avaliação', 'data' => true],
            'auditor'           => ['rotulo' => 'Auditor', 'max' => 150],
        ],
    ],
];

function pgq_texto($valor): string
{
    return is_string($valor) ? trim($valor) : '';
}

// Converte uma data no formato informado para "aaaa-mm-dd"; null se for inválida.
function pgq_data_iso(string $valor, string $formato = 'Y-m-d'): ?string
{
    $data = DateTime::createFromFormat('!' . $formato, $valor);
    if ($data === false || $data->format($formato) !== $valor) {
        return null;
    }
    return $data->format('Y-m-d');
}

// Valida e normaliza o POST do formulário do PGQ.
// Retorna ['erro' => mensagem] ou ['campos' => [...], 'linhas' => [chave => [[coluna => valor]]]].
// Textos vazios viram null; linhas totalmente vazias das sub-tabelas são descartadas.
function pgq_ler_entrada(array $post): array
{
    $campos = [];

    foreach (PGQ_CAMPOS_TEXTO as $campo => $regra) {
        $valor = pgq_texto($post[$campo] ?? '');
        if (mb_strlen($valor) > $regra['max']) {
            return ['erro' => "{$regra['rotulo']}: máximo de {$regra['max']} caracteres."];
        }
        $campos[$campo] = $valor === '' ? null : $valor;
    }

    foreach (PGQ_CAMPOS_DATA as $campo => $rotulo) {
        $valor = pgq_texto($post[$campo] ?? '');
        $campos[$campo] = $valor === '' ? null : pgq_data_iso($valor);
        if ($valor !== '' && $campos[$campo] === null) {
            return ['erro' => "{$rotulo}: data inválida."];
        }
    }

    $mes = pgq_texto($post['data_documento_mes'] ?? '');
    $ano = pgq_texto($post['data_documento_ano'] ?? '');
    if (($mes === '') !== ($ano === '')) {
        return ['erro' => 'Capa: selecione o mês e o ano (ou deixe os dois em branco).'];
    }
    $campos['data_documento'] = null;
    if ($mes !== '') {
        $campos['data_documento'] = ctype_digit($mes) && ctype_digit($ano)
            ? pgq_data_iso(sprintf('%04d-%02d', (int) $ano, (int) $mes), 'Y-m')
            : null;
        if ($campos['data_documento'] === null) {
            return ['erro' => 'Mês/ano da capa inválido.'];
        }
    }

    $linhas = [];
    foreach (PGQ_SUBTABELAS as $chave => $subtabela) {
        $enviadas = is_array($post[$chave] ?? null) ? $post[$chave] : [];
        $total = 0;
        foreach (array_keys($subtabela['colunas']) as $coluna) {
            $total = max($total, is_array($enviadas[$coluna] ?? null) ? count($enviadas[$coluna]) : 0);
        }
        if ($total > PGQ_LINHAS_MAX) {
            return ['erro' => "{$subtabela['secao']}: máximo de " . PGQ_LINHAS_MAX . ' linhas.'];
        }

        $linhas[$chave] = [];
        for ($i = 0; $i < $total; $i++) {
            $linha = [];
            foreach (array_keys($subtabela['colunas']) as $coluna) {
                $linha[$coluna] = pgq_texto($enviadas[$coluna][$i] ?? '');
            }
            if (implode('', $linha) === '') {
                continue;
            }

            $numero = count($linhas[$chave]) + 1;
            foreach ($subtabela['colunas'] as $coluna => $regra) {
                $valor = $linha[$coluna];
                $onde = "{$subtabela['secao']}, linha {$numero}";
                if ($valor === '' && !empty($regra['obrigatorio'])) {
                    return ['erro' => "{$onde}: preencha \"{$regra['rotulo']}\"."];
                }
                if (!empty($regra['data']) && $valor !== '') {
                    $valor = pgq_data_iso($valor);
                    if ($valor === null) {
                        return ['erro' => "{$onde}: \"{$regra['rotulo']}\" inválida."];
                    }
                } elseif (isset($regra['max']) && mb_strlen($valor) > $regra['max']) {
                    return ['erro' => "{$onde}: \"{$regra['rotulo']}\" com no máximo {$regra['max']} caracteres."];
                }
                $linha[$coluna] = $valor === '' ? null : $valor;
            }
            $linhas[$chave][] = $linha;
        }
    }

    return ['campos' => $campos, 'linhas' => $linhas];
}

// PGQ do projeto: ['pgq' => linha de pgq ou null (ainda não salvo), '<chave da sub-tabela>' => [linhas]].
function pgq_do_projeto(mysqli $conexao, int $projetoId): array
{
    $stmt = $conexao->prepare("SELECT * FROM pgq WHERE projeto_id = ?");
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    $pgq = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    $resultado = ['pgq' => $pgq];
    foreach (PGQ_SUBTABELAS as $chave => $subtabela) {
        $resultado[$chave] = [];
        if ($pgq === null) {
            continue;
        }
        $colunas = implode(', ', array_keys($subtabela['colunas']));
        $stmt = $conexao->prepare("SELECT {$colunas} FROM {$subtabela['tabela']} WHERE pgq_id = ? ORDER BY id");
        $stmt->bind_param("i", $pgq['id']);
        $stmt->execute();
        $resultado[$chave] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    return $resultado;
}

// Grava o PGQ inteiro numa transação: upsert em pgq e substituição das linhas das
// sub-tabelas. Recebe a saída de pgq_ler_entrada(). Retorna o novo atualizado_em.
function pgq_salvar(mysqli $conexao, int $projetoId, array $campos, array $linhas): string
{
    $colunas = array_keys($campos);
    $atualizacoes = implode(', ', array_map(fn($c) => "{$c} = novo.{$c}", $colunas));
    $sql = "INSERT INTO pgq (projeto_id, " . implode(', ', $colunas) . ")
            VALUES (?" . str_repeat(', ?', count($colunas)) . ") AS novo
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(pgq.id), {$atualizacoes}, atualizado_em = CURRENT_TIMESTAMP";

    $conexao->begin_transaction();
    try {
        $stmt = $conexao->prepare($sql);
        $valores = array_values($campos);
        $stmt->bind_param('i' . str_repeat('s', count($colunas)), $projetoId, ...$valores);
        $stmt->execute();
        $stmt->close();
        // Com LAST_INSERT_ID(pgq.id) no UPDATE, insert_id traz o id tanto na inserção quanto na atualização.
        $pgqId = $conexao->insert_id;

        foreach (PGQ_SUBTABELAS as $chave => $subtabela) {
            $stmt = $conexao->prepare("DELETE FROM {$subtabela['tabela']} WHERE pgq_id = ?");
            $stmt->bind_param("i", $pgqId);
            $stmt->execute();
            $stmt->close();

            if (!$linhas[$chave]) {
                continue;
            }
            $colunasSub = array_keys($subtabela['colunas']);
            $stmt = $conexao->prepare(
                "INSERT INTO {$subtabela['tabela']} (pgq_id, " . implode(', ', $colunasSub) . ")
                 VALUES (?" . str_repeat(', ?', count($colunasSub)) . ")"
            );
            foreach ($linhas[$chave] as $linha) {
                $valores = array_values($linha);
                $stmt->bind_param('i' . str_repeat('s', count($colunasSub)), $pgqId, ...$valores);
                $stmt->execute();
            }
            $stmt->close();
        }

        $stmt = $conexao->prepare("SELECT atualizado_em FROM pgq WHERE id = ?");
        $stmt->bind_param("i", $pgqId);
        $stmt->execute();
        $atualizadoEm = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }

    return $atualizadoEm;
}

// Nome do RQ (Representante da Qualidade) do PGQ do projeto, se preenchido.
function pgq_rq_nome(mysqli $conexao, int $projetoId): ?string
{
    $stmt = $conexao->prepare("SELECT rq_nome FROM pgq WHERE projeto_id = ?");
    $stmt->bind_param("i", $projetoId);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $linha[0] ?? null;
}
