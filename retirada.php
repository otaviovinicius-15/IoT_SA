<?php

require_once "./config/conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$nome    = $_SESSION['usuario_nome'];
$perfil  = $_SESSION['perfil'];
$ehAdmin = $perfil === 'administrador';

$erro    = "";
$sucesso = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo       = strtoupper(trim($_POST['codigo'] ?? ''));
    $ferramentaId = (int) ($_POST['ferramenta_id'] ?? 0);
    $dias         = max(1, (int) ($_POST['dias'] ?? 1));

    $stmt = $pdo->prepare("SELECT id, nome FROM funcionarios WHERE cartao_rfid = ? AND ativo = 1");
    $stmt->execute([$codigo]);
    $funcionario = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT id, nome, quantidade_disponivel FROM ferramentas WHERE id = ? AND ativo = 1");
    $stmt->execute([$ferramentaId]);
    $ferramenta = $stmt->fetch();

    if (!$funcionario) {
        $erro = "Cartão não autorizado.";
    } elseif (!$ferramenta) {
        $erro = "Selecione uma ferramenta.";
    } elseif ($ferramenta['quantidade_disponivel'] < 1) {
        $erro = "Não há unidades disponíveis de " . $ferramenta['nome'] . ".";
    } else {
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO movimentacoes (funcionario_id, ferramenta_id, prazo_devolucao)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))
        ")->execute([$funcionario['id'], $ferramenta['id'], $dias]);

        $pdo->prepare("UPDATE ferramentas SET quantidade_disponivel = quantidade_disponivel - 1 WHERE id = ?")
            ->execute([$ferramenta['id']]);

        $pdo->commit();

        $sucesso = "Retirada registrada: " . $ferramenta['nome'] . " para " . $funcionario['nome'] . ".";
    }
}

$ferramentas = $pdo->query("
    SELECT id, nome, quantidade_disponivel
    FROM ferramentas
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retirada - Ferramentaria</title>
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
            max-width: 600px;
        }

        h2.titulo {
            color: #263b55;
        }

        .caixa {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .campo {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
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
            background-color: #315b85;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }

        .botao:hover {
            background-color: #244565;
        }

        .aviso {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .aviso.ok {
            background-color: #e6f6ec;
            color: #1e7e45;
        }

        .aviso.erro {
            background-color: #fdecea;
            color: #a93226;
        }

        #cartao {
            font-size: 13px;
            margin-top: 6px;
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
        <h2 class="titulo">Retirada de Ferramenta</h2>

        <div class="caixa">
            <?php if ($sucesso): ?>
                <div class="aviso ok"><?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="aviso erro"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <form method="POST" action="retirada.php">
                <div class="campo">
                    <label for="codigo">Código do cartão RFID</label>
                    <input type="text" id="codigo" name="codigo" placeholder="Ex.: 1001" required autofocus>
                    <span id="cartao"></span>
                </div>

                <div class="campo">
                    <label for="ferramenta">Ferramenta</label>
                    <select id="ferramenta" name="ferramenta_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($ferramentas as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $f['quantidade_disponivel'] < 1 ? 'disabled' : '' ?>>
                            <?= htmlspecialchars($f['nome']) ?> (<?= $f['quantidade_disponivel'] ?> disponível)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="dias">Prazo de devolução (dias)</label>
                    <input type="number" id="dias" name="dias" value="1" min="1">
                </div>

                <button type="submit" class="botao">Registrar retirada</button>
            </form>
        </div>
    </main>

    <script>
        const campoCodigo = document.getElementById('codigo');
        const resultado   = document.getElementById('cartao');

        campoCodigo.addEventListener('change', async () => {
            const resposta = await fetch('api/rfid.php?codigo=' + encodeURIComponent(campoCodigo.value));
            const dados    = await resposta.json();

            if (dados.autorizado) {
                resultado.textContent = '✔ ' + dados.nome + ' (' + (dados.setor ?? '-') + ')';
                resultado.style.color = '#1e7e45';
            } else {
                resultado.textContent = '✘ ' + dados.mensagem;
                resultado.style.color = '#a93226';
            }
        });
    </script>
</body>
</html>
