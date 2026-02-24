<?php
// index.php

// Iniciar sessão para usar o padrão PRG (Post-Redirect-Get)
session_start();

// Importa o arquivo externo de funções
require_once __DIR__ . '/functions.php';

$feedbackMsg = '';
$feedbackClass = '';
$dadosPagamento = null;

// --- LÓGICA DE PROCESSAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Coleta dados
  $donor = htmlspecialchars($_POST['donor'] ?? '');
  $amountRaw = $_POST['amount'] ?? '0,00';
  $message = $_POST['message'] ?? '';
  $voiceId = $_POST['voice_id'] ?? '';

  // Formata valor para float
  $amountBrl = str_replace('.', '', $amountRaw);
  $amountBrl = str_replace(',', '.', $amountBrl);
  $amountFloat = floatval($amountBrl);

  // --- VALIDAÇÕES ---
  if ($amountFloat < 5) {
    $feedbackMsg = "O valor mínimo é R$ 5,00.";
    $feedbackClass = "error-msg show";
  } elseif (containsOffensiveWords($message) || containsOffensiveWords($donor)) {
    $feedbackMsg = "Sua mensagem ou nome contém termos não permitidos.";
    $feedbackClass = "error-msg show";
  } elseif (empty($voiceId)) {
    $feedbackMsg = "Por favor, selecione uma voz.";
    $feedbackClass = "error-msg show";
  } else {
    // Processa a doação (Cria o Pix)
    $resultado = processarDoacao($donor, $amountFloat, $message, $voiceId);

    if ($resultado['status'] === 'sucesso') {
      // Armazena os dados do pagamento na sessão
      $_SESSION['novo_pagamento'] = $resultado;

      // Redireciona para limpar o POST (Padrão PRG)
      header("Location: " . $_SERVER['REQUEST_URI']);
      exit;

    } else {
      $feedbackMsg = $resultado['msg'];
      $feedbackClass = "error-msg show";
    }
  }
}

// --- RECUPERAÇÃO DO ESTADO APÓS REDIRECT (GET) ---
if (isset($_SESSION['novo_pagamento'])) {
  $dadosPagamento = $_SESSION['novo_pagamento'];
  // Limpa a sessão para que o modal não abra novamente num F5 futuro
  unset($_SESSION['novo_pagamento']);
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />

  <meta property="og:title" content="Vitto" />
  <meta property="og:description" content="Doe na Live do Vitto" />
  <meta property="og:image" content="https://vittozao.com/assets/vitto-pic-1.jpg" />
  <meta property="og:url" content="URL_do_seu_site" />
  <meta property="og:type" content="website" />

  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Vitto" />
  <meta name="twitter:description" content="Doe na Live do Vitto" />
  <meta name="twitter:image" content="https://vittozao.com/assets/vitto-pic-1.jpg" />
  <meta name="twitter:url" content="URL_do_seu_site" />

  <link rel="shortcut icon" type="image/svg" href="https://vittozao.com/assets/favicon.ico" />
  <title>Donate pro Vitto</title>

  <style>
    /* ... (CSS MANTIDO IGUAL AO ORIGINAL) ... */
    :root {
      --bg: #0a0e13;
      --card: rgba(255, 255, 255, .04);
      --border: rgba(255, 255, 255, .08);
      --text: rgba(255, 255, 255, .95);
      --muted: rgba(255, 255, 255, .55);
      --accent: #60d394;
      --accent-dim: rgba(96, 211, 148, .15);
      --danger: #ff5a5a;
      --warning: #ffa726;
      --shadow: 0 20px 60px rgba(0, 0, 0, .4);
      --r: 16px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: var(--text);
      background:
        radial-gradient(1000px at 10% 10%, rgba(96, 211, 148, .08), transparent),
        var(--bg);
      line-height: 1.6;
    }

    .container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
    }

    .main {
      width: 100%;
      max-width: 540px;
    }

    .header {
      text-align: center;
      margin-bottom: 32px;
    }

    .logo {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      margin: 0 auto 16px;
      background: var(--card);
      border: 1px solid var(--border);
      padding: 8px;
    }

    h1 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 4px;
      letter-spacing: -.5px;
    }

    .subtitle {
      color: var(--muted);
      font-size: 14px;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--r);
      padding: 24px;
      box-shadow: var(--shadow);
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--muted);
      margin-bottom: 8px;
    }

    input,
    textarea {
      width: 100%;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: rgba(0, 0, 0, .2);
      color: var(--text);
      padding: 12px 14px;
      font-size: 15px;
      outline: none;
      transition: border-color .2s;
    }

    input:focus,
    textarea:focus {
      border-color: var(--accent);
    }

    textarea {
      min-height: 100px;
      resize: vertical;
      font-family: inherit;
    }

    .char-count {
      text-align: right;
      font-size: 12px;
      color: var(--muted);
      margin-top: 6px;
    }

    .char-count.warning {
      color: var(--warning);
    }

    .char-count.danger {
      color: var(--danger);
    }

    .voice-section {
      margin-bottom: 20px;
    }

    .voice-label {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .voice-label span {
      font-size: 12px;
      color: var(--muted);
    }

    .voice-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
      gap: 10px;
    }

    .voice-item {
      aspect-ratio: 1;
      border-radius: 12px;
      border: 2px solid transparent;
      background: rgba(0, 0, 0, .2);
      cursor: pointer;
      transition: all .2s;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 8px;
      position: relative;
    }

    .voice-item:hover {
      background: rgba(255, 255, 255, .06);
      transform: translateY(-2px);
    }

    .voice-item.selected {
      border-color: var(--accent);
      background: var(--accent-dim);
    }

    .voice-avatar {
      width: 42px;
      height: 42px;
      border-radius: 10px;
      background: linear-gradient(135deg, rgba(96, 211, 148, .2), rgba(255, 107, 181, .2));
      display: grid;
      place-items: center;
      font-weight: 700;
      font-size: 16px;
      overflow: hidden;
    }

    .voice-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .voice-name {
      font-size: 11px;
      font-weight: 600;
      text-align: center;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      width: 100%;
    }

    .play-btn {
      position: absolute;
      top: 6px;
      right: 6px;
      width: 24px;
      height: 24px;
      border-radius: 6px;
      background: rgba(0, 0, 0, .6);
      border: none;
      color: white;
      cursor: pointer;
      display: grid;
      place-items: center;
      font-size: 10px;
      opacity: 0;
      transition: opacity .2s;
    }

    .voice-item:hover .play-btn {
      opacity: 1;
    }

    .btn {
      width: 100%;
      padding: 14px;
      border-radius: 12px;
      border: none;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
      background: var(--accent);
      color: #0a0e13;
    }

    .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 20px rgba(96, 211, 148, .3);
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.1);
      color: var(--text);
    }

    .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.15);
    }

    .error-msg,
    .success-msg {
      padding: 12px 14px;
      border-radius: 10px;
      font-size: 13px;
      margin-top: 16px;
      display: none;
    }

    .error-msg {
      background: rgba(255, 90, 90, .15);
      border: 1px solid rgba(255, 90, 90, .3);
      color: #ffb3b3;
    }

    .show {
      display: block !important;
    }

    @media (max-width: 600px) {
      .voice-grid {
        grid-template-columns: repeat(auto-fill, minmax(75px, 1fr));
        gap: 8px;
      }
    }

    /* --- MODAL PIX --- */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.85);
      backdrop-filter: blur(5px);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .modal-overlay.open {
      display: flex;
      opacity: 1;
    }

    .modal-content {
      background: #15191f;
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 30px;
      width: 100%;
      max-width: 400px;
      text-align: center;
      box-shadow: 0 30px 80px rgba(0, 0, 0, 0.8);
      position: relative;
      transform: scale(0.9);
      transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .modal-overlay.open .modal-content {
      transform: scale(1);
    }

    .qr-container {
      background: white;
      padding: 15px;
      border-radius: 12px;
      margin: 20px auto;
      width: 220px;
      height: 220px;
    }

    .qr-container img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .pix-copy-box {
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px;
      font-family: monospace;
      font-size: 11px;
      color: var(--muted);
      word-break: break-all;
      max-height: 60px;
      overflow-y: auto;
      margin-bottom: 15px;
      text-align: left;
    }

    .close-modal {
      position: absolute;
      top: 15px;
      right: 15px;
      background: none;
      border: none;
      color: var(--muted);
      font-size: 20px;
      cursor: pointer;
    }

    .status-badge {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      background: rgba(255, 167, 38, 0.15);
      color: var(--warning);
      border: 1px solid rgba(255, 167, 38, 0.3);
      margin-bottom: 10px;
    }

    .status-badge.paid {
      background: rgba(96, 211, 148, 0.15);
      color: #60d394;
      border: 1px solid rgba(96, 211, 148, 0.3);
    }

    .copy-btn {
      background: var(--accent);
      color: #000;
      border: none;
      padding: 10px;
      border-radius: 8px;
      font-weight: 700;
      cursor: pointer;
      width: 100%;
      font-size: 14px;
      transition: all .2s;
    }

    .copy-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(96, 211, 148, .3);
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="main">
      <div class="header">
        <img class="logo" src="assets/images/vitto.webp" alt="Logo" onerror="this.style.display='none'" />
        <h1>Donate pro Vitto</h1>
        <p class="subtitle">Pix com voz de IA</p>
      </div>

      <form method="POST" action="" class="card">

        <div class="form-group">
          <label>Seu nome</label>
          <input id="donor" name="donor" type="text" placeholder="Ex: Lucas" maxlength="30" autocomplete="name" required
            value="<?php echo isset($_POST['donor']) ? htmlspecialchars($_POST['donor']) : ''; ?>" />
        </div>

        <div class="form-group">
          <label>Valor (mínimo R$ 5,00)</label>
          <input id="amount" name="amount" type="text" placeholder="5,00" inputmode="decimal" required
            value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>" />
        </div>

        <div class="form-group">
          <label>Mensagem</label>
          <textarea id="message" name="message" maxlength="200" placeholder="Sua mensagem será lida na live..."
            required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
          <div class="char-count" id="charCount">0/200</div>
        </div>

        <div class="voice-section">
          <div class="voice-label">
            <label style="margin:0;">Escolha a voz</label>
            <span id="voiceSelected">Nenhuma selecionada</span>
          </div>

          <input type="hidden" id="voiceInput" name="voice_id" value="">

          <div class="voice-grid" id="voiceGrid"></div>
        </div>

        <button class="btn" type="submit" id="payBtn">Enviar Mensagem</button>

        <?php if ($feedbackMsg && $feedbackClass): ?>
          <div class="<?php echo $feedbackClass; ?>">
            <?php echo $feedbackMsg; ?>
          </div>
        <?php endif; ?>

      </form>

    </div>
  </div>

  <div id="pixModal" class="modal-overlay">
    <div class="modal-content">
      <button class="close-modal" onclick="fecharModal()">×</button>

      <h3 style="margin-bottom:5px;">Pagamento Pix</h3>
      <div id="statusBadge" class="status-badge">Aguardando pagamento...</div>

      <div id="modalBodyContent">
        <div class="qr-container">
          <img id="qrImage" src="" alt="QR Code" />
        </div>

        <label style="font-size:12px; display:block; text-align:left; margin-bottom:5px;">Copia e Cola:</label>
        <div class="pix-copy-box" id="pixCopyText"></div>

        <button class="copy-btn" onclick="copiarPix()">Copiar Código</button>
      </div>

      <div id="successContent" style="display:none; padding: 20px 0;">
        <div style="font-size: 50px;">🎉</div>
        <h2 style="color:var(--accent); margin: 10px 0;">Doação Recebida!</h2>
        <p style="color:var(--muted); font-size:14px;">Sua mensagem foi enviada para a live.</p>
        <button class="btn btn-secondary" onclick="fecharModal()" style="margin-top:20px;">Fechar</button>
      </div>
    </div>
  </div>

  <audio id="previewAudio" preload="none"></audio>

  <script>
    const VOICES = <?php echo file_exists(__DIR__ . '/assets/voices.json') ? file_get_contents(__DIR__ . '/assets/voices.json') : '[]'; ?>;

    const elements = {
      donor: document.getElementById('donor'),
      amount: document.getElementById('amount'),
      message: document.getElementById('message'),
      charCount: document.getElementById('charCount'),
      voiceGrid: document.getElementById('voiceGrid'),
      voiceSelected: document.getElementById('voiceSelected'),
      voiceInput: document.getElementById('voiceInput'),
      payBtn: document.getElementById('payBtn'),
      previewAudio: document.getElementById('previewAudio'),
      pixModal: document.getElementById('pixModal'),
      qrImage: document.getElementById('qrImage'),
      pixCopyText: document.getElementById('pixCopyText'),
      modalBodyContent: document.getElementById('modalBodyContent'),
      successContent: document.getElementById('successContent'),
      statusBadge: document.getElementById('statusBadge')
    };

    // --- FUNÇÕES DO MODAL ---
    function abrirModal(qrBase64, copiaCola) {
      if (!qrBase64) {
        console.error('QR Code não fornecido!');
        return;
      }

      console.log('Abrindo modal com QR Code...');
      elements.qrImage.src = "data:image/png;base64," + qrBase64;
      elements.pixCopyText.textContent = copiaCola;

      elements.pixModal.classList.add('open');
    }

    function fecharModal() {
      elements.pixModal.classList.remove('open');
      // Remove parâmetro GET e recarrega limpo
      window.location.href = window.location.href.split('?')[0];
    }

    function copiarPix() {
      const codigo = elements.pixCopyText.textContent;
      navigator.clipboard.writeText(codigo).then(() => {
        const btn = document.querySelector('.copy-btn');
        const original = btn.textContent;
        btn.textContent = "✓ Copiado!";
        setTimeout(() => btn.textContent = original, 2000);
      });
    }

    // --- VERIFICAÇÃO DE PAGAMENTO EM TEMPO REAL (Para o Modal) ---
    let intervalId = null;
    let currentOrderId = null;

    function startPolling(id) {
      if (!id) return;
      currentOrderId = id;
      console.log("Monitorando pagamento:", id);

      intervalId = setInterval(() => {
        fetch(`check_status.php?id=${id}`)
          .then(r => r.json())
          .then(data => {
            console.log('Status:', data);
            if (data.status === 'approved') {
              paymentApproved();
            } else if (data.status === 'cancelled' || data.status === 'rejected') {
              paymentCancelled();
            }
          })
          .catch(err => console.error('Erro no polling:', err));
      }, 4000);
    }

    function paymentApproved() {
      clearInterval(intervalId);

      elements.statusBadge.textContent = "✓ PAGO";
      elements.statusBadge.classList.add('paid');

      elements.modalBodyContent.style.display = 'none';
      elements.successContent.style.display = 'block';
    }

    function paymentCancelled() {
      clearInterval(intervalId);
      elements.statusBadge.textContent = "CANCELADO";
      elements.statusBadge.style.color = 'var(--danger)';
    }

    // --- ABRE MODAL SE PAGAMENTO FOI CRIADO ---
    <?php if (isset($dadosPagamento) && isset($dadosPagamento['qr_code_base64']) && isset($dadosPagamento['order_id'])): ?>
      console.log('Pagamento criado! Abrindo modal...');

      abrirModal(
        "<?php echo $dadosPagamento['qr_code_base64']; ?>",
        "<?php echo addslashes($dadosPagamento['qr_code_copia_cola']); ?>"
      );

      startPolling("<?php echo $dadosPagamento['order_id']; ?>");
    <?php endif; ?>

    // --- VOZES ---
    let serverSelectedVoice = "<?php echo isset($_POST['voice_id']) ? htmlspecialchars($_POST['voice_id']) : ''; ?>";
    let selectedVoice = serverSelectedVoice || (VOICES[0]?.id || '');

    function renderVoices() {
      elements.voiceGrid.innerHTML = '';
      if (VOICES.length === 0) {
        elements.voiceGrid.innerHTML = '<p style="color:var(--muted); font-size:12px;">Nenhuma voz carregada.</p>';
        return;
      }
      VOICES.forEach((voice, idx) => {
        const item = document.createElement('div');
        item.className = 'voice-item';
        if (voice.id === selectedVoice) {
          item.classList.add('selected');
          elements.voiceSelected.textContent = voice.label;
          elements.voiceInput.value = voice.id;
        }
        item.innerHTML = `
          <div class="voice-avatar">
            ${voice.image ? `<img src="${voice.image}" alt="${voice.label}">` : idx + 1}
          </div>
          <div class="voice-name">${voice.label}</div>
          <button type="button" class="play-btn" onclick="playPreview('${voice.previewAudio}', event)">▶</button>
        `;
        item.onclick = () => selectVoice(voice.id, voice.label);
        elements.voiceGrid.appendChild(item);
      });
    }

    function selectVoice(id, label) {
      selectedVoice = id;
      elements.voiceSelected.textContent = label;
      elements.voiceInput.value = id;
      document.querySelectorAll('.voice-item').forEach(item => {
        item.classList.remove('selected');
      });
      event.currentTarget.classList.add('selected');
    }

    function playPreview(url, e) {
      e.stopPropagation();
      if (url) {
        elements.previewAudio.src = url;
        elements.previewAudio.play();
      }
    }

    // --- INPUTS ---
    elements.message.oninput = () => {
      const len = elements.message.value.length;
      elements.charCount.textContent = `${len}/200`;
      elements.charCount.className = 'char-count';
      if (len > 180) elements.charCount.classList.add('danger');
      else if (len > 150) elements.charCount.classList.add('warning');
    };

    elements.amount.oninput = (e) => {
      let v = e.target.value.replace(/\D/g, '');
      v = (parseInt(v || 0) / 100).toFixed(2);
      e.target.value = v.replace('.', ',');
    };

    renderVoices();
  </script>
</body>

</html>