<?php

require_once __DIR__ . '/../../src/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    responder(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}

$dados = json_decode(file_get_contents('php://input'), true);

if (!is_array($dados) || empty($dados['id']) || !isset($dados['preco'], $dados['estoque'])) {
    responder(400, ['sucesso' => false, 'mensagem' => 'Informe id, preco e estoque.']);
}

$stmt = bd()->prepare('SELECT * FROM produtos WHERE id = :id');
$stmt->execute([':id' => $dados['id']]);
$produto = $stmt->fetch();

if (!$produto) {
    responder(404, ['sucesso' => false, 'mensagem' => 'Produto não encontrado.']);
}

$preco = (float) $dados['preco'];
$estoque = (int) $dados['estoque'];

$payload = [
    'products' => [[
        'sku' => (int) $produto['sku_variacao'],
        'price' => $preco,
        'promotional_price' => $preco,
        'cost' => (float) $produto['custo'],
        'shippingTime' => 0,
        'status' => 'enabled',
        'stock' => [[
            'stores' => 1,
            'availableStock' => $estoque,
            'realStock' => $estoque,
        ]],
    ]],
];

$resposta = precode('PUT', 'v3/products/inventory', $payload);
$corpo = $resposta['corpo'];

$retorno = $corpo['products'][0]['return'][0] ?? null;
$sucesso = ($retorno['code'] ?? null) === 0;

if ($sucesso) {
    $stmt = bd()->prepare('UPDATE produtos SET preco = :preco, estoque = :estoque WHERE id = :id');
    $stmt->execute([':preco' => $preco, ':estoque' => $estoque, ':id' => $dados['id']]);
}

responder($sucesso ? 200 : 502, [
    'sucesso' => $sucesso,
    'mensagem' => $sucesso ? 'Preço e estoque atualizados.' : 'A API recusou a atualização.',
    'retornoApi' => $corpo,
]);