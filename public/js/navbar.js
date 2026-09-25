function addClick(id, url) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', () => window.location.href = url);
}

addClick('inicioButtonLink',    '/DeepCheck/menu');
addClick('estoquesButtonLink',  '/DeepCheck/estoque');
addClick('perfilButtonLink',    '/DeepCheck/perfil_usuario');
addClick('logoffButtonLink',    '/DeepCheck/src/Controllers/logoff.php');