<?php

session_start();
session_unset();

//Destroi a sessão
session_destroy();

//Redireciona para o login
header("Location: login.php");
exit;

