<?php

require_once "./config/conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$nome   = $_SESSION['usuario_nome'];
$perfil = $_SESSION['perfil'];
$ehAdmin = $perfil === 'administrador';

$erro = "";

$mensagens = [
    'cadastrada' => 'Ferramenta cadastrada com sucesso.',
    'editada'    => 'Ferramenta atualizada com sucesso.',
    'status'     => 'Status da ferramenta alterado.',
];
$sucesso = $mensagens[$_GET['msg'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ehAdmin) {

    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'salvar') {

        $nomeFerramenta = trim($_POST['nome'] ?? '');
        $quantidade     = (int) ($_POST['quantidade_disponivel'] ?? 0);
        $minimo         = (int) ($_POST['estoque_minimo'] ?? 0);

        if ($nomeFerramenta === '') {
            $erro = "Informe o nome da ferramenta.";
        } elseif ($quantidade < 0 || $minimo < 0) {
            $erro = "Quantidade e estoque mínimo não podem ser negativos.";
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("UPDATE ferramentas SET nome = ?, quantidade_disponivel = ?, estoque_minimo = ? WHERE id = ?");
            $stmt->execute([$nomeFerramenta, $quantidade, $minimo, $id]);

            header("Location: ferramentas.php?msg=editada");
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO ferramentas (nome, quantidade_disponivel, estoque_minimo) VALUES (?, ?, ?)");
            $stmt->execute([$nomeFerramenta, $quantidade, $minimo]);

            header("Location: ferramentas.php?msg=cadastrada");
            exit;
        }
    }

    if ($acao === 'status') {
        $stmt = $pdo->prepare("UPDATE ferramentas SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: ferramentas.php?msg=status");
        exit;
    }
}

$editando = null;

if ($ehAdmin && isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM ferramentas WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
}

$form = [
    'id'                    => $editando['id'] ?? ($_POST['id'] ?? ''),
    'nome'                  => $editando['nome'] ?? ($_POST['nome'] ?? ''),
    'quantidade_disponivel' => $editando['quantidade_disponivel'] ?? ($_POST['quantidade_disponivel'] ?? 0),
    'estoque_minimo'        => $editando['estoque_minimo'] ?? ($_POST['estoque_minimo'] ?? 1),
];

$ferramentas = $pdo->query("SELECT * FROM ferramentas ORDER BY ativo DESC, nome")->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ferramentas - Ferramentaria</title>
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

        .campo input {
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

        .botao.vermelho {
            background-color: #c0392b;
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

        .inativa {
            color: #999;
        }

        .etiqueta {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }

        .etiqueta.ativa {
            background-color: #e6f6ec;
            color: #1e7e45;
        }

        .etiqueta.inativa {
            background-color: #eee;
            color: #777;
        }

        .etiqueta.baixo {
            background-color: #fdf0e1;
            color: #b9600f;
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
        <a href="armarios.php">Armários</a>
        <a href="ferramentas.php">Ferramentas</a>
        <a href="historico.php">Movimentações</a>
        <?php if ($ehAdmin): ?>
        <a href="funcionarios.php">Funcionários</a>
        <a href="usuarios.php">Usuários</a>
        <?php endif; ?>
    </nav>

    <main>
        <h2 class="titulo">Ferramentas</h2>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= $sucesso ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= $erro ?></div>
        <?php endif; ?>

        <?php if ($ehAdmin): ?>
        <div class="caixa">
            <h3><?= $form['id'] ? 'Editar ferramenta' : 'Nova ferramenta' ?></h3>

            <form method="POST" action="ferramentas.php">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= $form['id'] ?>">

                <div class="campos">
                    <div class="campo">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" required maxlength="100"
                               value="<?= htmlspecialchars($form['nome']) ?>">
                    </div>

                    <div class="campo">
                        <label for="quantidade">Quantidade disponível</label>
                        <input type="number" id="quantidade" name="quantidade_disponivel" min="0" required
                               value="<?= (int) $form['quantidade_disponivel'] ?>">
                    </div>

                    <div class="campo">
                        <label for="minimo">Estoque mínimo</label>
                        <input type="number" id="minimo" name="estoque_minimo" min="0" required
                               value="<?= (int) $form['estoque_minimo'] ?>">
                    </div>

                    <div>
                        <button type="submit" class="botao">Salvar</button>
                        <?php if ($form['id']): ?>
                            <a href="ferramentas.php" class="botao cinza">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="caixa">
            <h3>Ferramentas cadastradas</h3>

            <?php if (count($ferramentas) === 0): ?>
                <p>Nenhuma ferramenta cadastrada.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Nome</th>
                        <th>Disponível</th>
                        <th>Mínimo</th>
                        <th>Status</th>
                        <?php if ($ehAdmin): ?>
                        <th>Ações</th>
                        <?php endif; ?>
                    </tr>

                    <?php foreach ($ferramentas as $f): ?>
                    <tr class="<?= $f['ativo'] ? '' : 'inativa' ?>">
                        <td><?= htmlspecialchars($f['nome']) ?></td>
                        <td>
                            <?= $f['quantidade_disponivel'] ?>
                            <?php if ($f['ativo'] && $f['quantidade_disponivel'] <= $f['estoque_minimo']): ?>
                                <span class="etiqueta baixo">Estoque baixo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $f['estoque_minimo'] ?></td>
                        <td>
                            <?php if ($f['ativo']): ?>
                                <span class="etiqueta ativa">Ativa</span>
                            <?php else: ?>
                                <span class="etiqueta inativa">Inativa</span>
                            <?php endif; ?>
                        </td>

                        <?php if ($ehAdmin): ?>
                        <td>
                            <a href="ferramentas.php?editar=<?= $f['id'] ?>" class="botao pequeno">Editar</a>

                            <form method="POST" action="ferramentas.php">
                                <input type="hidden" name="acao" value="status">
                                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                <?php if ($f['ativo']): ?>
                                    <button type="submit" class="botao pequeno vermelho"
                                            onclick="return confirm('Inativar esta ferramenta?')">Inativar</button>
                                <?php else: ?>
                                    <button type="submit" class="botao pequeno verde">Ativar</button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
