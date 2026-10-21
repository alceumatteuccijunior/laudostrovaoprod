// Ficheiro: assets/js/scripts.js

document.addEventListener("DOMContentLoaded", function() {
    // Lógica do menu lateral
    const menuToggle = document.getElementById("menu-toggle");
    const wrapper = document.getElementById("wrapper");
    if (menuToggle) {
        menuToggle.addEventListener("click", (e) => {
            e.preventDefault();
            wrapper.classList.toggle("toggled");
        });
    }

    // --- Lógica do Formulário de Registo de Serviço ---
    const registerServiceForm = document.getElementById('register-service-form');
    if (registerServiceForm) {
        // ... (código existente para o formulário de registo) ...
        const servicoSelect = document.getElementById('servico_select');
        const addServiceBtn = document.getElementById('add-service-btn');
        const servicosTabela = document.getElementById('servicos-selecionados-tabela');
        const descontoInput = document.getElementById('desconto');
        const subtotalEl = document.getElementById('subtotal-val');
        const totalEl = document.getElementById('total-val');
        let servicosSelecionados = [];

        const formatCurrency = (value) => value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        const atualizarTotais = () => {
            let subtotal = servicosSelecionados.reduce((acc, servico) => acc + servico.preco, 0);
            let desconto = parseFloat(descontoInput.value) || 0;
            let total = subtotal - desconto;
            subtotalEl.textContent = formatCurrency(subtotal);
            totalEl.textContent = formatCurrency(total);
        };

        addServiceBtn.addEventListener('click', () => {
            const selectedOption = servicoSelect.options[servicoSelect.selectedIndex];
            if (!selectedOption.value) return;

            const servicoId = parseInt(selectedOption.value);
            const servicoNome = selectedOption.text;
            const servicoPreco = parseFloat(selectedOption.dataset.preco);

            servicosSelecionados.push({ id: servicoId, nome: servicoNome, preco: servicoPreco });
            renderizarTabelaServicos();
            atualizarTotais();
        });

        const renderizarTabelaServicos = () => {
            servicosTabela.innerHTML = '';
            if (servicosSelecionados.length === 0) {
                servicosTabela.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Nenhum serviço selecionado.</td></tr>';
            } else {
                servicosSelecionados.forEach((servico, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <input type="hidden" name="servicos[${index}][id]" value="${servico.id}">
                            <input type="hidden" name="servicos[${index}][preco]" value="${servico.preco}">
                            ${servico.nome}
                        </td>
                        <td class="text-end">${formatCurrency(servico.preco)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-service" data-index="${index}">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    `;
                    servicosTabela.appendChild(tr);
                });
            }
        };

        servicosTabela.addEventListener('click', (e) => {
            if (e.target.closest('.btn-remove-service')) {
                const indexToRemove = parseInt(e.target.closest('.btn-remove-service').dataset.index);
                servicosSelecionados.splice(indexToRemove, 1);
                renderizarTabelaServicos();
                atualizarTotais();
            }
        });

        descontoInput.addEventListener('input', atualizarTotais);
        renderizarTabelaServicos();
    }

    // --- NOVA LÓGICA PARA O FORMULÁRIO DE EDIÇÃO DE SERVIÇO ---
    const editServiceForm = document.getElementById('edit-service-form');
    if (editServiceForm) {
        const servicoSelect = document.getElementById('servico_select');
        const addServiceBtn = document.getElementById('add-service-btn-details');
        const servicosTabela = document.getElementById('servicos-selecionados-tabela');
        const descontoInput = document.getElementById('desconto');
        const subtotalEl = document.getElementById('subtotal-val');
        const totalEl = document.getElementById('total-val');
        
        // A variável 'initialServices' é definida no final de service_details.php
        let servicosSelecionados = typeof initialServices !== 'undefined' ? initialServices.map(s => ({...s, id: parseInt(s.id), preco: parseFloat(s.preco)})) : [];

        const formatCurrency = (value) => value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        const atualizarTotais = () => {
            let subtotal = servicosSelecionados.reduce((acc, servico) => acc + servico.preco, 0);
            let desconto = parseFloat(descontoInput.value) || 0;
            let total = subtotal - desconto;
            subtotalEl.textContent = formatCurrency(subtotal);
            totalEl.textContent = formatCurrency(total);
        };

        const renderizarTabelaServicos = () => {
            servicosTabela.innerHTML = '';
             if (servicosSelecionados.length === 0) {
                servicosTabela.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Nenhum serviço selecionado.</td></tr>';
            } else {
                servicosSelecionados.forEach((servico, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <input type="hidden" name="servicos[${index}][id]" value="${servico.id}">
                            <input type="hidden" name="servicos[${index}][preco]" value="${servico.preco}">
                            ${servico.nome}
                        </td>
                        <td class="text-end">${formatCurrency(servico.preco)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-service" data-index="${index}">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    `;
                    servicosTabela.appendChild(tr);
                });
            }
        };

        addServiceBtn.addEventListener('click', () => {
            const selectedOption = servicoSelect.options[servicoSelect.selectedIndex];
            if (!selectedOption.value) return;

            const servicoId = parseInt(selectedOption.value);
            const servicoNome = selectedOption.text;
            const servicoPreco = parseFloat(selectedOption.dataset.preco);

            servicosSelecionados.push({ id: servicoId, nome: servicoNome, preco: servicoPreco });
            renderizarTabelaServicos();
            atualizarTotais();
        });

        servicosTabela.addEventListener('click', (e) => {
            if (e.target.closest('.btn-remove-service')) {
                const indexToRemove = parseInt(e.target.closest('.btn-remove-service').dataset.index);
                servicosSelecionados.splice(indexToRemove, 1);
                renderizarTabelaServicos();
                atualizarTotais();
            }
        });

        descontoInput.addEventListener('input', atualizarTotais);

        // Inicializa a tabela e os totais com os dados do pedido
        renderizarTabelaServicos();
        atualizarTotais();
    }


    // --- Lógica de Autocompletar para Clientes ---
    const clienteSearchInput = document.getElementById('cliente_search');
    const clienteIdInput = document.getElementById('cliente_id');
    const resultsContainer = document.getElementById('autocomplete-results');

    if (clienteSearchInput) {
        clienteSearchInput.addEventListener('keyup', async function() {
            const searchTerm = this.value;
            resultsContainer.innerHTML = '';
            if (searchTerm.length < 2) {
                clienteIdInput.value = '';
                return;
            }

            try {
                const response = await fetch(`../api/client_search.php?term=${searchTerm}`);
                const results = await response.json();

                if (response.ok && Array.isArray(results)) {
                    results.forEach(result => {
                        const div = document.createElement('div');
                        div.textContent = result.label;
                        div.classList.add('autocomplete-item');
                        div.addEventListener('click', function() {
                            clienteSearchInput.value = result.label;
                            clienteIdInput.value = result.id;
                            resultsContainer.innerHTML = '';
                        });
                        resultsContainer.appendChild(div);
                    });
                }
            } catch (error) {
                console.error('Erro ao procurar clientes:', error);
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target.id !== 'cliente_search') {
                resultsContainer.innerHTML = '';
            }
        });
    }
});
