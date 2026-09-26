<?php
// Navbar compartilhada por todas as telas autenticadas.
// A página que a inclui já deve ter carregado config/auth.php e chamado exigir_login().
?>
<link rel="stylesheet" href="<?= asset('/public/css/navbar.css') ?>">

<header class="navbar">
    <div>
        <a class="navbar-marca" href="/src/Views/menu.php">DeepCheck</a>
    </div>
    <div>
        <nav>
            <a href="/src/Views/menu.php">Início</a>
            <a href="/src/Views/perfil.php">Perfil</a>

            <?php if (isset($_SESSION['usuario']['nome'])): ?>
                <span class="usuarioLogado"><?= htmlspecialchars($_SESSION['usuario']['nome'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>

            <form method="post" action="/src/Controllers/logoff.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" id="logoffButtonLink">Sair</button>
            </form>
        </nav>
    </div>
</header>
