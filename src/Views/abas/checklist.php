<?php
// Aba "Checklist de Qualidade" — incluída por src/Views/projeto.php, que carrega $projeto,
// $itens (checklist_itens), $classificacoes, $indicadores (checklist_indicadores) e
// $responsavelQaPadrao (RQ do PGQ ou nome do usuário, usado no formulário de envio da NC).
// Colunas seguem o template "Modelo Checklist - Processo de Qualidade".
if (!isset($projeto, $itens, $classificacoes, $indicadores, $responsavelQaPadrao)) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/checklist_linha.php';

$aderencia = $indicadores['aderencia'] === null ? '—' : number_format($indicadores['aderencia'], 2, ',', '.') . '%';
?>
<div class="checklist" id="checklist" data-projeto-id="<?= (int) $projeto['id'] ?>" data-responsavel-qa="<?= e($responsavelQaPadrao) ?>">
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
                    <th>Solicitação de resolução</th>
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

    <dialog id="dialogEnviarNc" class="dialog-nc" aria-labelledby="tituloEnviarNc">
        <form id="formEnviarNc" method="dialog">
            <h3 id="tituloEnviarNc">Solicitação de Resolução de Não Conformidade</h3>
            <p class="dica">O documento será enviado em PDF, pela conta de e-mail do sistema, em seu nome. Você recebe uma cópia e as respostas chegam para você.</p>

            <dl class="dialog-nc-resumo">
                <dt>Projeto</dt><dd><?= e($projeto['nome']) ?></dd>
                <dt>Item</dt><dd id="ncItem"></dd>
                <dt>Responsável pela Resolução</dt><dd id="ncResponsavel"></dd>
                <dt>Data da 1ª Solicitação</dt><dd>no momento do envio</dd>
                <dt>Prazo de Resolução</dt><dd id="ncPrazo"></dd>
                <dt>Nº de Escalonamento</dt><dd>0</dd>
                <dt>Descrição</dt><dd id="ncDescricao"></dd>
                <dt>Classificação</dt><dd id="ncClassificacao"></dd>
                <dt>Ação Corretiva Indicada</dt><dd id="ncAcao"></dd>
            </dl>

            <label for="ncEmailResponsavel">E-mail do responsável pela resolução <span class="obrigatorio">*</span></label>
            <input type="email" id="ncEmailResponsavel" name="email_responsavel" maxlength="255" required>

            <label for="ncResponsavelQa">Responsável por QA <span class="obrigatorio">*</span></label>
            <input type="text" id="ncResponsavelQa" name="responsavel_qa" maxlength="255" required>

            <label for="ncCc">Com cópia para (opcional, separe por vírgula)</label>
            <input type="text" id="ncCc" name="cc" maxlength="2600" placeholder="fulano@exemplo.com, ciclano@exemplo.com">

            <label for="ncObservacoes">Observações</label>
            <textarea id="ncObservacoes" name="observacoes" rows="3" maxlength="5000" placeholder="Nenhuma observação adicional."></textarea>

            <p class="mensagem" id="mensagemEnviarNc" role="status"></p>
            <div class="dialog-nc-acoes">
                <button type="button" id="cancelarEnviarNc">Cancelar</button>
                <button type="submit" id="confirmarEnviarNc">Enviar e-mail</button>
            </div>
        </form>
    </dialog>
</div>
