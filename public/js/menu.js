const formNovoProjeto = document.getElementById('formNovoProjeto');
const formAcessarProjeto = document.getElementById('formAcessarProjeto');
const listaProjetos = document.getElementById('listaProjetos');

function mostrarMensagem(elemento, texto, sucesso) {
    elemento.textContent = texto;
    elemento.classList.toggle('mensagem-sucesso', sucesso);
    elemento.classList.toggle('mensagem-erro', !sucesso);
}

formNovoProjeto.addEventListener('submit', async (event) => {
    event.preventDefault();
    const mensagem = document.getElementById('mensagemNovoProjeto');
    const botao = formNovoProjeto.querySelector('button[type="submit"]');

    if (formNovoProjeto.senha.value !== formNovoProjeto.confirmar_senha.value) {
        mostrarMensagem(mensagem, 'A confirmação não confere com a senha do projeto.', false);
        return;
    }

    botao.disabled = true;
    const resposta = await enviarPost('/src/Controllers/projeto_criar.php', new FormData(formNovoProjeto));
    botao.disabled = false;

    if (resposta.status === 'ok') {
        // O código de acesso aparece no card do projeto após recarregar.
        window.location.reload();
        return;
    }
    mostrarMensagem(mensagem, resposta.mensagem, false);
});

formAcessarProjeto.addEventListener('submit', async (event) => {
    event.preventDefault();
    const mensagem = document.getElementById('mensagemAcessarProjeto');
    const botao = formAcessarProjeto.querySelector('button[type="submit"]');

    botao.disabled = true;
    const resposta = await enviarPost('/src/Controllers/projeto_acessar.php', new FormData(formAcessarProjeto));
    botao.disabled = false;

    if (resposta.status === 'ok') {
        window.location.href = resposta.redirect;
        return;
    }
    mostrarMensagem(mensagem, resposta.mensagem, false);
    formAcessarProjeto.senha.value = '';
});

if (listaProjetos) {
    listaProjetos.addEventListener('click', async (event) => {
        const botao = event.target.closest('.js-renomear, .js-excluir');
        if (!botao) {
            return;
        }
        const card = botao.closest('.projeto-card');
        const { id, nome } = card.dataset;

        if (botao.classList.contains('js-renomear')) {
            const novoNome = window.prompt('Novo nome do projeto:', nome);
            if (novoNome === null || novoNome.trim() === '' || novoNome.trim() === nome) {
                return;
            }
            const resposta = await enviarPost('/src/Controllers/projeto_editar.php', { id, nome: novoNome.trim() });
            if (resposta.status === 'ok') {
                window.location.reload();
            } else {
                window.alert(resposta.mensagem);
            }
            return;
        }

        const confirmado = window.confirm(
            `Excluir o projeto "${nome}"?\n\nTodos os dados do projeto serão apagados para todos os membros. Esta ação não pode ser desfeita.`
        );
        if (!confirmado) {
            return;
        }
        const resposta = await enviarPost('/src/Controllers/projeto_excluir.php', { id });
        if (resposta.status === 'ok') {
            window.location.reload();
        } else {
            window.alert(resposta.mensagem);
        }
    });
}
