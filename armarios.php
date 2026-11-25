<?php

require_once "./config/conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$nome    = $_SESSION['usuario_nome'];
$perfil  = $_SESSION['perfil'];
$ehAdmin = $perfil === 'administrador';

$erro = "";

$mensagens = [
    'cadastrado' => 'Armário cadastrado com sucesso.',
    'editado'    => 'Armário atualizado com sucesso.',
    'status'     => 'Status do armário alterado.',
];
$sucesso = $mensagens[$_GET['msg'] ?? ''] ?? '';

// Armário que está sendo editado (para calcular o saldo livre das ferramentas)
$armarioAtual = (int) ($_POST['id'] ?? ($_GET['editar'] ?? 0));


// ===== FUNCIONÁRIOS E FERRAMENTAS CADASTRADOS =====

$funcionarios = $pdo->query("SELECT id, nome, setor, ativo FROM funcionarios ORDER BY nome")->fetchAll();

// livre = estoque geral - o que já está reservado em OUTROS armários
$stmt = $pdo->prepare("
    SELECT fe.id, fe.nome, fe.ativo,
           fe.quantidade_disponivel - COALESCE((
               SELECT SUM(af.quantidade)
               FROM armario_ferramentas af
               WHERE af.ferramenta_id = fe.id AND af.armario_id <> ?
           ), 0) AS livre
    FROM ferramentas fe
    ORDER BY fe.nome
");
$stmt->execute([$armarioAtual]);
$ferramentas = $stmt->fetchAll();

$funcionariosPorId = array_column($funcionarios, null, 'id');
$ferramentasPorId  = array_column($ferramentas, null, 'id');


// ===== PROCESSAR AÇÕES =====

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ehAdmin) {

    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'salvar') {

        $nomeArmario  = trim($_POST['nome'] ?? '');
        $selecionados = array_map('intval', $_POST['funcionarios'] ?? []);
        $quantidades  = array_map('intval', $_POST['quantidade'] ?? []);
        $prazos       = array_map('intval', $_POST['prazo'] ?? []);

        $itens = array_filter($quantidades, fn($q) => $q > 0);

        if ($nomeArmario === '') {
            $erro = "Informe o nome do armário.";
        }

        foreach ($selecionados as $funcionarioId) {
            if (!$erro && !isset($funcionariosPorId[$funcionarioId])) {
                $erro = "Funcionário inválido.";
            }
        }

        foreach ($itens as $ferramentaId => $quantidade) {
            $ferramenta = $ferramentasPorId[$ferramentaId] ?? null;

            if ($erro) {
                break;
            } elseif (!$ferramenta || !$ferramenta['ativo']) {
                $erro = "Ferramenta inválida.";
            } elseif ($quantidade > $ferramenta['livre']) {
                $erro = "Saldo insuficiente de " . $ferramenta['nome'] . " (livre: " . max(0, $ferramenta['livre']) . ").";
            } elseif (($prazos[$ferramentaId] ?? 0) < 1) {
                $erro = "Informe o prazo de " . $ferramenta['nome'] . ".";
            }
        }

        if (!$erro) {
            $pdo->beginTransaction();

            if ($id > 0) {
                $pdo->prepare("UPDATE armarios SET nome = ? WHERE id = ?")->execute([$nomeArmario, $id]);
            } else {
                $pdo->prepare("INSERT INTO armarios (nome) VALUES (?)")->execute([$nomeArmario]);
                $id = (int) $pdo->lastInsertId();
            }

            // Recria os vínculos (o histórico fica em movimentacoes e não é afetado)
            $pdo->prepare("DELETE FROM armario_funcionarios WHERE armario_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM armario_ferramentas WHERE armario_id = ?")->execute([$id]);

            $stmt = $pdo->prepare("INSERT INTO armario_funcionarios (armario_id, funcionario_id) VALUES (?, ?)");
            foreach ($selecionados as $funcionarioId) {
                $stmt->execute([$id, $funcionarioId]);
            }

            $stmt = $pdo->prepare("INSERT INTO armario_ferramentas (armario_id, ferramenta_id, quantidade, prazo_dias) VALUES (?, ?, ?, ?)");
            foreach ($itens as $ferramentaId => $quantidade) {
                $stmt->execute([$id, $ferramentaId, $quantidade, $prazos[$ferramentaId]]);
            }

            $pdo->commit();

            header("Location: armarios.php?msg=" . ($_POST['id'] ? 'editado' : 'cadastrado'));
            exit;
        }
    }

    if ($acao === 'status') {
        $pdo->prepare("UPDATE armarios SET ativo = NOT ativo WHERE id = ?")->execute([$id]);

        header("Location: armarios.php?msg=status");
        exit;
    }
}


// ===== DADOS DO FORMULÁRIO =====

$form = [
    'id'           => $_POST['id'] ?? '',
    'nome'         => $_POST['nome'] ?? '',
    'funcionarios' => array_map('intval', $_POST['funcionarios'] ?? []),
    'quantidade'   => $_POST['quantidade'] ?? [],
    'prazo'        => $_POST['prazo'] ?? [],
];

if ($ehAdmin && isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM armarios WHERE id = ?");
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();

    if ($editando) {
        $form['id']   = $editando['id'];
        $form['nome'] = $editando['nome'];

        $stmt = $pdo->prepare("SELECT funcionario_id FROM armario_funcionarios WHERE armario_id = ?");
        $stmt->execute([$editando['id']]);
        $form['funcionarios'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $stmt = $pdo->prepare("SELECT ferramenta_id, quantidade, prazo_dias FROM armario_ferramentas WHERE armario_id = ?");
        $stmt->execute([$editando['id']]);
        foreach ($stmt->fetchAll() as $item) {
            $form['quantidade'][$item['ferramenta_id']] = $item['quantidade'];
            $form['prazo'][$item['ferramenta_id']]      = $item['prazo_dias'];
        }
    }
}


// ===== LISTAGEM =====

$armarios = $pdo->query("SELECT * FROM armarios ORDER BY ativo DESC, nome")->fetchAll();

$autorizados = [];
foreach ($pdo->query("
    SELECT af.armario_id, f.nome
    FROM armario_funcionarios af
    JOIN funcionarios f ON f.id = af.funcionario_id
    ORDER BY f.nome
") as $linha) {
    $autorizados[$linha['armario_id']][] = $linha['nome'];
}

$produtos = [];
foreach ($pdo->query("
    SELECT af.armario_id, fe.nome, af.quantidade, af.prazo_dias
    FROM armario_ferramentas af
    JOIN ferramentas fe ON fe.id = af.ferramenta_id
    ORDER BY fe.nome
") as $linha) {
    $produtos[$linha['armario_id']][] = $linha;
}

$acessos = $pdo->query("
    SELECT ac.data_hora, ac.cartao_rfid, ac.autorizado, ac.mensagem,
           a.nome AS armario, f.nome AS funcionario
    FROM acessos_armario ac
    LEFT JOIN armarios a     ON a.id = ac.armario_id
    LEFT JOIN funcionarios f ON f.id = ac.funcionario_id
    ORDER BY ac.data_hora DESC
    LIMIT 20
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Armários - Ferramentaria</title>
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

        .caixa h4 {
            color: #555;
            margin: 20px 0 10px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            max-width: 400px;
        }

        .campo label {
            font-size: 13px;
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }

        .campo input,
        td input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        td input {
            width: 90px;
            padding: 6px;
        }

        .lista {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 25px;
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

        .acoes {
            margin-top: 20px;
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
            vertical-align: top;
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

        .etiqueta.negado {
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
        <a href="armarios.php">Armários</a>
        <a href="ferramentas.php">Ferramentas</a>
        <a href="historico.php">Movimentações</a>
        <?php if ($ehAdmin): ?>
        <a href="funcionarios.php">Funcionários</a>
        <a href="usuarios.php">Usuários</a>
        <?php endif; ?>
    </nav>

    <main>
        <h2 class="titulo">Armários</h2>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= $sucesso ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($ehAdmin): ?>
        <div class="caixa">
            <h3><?= $form['id'] ? 'Editar armário' : 'Novo armário' ?></h3>

            <form method="POST" action="armarios.php">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= $form['id'] ?>">

                <div class="campo">
                    <label for="nome">Nome / identificação</label>
                    <input type="text" id="nome" name="nome" required maxlength="100"
                           value="<?= htmlspecialchars($form['nome']) ?>">
                </div>

                <h4>Funcionários autorizados</h4>
                <div class="lista">
                    <?php foreach ($funcionarios as $f): ?>
                    <label class="<?= $f['ativo'] ? '' : 'inativa' ?>">
                        <input type="checkbox" name="funcionarios[]" value="<?= $f['id'] ?>"
                               <?= in_array((int) $f['id'], $form['funcionarios']) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($f['nome']) ?>
                        <?= $f['ativo'] ? '' : '(inativo)' ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <h4>Ferramentas no armário</h4>
                <table>
                    <tr>
                        <th>Ferramenta</th>
                        <th>Saldo livre</th>
                        <th>Quantidade</th>
                        <th>Prazo (dias)</th>
                    </tr>

                    <?php foreach ($ferramentas as $f): ?>
                    <?php
                        $quantidade = (int) ($form['quantidade'][$f['id']] ?? 0);
                        if (!$f['ativo'] || ($f['livre'] < 1 && $quantidade < 1)) continue;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($f['nome']) ?></td>
                        <td><?= max(0, $f['livre']) ?></td>
                        <td>
                            <input type="number" name="quantidade[<?= $f['id'] ?>]" min="0"
                                   max="<?= max(0, $f['livre']) ?>" value="<?= $quantidade ?>">
                        </td>
                        <td>
                            <input type="number" name="prazo[<?= $f['id'] ?>]" min="1"
                                   value="<?= (int) ($form['prazo'][$f['id']] ?? 1) ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <div class="acoes">
                    <button type="submit" class="botao">Salvar</button>
                    <?php if ($form['id']): ?>
                        <a href="armarios.php" class="botao cinza">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="caixa">
            <h3>Armários cadastrados</h3>

            <?php if (count($armarios) === 0): ?>
                <p>Nenhum armário cadastrado.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Funcionários autorizados</th>
                        <th>Ferramentas</th>
                        <th>Status</th>
                        <?php if ($ehAdmin): ?>
                        <th>Ações</th>
                        <?php endif; ?>
                    </tr>

                    <?php foreach ($armarios as $a): ?>
                    <tr class="<?= $a['ativo'] ? '' : 'inativa' ?>">
                        <td><?= $a['id'] ?></td>
                        <td><?= htmlspecialchars($a['nome']) ?></td>
                        <td>
                            <?= htmlspecialchars(implode(', ', $autorizados[$a['id']] ?? ['-'])) ?>
                        </td>
                        <td>
                            <?php foreach ($produtos[$a['id']] ?? [] as $p): ?>
                                <?= htmlspecialchars($p['nome']) ?>: <?= $p['quantidade'] ?> un. (<?= $p['prazo_dias'] ?> dia(s))<br>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php if ($a['ativo']): ?>
                                <span class="etiqueta ativa">Ativo</span>
                            <?php else: ?>
                                <span class="etiqueta inativa">Inativo</span>
                            <?php endif; ?>
                        </td>

                        <?php if ($ehAdmin): ?>
                        <td>
                            <a href="armarios.php?editar=<?= $a['id'] ?>" class="botao pequeno">Editar</a>

                            <form method="POST" action="armarios.php">
                                <input type="hidden" name="acao" value="status">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <?php if ($a['ativo']): ?>
                                    <button type="submit" class="botao pequeno vermelho"
                                            onclick="return confirm('Inativar este armário?')">Inativar</button>
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

        <div class="caixa">
            <h3>Últimas tentativas de acesso</h3>

            <?php if (count($acessos) === 0): ?>
                <p>Nenhuma tentativa registrada.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Data</th>
                        <th>Armário</th>
                        <th>Cartão</th>
                        <th>Funcionário</th>
                        <th>Resultado</th>
                    </tr>

                    <?php foreach ($acessos as $ac): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($ac['data_hora'])) ?></td>
                        <td><?= htmlspecialchars($ac['armario'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($ac['cartao_rfid']) ?></td>
                        <td><?= htmlspecialchars($ac['funcionario'] ?? '-') ?></td>
                        <td>
                            <span class="etiqueta <?= $ac['autorizado'] ? 'ativa' : 'negado' ?>">
                                <?= htmlspecialchars($ac['mensagem']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
