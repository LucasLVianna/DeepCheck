const abaNc = document.getElementById('abaNc');
const statusNc = document.getElementById('statusNc');
const dialogEscalonar = document.getElementById('dialogEscalonar');
const formEscalonar = document.getElementById('formEscalonar');
const mensagemEscalonar = document.getElementById('mensagemEscalonar');
const CHAVE_MENSAGEM = 'deepcheck-mensagem-nc';

function mostrarStatus(texto, sucesso) {
    statusNc.textContent = texto;
    statusNc.classList.toggle('mensagem-sucesso', sucesso === true);
    statusNc.classList.toggle('mensagem-erro', sucesso === false);
}

// Depois de uma ação que muda o histórico, recarrega a aba e mostra a mensagem.
function recarregarComMensagem(texto) {
    try {
        sessionStorage.setItem(CHAVE_MENSAGEM, texto);
    } catch (erro) {
        // Sem sessionStorage a mensagem só não aparece após recarregar.
    }
    window.location.reload();
}

try {
    const mensagem = sessionStorage.getItem(CHAVE_MENSAGEM);
    if (mensagem) {
        sessionStorage.removeItem(CHAVE_MENSAGEM);
        mostrarStatus(mensagem, true);
    }
} catch (erro) {
    // Ignorado: ver recarregarComMensagem().
}

// --- Filtro ---
document.getElementById('filtroNc').addEventListener('change', (event) => {
    const filtro = event.target.value;
    for (const card of abaNc.querySelectorAll('.nc-card')) {
        const aberta = card.dataset.aberta === '1';
        const visivel = filtro === 'todas'
            || (filtro === 'abertas' && aberta)
            || (filtro === 'encerradas' && !aberta)
            || (filtro === 'atrasadas' && card.dataset.atrasada === '1');
        card.hidden = !visivel;
    }
});

// --- Status (mesmo endpoint e mesmas regras do checklist) ---
abaNc.addEventListener('change', async (event) => {
    const select = event.target.closest('.js-status-nc');
    if (!select) return;
    const card = select.closest('.nc-card');
    select.disabled = true;

    const resposta = await enviarPost('/src/Controllers/checklist_item_atualizar.php', {
        item_id: card.dataset.itemId,
        campo: 'status_nc',
        valor: select.value
    });
    select.disabled = false;

    if (resposta.status === 'ok') {
        recarregarComMensagem(`Item ${card.dataset.numeroItem}: status alterado para "${select.selectedOptions[0].textContent}".`);
        return;
    }
    select.value = select.dataset.valorSalvo;
    mostrarStatus(`Item ${card.dataset.numeroItem}: ${resposta.mensagem}`, false);
});

// --- Reenviar ---
abaNc.addEventListener('click', async (event) => {
    const botao = event.target.closest('.js-reenviar');
    if (!botao) return;
    const card = botao.closest('.nc-card');
    if (!window.confirm(`Reenviar a solicitação do item ${card.dataset.numeroItem} para ${card.dataset.responsavelEmail}?`)) return;

    botao.disabled = true;
    mostrarStatus('Reenviando o e-mail...');
    const resposta = await enviarPost('/src/Controllers/nc_reenviar.php', { nc_id: card.dataset.ncId });
    botao.disabled = false;
    if (resposta.status === 'ok') {
        recarregarComMensagem(resposta.mensagem);
    } else {
        mostrarStatus(resposta.mensagem, false);
    }
});

// --- Escalonar ---
abaNc.addEventListener('click', (event) => {
    const botao = event.target.closest('.js-escalonar');
    if (!botao) return;
    const card = botao.closest('.nc-card');
    const proximo = Number(card.dataset.numeroEscalonamento) + 1;

    formEscalonar.reset();
    formEscalonar.nc_id.value = card.dataset.ncId;
    formEscalonar.numero_escalonamento.value = card.dataset.numeroEscalonamento;
    formEscalonar.responsavel_resolucao.value = card.dataset.responsavel;
    formEscalonar.responsavel_email.value = card.dataset.responsavelEmail;
    document.getElementById('escItem').textContent = card.dataset.numeroItem;
    document.getElementById('escClassificacao').textContent = card.dataset.classificacao;
    document.getElementById('escPrazoAtual').textContent = card.dataset.prazoAtual;
    document.getElementById('escNumero').textContent = `Nº ${proximo}`;

    // Prazo em dias: só a data (vale até o fim do dia). Em horas: data e hora.
    const prazo = formEscalonar.novo_prazo;
    prazo.type = card.dataset.unidade === 'dias' ? 'date' : 'datetime-local';
    prazo.value = card.dataset.prazoSugerido;
    const agora = new Date();
    const sugeridoNoPassado = new Date(card.dataset.prazoSugerido + (card.dataset.unidade === 'dias' ? 'T23:59:59' : '')) <= agora;
    document.getElementById('escPrazoDica').textContent = sugeridoNoPassado
        ? 'O prazo calculado (mesma duração da classificação, a partir do dia útil seguinte ao prazo atual) já passou: ajuste para uma data futura.'
        : 'Calculado com a mesma duração da classificação, a partir do dia útil seguinte ao prazo atual. Pode ser ajustado.';

    mensagemEscalonar.textContent = '';
    mensagemEscalonar.classList.remove('mensagem-erro');
    dialogEscalonar.showModal();
    formEscalonar.superior_nome.focus();
});

document.getElementById('cancelarEscalonar').addEventListener('click', () => dialogEscalonar.close());

document.getElementById('previsualizarEscalonamento').addEventListener('click', async () => {
    if (!formEscalonar.reportValidity()) return;
    const dados = new FormData(formEscalonar);
    dados.append('tipo', 'escalonamento');
    const erro = await abrirPdfPost('/src/Controllers/nc_previsualizar.php', dados);
    mensagemEscalonar.classList.toggle('mensagem-erro', erro !== null);
    mensagemEscalonar.textContent = erro || '';
});

formEscalonar.addEventListener('submit', async (event) => {
    event.preventDefault();
    const botao = document.getElementById('confirmarEscalonar');
    botao.disabled = true;
    botao.textContent = 'Enviando...';
    mensagemEscalonar.classList.remove('mensagem-erro');
    mensagemEscalonar.textContent = 'Gerando o PDF e enviando o e-mail. Isso pode levar alguns segundos.';

    const resposta = await enviarPost('/src/Controllers/nc_escalonar.php', new FormData(formEscalonar));

    botao.disabled = false;
    botao.textContent = 'Enviar escalonamento';
    if (resposta.status === 'ok') {
        dialogEscalonar.close();
        recarregarComMensagem(resposta.mensagem);
        return;
    }
    mensagemEscalonar.classList.add('mensagem-erro');
    mensagemEscalonar.textContent = resposta.mensagem;
});
