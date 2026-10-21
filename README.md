<div align="center">
  <img src="https://trovaobrasil.com.br/estoque/logo.svg" alt="Trovão Brasil Logo" height="65">
  
  # Sistema de Gestão Operacional & Gerador de Laudos
  
  **Plataforma Integrada de Gestão de Lavagem Pesada, Higienização Técnica, CRM Preditivo, Inteligência de Frota e BI**

  [![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
  [![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-00758F?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
  [![Bootstrap](https://img.shields.io/badge/UI-Bootstrap%205.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
  [![ChartJS](https://img.shields.io/badge/Charts-Chart.js-FF6384?style=flat-square&logo=chartdotjs&logoColor=white)](https://www.chartjs.org/)
  [![AI](https://img.shields.io/badge/AI%20Engine-Google%20Gemini-4285F4?style=flat-square&logo=google&logoColor=white)](https://ai.google.dev/)
  [![ERP Integration](https://img.shields.io/badge/Integration-Omie%20ERP-00A859?style=flat-square)](https://www.omie.com.br/)
</div>

---

## 📌 Visão Geral

O **Gerador de Laudos e Gestão Operacional da TrovãoBrasil** é uma solução corporativa robusta desenvolvida para atender às rigorosas exigências do setor de **lavagem, descontaminação e higienização técnica de veículos pesados** (carretas tanque, baús, caminhões truck, cavalos mecânicos e maquinário pesado).

Além de emitir Laudos Técnicos de Higienização certificados (com conformidade para clientes de alto padrão como **AAK**, **Bunge** e alimentícias em geral), a plataforma centraliza todo o ciclo financeiro, fluxo de pátio, monitoramento de churn via CRM, rastreabilidade de frotas ("Teia de Aranha"), painéis analíticos gerenciais e suporte à decisão assistido por **Inteligência Artificial**.

---

## 🚀 Principais Módulos do Sistema

### 1. 📋 Registro e Gestão de Ordens de Serviço (OS)
- **Fluxo Operacional Completo**: Cadastro de OS com motorista, placas (cavalo e carreta/truck), horários precisos de chegada/saída e turno de trabalho (Manhã, Tarde, Noite).
- **Rastreabilidade de Carga Tríplice**: Armazenamento do histórico dos três últimos carregamentos (Último, Penúltimo e Antepenúltimo produto transportado), essencial para controle químico de contaminação cruzada.
- **Auditoria de Lacres de Segurança**: Mapeamento e amarração numerada por compartimento (Boca de Visita Superior 1, 2 e 3, Válvula de Descarga, Válvula de Dreno e Suporte de Mangote).
- **Checklist Técnico e Procedimentos**: Registro de protocolos aplicados, tais como: lavagem interna a quente, vaporização Kosher/Halal, detergentes neutros, teste de pH de enxágue final (7,5 a 9,0), secagem manual, desmonte de válvulas e limpeza de manômetros.
- **Regra de Validade Automática**: Cálculo instantâneo da vigência do laudo fixada em **5 dias corridos** a contar da data de lavagem.
- **Divisão de Responsabilidade**: Suporte a 1º e 2º Responsáveis técnicos (para comissão, conferência e auditoria).
- **Gestão de Exceções**: Fluxos dedicados para cancelamento com justificativa e **reprovação técnica de laudos** (com motivo e data).

### 2. 📄 Emissão de Documentos Oficiais (PDF)
- **Laudo Técnico TrovãoBrasil**: Certificado completo com layout institucional, procedimentos executados, identificação de lacres e assinaturas.
- **Laudo Homologado AAK**: Modelo formatado sob a norma técnica `FOR.01.01.0088` (Revisão 03), pronto para atendimento fiscal e auditorias da multinacional AAK.
- **Recibo Operacional & Térmico**: Comprovante para motorista e expedição em 2 vias, com detalhamento dos itens e horários de pátio.

### 3. 🕸️ Módulo "Teia de Aranha" — Rastreabilidade de Frota
- **Captura Automática de Placas**: Todas as placas cavalo ou carreta são automaticamente indexadas ao banco de dados no momento da criação da OS.
- **Raio-X do Veículo**: Consulta individual por placa informando:
  - Total de lavagens realizadas e histórico de entradas.
  - Soma acumulada de faturamento investido naquela placa.
  - Mapeamento de todos os motoristas que já conduziram o veículo.
  - Vínculo com a empresa proprietária vs. clientes pagadores.

### 4. 📡 Radar de Monitoramento CRM (Retenção & Churn)
- **Detecção Preditiva de Queda**: Algoritmo que compara a quantidade de serviços dos últimos 30 dias com o período de 30 a 60 dias anteriores.
- **Alerta Visual e Classificação**: Sinalização automática por cores (Crescimento/Estável, Atenção e Alerta Vermelho de Churn).
- **Notificação Automática via Cron Job** (`cron_alertas_crm.php`): Disparo de e-mails para administradores configurados sempre que um cliente apresentar queda brusca (limite configurável, padrão 20%), com filtro anti-flood de 7 dias.

### 5. 📊 Relatórios Gerenciais & Produtividade
- **KPIs Superiores em Tempo Real**: Faturamento total líquido, volume de descontos concedidos, pedidos ativos e taxa de cancelamentos.
- **Gráficos Interativos (Chart.js)**:
  - Faturamento no período exato filtrado (com preenchimento de dias vazios).
  - Evolução mensal dos últimos 12 meses.
  - Motivos de cancelamento e motivos de reprovação.
  - Ranking Top 10 Clientes em faturamento.
  - Ranking de Serviços mais procurados do Catálogo.
  - Evolução de desempenho da equipe (Top 5 colaboradores com histórico de 6 meses).
- **Produtividade Individual**: Análise de rendimento por profissional (`pages/professional_productivity.php`), com segregação de atuações principais e secundárias.

### 6. 💼 Integração com ERP Omie & Exportação Excel
- **Exportação Especializada Omie** (`template_omie.xlsx`): Gera planilha pronta para importação direta no sistema ERP Omie, já com:
  - Desmembramento de itens de serviço por linha.
  - Regras fiscais parametrizadas (Código NBS `118033000`, Código de Serviço Municipal e LC116 `14.01`, Município de prestação São Roque/SP).
  - Vendedor, Categoria (`Lavagem Cam. Tanque Carreta`), Projeto e regras tributárias.
- **Exportação Customizada em Excel**: Ferramenta dinâmica que permite escolher quais colunas exportar (incluindo reprovas, 2º responsável, lacres e motivos).

### 7. 🤖 Consultor Executivo com IA (Google Gemini)
- Interface conversacional para diretoria (`pages/ai_dashboard.php`).
- Analisa a base de dados agregada (clientes, produtos, pagamentos, turnos, motoristas) e responde perguntas em linguagem natural, formulando recomendações de negócios e montando gráficos dinâmicos sob demanda.

### 8. 📐 Super BI Drag & Drop (`bi_advanced.php` / `bi_builder.php`)
- Painel estilo PowerBI/Metabase interno com GridStack.js para criação e customização livre de painéis com cartões KPI, gráficos e tabelas.

---

## 🏗️ Estrutura de Diretórios

```
├── api/                             # Endpoints REST e integrações JSON
│   ├── ai_consultant.php            # Integração com API Google Gemini
│   ├── bi_data.php                  # Provedor de métricas para o BI
│   ├── bi_dataset.php               # Data warehouse do BI para o frontend
│   ├── bi_load.php / bi_save.php    # Persistência de layouts de dashboards
│   ├── client_search.php            # Endpoint do autocomplete de clientes
│   ├── dashboard_data.php           # KPIs para telas iniciais
│   ├── sync_receive.php             # Receptor de pacotes de sincronização
│   └── sync_send.php                # Emissor de pacotes para servidor mestre
├── assets/                          # Assets estáticos da aplicação
│   ├── css/style.css                # Folha de estilos Dark Mode personalizada
│   ├── img/                         # Logomarcas e imagens institucionais
│   └── js/scripts.js                # Lógica de interface, autocomplete e totais
├── certs/                           # Certificados digitais (.pfx)
├── includes/                        # Camada de lógica de backend e processos
│   ├── db_connect.php               # Conexão PDO segura (Ambiente Local vs Prod)
│   ├── functions.php                # Sessão blindada e validação de login
│   ├── generate_laudo.php           # Renderizador TCPDF dos Laudos Trovão/AAK
│   ├── generate_recibo.php          # Renderizador TCPDF do Recibo Operacional
│   ├── generate_report_excel.php    # Exportação gerencial para formato XLSX
│   ├── generate_report_omie.php     # Exportação parametrizada para Omie ERP
│   ├── login_process.php / logout   # Autenticação e encerramento de sessão
│   ├── service_process.php          # Persistência de nova OS e cálculo de validade
│   ├── service_update_details_process.php # Atualização granular de OS
│   ├── service_cancel_process.php   # Cancelamento de pedidos
│   ├── service_reprova_process.php  # Registro de reprovação de laudos
│   ├── client_process.php           # CRUD de clientes
│   ├── professional_process.php     # CRUD de funcionários/usuários
│   ├── catalog_process.php          # CRUD do catálogo de serviços
│   └── template_omie.xlsx           # Planilha modelo oficial do Omie ERP
├── pages/                           # Views e controladores de tela
│   ├── ai_dashboard.php             # Tela do Consultor de Negócios com IA
│   ├── bi_advanced.php / bi_builder # Dashboards dinâmicos com GridStack.js
│   ├── catalog_form.php / list.php  # Gestão do Catálogo de Preços
│   ├── client_form.php / list.php   # Gestão de Clientes e Transportadoras
│   ├── crm_clientes.php             # Radar CRM e Alerta de Churn
│   ├── dashboard.php                # Tela de boas-vindas do sistema
│   ├── professional_form.php / list # Gestão de Profissionais e Cargos
│   ├── professional_productivity.php# Desempenho individual do funcionário
│   ├── reports.php                  # Painel de Relatórios Gerenciais
│   ├── service_details.php          # Visão 360º da Ordem de Serviço
│   ├── service_register.php         # Formulário de abertura de nova OS
│   ├── service_success.php          # Confirmação pós-gravação
│   ├── services_list.php            # Histórico e filtros avançados de OS
│   ├── vehicle_dashboard.php        # Teia de Aranha detalhada da placa
│   └── vehicles_list.php            # Listagem de frota mapeada
├── templates/                       # Componentes reutilizáveis de interface
│   ├── header.php                   # Cabeçalho HTML, fontes e bibliotecas
│   ├── sidebar.php                  # Menu lateral responsivo e permissões
│   └── footer.php                   # Rodapé, modal de novidades e scripts
├── castelob_laudos.sql              # Dump inicial da base de dados MySQL
├── cron_alertas_crm.php             # Script de linha de comando para alertas
├── setup_bi_db.php                  # Migração da tabela bi_dashboards
├── setup_crm_db.php                 # Migração das tabelas e flags de CRM
├── setup_veiculos_db.php            # Migração da estrutura da Teia de Aranha
├── composer.json                    # Dependências PHP (PhpSpreadsheet)
├── index.php                        # Tela de Login principal
└── README.md                        # Documentação do projeto
```

---

## 🛠️ Requisitos e Instalação

### Pré-requisitos
- **PHP**: 8.1 ou superior (com extensões `pdo_mysql`, `gd`, `mbstring`, `curl`, `zip`, `xml`).
- **MySQL / MariaDB**: 10.4 ou superior.
- **Servidor Web**: Apache (com `mod_rewrite`) ou Nginx.
- **Composer**: Para gestão de dependências.

### 1. Clonar o Repositório
```bash
git clone https://github.com/alceumatteuccijunior/laudostrovaoprod.git
cd laudostrovaoprod
```

### 2. Instalar Dependências do PHP
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurar o Banco de Dados
1. Crie uma base de dados no MySQL com charset `utf8mb4` e collation `utf8mb4_general_ci`:
   ```sql
   CREATE DATABASE castelob_laudos CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
2. Importe o arquivo principal `castelob_laudos.sql`:
   ```bash
   mysql -u seu_usuario -p castelob_laudos < castelob_laudos.sql
   ```
3. Execute os scripts de migração caso utilize uma base vazia ou deseje garantir as estruturas mais recentes:
   - `http://localhost/setup_veiculos_db.php` (Criação da tabela `veiculos` e indexação de histórico)
   - `http://localhost/setup_crm_db.php` (Criação de `configuracoes`, `crm_alertas_log` e coluna em `profissionais`)
   - `http://localhost/setup_bi_db.php` (Criação da tabela `bi_dashboards`)

### 4. Configurar Conexão com o Banco
Edite o arquivo [includes/db_connect.php](file:///c:/Users/alceu/OneDrive/Documentos/02%20-%20Documentos/Trov%C3%A3o/18%20-%20Gerador%20de%20Laudos/ULTIMA%20VERS%C3%83O/includes/db_connect.php) ajustando os parâmetros de conexão para seu ambiente local ou de produção:

```php
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    define("DB_HOST", "localhost");
    define("DB_NAME", "castelob_laudos");
    define("DB_USER", "root");
    define("DB_PASS", "");
} else {
    define("DB_HOST", "localhost");
    define("DB_NAME", "castelob_laudos");
    define("DB_USER", "seu_usuario_prod");
    define("DB_PASS", "sua_senha_prod");
}
```

### 5. Configurar Tarefa Agendada (Cron Job)
Para que os alertas automáticos do Radar CRM funcionem, adicione uma entrada no crontab do servidor para rodar o script semanalmente (ex.: todas as segundas às 08h00):

```bash
0 8 * * 1 /usr/bin/php /caminho/do/projeto/cron_alertas_crm.php >> /var/log/cron_alertas_crm.log 2>&1
```

---

## 🔐 Segurança e Autenticação

- **Sessões Blindadas**: Função `secure_session_start()` com `httponly`, isolamento de cookie e validação cruzada contínua de integridade via hash `SHA-512` do hash da senha associado ao `HTTP_USER_AGENT` do navegador (`includes/functions.php`).
- **Criptografia de Senhas**: Utilização de `password_hash()` e `password_verify()` com algoritmo Bcrypt.
- **Prevenção a SQL Injection**: Todas as queries e buscas no banco são executadas via **Prepared Statements** com PDO (`bindParam` / `bindValue`).
- **Hierarquia de Permissões**: 
  - Perfis divididos entre `admin` e `operador`, garantindo que operadores de pista não tenham acesso a margens de lucro, faturamento geral, precificação ou edição indevida de dados fiscais.

---

## 👥 Autoria e Suporte

**Trovão Brasil Comércio e Serviços Automotivos Ltda**  
- **CNPJ**: 50.075.298/0001-77  
- **Telefone**: (11) 4712-8839  
- **E-mail Operacional**: operacao.lavador@trovaobrasil.com.br  
- **Responsável Técnico / TI**: Alceu Matteucci Junior (`alceumatteuccijunior@gmail.com`)

---
<div align="center">
  <small>© 2026 TrovãoBrasil. Todos os direitos reservados. Sistema interno de uso exclusivo.</small>
</div>
