CREATE TABLE IF NOT EXISTS produtos (
    id           SERIAL PRIMARY KEY,
    nome         VARCHAR(255) NOT NULL,
    marca        VARCHAR(100) NOT NULL,
    preco        NUMERIC(10,2) NOT NULL,
    custo        NUMERIC(10,2) NOT NULL DEFAULT 0,
    estoque      INTEGER NOT NULL DEFAULT 0,
    criado_em    TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS pedidos (
    id                 SERIAL PRIMARY KEY,
    codigo_pedido      INTEGER,
    id_pedido_parceiro VARCHAR(50),
    nome_cliente       VARCHAR(255) NOT NULL,
    valor_total        NUMERIC(10,2) NOT NULL,
    status             VARCHAR(20) NOT NULL DEFAULT 'novo',
    criado_em          TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS pedido_itens (
    id             SERIAL PRIMARY KEY,
    pedido_id      INTEGER NOT NULL REFERENCES pedidos(id),
    produto_id     INTEGER NOT NULL REFERENCES produtos(id),
    quantidade     INTEGER NOT NULL,
    valor_unitario NUMERIC(10,2) NOT NULL
);
