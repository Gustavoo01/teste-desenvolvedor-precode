const formulario = document.getElementById('formulario-pedido');
const seletorProduto = document.getElementById('seletor-produto');
const tabelaItens = document.getElementById('tabela-itens');
const corpoItens = tabelaItens.querySelector('tbody');
const corpoPedidos = document.querySelector('#tabela-pedidos tbody');
const inputFrete = document.getElementById('frete');

let produtos = [];
let itens = [];

carregarProdutos();
carregarPedidos();

async function carregarProdutos() {
    try {
        const resposta = await fetch('api/produtos.php');
        const dados = await resposta.json();
        produtos = dados.produtos || [];

        seletorProduto.innerHTML = produtos.length
            ? produtos.map(p => `<option value="${p.id}">${p.nome} — R$ ${Number(p.preco).toFixed(2)}</option>`).join('')
            : '<option value="">Nenhum produto cadastrado</option>';
    } catch {
        seletorProduto.innerHTML = '<option value="">Erro ao carregar produtos</option>';
    }
}

document.getElementById('botao-adicionar').addEventListener('click', () => {
    const produtoId = parseInt(seletorProduto.value, 10);
    const quantidade = parseInt(document.getElementById('quantidade-item').value, 10) || 1;
    const produto = produtos.find(p => p.id === produtoId);

    if (!produto) return;

    const existente = itens.find(i => i.produto_id === produtoId);
    if (existente) {
        existente.quantidade += quantidade;
    } else {
        itens.push({
            produto_id: produtoId,
            nome: produto.nome,
            preco: Number(produto.preco),
            quantidade,
        });
    }

    renderizarItens();
});

inputFrete.addEventListener('input', atualizarTotal);

function renderizarItens() {
    tabelaItens.classList.toggle('oculto', itens.length === 0);
    corpoItens.innerHTML = '';

    itens.forEach((item, indice) => {
        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td>${item.nome}</td>
            <td>${item.quantidade}</td>
            <td>${item.preco.toFixed(2)}</td>
            <td>${(item.preco * item.quantidade).toFixed(2)}</td>
            <td><button type="button" class="botao-pequeno botao-perigo">Remover</button></td>
        `;
        linha.querySelector('button').addEventListener('click', () => {
            itens.splice(indice, 1);
            renderizarItens();
        });
        corpoItens.appendChild(linha);
    });

    atualizarTotal();
}

function atualizarTotal() {
    const frete = parseFloat(inputFrete.value) || 0;
    const total = itens.reduce((soma, i) => soma + i.preco * i.quantidade, 0) + frete;
    document.getElementById('valor-total').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
}

formulario.addEventListener('submit', async e => {
    e.preventDefault();

    if (itens.length === 0) {
        exibirRetorno({ sucesso: false, mensagem: 'Adicione ao menos um item ao pedido.' });
        return;
    }

    const campos = Object.fromEntries(new FormData(formulario));
    const botao = document.getElementById('botao-criar');

    botao.disabled = true;
    botao.textContent = 'Enviando...';

    try {
        const resposta = await fetch('api/pedidos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cliente: campos,
                frete: parseFloat(inputFrete.value) || 0,
                itens: itens.map(i => ({ produto_id: i.produto_id, quantidade: i.quantidade })),
            }),
        });

        const retorno = await resposta.json();
        exibirRetorno(retorno);

        if (retorno.sucesso) {
            formulario.reset();
            itens = [];
            renderizarItens();
            carregarPedidos();
        }
    } catch (erro) {
        exibirRetorno({ sucesso: false, mensagem: 'Falha de comunicação: ' + erro.message });
    } finally {
        botao.disabled = false;
        botao.textContent = 'Criar pedido';
    }
});

async function carregarPedidos() {
    try {
        const resposta = await fetch('api/pedidos.php');
        const dados = await resposta.json();
        const pedidos = dados.pedidos || [];

        if (pedidos.length === 0) {
            corpoPedidos.innerHTML = '<tr><td colspan="6" class="vazio">Nenhum pedido enviado ainda.</td></tr>';
            return;
        }

        corpoPedidos.innerHTML = '';
        pedidos.forEach(montarLinhaPedido);
    } catch (erro) {
        corpoPedidos.innerHTML = `<tr><td colspan="6" class="vazio">Erro ao carregar pedidos: ${erro.message}</td></tr>`;
    }
}

function montarLinhaPedido(pedido) {
    const linha = document.createElement('tr');
    const resumoItens = (pedido.itens || []).map(i => `${i.quantidade}x ${i.nome}`).join(', ');
    const pendente = pedido.status === 'novo';

    linha.innerHTML = `
        <td>${pedido.codigo_pedido}</td>
        <td>${pedido.nome_cliente}</td>
        <td class="celula-itens">${resumoItens}</td>
        <td>${Number(pedido.valor_total).toFixed(2)}</td>
        <td><span class="badge ${pedido.status}">${pedido.status}</span></td>
        <td class="celula-acoes">
            ${pendente
                ? `<button class="botao-pequeno" data-acao="aprovar">Aprovar</button>
                   <button class="botao-pequeno botao-perigo" data-acao="cancelar">Cancelar</button>`
                : ''}
        </td>
    `;

    linha.querySelectorAll('button').forEach(botao => {
        botao.addEventListener('click', () => acionarPedido(pedido.id, botao.dataset.acao, botao));
    });

    corpoPedidos.appendChild(linha);
}

async function acionarPedido(id, acao, botao) {
    botao.disabled = true;
    botao.textContent = 'Enviando...';

    try {
        const resposta = await fetch('api/pedidos.php', {
            method: acao === 'aprovar' ? 'PUT' : 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });

        const retorno = await resposta.json();
        exibirRetorno(retorno);

        if (retorno.sucesso) carregarPedidos();
    } catch (erro) {
        exibirRetorno({ sucesso: false, mensagem: 'Falha de comunicação: ' + erro.message });
    } finally {
        botao.disabled = false;
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