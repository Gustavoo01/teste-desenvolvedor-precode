CREATE TABLE produtos (
    id SERIAL PRIMARY KEY,
    criado_em TIMESTAMP DEFAULT NOW(),
    atualizado_em TIMESTAMP DEFAULT NOW()
);