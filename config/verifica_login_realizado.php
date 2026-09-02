<?php

    if(!empty($_SESSION['logado']) && $_SESSION['logado'] == true) {
        header("Location: /DeepCheck/home");
        exit;
    };
?>