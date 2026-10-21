<?php
// Arquivo: templates/footer.php
?>
<!-- Fim do conteúdo específico da página -->
</div>
</div>
<!-- /#page-content-wrapper -->
</div>

<?php
// --- Modal de Novidades (Apenas para Admins) ---
if (isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'admin' && !isset($_COOKIE['novidades_v1_0'])) {
  ?>
  <!-- Modal de Novidades -->
  <div class="modal fade" id="novidadesModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="novidadesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark text-light border-primary shadow-lg">
        <div class="modal-header border-secondary text-primary">
          <h5 class="modal-title fw-bold" id="novidadesModalLabel"><i class="bi bi-stars me-2"></i>Novas Atualizações no
            Sistema!</h5>
        </div>
        <div class="modal-body p-4" style="font-size: 0.95rem;">
          <p class="mb-4">Olá! Implementamos diversas melhorias poderosas para facilitar sua gestão e controle. Veja o que
            mudou:</p>

          <h6 class="text-info fw-bold"><i class="bi bi-people-fill me-2"></i>1. Gestão de Usuários Aprimorada</h6>
          <p class="text-muted mb-3">Agora é possível marcar funcionários como <strong>"Desligados"</strong> para bloquear
            o acesso sem perder o histórico deles. Também adicionamos uma opção para escolher exatamente quais
            administradores vão receber os e-mails automáticos de alertas de empresas.</p>
          <p class="text-muted mb-3">Além disso, agora é possível verificar como anda o <strong>desempenho</strong>
            individual de cada funcionário. Basta acessar a tela "Profissionais" e clicar no icone de "gráfico" no
            profissional corresponde.</p>

          <h6 class="text-info fw-bold"><i class="bi bi-envelope-paper-fill me-2"></i>2. Alertas de CRM Automáticos</h6>
          <p class="text-muted mb-3">O sistema faz varreduras e envia e-mails diários de alerta (para os usuários
            configurados) avisando quais transportadoras estão precisando de atenção ou estão há muito tempo sem fazer
            serviços vitais como lavagem de chassi.</p>

          <h6 class="text-info fw-bold"><i class="bi bi-diagram-3-fill me-2"></i>3. Módulo "Teia de Aranha"
            (Rastreabilidade de Frota)</h6>
          <p class="text-muted mb-3">Sempre que uma OS é gerada, o sistema passa a capturar e salvar automaticamente a
            placa do caminhão (cavalo ou carreta). Adicionamos a aba <strong>Frota / Veículos</strong> no menu lateral,
            onde você consulta a "Teia de Aranha" completa: o histórico de gastos de uma placa, total de lavagens, empresa
            dona e todos os motoristas vinculados a ela. Essa rastreabilidade ponta a ponta será vital para nossa
            inteligência de negócio daqui para frente!</p>

          <h6 class="text-info fw-bold"><i class="bi bi-graph-up-arrow me-2"></i>4. Novos Gráficos e Desempenho</h6>
          <p class="text-muted mb-3">Na tela de <strong>Relatórios</strong>:
            <br>- Criamos um novo gráfico de <strong>Faturamento no Período</strong> que responde exatamente ao intervalo
            de datas e clientes que você filtrar, mostrando a evolução financeira real dia a dia.
            <br>- Adicionamos um gráfico de rosca mapeando os <strong>Motivos de Reprova</strong>.
            <br>- Aprimoramos o gráfico de <strong>Evolução da Equipe</strong>, que agora rastreia o histórico de 6 meses
            de rendimento dos seus top 5 funcionários atuais, ajudando na tomada de decisão sobre quem está melhorando ou
            piorando.
          </p>

          <h6 class="text-info fw-bold"><i class="bi bi-file-earmark-excel-fill me-2"></i>5. Exportação Customizada
            Expandida</h6>
          <p class="text-muted mb-0">Você agora tem o poder de exportar planilhas gerenciais informando se o veículo foi
            reprovado e os exatos motivos. Além disso, corrigimos os campos cruciais (como Data de Registro e 2º
            Responsável) que antes não saíam no Excel!</p>
        </div>
        <div class="modal-footer border-secondary d-flex justify-content-between align-items-center">
          <span class="text-muted small" id="novidadesTimer">Liberando em 10 segundos...</span>
          <button type="button" class="btn btn-primary px-4 fw-bold" id="btnFecharNovidades" data-bs-dismiss="modal"
            disabled>Entendi!</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var myModal = new bootstrap.Modal(document.getElementById('novidadesModal'));
      myModal.show();

      // Define o cookie para não mostrar novamente (válido por muito tempo)
      document.cookie = "novidades_v1_0=1; expires=Fri, 31 Dec 2030 23:59:59 GMT; path=/";

      var tempo = 10;
      var btn = document.getElementById('btnFecharNovidades');
      var timerDisplay = document.getElementById('novidadesTimer');

      var interval = setInterval(function () {
        tempo--;
        timerDisplay.innerText = "Você poderá fechar em " + tempo + " segundos...";
        if (tempo <= 0) {
          clearInterval(interval);
          btn.disabled = false;
          timerDisplay.innerText = "Você já pode fechar esta janela para usar o sistema.";
        }
      }, 1000);
    });
  </script>
<?php } ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js (Biblioteca de Gráficos) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Nosso JS customizado -->
<script src="../assets/js/scripts.js"></script>

</body>

</html>