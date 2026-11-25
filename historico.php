<?php

require_once "./config/conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$nome    = $_SESSION['usuario_nome'];
$perfil  = $_SESSION['perfil'];
$ehAdmin = $perfil === 'administrador';

$filtro = [
    'funcionario_id' => (int) ($_GET['funcionario_id'] ?? 0),
    'ferramenta_id'  => (int) ($_GET['ferramenta_id'] ?? 0),
    'data_inicio'    => $_GET['data_inicio'] ?? '',
    'data_fim'       => $_GET['data_fim'] ?? '',
    'situacao'       => $_GET['situacao'] ?? '',
];

$condicoes = [];
$parametros = [];

if ($filtro['funcionario_id']) {
    $condicoes[] = "m.funcionario_id = ?";
    $parametros[] = $filtro['funcionario_id'];
}

if ($filtro['ferramenta_id']) {
    $condicoes[] = "m.ferramenta_id = ?";
    $parametros[] = $filtro['ferramenta_id'];
}

if ($filtro['data_inicio']) {
    $condicoes[] = "DATE(m.data_retirada) >= ?";
    $parametros[] = $filtro['data_inicio'];
}

if ($filtro['data_fim']) {
    $condicoes[] = "DATE(m.data_retirada) <= ?";
    $parametros[] = $filtro['data_fim'];
}

$situacoes = [
    'aberto'    => "m.data_devolucao IS NULL AND m.prazo_devolucao >= NOW()",
    'atrasada'  => "m.data_devolucao IS NULL AND m.prazo_devolucao < NOW()",
    'devolvida' => "m.data_devolucao IS NOT NULL",
];

if (isset($situacoes[$filtro['situacao']])) {
    $condicoes[] = $situacoes[$filtro['situacao']];
}

$where = $condicoes ? "WHERE " . implode(" AND ", $condicoes) : "";

$stmt = $pdo->prepare("
    SELECT f.nome AS funcionario, f.setor, fe.nome AS ferramenta,
           m.data_retirada, m.prazo_devolucao, m.data_devolucao
    FROM movimentacoes m
    JOIN funcionarios f  ON f.id  = m.funcionario_id
    JOIN ferramentas  fe ON fe.id = m.ferramenta_id
    $where
    ORDER BY m.data_retirada DESC
");
$stmt->execute($parametros);
$movimentacoes = $stmt->fetchAll();

$funcionarios = $pdo->query("SELECT id, nome FROM funcionarios ORDER BY nome")->fetchAll();
$ferramentas  = $pdo->query("SELECT id, nome FROM ferramentas ORDER BY nome")->fetchAll();

function formatarData($data)
{
    return $data ? date('d/m/Y H:i', strtotime($data)) : '-';
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico - Ferramentaria</title>
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

        h2.titulo {
            color: #263b55;
        }

        .caixa {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }

        .caixa h3 {
            margin-top: 0;
        }

        .campos {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .campo {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 150px;
        }

        .campo label {
            font-size: 13px;
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }

        .campo input,
        .campo select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        .botao {
            display: inline-block;
            background-color: #315b85;
            color: white;
            border: none;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }

        .botao:hover {
            background-color: #244565;
        }

        .botao.cinza {
            background-color: #888;
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

        .total {
            color: #666;
            font-size: 14px;
        }

        .etiqueta {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }

        .etiqueta.devolvida {
            background-color: #e6f6ec;
            color: #1e7e45;
        }

        .etiqueta.aberto {
            background-color: #e3edf7;
            color: #315b85;
        }

        .etiqueta.atrasada {
            background-color: #fdecea;
            color: #a93226;
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
            Perfil: <?= strtoupper($perfil) ?><br>
            <a href="sair.php">Sair do sistema</a>
        </div>
    </header>

    <nav>
        <a href="dashboard.php">Início</a>
        <a href="retirada.php">Retirada</a>
        <a href="ferramentas.php">Ferramentas</a>
        <a href="historico.php">Movimentações</a>
        <?php if ($ehAdmin): ?>
        <a href="funcionarios.php">Funcionários</a>
        <a href="usuarios.php">Usuários</a>
        <?php endif; ?>
    </nav>

    <main>
        <h2 class="titulo">Histórico de Movimentações</h2>

        <div class="caixa">
            <h3>Filtros</h3>

            <form method="GET" action="historico.php">
                <div class="campos">
                    <div class="campo">
                        <label for="funcionario">Funcionário</label>
                        <select id="funcionario" name="funcionario_id">
                            <option value="">Todos</option>
                            <?php foreach ($funcionarios as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $filtro['funcionario_id'] == $f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="ferramenta">Ferramenta</label>
                        <select id="ferramenta" name="ferramenta_id">
                            <option value="">Todas</option>
                            <?php foreach ($ferramentas as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $filtro['ferramenta_id'] == $f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="inicio">Retirada de</label>
                        <input type="date" id="inicio" name="data_inicio" value="<?= htmlspecialchars($filtro['data_inicio']) ?>">
                    </div>

                    <div class="campo">
                        <label for="fim">Até</label>
                        <input type="date" id="fim" name="data_fim" value="<?= htmlspecialchars($filtro['data_fim']) ?>">
                    </div>

                    <div class="campo">
                        <label for="situacao">Situação</label>
                        <select id="situacao" name="situacao">
                            <option value="">Todas</option>
                            <option value="aberto"    <?= $filtro['situacao'] === 'aberto' ? 'selected' : '' ?>>Em aberto</option>
                            <option value="atrasada"  <?= $filtro['situacao'] === 'atrasada' ? 'selected' : '' ?>>Atrasada</option>
                            <option value="devolvida" <?= $filtro['situacao'] === 'devolvida' ? 'selected' : '' ?>>Devolvida</option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="botao">Filtrar</button>
                        <a href="historico.php" class="botao cinza">Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="caixa">
            <h3>Movimentações</h3>
            <p class="total"><?= count($movimentacoes) ?> registro(s) encontrado(s).</p>

            <?php if (count($movimentacoes) > 0): ?>
                <table>
                    <tr>
                        <th>Funcionário</th>
                        <th>Setor</th>
                        <th>Ferramenta</th>
                        <th>Retirada</th>
                        <th>Prazo</th>
                        <th>Devolução</th>
                        <th>Situação</th>
                    </tr>

                    <?php foreach ($movimentacoes as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['funcionario']) ?></td>
                        <td><?= htmlspecialchars($m['setor'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($m['ferramenta']) ?></td>
                        <td><?= formatarData($m['data_retirada']) ?></td>
                        <td><?= formatarData($m['prazo_devolucao']) ?></td>
                        <td><?= formatarData($m['data_devolucao']) ?></td>
                        <td>
                            <?php if ($m['data_devolucao']): ?>
                                <span class="etiqueta devolvida">Devolvida</span>
                            <?php elseif (strtotime($m['prazo_devolucao']) < time()): ?>
                                <span class="etiqueta atrasada">Atrasada</span>
                            <?php else: ?>
                                <span class="etiqueta aberto">Em aberto</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
