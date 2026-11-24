<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$banco = "";
$usuario = "root";
$senha = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log($e->getMessage());
    die("Erro ao conectar ao banco de dados.");

}
