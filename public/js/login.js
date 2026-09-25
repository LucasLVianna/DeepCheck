document.getElementById('formLogin').addEventListener('submit',(e)=>{
    e.preventDefault();
    login();
})

document.getElementById('createAccount').addEventListener('click', ()=>{
    window.location.href = '/src/Views/cadastro.php';
})
document.addEventListener('DOMContentLoaded', async ()=>{
    const params = new URLSearchParams(window.location.search);
    if (params.get('motivo') === 'expirado') {
        document.getElementById('error').style.color = '#ffcc00';
        document.getElementById('error').textContent = 'Sua sessão expirou por inatividade. Faça login novamente.';
    }
});


async function login() {
    let email = document.getElementById('email').value;
    let senha = document.getElementById('senha').value;

    if(!email){
        document.getElementById('error-email').textContent = 'Email precisa receber valores';
        return;
    }else if(!email.includes('@') && !email.includes('.')) {
        document.getElementById('error-email').textContent = 'Digite um email válido, no formato @xxx.xxx';
        return;
    }

    if(!senha){
        document.getElementById('error-senha').textContent = 'Senha precisa receber valores';
        return;
    }else if(senha.length < 8) {
        document.getElementById('error-senha').textContent = 'ERRO! Senha muito curta';
        return;
    }

    const fd = new FormData();
    fd.append('email', email);
    fd.append('senha', senha);

    const retorno = await fetch('/src/Controllers/login_backend.php',{
        method: "POST",
        headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
        body: fd
    })

    const resposta = await retorno.json();
    if(resposta.status == 'ok'){
        document.getElementById('error').style.color = '#00ffa3';
        document.getElementById('error').textContent = 'SUCESSO! ' + resposta.mensagem + '. Redirecionando...';
        setTimeout(() => {
            window.location.href = resposta.redirect;
        }, 1000);
    }else{
        document.getElementById('error').style.color = '#ff6b6b';
        document.getElementById('error').textContent = 'ERRO! ' + resposta.mensagem;
    };
}
