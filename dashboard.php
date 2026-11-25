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

// ===== Números dos cards =====


$totalFerramentas = $pdo->query("SELECT COUNT(*) FROM ferramentas WHERE ativo = 1")->fetchColumn();


$totalDisponivel = $pdo->query("SELECT COALESCE(SUM(quantidade_disponivel), 0) FROM ferramentas WHERE ativo = 1")->fetchColumn();


$totalEmprestado = $pdo->query("SELECT COUNT(*) FROM movimentacoes WHERE data_devolucao IS NULL")->fetchColumn();


$estoqueBaixo = $pdo->query("
    SELECT nome, quantidade_disponivel, estoque_minimo
    FROM ferramentas
    WHERE ativo = 1 AND quantidade_disponivel <= estoque_minimo
    ORDER BY nome
")->fetchAll();


$atrasadas = $pdo->query("
    SELECT f.nome AS funcionario, f.setor, fe.nome AS ferramenta,
           m.data_retirada, m.prazo_devolucao
    FROM movimentacoes m
    JOIN funcionarios f  ON f.id  = m.funcionario_id
    JOIN ferramentas  fe ON fe.id = m.ferramenta_id
    WHERE m.data_devolucao IS NULL AND m.prazo_devolucao < NOW()
    ORDER BY m.prazo_devolucao
")->fetchAll();

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

        .alertas {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .alerta {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            min-width: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 5px solid #e67e22;
            overflow-x: auto;
        }

        .alerta.vermelho {
            border-left-color: #c0392b;
        }

        .alerta h3 {
             margin-top: 0;
            color: #555;
            font-size: 15px;
        }

        .alerta p {
            margin-bottom: 0;
        }

        .ok {
            color: #27ae60;
        }

        @media (max-width: 600px) {
            .alertas {
                grid-template-columns: 1fr;
            }
        }

        .alerta h3 {
            margin-top: 0;
        }

        .ok {
            color: #27ae60;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #e3e6ea;
        }

        th {
            background-color: #f5f7fa;
            color: #555;
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
            <?= $perfil === 'administrador' ? 'ADMINISTRADOR' : 'SUPERVISOR' ?>

            <br>

            <a href="sair.php">Sair do sistema</a>
        </div>

    </header>

    <nav>
        <a href="dashboard.php">Início</a>
        <a href="ferramentas.php">Ferramentas</a>
        <a href="historico.php">Movimentações</a>
        <?php if ($_SESSION['perfil'] === 'administrador'): ?>
        <a href="funcionarios.php">Funcionários</a>
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

        
        <div class="alertas">

    <!-- ESTOQUE ABAIXO DO MÍNIMO -->
    <div class="alerta">

        <h3>⚠ Estoque abaixo do mínimo</h3>

        <?php if (count($estoqueBaixo) === 0): ?>

            <p class="ok">
                Nenhuma ferramenta abaixo do estoque mínimo.
            </p>

        <?php else: ?>

            <table>
                <tr>
                    <th>Ferramenta</th>
                    <th>Disponível</th>
                    <th>Mínimo</th>
                </tr>

                <?php foreach ($estoqueBaixo as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['nome']) ?></td>
                    <td><?= $item['quantidade_disponivel'] ?></td>
                    <td><?= $item['estoque_minimo'] ?></td>
                </tr>
                <?php endforeach; ?>

            </table>

        <?php endif; ?>

    </div>


    <!-- DEVOLUÇÕES ATRASADAS -->
    <div class="alerta vermelho">

        <h3>⏰ Devoluções atrasadas</h3>

        <?php if (count($atrasadas) === 0): ?>

            <p class="ok">
                Nenhuma ferramenta em atraso.
            </p>

        <?php else: ?>

            <table>
                <tr>
                    <th>Funcionário</th>
                    <th>Setor</th>
                    <th>Ferramenta</th>
                    <th>Retirada</th>
                    <th>Prazo</th>
                </tr>

                <?php foreach ($atrasadas as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['funcionario']) ?></td>
                    <td><?= htmlspecialchars($item['setor'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($item['ferramenta']) ?></td>
                    <td>
                        <?= date('d/m/Y H:i', strtotime($item['data_retirada'])) ?>
                    </td>
                    <td>
                        <?= date('d/m/Y H:i', strtotime($item['prazo_devolucao'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>

            </table>

        <?php endif; ?>

    </div>



        <div class="atalhos">

            <h3>Acesso rápido</h3>

            <a href="ferramentas.php">Consultar ferramentas</a>

            <a href="historico.php">Histórico de movimentações</a>

            <?php if ($perfil === 'administrador'): ?>
            <a href="usuarios.php">Gerenciar usuários</a>
            <?php endif; ?>

        </div>

    </main>
</body>
</html>
