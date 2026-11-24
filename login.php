<?php

require_once "./config/conexao.php";

// Se logado, redireciona
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit;
}

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? "");
    $senha = $_POST['senha'] ?? "";

    if (empty($email) || empty($senha)) {

        $erro = "Preencha todos os campos.";

    } else {

        $sql = "SELECT * FROM usuarios WHERE email = :email AND ativo = 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);

        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {

            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['perfil'] = $usuario['perfil'];

            header("Location: dashboard.php");
            exit;

        } else {
            $erro = "E-mail ou senha inválidos.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ferramentaria</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #eef1f5;

            display: flex;
            justify-content: center;
            align-items: center;

            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 400px;
            padding: 35px;

            background-color: white;
            border-radius: 10px;

            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.10);
        }

        .titulo {
            text-align: center;
            margin-bottom: 30px;
        }

        .titulo h2 {
            color: #263b55;
            margin-bottom: 8px;
        }

        .titulo p {
            color: #777;
            font-size: 14px;
            margin: 0;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #333;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;

            border: 1px solid #ccc;
            border-radius: 5px;

            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #315b85;
        }

        button {
            width: 100%;
            padding: 12px;

            background-color: #315b85;
            color: white;

            border: none;
            border-radius: 5px;

            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background-color: #244565;
        }

        .erro {
            background-color: #ffe5e5;
            color: #b30000;

            padding: 10px;
            border-radius: 5px;

            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        .rodape {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 25px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="titulo">
            <h2>Ferramentaria</h2>
            <p>Sistema de Controle de Ferramentas</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="erro">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <label for="email">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="Digite seu e-mail"
                required
            >

            <label for="senha">Senha</label>
            <input
                type="password"
                id="senha"
                name="senha"
                placeholder="Digite sua senha"
                required
            >

            <button type="submit">Entrar</button>

        </form>

        <div class="rodape">
            Sistema de Controle de Ferramentaria
        </div>

    </div>
</body>
</html>
