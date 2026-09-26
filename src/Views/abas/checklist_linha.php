<?php
// HTML de uma linha do checklist. Usado pela aba (src/Views/abas/checklist.php) e pelo
// endpoint que adiciona item (devolve a linha pronta). Requer src/Models/checklist.php,
// src/Models/classificacoes_nc.php e e() (config/auth.php).

function checklist_opcoes_html(array $opcoes, string $selecionado, string $vazio = ''): string
{
    $html = $vazio;
    foreach ($opcoes as $valor => $rotulo) {
        $html .= sprintf(
            '<option value="%s"%s>%s</option>',
            e((string) $valor),
            (string) $valor === $selecionado ? ' selected' : '',
            e($rotulo)
        );
    }
    return $html;
}

// Célula "Solicitação de resolução": data do envio ou botão para enviar.
function checklist_solicitacao_html(array $dados): string
{
    if ($dados['nc_enviada']) {
        return '<span class="nc-enviada">Enviada em ' . e($dados['nc_enviada_em']) . '</span>';
    }
    return '<button type="button" class="js-enviar-nc" title="Disponível para itens em não conformidade com classificação e responsável preenchidos"'
        . ($dados['pode_enviar'] ? '' : ' disabled') . '>Enviar NC</button>';
}

function checklist_linha_html(array $item, array $classificacoes): string
{
    $dados = checklist_item_para_json($item);
    $numero = $dados['numero_item'];
    $enviada = $dados['nc_enviada'];
    // Campos de NC só valem em item NC; depois do envio da NC, só o status continua editável.
    $travado = $enviada ? ' disabled' : '';
    $bloqueado = $dados['resultado'] === 'nao_conformidade' && !$enviada ? '' : ' disabled';
    $bloqueadoStatus = $dados['resultado'] === 'nao_conformidade' ? '' : ' disabled';

    $opcoesClassificacao = [];
    foreach ($classificacoes as $classificacao) {
        $opcoesClassificacao[$classificacao['id']] = $classificacao['nome'] . ' | ' . classificacao_prazo_texto($classificacao);
    }

    $classes = 'checklist-item'
        . ($dados['resultado'] === 'nao_conformidade' ? ' item-nc' : '')
        . ($dados['atrasado'] ? ' item-atrasado' : '');

    return '<tr class="' . $classes . '" data-item-id="' . $dados['id'] . '">'
        . '<td class="col-numero">' . $numero . '</td>'
        . '<td><textarea data-campo="descricao" rows="2" maxlength="' . CHECKLIST_DESCRICAO_MAX . '" aria-label="Descrição do item ' . $numero . '"' . $travado . '>' . e($dados['descricao']) . '</textarea></td>'
        . '<td><select data-campo="resultado" aria-label="Resultado do item ' . $numero . '"' . $travado . '>'
            . checklist_opcoes_html(CHECKLIST_RESULTADOS, $dados['resultado'], '<option value="">Não avaliado</option>') . '</select></td>'
        . '<td class="col-data" data-exibe="data_identificacao_nc">' . e($dados['data_identificacao_nc']) . '</td>'
        . '<td><input type="text" data-campo="responsavel_resolucao" maxlength="' . CHECKLIST_RESPONSAVEL_MAX . '" value="' . e($dados['responsavel_resolucao']) . '" aria-label="Responsável pela resolução do item ' . $numero . '"' . $bloqueado . '></td>'
        . '<td><select data-campo="classificacao_nc_id" aria-label="Classificação da NC do item ' . $numero . '"' . $bloqueado . '>'
            . checklist_opcoes_html($opcoesClassificacao, $dados['classificacao_nc_id'], '<option value="">—</option>') . '</select></td>'
        . '<td><textarea data-campo="acao_corretiva_indicada" rows="2" maxlength="' . CHECKLIST_ACAO_MAX . '" aria-label="Ação corretiva indicada do item ' . $numero . '"' . $bloqueado . '>' . e($dados['acao_corretiva_indicada']) . '</textarea></td>'
        . '<td class="col-data" data-exibe="data_prevista_resolucao">' . e($dados['data_prevista_resolucao']) . '</td>'
        . '<td class="col-data" data-exibe="data_escalonamento">' . e($dados['data_escalonamento']) . '</td>'
        . '<td class="col-data" data-exibe="data_conclusao_nc">' . e($dados['data_conclusao_nc']) . '</td>'
        . '<td><select data-campo="status_nc" aria-label="Status da NC do item ' . $numero . '"' . $bloqueadoStatus . '>'
            . checklist_opcoes_html(CHECKLIST_STATUS_NC, $dados['status_nc'], '<option value="" disabled' . ($dados['status_nc'] === '' ? ' selected' : '') . '>—</option>') . '</select></td>'
        . '<td class="col-solicitacao">' . checklist_solicitacao_html($dados) . '</td>'
        . '<td><button type="button" class="js-excluir-item botao-perigo" aria-label="Excluir item ' . $numero . '"' . $travado . '>Excluir</button></td>'
        . '</tr>';
}
