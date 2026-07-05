<?php

require_once __DIR__ . '/../../src/config.php';

match ($_SERVER['REQUEST_METHOD']) {
    'GET' => listarPedidos(),
    'POST' => criarPedido(),
    'PUT' => atualizarStatus('PUT', 'aprovado'),
    'DELETE' => atualizarStatus('DELETE', 'cancelado'),
    default => responder(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']),
};

function listarPedidos(): void
{
    $pedidos = bd()->query('SELECT * FROM pedidos ORDER BY id DESC')->fetchAll();

    $stmtItens = bd()->prepare(
        'SELECT i.quantidade, i.valor_unitario, i.sku, p.nome
         FROM pedido_itens i
         JOIN produtos p ON p.id = i.produto_id
         WHERE i.pedido_id = :pedido_id'
    );

    foreach ($pedidos as &$pedido) {
        $stmtItens->execute([':pedido_id' => $pedido['id']]);
        $pedido['itens'] = $stmtItens->fetchAll();
    }

    responder(200, ['sucesso' => true, 'pedidos' => $pedidos]);
}

function criarPedido(): void
{
    $dados = json_decode(file_get_contents('php://input'), true);

    if (!is_array($dados) || empty($dados['cliente']) || empty($dados['itens'])) {
        responder(400, ['sucesso' => false, 'mensagem' => 'Informe os dados do cliente e ao menos um item.']);
    }

    $cliente = $dados['cliente'];
    $frete = (float) ($dados['frete'] ?? 0);

    $stmt = bd()->prepare('SELECT * FROM produtos WHERE id = :id');
    $itensApi = [];
    $itensLocal = [];
    $totalItens = 0;

    foreach ($dados['itens'] as $item) {
        $stmt->execute([':id' => (int) $item['produto_id']]);
        $produto = $stmt->fetch();

        if (!$produto || !$produto['sku_variacao']) {
            responder(422, ['sucesso' => false, 'mensagem' => 'Produto inválido ou sem SKU sincronizado.']);
        }

        $quantidade = max(1, (int) $item['quantidade']);
        $valorUnitario = (float) $produto['preco'];
        $totalItens += $valorUnitario * $quantidade;

        $itensApi[] = [
            'sku' => (int) $produto['sku_variacao'],
            'valorUnitario' => $valorUnitario,
            'quantidade' => $quantidade,
        ];

        $itensLocal[] = [
            'produto_id' => $produto['id'],
            'sku' => (int) $produto['sku_variacao'],
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
        ];
    }

    $valorTotal = $totalItens + $frete;
    $idPedidoParceiro = 'TESTE-' . time();

    $payload = [
        'pedido' => [
            'idPedidoParceiro' => $idPedidoParceiro,
            'valorFrete' => $frete,
            'valorTotalCompra' => $valorTotal,
            'formaPagamento' => '4',
            'dadosCliente' => [
                'cpfCnpj' => $cliente['cpfCnpj'] ?? '',
                'nomeRazao' => $cliente['nome'] ?? '',
                'fantasia' => $cliente['nome'] ?? '',
                'email' => $cliente['email'] ?? '',
                'dadosEntrega' => [
                    'cep' => $cliente['cep'] ?? '',
                    'endereco' => $cliente['endereco'] ?? '',
                    'numero' => $cliente['numero'] ?? 's/n',
                    'bairro' => $cliente['bairro'] ?? '',
                    'complemento' => '',
                    'cidade' => $cliente['cidade'] ?? '',
                    'uf' => $cliente['uf'] ?? '',
                ],
                'telefones' => [
                    'residencial' => $cliente['telefone'] ?? '',
                    'celular' => $cliente['telefone'] ?? '',
                ],
            ],
            'pagamento' => [[
                'valor' => $valorTotal,
                'quantidadeParcelas' => 1,
            ]],
            'itens' => $itensApi,
        ],
    ];

    $resposta = precode('POST', 'v1/pedido/pedido', $payload);
    $corpo = $resposta['corpo'];
    $numeroPedido = (int) ($corpo['pedido']['numeroPedido'] ?? 0);
    $sucesso = $numeroPedido > 0;

    if ($sucesso) {
        $bd = bd();
        $bd->beginTransaction();

        $stmtPedido = $bd->prepare(
            'INSERT INTO pedidos (codigo_pedido, id_pedido_parceiro, nome_cliente, valor_total, status)
             VALUES (:codigo, :parceiro, :cliente, :total, :status)
             RETURNING id'
        );
        $stmtPedido->execute([
            ':codigo' => $numeroPedido,
            ':parceiro' => $idPedidoParceiro,
            ':cliente' => $cliente['nome'] ?? '',
            ':total' => $valorTotal,
            ':status' => 'novo',
        ]);
        $pedidoId = (int) $stmtPedido->fetchColumn();

        $stmtItem = $bd->prepare(
            'INSERT INTO pedido_itens (pedido_id, produto_id, sku, quantidade, valor_unitario)
             VALUES (:pedido_id, :produto_id, :sku, :quantidade, :valor_unitario)'
        );

        foreach ($itensLocal as $item) {
            $stmtItem->execute([
                ':pedido_id' => $pedidoId,
                ':produto_id' => $item['produto_id'],
                ':sku' => $item['sku'],
                ':quantidade' => $item['quantidade'],
                ':valor_unitario' => $item['valor_unitario'],
            ]);
        }

        $bd->commit();
    }

    responder($sucesso ? 201 : 502, [
        'sucesso' => $sucesso,
        'mensagem' => $sucesso
            ? "Pedido criado na Precode com o número {$numeroPedido}."
            : 'A API recusou a criação do pedido.',
        'retornoApi' => $corpo,
    ]);
}

function atualizarStatus(string $metodoHttp, string $novoStatus): void
{
    $dados = json_decode(file_get_contents('php://input'), true);

    if (!is_array($dados) || empty($dados['id'])) {
        responder(400, ['sucesso' => false, 'mensagem' => 'Informe o id do pedido.']);
    }

    $stmt = bd()->prepare('SELECT * FROM pedidos WHERE id = :id');
    $stmt->execute([':id' => (int) $dados['id']]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        responder(404, ['sucesso' => false, 'mensagem' => 'Pedido não encontrado.']);
    }

    $payload = [
        'pedido' => [
            'codigoPedido' => (int) $pedido['codigo_pedido'],
            'idPedidoParceiro' => $pedido['id_pedido_parceiro'],
        ],
    ];

    $resposta = precode($metodoHttp, 'v1/pedido/pedido', $payload);
    $corpo = $resposta['corpo'];

    $mensagemApi = mb_strtolower((string) ($corpo['pedido']['mensagem'] ?? ''));
    $sucesso = $resposta['status'] >= 200 && $resposta['status'] < 300
        && (str_contains($mensagemApi, 'sucesso') || str_contains($mensagemApi, $novoStatus));

    if ($sucesso) {
        $stmt = bd()->prepare('UPDATE pedidos SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $novoStatus, ':id' => (int) $dados['id']]);
    }

    responder($sucesso ? 200 : 502, [
        'sucesso' => $sucesso,
        'mensagem' => $sucesso
            ? "Pedido {$pedido['codigo_pedido']} {$novoStatus}."
            : 'A API recusou a operação.',
        'retornoApi' => $corpo,
    ]);
}