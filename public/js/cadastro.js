const formulario = document.getElementById('formulario');
const botao = document.getElementById('botao');

formulario.addEventListener('submit', async e => {
    e.preventDefault();

    const dados = Object.fromEntries(new FormData(formulario));

    botao.disabled = true;
    botao.textContent = 'Enviando...';

    try {
        const resposta = await fetch('api/produtos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados),
        });

        const retorno = await resposta.json();
        exibirRetorno(retorno);

        if (retorno.sucesso) formulario.reset();
    } catch (erro) {
        exibirRetorno({ sucesso: false, mensagem: 'Falha de comunicação: ' + erro.message });
    } finally {
        botao.disabled = false;
        botao.textContent = 'Cadastrar produto';
    }
});

function exibirRetorno(retorno) {
    const painel = document.getElementById('retorno');

    painel.className = retorno.sucesso ? 'sucesso' : 'erro';
    document.getElementById('retorno-titulo').textContent = retorno.sucesso ? 'Sucesso' : 'Erro';
    document.getElementById('retorno-mensagem').textContent = retorno.mensagem || '';
    document.getElementById('retorno-json').textContent =
        retorno.retornoApi ? JSON.stringify(retorno.retornoApi, null, 2) : '';

    painel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}