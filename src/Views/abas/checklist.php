<?php
// Aba "Checklist de Qualidade" — incluída por src/Views/projeto.php, que carrega $projeto,
// $itens (checklist_itens), $classificacoes e $indicadores (checklist_indicadores).
// Colunas seguem o template "Modelo Checklist - Processo de Qualidade".
if (!isset($projeto, $itens, $classificacoes, $indicadores)) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/checklist_linha.php';

$aderencia = $indicadores['aderencia'] === null ? '—' : number_format($indicadores['aderencia'], 2, ',', '.') . '%';
?>
<div class="checklist" id="checklist" data-projeto-id="<?= (int) $projeto['id'] ?>">
    <section class="checklist-indicadores" aria-label="Indicadores de aderência">
        <div class="indicador indicador-destaque"><span>Aderência</span><strong id="ind-aderencia"><?= $aderencia ?></strong></div>
        <div class="indicador"><span>Total de itens (NT)</span><strong id="ind-nt"><?= $indicadores['nt'] ?></strong></div>
        <div class="indicador"><span>Não avaliados (NA)</span><strong id="ind-na"><?= $indicadores['na'] ?></strong></div>
        <div class="indicador"><span>Avaliados (NTA)</span><strong id="ind-nta"><?= $indicadores['nta'] ?></strong></div>
        <div class="indicador"><span>Conformidades (NC)</span><strong id="ind-nc"><?= $indicadores['nc'] ?></strong></div>
        <div class="indicador"><span>Não conformidades (NNC)</span><strong id="ind-nnc"><?= $indicadores['nnc'] ?></strong></div>
        <div class="indicador"><span>Não aplicáveis</span><strong id="ind-nna"><?= $indicadores['nna'] ?></strong></div>
    </section>
    <p class="dica">
        NTA = NT − NA − não aplicáveis · NC = NTA − NNC · Aderência = NC / NTA × 100.
        NC resolvida conta como conformidade; NC fechada por exceção conta como não aplicável.
    </p>

    <p class="mensagem" id="statusChecklist" role="status"></p>

    <div class="tabela-rolagem">
        <table class="checklist-tabela">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Descrição</th>
                    <th>Resultado</th>
                    <th>Data e hora da identificação da NC</th>
                    <th>Responsável pela resolução</th>
                    <th>Classificação da NC</th>
                    <th>Ação corretiva indicada</th>
                    <th>Data prevista de resolução</th>
                    <th>Data e hora do escalonamento</th>
                    <th>Data e hora da conclusão da NC</th>
                    <th>Status da NC</th>
                    <th><span class="visualmente-oculto">Ações</span></th>
                </tr>
            </thead>
            <tbody id="itensChecklist">
                <?php foreach ($itens as $item): ?>
                    <?= checklist_linha_html($item, $classificacoes) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="vazio" id="checklistVazio"<?= $itens ? ' hidden' : '' ?>>O checklist ainda não tem itens. Adicione o primeiro abaixo.</p>

    <form id="formNovoItem" class="checklist-novo-item">
        <label for="novoItemDescricao">Novo item</label>
        <textarea id="novoItemDescricao" name="descricao" rows="2" maxlength="<?= CHECKLIST_DESCRICAO_MAX ?>" placeholder="Ex.: O item 1 (Introdução) foi preenchido?" required></textarea>
        <button type="submit">Adicionar item</button>
    </form>
</div>
