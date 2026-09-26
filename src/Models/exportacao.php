<?php
// Dados para os PDFs de exportação (scripts/documentos_pdf.py): Plano de Garantia da
// Qualidade e Checklist. Aqui os valores já saem formatados (datas, rótulos); o Python
// só faz o layout.
require_once __DIR__ . '/pgq.php';
require_once __DIR__ . '/checklist.php';
require_once __DIR__ . '/classificacoes_nc.php';

function exportacao_data(?string $data, string $formato = 'd/m/Y'): string
{
    return $data ? date($formato, strtotime($data)) : '';
}

function exportacao_classificacoes(mysqli $conexao, int $projetoId): array
{
    return array_map(
        fn($c) => ['nome' => $c['nome'], 'prazo' => classificacao_prazo_texto($c)],
        classificacoes_do_projeto($conexao, $projetoId)
    );
}

function exportacao_pgq(mysqli $conexao, array $projeto): array
{
    $pgq = pgq_do_projeto($conexao, (int) $projeto['id']);
    $d = $pgq['pgq'] ?? [];
    $mesAno = isset($d['data_documento']) ? date('m/Y', strtotime($d['data_documento'])) : '';
    $cidade = $d['cidade'] ?? '';

    return [
        'projeto'                      => $projeto['nome'],
        'logo'                         => ($logo = pgq_logo($conexao, (int) $projeto['id'])) ? base64_encode($logo['dados']) : null,
        'autor_gqa'                    => $d['autor_gqa'] ?? '',
        'versao_documento'             => $d['versao_documento'] ?? '',
        'local_data'                   => implode(', ', array_filter([$cidade, $mesAno])),
        'comprometimento'              => [
            ['papel' => 'Responsável pelo Projeto', 'nome' => $d['responsavel_projeto'] ?? '', 'data' => exportacao_data($d['data_comprometimento_responsavel'] ?? null)],
            ['papel' => 'RQ (Representante da Qualidade)', 'nome' => $d['rq_nome'] ?? '', 'data' => exportacao_data($d['data_comprometimento_rq'] ?? null)],
        ],
        'objetivo'                     => $d['objetivo'] ?? '',
        'visao_geral'                  => $d['visao_geral'] ?? '',
        'documentos'                   => array_map(fn($l) => [$l['documento'], $l['versao'] ?? ''], $pgq['documentos']),
        'itens_avaliados'              => array_map(fn($l) => [$l['documento'], $l['local_armazenamento'] ?? '', $l['versao'] ?? ''], $pgq['itens_avaliados']),
        'plano_avaliacoes'             => array_map(fn($l) => [$l['artefato_avaliado'], exportacao_data($l['data_avaliacao']), $l['auditor'] ?? ''], $pgq['plano_avaliacoes']),
        'registros_qualidade_local'    => $d['registros_qualidade_local'] ?? '',
        'classificacoes'               => exportacao_classificacoes($conexao, (int) $projeto['id']),
        'definicao_nc_texto'           => $d['definicao_nc_texto'] ?? '',
        'processo_escalonamento_texto' => $d['processo_escalonamento_texto'] ?? '',
        'gerado_em'                    => date('d/m/Y H:i'),
    ];
}

function exportacao_checklist(mysqli $conexao, array $projeto): array
{
    $projetoId = (int) $projeto['id'];
    $checklist = checklist_do_projeto($conexao, $projetoId);
    $itens = $checklist ? checklist_itens($conexao, (int) $checklist['id']) : [];
    $classificacoes = array_column(classificacoes_do_projeto($conexao, $projetoId), null, 'id');
    $indicadores = checklist_indicadores($itens);

    return [
        'nome'           => $checklist['nome'] ?? CHECKLIST_NOME_PADRAO,
        'projeto'        => $projeto['nome'],
        'indicadores'    => [
            'aderencia' => $indicadores['aderencia'] === null ? '—' : number_format($indicadores['aderencia'], 2, ',', '.') . '%',
        ] + array_map('strval', array_intersect_key($indicadores, array_flip(['nt', 'na', 'nta', 'nc', 'nnc', 'nna']))),
        'itens'          => array_map(function ($item) use ($classificacoes) {
            $classificacao = $classificacoes[$item['classificacao_nc_id']] ?? null;
            return [
                'numero'             => (string) $item['numero_item'],
                'descricao'          => $item['descricao'],
                'resultado'          => $item['resultado'] ?? '',
                'resultado_rotulo'   => $item['resultado'] === null ? 'Não avaliado' : CHECKLIST_RESULTADOS[$item['resultado']],
                'data_identificacao' => exportacao_data($item['data_identificacao_nc'], 'd/m/Y H:i'),
                'responsavel'        => $item['responsavel_resolucao'] ?? '',
                'classificacao'      => $classificacao ? $classificacao['nome'] . ' | ' . classificacao_prazo_texto($classificacao) : '',
                'acao'               => $item['acao_corretiva_indicada'] ?? '',
                'data_prevista'      => checklist_formatar_prazo($item['data_prevista_resolucao']),
                'data_escalonamento' => exportacao_data($item['data_escalonamento'], 'd/m/Y H:i'),
                'data_conclusao'     => exportacao_data($item['data_conclusao_nc'], 'd/m/Y H:i'),
                'status'             => $item['status_nc'] ?? '',
                'status_rotulo'      => $item['status_nc'] === null ? '' : CHECKLIST_STATUS_NC[$item['status_nc']],
                'atrasado'           => checklist_item_atrasado($item),
            ];
        }, $itens),
        'classificacoes' => exportacao_classificacoes($conexao, $projetoId),
        'gerado_em'      => date('d/m/Y H:i'),
    ];
}

// Nome do arquivo: "PGQ - <Projeto> - aaaa-mm-dd.pdf" (sem caracteres problemáticos).
function exportacao_nome_arquivo(string $prefixo, string $projeto): string
{
    $projeto = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $projeto));
    return "{$prefixo} - " . mb_substr($projeto, 0, 80) . ' - ' . date('Y-m-d') . '.pdf';
}
