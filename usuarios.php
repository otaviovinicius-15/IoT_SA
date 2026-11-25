<?php

require_once "./config/conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['perfil'] !== 'administrador') {
    header("Location: dashboard.php");
    exit;
}

$nome    = $_SESSION['usuario_nome'];
$perfil  = $_SESSION['perfil'];
$meuId   = (int) $_SESSION['usuario_id'];

$perfis = [
    'administrador' => 'Administrador',
    'supervisor'    => 'Supervisor',
];

$erro = "";

$mensagens = [
    'cadastrado' => 'Usuário cadastrado com sucesso.',
    'editado'    => 'Usuário atualizado com sucesso.',
    'status'     => 'Status do usuário alterado.',
];
$sucesso = $mensagens[$_GET['msg'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'salvar') {

        $nomeUsuario   = trim($_POST['nome'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $senha         = $_POST['senha'] ?? '';
        $perfilUsuario = $_POST['perfil'] ?? '';

        if ($id === $meuId) {
            $perfilUsuario = $perfil;
        }

        if ($nomeUsuario === '') {
            $erro = "Informe o nome do usuário.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "Informe um e-mail válido.";
        } elseif (!isset($perfis[$perfilUsuario])) {
            $erro = "Selecione um perfil válido.";
        } elseif ($id === 0 && strlen($senha) < 6) {
            $erro = "A senha deve ter pelo menos 6 caracteres.";
        } elseif ($id > 0 && $senha !== '' && strlen($senha) < 6) {
            $erro = "A nova senha deve ter pelo menos 6 caracteres.";
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, perfil = ? WHERE id = ?");
                    $stmt->execute([$nomeUsuario, $email, $perfilUsuario, $id]);

                    if ($senha !== '') {
                        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                        $stmt->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
                    }

                    if ($id === $meuId) {
                        $_SESSION['usuario_nome'] = $nomeUsuario;
                    }

                    header("Location: usuarios.php?msg=editado");
                    exit;
                }

                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nomeUsuario, $email, password_hash($senha, PASSWORD_DEFAULT), $perfilUsuario]);

                header("Location: usuarios.php?msg=cadastrado");
                exit;

            } catch (PDOException $e) {
                $erro = $e->getCode() === '23000'
                    ? "Este e-mail já está cadastrado."
                    : "Erro ao salvar o usuário.";
            }
        }
    }

    if ($acao === 'status') {
        if ($id === $meuId) {
            $erro = "Você não pode inativar o seu próprio usuário.";
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET ativo = NOT ativo WHERE id = ?");
            $stmt->execute([$id]);

            header("Location: usuarios.php?msg=status");
            exit;
        }
    }
}

$editando = null;

if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT id, nome, email, perfil FROM usuarios WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
}

$form = [
    'id'     => $editando['id'] ?? ($_POST['id'] ?? ''),
    'nome'   => $editando['nome'] ?? ($_POST['nome'] ?? ''),
    'email'  => $editando['email'] ?? ($_POST['email'] ?? ''),
    'perfil' => $editando['perfil'] ?? ($_POST['perfil'] ?? 'supervisor'),
];

$editandoProprio = (int) $form['id'] === $meuId;

$usuarios = $pdo->query("SELECT id, nome, email, perfil, ativo FROM usuarios ORDER BY ativo DESC, nome")->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Ferramentaria</title>
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
            min-width: 170px;
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

        .dica {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
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

        .inativo {
            color: #999;
        }

        .etiqueta {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }

        .etiqueta.ativo {
            background-color: #e6f6ec;
            color: #1e7e45;
        }

        .etiqueta.inativo {
            background-color: #eee;
            color: #777;
        }

        .etiqueta.voce {
            background-color: #e3edf7;
            color: #315b85;
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
        <a href="funcionarios.php">Funcionários</a>
        <a href="usuarios.php">Usuários</a>
    </nav>

    <main>
        <h2 class="titulo">Usuários do Sistema</h2>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= $sucesso ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= $erro ?></div>
        <?php endif; ?>

        <div class="caixa">
            <h3><?= $form['id'] ? 'Editar usuário' : 'Novo usuário' ?></h3>

            <form method="POST" action="usuarios.php">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= $form['id'] ?>">

                <div class="campos">
                    <div class="campo">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" required maxlength="100"
                               value="<?= htmlspecialchars($form['nome']) ?>">
                    </div>

                    <div class="campo">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" required maxlength="100"
                               value="<?= htmlspecialchars($form['email']) ?>">
                    </div>

                    <div class="campo">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" minlength="6"
                               <?= $form['id'] ? '' : 'required' ?>>
                        <?php if ($form['id']): ?>
                            <span class="dica">Deixe em branco para manter a atual.</span>
                        <?php endif; ?>
                    </div>

                    <div class="campo">
                        <label for="perfil">Perfil</label>
                        <select id="perfil" name="perfil" <?= $editandoProprio ? 'disabled' : '' ?>>
                            <?php foreach ($perfis as $valor => $rotulo): ?>
                            <option value="<?= $valor ?>" <?= $form['perfil'] === $valor ? 'selected' : '' ?>>
                                <?= $rotulo ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($editandoProprio): ?>
                            <span class="dica">Você não pode alterar o próprio perfil.</span>
                        <?php endif; ?>
                    </div>

                    <div>
                        <button type="submit" class="botao">Salvar</button>
                        <?php if ($form['id']): ?>
                            <a href="usuarios.php" class="botao cinza">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="caixa">
            <h3>Usuários cadastrados</h3>

            <table>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>

                <?php foreach ($usuarios as $u): ?>
                <tr class="<?= $u['ativo'] ? '' : 'inativo' ?>">
                    <td>
                        <?= htmlspecialchars($u['nome']) ?>
                        <?php if ($u['id'] == $meuId): ?>
                            <span class="etiqueta voce">Você</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= $perfis[$u['perfil']] ?></td>
                    <td>
                        <?php if ($u['ativo']): ?>
                            <span class="etiqueta ativo">Ativo</span>
                        <?php else: ?>
                            <span class="etiqueta inativo">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="usuarios.php?editar=<?= $u['id'] ?>" class="botao pequeno">Editar</a>

                        <?php if ($u['id'] != $meuId): ?>
                        <form method="POST" action="usuarios.php">
                            <input type="hidden" name="acao" value="status">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <?php if ($u['ativo']): ?>
                                <button type="submit" class="botao pequeno vermelho"
                                        onclick="return confirm('Inativar este usuário?')">Inativar</button>
                            <?php else: ?>
                                <button type="submit" class="botao pequeno verde">Ativar</button>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </main>
</body>
</html>
