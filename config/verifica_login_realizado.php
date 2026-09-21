<?php

    if(!empty($_SESSION['logado']) && $_SESSION['logado'] == true) {
        header("Location: /src/Views/home.php");
        exit;
    };
?>