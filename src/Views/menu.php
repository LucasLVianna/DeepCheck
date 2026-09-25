<?php
    require_once __DIR__ . '/../../config/auth.php';
    exigir_login();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeepCheck</title>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <main id="projetos">
        <h1>Projetos</h1>
    </main>
</body>
</html>
