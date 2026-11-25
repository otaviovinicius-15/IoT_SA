<?php

require_once __DIR__ . "/../config/conexao.php";

header("Content-Type: application/json; charset=utf-8");

$codigo    = strtoupper(trim($_REQUEST['codigo'] ?? ''));
$armarioId = (int) ($_REQUEST['armario'] ?? 0);

// Grava a tentativa de acesso e devolve a resposta para o ESP
function responder($autorizado, $mensagem, $extra = [])
{
    global $pdo, $codigo, $armario, $funcionario;

    $pdo->prepare("
        INSERT INTO acessos_armario (armario_id, funcionario_id, cartao_rfid, autorizado, mensagem)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([
        $armario['id'] ?? null,
        $funcionario['id'] ?? null,
        $codigo,
        $autorizado ? 1 : 0,
        $mensagem,
    ]);

    echo json_encode(['autorizado' => $autorizado, 'abrir' => $autorizado, 'mensagem' => $mensagem] + $extra);
    exit;
}

$armario     = null;
$funcionario = null;

if ($codigo === '') {
    echo json_encode(['autorizado' => false, 'abrir' => false, 'mensagem' => 'Codigo nao informado']);
    exit;
}

// 1. Armário
$stmt = $pdo->prepare("SELECT id, nome FROM armarios WHERE id = ? AND ativo = 1");
$stmt->execute([$armarioId]);
$armario = $stmt->fetch();

if (!$armario) {
    responder(false, 'Armario invalido');
}

// 2. Cartão / funcionário
$stmt = $pdo->prepare("SELECT id, nome, ativo FROM funcionarios WHERE cartao_rfid = ?");
$stmt->execute([$codigo]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    responder(false, 'Cartao nao cadastrado');
}

if (!$funcionario['ativo']) {
    responder(false, 'Funcionario inativo', ['nome' => $funcionario['nome']]);
}

// 3. Permissão no armário
$stmt = $pdo->prepare("SELECT 1 FROM armario_funcionarios WHERE armario_id = ? AND funcionario_id = ?");
$stmt->execute([$armario['id'], $funcionario['id']]);

if (!$stmt->fetch()) {
    responder(false, 'Sem acesso ao armario', ['nome' => $funcionario['nome']]);
}

// 4. Produto disponível no armário
$stmt = $pdo->prepare("
    SELECT af.ferramenta_id, af.prazo_dias, fe.nome
    FROM armario_ferramentas af
    JOIN ferramentas fe ON fe.id = af.ferramenta_id
    WHERE af.armario_id = ? AND af.quantidade > 0
      AND fe.ativo = 1 AND fe.quantidade_disponivel > 0
    ORDER BY fe.nome
    LIMIT 1
");
$stmt->execute([$armario['id']]);
$item = $stmt->fetch();

if (!$item) {
    responder(false, 'Armario vazio', ['nome' => $funcionario['nome']]);
}

// 5. Registra a retirada e baixa o estoque (armário e geral)
$pdo->beginTransaction();

$pdo->prepare("
    INSERT INTO movimentacoes (armario_id, funcionario_id, ferramenta_id, quantidade, prazo_devolucao)
    VALUES (?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL ? DAY))
")->execute([$armario['id'], $funcionario['id'], $item['ferramenta_id'], $item['prazo_dias']]);

$pdo->prepare("UPDATE armario_ferramentas SET quantidade = quantidade - 1 WHERE armario_id = ? AND ferramenta_id = ?")
    ->execute([$armario['id'], $item['ferramenta_id']]);

$pdo->prepare("UPDATE ferramentas SET quantidade_disponivel = quantidade_disponivel - 1 WHERE id = ?")
    ->execute([$item['ferramenta_id']]);

$pdo->commit();

responder(true, 'Acesso liberado', [
    'nome'       => $funcionario['nome'],
    'ferramenta' => $item['nome'],
    'prazo'      => date('d/m/Y', strtotime("+{$item['prazo_dias']} days")),
]);
