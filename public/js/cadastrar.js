const formCadastro = document.getElementById('formCadastro');
const campos = {
    nome: document.getElementById('nome'),
    email: document.getElementById('email'),
    cep: document.getElementById('cep'),
    senha: document.getElementById('senha'),
    confirmarSenha: document.getElementById('confirmarSenha')
};

// Máscara 00000-000.
campos.cep.addEventListener('input', () => {
    const digitos = campos.cep.value.replace(/\D/g, '').slice(0, 8);
    campos.cep.value = digitos.length > 5 ? `${digitos.slice(0, 5)}-${digitos.slice(5)}` : digitos;
});

function validar() {
    const erros = [];
    if (!campos.nome.value.trim()) erros.push([campos.nome, 'Informe seu nome.']);
    if (!emailValido(campos.email.value.trim())) {
        erros.push([campos.email, campos.email.value.trim() ? 'Digite um e-mail válido.' : 'Informe seu e-mail.']);
    }
    if (campos.cep.value.replace(/\D/g, '').length !== 8) erros.push([campos.cep, 'Digite um CEP válido (00000-000).']);
    if (!senhaForte(campos.senha.value)) erros.push([campos.senha, 'A senha ainda não atende a todos os requisitos acima.']);
    if (!campos.confirmarSenha.value) {
        erros.push([campos.confirmarSenha, 'Confirme a senha.']);
    } else if (campos.confirmarSenha.value !== campos.senha.value) {
        erros.push([campos.confirmarSenha, 'As senhas não conferem.']);
    }
    erros.forEach(([campo, mensagem]) => mostrarErroCampo(campo, mensagem));
    return erros.length === 0;
}

formCadastro.addEventListener('submit', async (event) => {
    event.preventDefault();
    esconderAlerta();
    if (!validar()) {
        formCadastro.querySelector('[aria-invalid="true"]').focus();
        return;
    }

    const botao = document.getElementById('botaoCriarConta');
    botaoCarregando(botao, true, 'Criando conta...');
    const resposta = await enviarPost('/src/Controllers/cadastrar_backend.php', {
        nome: campos.nome.value.trim(),
        email: campos.email.value.trim(),
        cep: campos.cep.value,
        senha: campos.senha.value
    });

    if (resposta.status === 'ok') {
        window.location.href = '/src/Views/login.php?motivo=cadastro';
        return;
    }
    botaoCarregando(botao, false);
    mostrarAlerta(resposta.mensagem, 'erro');
});
