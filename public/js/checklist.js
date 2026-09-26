const checklist = document.getElementById('checklist');
const corpoItens = document.getElementById('itensChecklist');
const statusChecklist = document.getElementById('statusChecklist');
const formNovoItem = document.getElementById('formNovoItem');
const CAMPOS_NC = ['responsavel_resolucao', 'classificacao_nc_id', 'acao_corretiva_indicada', 'status_nc'];
let salvamentosPendentes = 0;

// Mesma regra de src/Models/checklist.php (checklist_categoria_item).
function categoriaItem(resultado, statusNc) {
    if (resultado === '') return 'na';
    if (resultado === 'nao_se_aplica') return 'nna';
    if (resultado === 'conforme') return 'conforme';
    if (statusNc === 'resolvida') return 'conforme';
    if (statusNc === 'fechada_por_excecao') return 'nna';
    return 'nnc';
}

function mostrarIndicadores(ind) {
    for (const chave of ['nt', 'na', 'nta', 'nc', 'nnc', 'nna']) {
        document.getElementById(`ind-${chave}`).textContent = ind[chave];
    }
    document.getElementById('ind-aderencia').textContent = ind.aderencia === null
        ? '—'
        : `${ind.aderencia.toFixed(2).replace('.', ',')}%`;
}

// Recalcula na hora, a partir da tela; a resposta do servidor confirma em seguida.
function recalcularLocal() {
    const total = { na: 0, nna: 0, conforme: 0, nnc: 0 };
    const linhas = corpoItens.querySelectorAll('tr[data-item-id]');
    for (const linha of linhas) {
        const resultado = linha.querySelector('[data-campo="resultado"]').value;
        const status = linha.querySelector('[data-campo="status_nc"]').value;
        total[categoriaItem(resultado, status)]++;
    }
    const nt = linhas.length;
    const nta = nt - total.na - total.nna;
    const nc = nta - total.nnc;
    mostrarIndicadores({
        nt, na: total.na, nna: total.nna, nta, nnc: total.nnc, nc,
        aderencia: nta > 0 ? Math.round((nc / nta) * 10000) / 100 : null
    });
}

function mostrarStatus(texto, sucesso) {
    statusChecklist.textContent = texto;
    statusChecklist.classList.toggle('mensagem-sucesso', sucesso === true);
    statusChecklist.classList.toggle('mensagem-erro', sucesso === false);
}

function marcarComoSalvo(campo) {
    campo.dataset.valorSalvo = campo.value;
}

// Atualiza a linha com o item devolvido pelo servidor, sem mexer no campo em que o
// usuário está digitando nem em campos com alteração ainda não enviada.
function aplicarItem(linha, item) {
    for (const campo of linha.querySelectorAll('[data-campo]')) {
        const nome = campo.dataset.campo;
        const alterado = campo.value !== campo.dataset.valorSalvo;
        if (campo !== document.activeElement && !alterado) {
            campo.value = item[nome];
            marcarComoSalvo(campo);
        }
        // Campos de NC só em item NC; depois do envio da NC, só o status continua editável.
        if (nome === 'status_nc') {
            campo.disabled = item.resultado !== 'nao_conformidade';
        } else if (CAMPOS_NC.includes(nome)) {
            campo.disabled = item.resultado !== 'nao_conformidade' || item.nc_enviada;
        } else {
            campo.disabled = item.nc_enviada;
        }
    }
    for (const celula of linha.querySelectorAll('[data-exibe]')) {
        celula.textContent = item[celula.dataset.exibe];
    }
    linha.classList.toggle('item-nc', item.resultado === 'nao_conformidade');
    linha.classList.toggle('item-atrasado', item.atrasado);
    atualizarSolicitacao(linha, item);
}

// Célula "Solicitação de resolução": botão "Enviar NC" ou a data do envio.
function atualizarSolicitacao(linha, item) {
    const celula = linha.querySelector('.col-solicitacao');
    if (item.nc_enviada) {
        const enviada = document.createElement('span');
        enviada.className = 'nc-enviada';
        enviada.textContent = `Enviada em ${item.nc_enviada_em}`;
        celula.replaceChildren(enviada);
    } else {
        celula.querySelector('.js-enviar-nc').disabled = !item.pode_enviar;
    }
    linha.querySelector('.js-excluir-item').disabled = item.nc_enviada;
}

function temDadosDeNc(linha) {
    return ['responsavel_resolucao', 'classificacao_nc_id', 'acao_corretiva_indicada']
        .some((nome) => linha.querySelector(`[data-campo="${nome}"]`).value.trim() !== '');
}

// Salvamentos da mesma linha vão em fila, para as respostas não chegarem fora de ordem.
function salvarCampo(linha, campo) {
    salvamentosPendentes++;
    mostrarStatus('Salvando...');
    linha.filaSalvamento = (linha.filaSalvamento || Promise.resolve()).then(async () => {
        const resposta = await enviarPost('/src/Controllers/checklist_item_atualizar.php', {
            item_id: linha.dataset.itemId,
            campo: campo.dataset.campo,
            valor: campo.value
        });
        salvamentosPendentes--;

        if (resposta.status === 'ok') {
            marcarComoSalvo(campo);
            aplicarItem(linha, resposta.item);
            mostrarIndicadores(resposta.indicadores);
            if (salvamentosPendentes === 0) {
                mostrarStatus('Todas as alterações foram salvas.', true);
            }
            return;
        }
        campo.value = campo.dataset.valorSalvo;
        recalcularLocal();
        mostrarStatus(`Item ${linha.querySelector('.col-numero').textContent}: ${resposta.mensagem}`, false);
    });
}

corpoItens.addEventListener('change', (event) => {
    const campo = event.target.closest('[data-campo]');
    if (!campo) return;
    const linha = campo.closest('tr');

    if (campo.dataset.campo === 'resultado'
        && campo.dataset.valorSalvo === 'nao_conformidade'
        && campo.value !== 'nao_conformidade'
        && temDadosDeNc(linha)
        && !window.confirm('Este item deixará de ser uma não conformidade e os dados da NC (responsável, classificação, ação corretiva, datas e status) serão apagados. Continuar?')) {
        campo.value = campo.dataset.valorSalvo;
        return;
    }

    if (campo.dataset.campo === 'descricao' && campo.value.trim() === '') {
        campo.value = campo.dataset.valorSalvo;
        mostrarStatus('A descrição do item não pode ficar vazia.', false);
        return;
    }

    recalcularLocal();
    salvarCampo(linha, campo);
});

corpoItens.addEventListener('click', async (event) => {
    const botao = event.target.closest('.js-excluir-item');
    if (!botao) return;
    const linha = botao.closest('tr');
    const numero = linha.querySelector('.col-numero').textContent;

    if (!window.confirm(`Excluir o item ${numero}? Esta ação não pode ser desfeita.`)) return;

    const resposta = await enviarPost('/src/Controllers/checklist_item_excluir.php', { item_id: linha.dataset.itemId });
    if (resposta.status === 'ok') {
        linha.remove();
        mostrarIndicadores(resposta.indicadores);
        document.getElementById('checklistVazio').hidden = corpoItens.children.length > 0;
        mostrarStatus(`Item ${numero} excluído.`, true);
    } else {
        mostrarStatus(resposta.mensagem, false);
    }
});

formNovoItem.addEventListener('submit', async (event) => {
    event.preventDefault();
    const descricao = formNovoItem.descricao;
    if (descricao.value.trim() === '') return;
    const botao = formNovoItem.querySelector('button[type="submit"]');

    botao.disabled = true;
    const resposta = await enviarPost('/src/Controllers/checklist_item_adicionar.php', {
        projeto_id: checklist.dataset.projetoId,
        descricao: descricao.value
    });
    botao.disabled = false;

    if (resposta.status !== 'ok') {
        mostrarStatus(resposta.mensagem, false);
        return;
    }
    corpoItens.insertAdjacentHTML('beforeend', resposta.item_html);
    corpoItens.lastElementChild.querySelectorAll('[data-campo]').forEach(marcarComoSalvo);
    document.getElementById('checklistVazio').hidden = true;
    mostrarIndicadores(resposta.indicadores);
    mostrarStatus('Item adicionado.', true);
    descricao.value = '';
    descricao.focus();
});

// --- Envio da Solicitação de Resolução de Não Conformidade ---
const dialogNc = document.getElementById('dialogEnviarNc');
const formNc = document.getElementById('formEnviarNc');
const mensagemNc = document.getElementById('mensagemEnviarNc');
let linhaEnvio = null;

corpoItens.addEventListener('click', (event) => {
    const botao = event.target.closest('.js-enviar-nc');
    if (!botao) return;
    linhaEnvio = botao.closest('tr');
    const valor = (campo) => linhaEnvio.querySelector(`[data-campo="${campo}"]`);

    document.getElementById('ncItem').textContent = linhaEnvio.querySelector('.col-numero').textContent;
    document.getElementById('ncResponsavel').textContent = valor('responsavel_resolucao').value;
    document.getElementById('ncPrazo').textContent = linhaEnvio.querySelector('[data-exibe="data_prevista_resolucao"]').textContent;
    document.getElementById('ncDescricao').textContent = valor('descricao').value;
    document.getElementById('ncClassificacao').textContent = valor('classificacao_nc_id').selectedOptions[0].textContent;
    document.getElementById('ncAcao').textContent = valor('acao_corretiva_indicada').value || '—';

    formNc.reset();
    formNc.responsavel_qa.value = checklist.dataset.responsavelQa;
    mensagemNc.textContent = '';
    dialogNc.showModal();
    formNc.email_responsavel.focus();
});

document.getElementById('cancelarEnviarNc').addEventListener('click', () => dialogNc.close());

formNc.addEventListener('submit', async (event) => {
    event.preventDefault();
    const botao = document.getElementById('confirmarEnviarNc');
    botao.disabled = true;
    botao.textContent = 'Enviando...';
    mensagemNc.classList.remove('mensagem-erro');
    mensagemNc.textContent = 'Gerando o PDF e enviando o e-mail. Isso pode levar alguns segundos.';

    // Garante que alterações da linha ainda em salvamento cheguem ao servidor antes do envio.
    await (linhaEnvio.filaSalvamento || Promise.resolve());
    const dados = new FormData(formNc);
    dados.append('item_id', linhaEnvio.dataset.itemId);
    const resposta = await enviarPost('/src/Controllers/nc_enviar.php', dados);

    botao.disabled = false;
    botao.textContent = 'Enviar e-mail';
    if (resposta.status === 'ok') {
        aplicarItem(linhaEnvio, resposta.item);
        dialogNc.close();
        mostrarStatus(resposta.mensagem, true);
        return;
    }
    mensagemNc.classList.add('mensagem-erro');
    mensagemNc.textContent = resposta.mensagem;
});

window.addEventListener('beforeunload', (event) => {
    if (salvamentosPendentes > 0) {
        event.preventDefault();
        event.returnValue = '';
    }
});

corpoItens.querySelectorAll('[data-campo]').forEach(marcarComoSalvo);
