<?php
    include_once(__DIR__ . '/../../config/valida_sessao.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeepCheck</title>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <div class="brand">
                <span class="brand-mark">DC</span>
                <span class="brand-name">DeepCheck</span>
            </div>
            <nav class="nav">
                <a href="#projetos">Proejtos</a>
                
            </nav>
            <a href="/src/Views/perfil.php" class="btn btn-primary nav-cta">Perfil</a>
            <button id="logoff">Sair</button>
        </div>
    </header>
</body>
<script src="/DeepCheck/public/js/menu.js"></script>
</html>