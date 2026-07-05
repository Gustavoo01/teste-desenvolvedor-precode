<?php

require_once __DIR__ . '/../../src/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $produtos = bd()->query('SELECT * FROM produtos ORDER BY id DESC')->fetchAll();
    responder(200, ['sucesso' => true, 'produtos' => $produtos]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}

$dados = json_decode(file_get_contents('php://input'), true);

if (!is_array($dados)) {
    responder(400, ['sucesso' => false, 'mensagem' => 'JSON inválido.']);
}

$payload = [
    'product' => [
        'sku' => null,
        'name' => trim($dados['nome'] ?? ''),
        'description' => trim($dados['descricao'] ?? ''),
        'status' => 'enabled',
        'price' => (float) ($dados['preco'] ?? 0),
        'promotional_price' => (float) ($dados['preco'] ?? 0),
        'cost' => (float) ($dados['custo'] ?? 0),
        'weight' => (float) ($dados['peso'] ?? 0),
        'width' => (float) ($dados['largura'] ?? 0),
        'height' => (float) ($dados['altura'] ?? 0),
        'length' => (float) ($dados['comprimento'] ?? 0),
        'brand' => trim($dados['marca'] ?? ''),
        'variations' => [
            ['sku' => null, 'qty' => (string) (int) ($dados['estoque'] ?? 0)],
        ],
    ],
];

$resposta = precode('POST', 'v3/products', $payload);
$corpo = $resposta['corpo'];
$sucesso = ($corpo['code'] ?? null) === 0;

if ($sucesso) {
    $stmt = bd()->prepare(
        'INSERT INTO produtos (sku_pai, sku_variacao, nome, marca, preco, custo, estoque)
         VALUES (:sku_pai, :sku_variacao, :nome, :marca, :preco, :custo, :estoque)'
    );
    $stmt->execute([
        ':sku_pai' => $corpo['sku'] ?? null,
        ':sku_variacao' => $corpo['variations'][0]['sku'] ?? null,
        ':nome' => $dados['nome'],
        ':marca' => $dados['marca'],
        ':preco' => $dados['preco'],
        ':custo' => $dados['custo'],
        ':estoque' => (int) $dados['estoque'],
    ]);
}

responder($sucesso ? 201 : 502, [
    'sucesso' => $sucesso,
    'mensagem' => $sucesso ? 'Produto cadastrado na Precode.' : 'A API recusou o cadastro.',
    'retornoApi' => $corpo ?? ['erro' => $resposta['erro'] ?? 'Sem resposta da API'],
]);