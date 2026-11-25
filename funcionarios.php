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
    'cadastrado' => 'Funcionário cadastrado com sucesso.',
    'editado'    => 'Funcionário atualizado com sucesso.',
    'status'     => 'Status do funcionário alterado.'
];

$sucesso = $mensagens[$_GET['msg'] ?? ''] ?? '';


// ===== PROCESSAR AÇÕES =====

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ehAdmin) {

    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    // CADASTRAR / EDITAR
    if ($acao === 'salvar') {

        $nomeFuncionario = trim($_POST['nome'] ?? '');
        $setor           = trim($_POST['setor'] ?? '');
        $cartao          = trim($_POST['cartao_rfid'] ?? '');

        if ($nomeFuncionario === '') {
            $erro = "Informe o nome do funcionário.";

        } elseif ($cartao === '') {
            $erro = "Informe o cartão RFID.";

        } elseif ($id > 0) {

            try {

                $stmt = $pdo->prepare("
                    UPDATE funcionarios
                    SET nome = ?,
                        setor = ?,
                        cartao_rfid = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $nomeFuncionario,
                    $setor !== '' ? $setor : null,
                    $cartao,
                    $id
                ]);

                header("Location: funcionarios.php?msg=editado");
                exit;

            } catch (PDOException $e) {

                if ($e->getCode() === '23000') {
                    $erro = "Este cartão RFID já está cadastrado para outro funcionário.";
                } else {
                    $erro = "Erro ao atualizar funcionário.";
                }
            }

        } else {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO funcionarios
                    (nome, setor, cartao_rfid)
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $nomeFuncionario,
                    $setor !== '' ? $setor : null,
                    $cartao
                ]);

                header("Location: funcionarios.php?msg=cadastrado");
                exit;

            } catch (PDOException $e) {

                if ($e->getCode() === '23000') {
                    $erro = "Este cartão RFID já está cadastrado para outro funcionário.";
                } else {
                    $erro = "Erro ao cadastrar funcionário.";
                }
            }
        }
    }

    // ALTERAR STATUS
    if ($acao === 'status') {

        $stmt = $pdo->prepare("
            UPDATE funcionarios
            SET ativo = NOT ativo
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        header("Location: funcionarios.php?msg=status");
        exit;
    }
}


// ===== BUSCAR FUNCIONÁRIO PARA EDIÇÃO =====

$editando = null;

if ($ehAdmin && isset($_GET['editar'])) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM funcionarios
        WHERE id = ?
    ");

    $stmt->execute([(int) $_GET['editar']]);

    $editando = $stmt->fetch();
}


// ===== DADOS DO FORMULÁRIO =====

$form = [
    'id'          => $editando['id'] ?? ($_POST['id'] ?? ''),
    'nome'        => $editando['nome'] ?? ($_POST['nome'] ?? ''),
    'setor'       => $editando['setor'] ?? ($_POST['setor'] ?? ''),
    'cartao_rfid' => $editando['cartao_rfid'] ?? ($_POST['cartao_rfid'] ?? '')
];


// ===== LISTAR FUNCIONÁRIOS =====

$funcionarios = $pdo->query("
    SELECT *
    FROM funcionarios
    ORDER BY ativo DESC, nome
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Funcionários - Ferramentaria</title>

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
        <a href="armarios.php">Armários</a>
        <a href="ferramentas.php">Ferramentas</a>
        <a href="historico.php">Movimentações</a>

        <?php if ($ehAdmin): ?>
            <a href="funcionarios.php">Funcionários</a>
            <a href="usuarios.php">Usuários</a>
        <?php endif; ?>

    </nav>


    <main>

        <h2 class="titulo">Funcionários</h2>


        <?php if ($sucesso): ?>

            <div class="mensagem sucesso">
                <?= htmlspecialchars($sucesso) ?>
            </div>

        <?php endif; ?>


        <?php if ($erro): ?>

            <div class="mensagem erro">
                <?= htmlspecialchars($erro) ?>
            </div>

        <?php endif; ?>


        <?php if ($ehAdmin): ?>

        <!-- CADASTRO / EDIÇÃO -->

        <div class="caixa">

            <h3>
                <?= $form['id'] ? 'Editar funcionário' : 'Novo funcionário' ?>
            </h3>

            <form method="POST" action="funcionarios.php">

                <input type="hidden" name="acao" value="salvar">

                <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

                <div class="campos">

                    <div class="campo">

                        <label for="nome">Nome</label>

                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            maxlength="100"
                            required
                            value="<?= htmlspecialchars($form['nome']) ?>"
                            placeholder="Nome do funcionário"
                        >

                    </div>


                    <div class="campo">

                        <label for="setor">Setor</label>

                        <input
                            type="text"
                            id="setor"
                            name="setor"
                            maxlength="60"
                            value="<?= htmlspecialchars($form['setor']) ?>"
                            placeholder="Setor do funcionário"
                        >

                    </div>


                    <div class="campo">

                        <label for="cartao_rfid">Cartão RFID (UID)</label>

                        <input
                            type="text"
                            id="cartao_rfid"
                            name="cartao_rfid"
                            maxlength="50"
                            required
                            value="<?= htmlspecialchars($form['cartao_rfid']) ?>"
                            placeholder="UID do cartão"
                        >

                    </div>


                    <div>

                        <button type="submit" class="botao">
                            Salvar
                        </button>

                        <?php if ($form['id']): ?>

                            <a href="funcionarios.php" class="botao cinza">
                                Cancelar
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            </form>

        </div>

        <?php endif; ?>


        <!-- LISTAGEM -->

        <div class="caixa">

            <h3>Funcionários cadastrados</h3>


            <?php if (count($funcionarios) === 0): ?>

                <p>Nenhum funcionário cadastrado.</p>

            <?php else: ?>

                <table>

                    <tr>

                        <th>Nome</th>
                        <th>Setor</th>
                        <th>Cartão RFID</th>
                        <th>Status</th>

                        <?php if ($ehAdmin): ?>
                            <th>Ações</th>
                        <?php endif; ?>

                    </tr>


                    <?php foreach ($funcionarios as $f): ?>

                    <tr class="<?= $f['ativo'] ? '' : 'inativa' ?>">

                        <td>
                            <?= htmlspecialchars($f['nome']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($f['setor'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($f['cartao_rfid']) ?>
                        </td>

                        <td>

                            <?php if ($f['ativo']): ?>

                                <span class="etiqueta ativa">
                                    Ativo
                                </span>

                            <?php else: ?>

                                <span class="etiqueta inativa">
                                    Inativo
                                </span>

                            <?php endif; ?>

                        </td>


                        <?php if ($ehAdmin): ?>

                        <td>

                            <a
                                href="funcionarios.php?editar=<?= $f['id'] ?>"
                                class="botao pequeno"
                            >
                                Editar
                            </a>


                            <form method="POST" action="funcionarios.php">

                                <input type="hidden" name="acao" value="status">

                                <input type="hidden" name="id" value="<?= $f['id'] ?>">

                                <?php if ($f['ativo']): ?>

                                    <button
                                        type="submit"
                                        class="botao pequeno vermelho"
                                        onclick="return confirm('Inativar este funcionário?')"
                                    >
                                        Inativar
                                    </button>

                                <?php else: ?>

                                    <button
                                        type="submit"
                                        class="botao pequeno verde"
                                        onclick="return confirm('Reativar este funcionário?')"
                                    >
                                        Ativar
                                    </button>

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
