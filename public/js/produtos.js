const corpoTabela = document.querySelector('#tabela-produtos tbody');

carregarProdutos();

async function carregarProdutos() {
    try {
        const resposta = await fetch('api/produtos.php');
        const dados = await resposta.json();

        if (!dados.produtos || dados.produtos.length === 0) {
            corpoTabela.innerHTML = '<tr><td colspan="6" class="vazio">Nenhum produto cadastrado ainda. <a href="index.html">Cadastre o primeiro</a>.</td></tr>';
            return;
        }

        corpoTabela.innerHTML = '';
        dados.produtos.forEach(montarLinha);
    } catch (erro) {
        corpoTabela.innerHTML = `<tr><td colspan="6" class="vazio">Erro ao carregar produtos: ${erro.message}</td></tr>`;
    }
}

function montarLinha(produto) {
    const linha = document.createElement('tr');

    linha.innerHTML = `
        <td>${produto.sku_variacao ?? '—'}</td>
        <td>${produto.nome}</td>
        <td>${produto.marca}</td>
        <td><input type="number" step="0.01" min="0.01" value="${Number(produto.preco).toFixed(2)}"></td>
        <td><input type="number" step="1" min="0" value="${produto.estoque}"></td>
        <td><button class="botao-pequeno">Atualizar</button></td>
    `;

    const [inputPreco, inputEstoque] = linha.querySelectorAll('input');
    const botao = linha.querySelector('button');

    botao.addEventListener('click', () => atualizar(produto.id, inputPreco, inputEstoque, botao));
    corpoTabela.appendChild(linha);
}

async function atualizar(id, inputPreco, inputEstoque, botao) {
    botao.disabled = true;
    botao.textContent = 'Enviando...';

    try {
        const resposta = await fetch('api/inventario.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id,
                preco: parseFloat(inputPreco.value),
                estoque: parseInt(inputEstoque.value, 10),
            }),
        });

        exibirRetorno(await resposta.json());
    } catch (erro) {
        exibirRetorno({ sucesso: false, mensagem: 'Falha de comunicação: ' + erro.message });
    } finally {
        botao.disabled = false;
        botao.textContent = 'Atualizar';
    }
}

function exibirRetorno(retorno) {
    const painel = document.getElementById('retorno');

    painel.className = retorno.sucesso ? 'sucesso' : 'erro';
    document.getElementById('retorno-titulo').textContent = retorno.sucesso ? 'Sucesso' : 'Erro';
    document.getElementById('retorno-mensagem').textContent = retorno.mensagem || '';
    document.getElementById('retorno-json').textContent =
        retorno.retornoApi ? JSON.stringify(retorno.retornoApi, null, 2) : '';

    painel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}