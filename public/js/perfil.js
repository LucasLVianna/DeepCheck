// Página de perfil: três formulários independentes (dados, e-mail, senha).
const campoCep = document.getElementById('cep');
campoCep.addEventListener('input', () => {
    const digitos = campoCep.value.replace(/\D/g, '').slice(0, 8);
    campoCep.value = digitos.length > 5 ? `${digitos.slice(0, 5)}-${digitos.slice(5)}` : digitos;
});

function mensagemDoForm(form, texto, sucesso) {
    const mensagem = form.querySelector('.mensagem');
    mensagem.textContent = texto;
    mensagem.classList.toggle('mensagem-sucesso', sucesso);
    mensagem.classList.toggle('mensagem-erro', !sucesso);
}

// Validação no navegador antes de enviar (o servidor valida de novo).
const validacoes = {
    dados: (form) => {
        if (!form.nome.value.trim()) return 'Informe o nome.';
        if (form.cep.value.replace(/\D/g, '').length !== 8) return 'Digite um CEP válido (00000-000).';
        return null;
    },
    email: (form) => {
        if (!emailValido(form.email.value.trim())) return 'Digite um e-mail válido.';
        if (!form.senha_atual.value) return 'Informe sua senha atual para confirmar.';
        return null;
    },
    senha: (form) => {
        if (!form.senha_atual.value) return 'Informe sua senha atual.';
        if (!senhaForte(form.nova_senha.value)) return 'A nova senha ainda não atende a todos os requisitos.';
        if (form.nova_senha.value !== form.confirmar_senha.value) return 'A confirmação não confere com a nova senha.';
        return null;
    }
};

document.querySelectorAll('form[data-acao]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const acao = form.dataset.acao;
        const erro = validacoes[acao](form);
        if (erro) {
            mensagemDoForm(form, erro, false);
            return;
        }

        const botao = form.querySelector('button[type="submit"]');
        botao.disabled = true;
        const dados = new FormData(form);
        dados.append('acao', acao);
        const resposta = await enviarPost('/src/Controllers/perfil_salvar.php', dados);
        botao.disabled = false;

        mensagemDoForm(form, resposta.mensagem, resposta.status === 'ok');
        if (resposta.status !== 'ok') return;

        if (acao === 'dados') {
            const nomeNavbar = document.querySelector('.usuarioLogado');
            if (nomeNavbar) nomeNavbar.textContent = resposta.nome;
        } else if (acao === 'email') {
            document.getElementById('emailAtual').textContent = resposta.email;
            form.reset();
        } else {
            form.reset();
            form.querySelectorAll('.requisitos-senha li').forEach((item) => item.classList.remove('atendido'));
        }
    });
});
