<?php
require_once __DIR__ . '/../../config/auth.php';

// Logoff só por POST com token CSRF, para que um link ou imagem de outro site
// não consiga deslogar o usuário.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido()) {
    header('Location: /src/Views/menu.php');
    exit;
}

encerrar_sessao();
header('Location: /src/Views/login.php');
exit;
