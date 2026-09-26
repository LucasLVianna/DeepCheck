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
        if (CAMPOS_NC.includes(nome)) {
            campo.disabled = item.resultado !== 'nao_conformidade';
        }
    }
    for (const celula of linha.querySelectorAll('[data-exibe]')) {
        celula.textContent = item[celula.dataset.exibe];
    }
    linha.classList.toggle('item-nc', item.resultado === 'nao_conformidade');
    linha.classList.toggle('item-atrasado', item.atrasado);
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

window.addEventListener('beforeunload', (event) => {
    if (salvamentosPendentes > 0) {
        event.preventDefault();
        event.returnValue = '';
    }
});

corpoItens.querySelectorAll('[data-campo]').forEach(marcarComoSalvo);
