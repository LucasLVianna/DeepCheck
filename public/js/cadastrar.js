const cadastroForm = document.getElementById('formCadastro');
const entrarButton = document.getElementById('entrar');
const cepInput = document.getElementById('cep');

function formatCep(value) {
    let digits = String(value || '').replace(/\D/g, '').slice(0, 8);

    if (digits.length > 5) {
        digits = `${digits.slice(0, 5)}-${digits.slice(5)}`;
    }

    return digits;
}

function cepDigitsLength(value) {
    return String(value || '').replace(/\D/g, '').length;
}

cadastroForm.addEventListener('submit', (event) => {
    event.preventDefault();
    cadastrar();
});

entrarButton.addEventListener('click', () => {
    window.location.href = '/DeepCheck/login';
});

cepInput.addEventListener('input', () => {
    cepInput.value = formatCep(cepInput.value);
});

async function cadastrar() {
    const nome = document.getElementById('nome').value.trim();
    const email = document.getElementById('email').value.trim();
    const senha = document.getElementById('senha').value;
    const cep = formatCep(cepInput.value);

    if(!nome){
        document.getElementById('error-nome').textContent = 'Nome precisa receber valores';
        return;
    }

    if(!email){
        document.getElementById('error-email').textContent = 'Email precisa receber valores';
        return;
    }else if(!email.includes('@') && !email.includes('.')) {
        document.getElementById('error-email').textContent = 'Digite um email válido, no formato @xxx.xxx';
        return;
    }

    if(!senha){
        document.getElementById('error-senha').textContent = 'Senha precisa receber valores'
    }else if(senha.length < 8 || !/[A-Z]/.test(senha) || !/[a-z]/.test(senha) || !/[0-9]/.test(senha) || !/[@$!%*?&]/.test(senha)) {
        document.getElementById('error-senha').textContent = 'ERRO! Senha deve conter no mínimo 8 caracteres, letras maiúsculas, minúsculas, números e caracteres especiais.';
        return;
    }

    if (!cep || cepDigitsLength(cep) !== 8) {
        document.getElementById('error-cep').textContent = 'Digite um CEP válido no formato 00000-000.';
        cepInput.focus();
        return;
    }

    const fd = new FormData();
    fd.append('nome', nome);
    fd.append('email', email);
    fd.append('senha', senha);
    fd.append('cep', cep);

    const retorno = await fetch('/DeepCheck/src/Controllers/cadastrar_backend.php', {
        method: 'POST',
        body: fd
    });

    const resposta = await retorno.json();
    if (resposta.status === 'ok') {
        document.getElementById('error').style.color = '#00ffa3';
        document.getElementById('error').textContent = 'SUCESSO! Cadastro realizado com êxito' + '. Redirecionando para a página de login...';
        setTimeout(() => {
            window.location.href = '/mykeeper/usuario_login';
        }, 1000);
        return;
    }else{
        document.getElementById('error').style.color = '#ff6b6b';
        document.getElementById('error').textContent = 'ERRO! ' + resposta.mensagem;
    }

    
}