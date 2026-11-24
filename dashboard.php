<?php

require_once "./config/conexao.php";

// Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Dados do usuário
$nome = $_SESSION['usuario_nome'];
$perfil = $_SESSION['perfil'];

// Consulta quantidade de ferramentas
$sql = "SELECT COUNT(*) AS total FROM ferramentas";
$stmt = $pdo->query($sql);
$totalFerramentas = $stmt->fetch()['total'];

// Consulta quantidade total disponível
$sql = "SELECT COALESCE(SUM(quantidade_disponivel), 0) AS total
        FROM ferramentas";
$stmt = $pdo->query($sql);
$totalDisponivel = $stmt->fetch()['total'];

// Consulta quantidade emprestada
$sql = "SELECT COALESCE(SUM(quantidade) - 
        COALESCE((SELECT SUM(quantidade) FROM devolucoes), 0), 0) AS total
        FROM emprestimos";
$stmt = $pdo->query($sql);
$totalEmprestado = $stmt->fetch()['total'];

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ferramentaria</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #eef1f5;
        }

        header {
            background-color: #263b55;
            color: white;
            padding: 20px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h2 {
            margin: 0;
        }

        .usuario {
            text-align: right;
            font-size: 14px;
        }

        .usuario a {
            color: white;
            display: inline-block;
            margin-top: 8px;
        }

        nav {
            background-color: #315b85;
            padding: 14px 30px;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin-right: 25px;
            font-weight: bold;
        }

        nav a:hover {
            text-decoration: underline;
        }

        main {
            padding: 30px;
        }

        .boas-vindas {
            margin-bottom: 25px;
        }

        .boas-vindas h2 {
            color: #263b55;
        }

        .cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .card {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            flex: 1;
            min-width: 200px;

            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .card h3 {
            color: #666;
            font-size: 15px;
        }

        .card p {
            color: #315b85;
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 0;
        }

        .atalhos {
            margin-top: 30px;
            background-color: white;
            padding: 25px;
            border-radius: 8px;
        }

        .atalhos a {
            display: inline-block;
            background-color: #315b85;
            color: white;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 5px;
            margin: 8px 8px 0 0;
        }

        .atalhos a:hover {
            background-color: #244565;
        }

        @media (max-width: 600px) {
            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .usuario {
                text-align: left;
            }

            nav a {
                display: inline-block;
                margin: 5px 12px 5px 0;
            }

            main {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <header>

        <h2>Ferramentaria</h2>

        <div class="usuario">
            Olá, <?= htmlspecialchars($nome) ?><br>

            Perfil:
            <?= $perfil === 'SUPERVISOR' ? 'Supervisor de Manutenção' : 'Técnico de Ferramentaria' ?>

            <br>

            <a href="sair.php">Sair do sistema</a>
        </div>

    </header>

    <nav>
        <a href="dashboard.php">Início</a>
        <a href="ferramentas.php">Ferramentas</a>
        <a href="movimentacoes.php">Movimentações</a>
        <?php if ($_SESSION['perfil'] === 'SUPERVISOR'): ?>
        <a href="usuarios.php">Usuários</a>
        <?php endif; ?>
    </nav>

    <main>

        <div class="boas-vindas">
            <h2>Painel inicial</h2>
            <p>Bem-vindo ao Sistema de Controle de Ferramentaria.</p>
        </div>

        <div class="cards">

            <div class="card">
                <h3>Ferramentas cadastradas</h3>
                <p><?= $totalFerramentas ?></p>
            </div>

            <div class="card">
                <h3>Ferramentas disponíveis</h3>
                <p><?= $totalDisponivel ?></p>
            </div>

            <div class="card">
                <h3>Ferramentas emprestadas</h3>
                <p><?= $totalEmprestado ?></p>
            </div>

        </div>

        <div class="atalhos">

            <h3>Acesso rápido</h3>

            <a href="ferramentas.php">Consultar ferramentas</a>

            <a href="movimentacoes.php">Registrar movimentação</a>

            <a href="usuarios.php">Gerenciar usuários</a>

        </div>

    </main>
</body>
</html>
