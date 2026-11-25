<?php

require_once "./config/conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$nome    = $_SESSION['usuario_nome'];
$perfil  = $_SESSION['perfil'];
$ehAdmin = $perfil === 'administrador';

$erro   = "";
$cartao = trim($_POST['cartao'] ?? $_GET['cartao'] ?? '');

$mensagens = [
    'retirada'  => 'Retirada registrada com sucesso.',
    'devolucao' => 'Devolução registrada com sucesso.',
];
$sucesso = $mensagens[$_GET['msg'] ?? ''] ?? '';

$funcionario = null;

if ($cartao !== '') {
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE cartao_rfid = ?");
    $stmt->execute([$cartao]);
    $funcionario = $stmt->fetch();

    if (!$funcionario) {
        $erro = "Cartão não cadastrado.";
    } elseif (!$funcionario['ativo']) {
        $erro = "Funcionário inativo. Retirada e devolução não autorizadas.";
        $funcionario = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $funcionario) {

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'retirar') {

        $ferramentaId = (int) ($_POST['ferramenta_id'] ?? 0);
        $prazo        = $_POST['prazo_devolucao'] ?? '';

        if ($ferramentaId === 0) {
            $erro = "Selecione uma ferramenta.";
        } elseif (strtotime($prazo) === false || strtotime($prazo) <= time()) {
            $erro = "O prazo de devolução deve ser uma data futura.";
        } else {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE ferramentas
                SET quantidade_disponivel = quantidade_disponivel - 1
                WHERE id = ? AND ativo = 1 AND quantidade_disponivel > 0
            ");
            $stmt->execute([$ferramentaId]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                $erro = "Não há unidades disponíveis dessa ferramenta.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO movimentacoes (funcionario_id, ferramenta_id, data_retirada, prazo_devolucao)
                    VALUES (?, ?, NOW(), ?)
                ");
                $stmt->execute([$funcionario['id'], $ferramentaId, date('Y-m-d H:i:s', strtotime($prazo))]);
                $pdo->commit();

                header("Location: movimentacoes.php?cartao=" . urlencode($cartao) . "&msg=retirada");
                exit;
            }
        }
    }

    if ($acao === 'devolver') {

        $movimentacaoId = (int) ($_POST['movimentacao_id'] ?? 0);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT ferramenta_id FROM movimentacoes
            WHERE id = ? AND funcionario_id = ? AND data_devolucao IS NULL
            FOR UPDATE
        ");
        $stmt->execute([$movimentacaoId, $funcionario['id']]);
        $ferramentaId = $stmt->fetchColumn();

        if (!$ferramentaId) {
            $pdo->rollBack();
            $erro = "Retirada não encontrada ou já devolvida.";
        } else {
            $pdo->prepare("UPDATE movimentacoes SET data_devolucao = NOW() WHERE id = ?")
                ->execute([$movimentacaoId]);

            $pdo->prepare("UPDATE ferramentas SET quantidade_disponivel = quantidade_disponivel + 1 WHERE id = ?")
                ->execute([$ferramentaId]);

            $pdo->commit();

            header("Location: movimentacoes.php?cartao=" . urlencode($cartao) . "&msg=devolucao");
            exit;
        }
    }
}

$ferramentas = [];
$emAberto    = [];

if ($funcionario) {
    $ferramentas = $pdo->query("
        SELECT id, nome, quantidade_disponivel
        FROM ferramentas
        WHERE ativo = 1 AND quantidade_disponivel > 0
        ORDER BY nome
    ")->fetchAll();

    $stmt = $pdo->prepare("
        SELECT m.id, f.nome AS ferramenta, m.data_retirada, m.prazo_devolucao
        FROM movimentacoes m
        JOIN ferramentas f ON f.id = m.ferramenta_id
        WHERE m.funcionario_id = ? AND m.data_devolucao IS NULL
        ORDER BY m.data_retirada
    ");
    $stmt->execute([$funcionario['id']]);
    $emAberto = $stmt->fetchAll();
}

$prazoPadrao = date('Y-m-d\TH:i', strtotime('+1 day'));

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimentações - Ferramentaria</title>
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
            min-width: 180px;
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

        .botao.verde {
            background-color: #27ae60;
        }

        .botao.pequeno {
            padding: 6px 10px;
            font-size: 13px;
        }

        .mensagem {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .mensagem.sucesso {
            background-color: #e6f6ec;
            color: #1e7e45;
        }

        .mensagem.erro {
            background-color: #fdecea;
            color: #a93226;
        }

        .funcionario {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .funcionario p {
            margin: 4px 0;
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

        td form {
            display: inline;
        }

        .etiqueta {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }

        .etiqueta.prazo {
            background-color: #e6f6ec;
            color: #1e7e45;
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
            Perfil: <?= $ehAdmin ? 'ADMINISTRADOR' : 'OPERADOR' ?><br>
            <a href="sair.php">Sair do sistema</a>
        </div>
    </header>

    <nav>
        <a href="dashboard.php">Início</a>
        <a href="ferramentas.php">Ferramentas</a>
        <a href="movimentacoes.php">Movimentações</a>
        <?php if ($ehAdmin): ?>
        <a href="usuarios.php">Usuários</a>
        <?php endif; ?>
    </nav>

    <main>
        <h2 class="titulo">Retirada e Devolução</h2>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= $sucesso ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= $erro ?></div>
        <?php endif; ?>

        <?php if (!$funcionario): ?>

        <div class="caixa">
            <h3>Identificar funcionário</h3>

            <form method="GET" action="movimentacoes.php">
                <div class="campos">
                    <div class="campo">
                        <label for="cartao">Cartão RFID</label>
                        <input type="text" id="cartao" name="cartao" required autofocus autocomplete="off"
                               placeholder="Aproxime o cartão ou digite o código">
                    </div>

                    <div>
                        <button type="submit" class="botao">Identificar</button>
                    </div>
                </div>
            </form>
        </div>

        <?php else: ?>

        <div class="caixa funcionario">
            <div>
                <p><strong><?= htmlspecialchars($funcionario['nome']) ?></strong></p>
                <p>Setor: <?= htmlspecialchars($funcionario['setor'] ?? '-') ?></p>
                <p>Cartão: <?= htmlspecialchars($funcionario['cartao_rfid']) ?></p>
            </div>

            <a href="movimentacoes.php" class="botao cinza">Trocar funcionário</a>
        </div>

        <div class="caixa">
            <h3>Retirada</h3>

            <?php if (count($ferramentas) === 0): ?>
                <p>Nenhuma ferramenta disponível no estoque.</p>
            <?php else: ?>
                <form method="POST" action="movimentacoes.php">
                    <input type="hidden" name="acao" value="retirar">
                    <input type="hidden" name="cartao" value="<?= htmlspecialchars($cartao) ?>">

                    <div class="campos">
                        <div class="campo">
                            <label for="ferramenta">Ferramenta</label>
                            <select id="ferramenta" name="ferramenta_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($ferramentas as $f): ?>
                                <option value="<?= $f['id'] ?>">
                                    <?= htmlspecialchars($f['nome']) ?> (<?= $f['quantidade_disponivel'] ?> disponível)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="campo">
                            <label for="prazo">Prazo de devolução</label>
                            <input type="datetime-local" id="prazo" name="prazo_devolucao" required
                                   value="<?= $prazoPadrao ?>">
                        </div>

                        <div>
                            <button type="submit" class="botao">Registrar retirada</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="caixa">
            <h3>Devolução</h3>

            <?php if (count($emAberto) === 0): ?>
                <p>Este funcionário não possui ferramentas a devolver.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Ferramenta</th>
                        <th>Retirada</th>
                        <th>Prazo</th>
                        <th>Situação</th>
                        <th>Ação</th>
                    </tr>

                    <?php foreach ($emAberto as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['ferramenta']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($m['data_retirada'])) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($m['prazo_devolucao'])) ?></td>
                        <td>
                            <?php if (strtotime($m['prazo_devolucao']) < time()): ?>
                                <span class="etiqueta atrasada">Atrasada</span>
                            <?php else: ?>
                                <span class="etiqueta prazo">No prazo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="movimentacoes.php">
                                <input type="hidden" name="acao" value="devolver">
                                <input type="hidden" name="cartao" value="<?= htmlspecialchars($cartao) ?>">
                                <input type="hidden" name="movimentacao_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="botao pequeno verde">Devolver</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </main>
</body>
</html>
