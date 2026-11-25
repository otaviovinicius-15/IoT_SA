<?php

require_once __DIR__ . "/../config/conexao.php";

header("Content-Type: application/json; charset=utf-8");

$codigo = strtoupper(trim($_REQUEST['codigo'] ?? ''));

if ($codigo === '') {
    echo json_encode(['autorizado' => false, 'mensagem' => 'Codigo nao informado']);
    exit;
}

$stmt = $pdo->prepare("SELECT nome, setor, ativo FROM funcionarios WHERE cartao_rfid = ?");
$stmt->execute([$codigo]);
$funcionario = $stmt->fetch();

if (!$funcionario) {
    echo json_encode(['autorizado' => false, 'mensagem' => 'Cartao nao cadastrado']);
    exit;
}

if (!$funcionario['ativo']) {
    echo json_encode(['autorizado' => false, 'nome' => $funcionario['nome'], 'mensagem' => 'Funcionario inativo']);
    exit;
}

echo json_encode([
    'autorizado' => true,
    'nome'       => $funcionario['nome'],
    'setor'      => $funcionario['setor'],
    'mensagem'   => 'Acesso liberado',
]);
