# Teste Desenvolvedor - Precode (HUB)

Integração com a API da Precode: cadastro de produtos, atualização de preço e estoque, e criação/aprovação/cancelamento de pedidos.

Feito com HTML, CSS, JavaScript, PHP e PostgreSQL, conforme pedido no teste.

## Como rodar

Requisitos: PHP 8+ (com pdo_pgsql e curl habilitados) e PostgreSQL.

1. Criar o banco e as tabelas:

```
psql -U postgres -c "CREATE DATABASE precode;"
psql -U postgres -d precode -f schema.sql
```

2. Ajustar usuário e senha do banco em `src/config.php` se necessário.

3. Subir o servidor:

```
php -S localhost:8000 -t public
```

4. Acessar http://localhost:8000/index.html