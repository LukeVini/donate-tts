<?php
// Configuração do Mercado Pago
$access_token = 'APP_USR-1548246959832477-012011-f6bfb0894df2d3d301e8525ad9cc665c-3146278121';

// Handler para criar pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'criar_pagamento') {
    header('Content-Type: application/json; charset=utf-8');
    
    $input = file_get_contents('php://input');
    $dados = json_decode($input, true);
    
    if (!$dados) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos']);
        exit;
    }
    
    $valor = floatval($dados['amountValue'] ?? 0);
    $donor = $dados['donor'] ?? 'Anônimo';
    $message = $dados['message'] ?? '';
    $voiceId = $dados['voiceId'] ?? '';
    $voiceLabel = $dados['voiceLabel'] ?? '';
    
    if ($valor < 5.00) {
        http_response_code(400);
        echo json_encode(['error' => 'Valor mínimo é R$ 5,00']);
        exit;
    }
    
    // Tratamento seguro de strings para não quebrar JSON
    $safe_donor = mb_substr($donor, 0, 50, 'UTF-8');
    $safe_message = mb_substr($message, 0, 200, 'UTF-8');
    
    $idempotency_key = uniqid('donate_tts_', true);
    $external_reference = 'donate_' . time() . '_' . substr(md5($donor . $message), 0, 8);
    
    // PAYLOAD CORRIGIDO (Estrutura idêntica à documentação de Orders)
    $payload = [
        "type" => "online",
        "external_reference" => $external_reference,
        "total_amount" => number_format($valor, 2, '.', ''),
        "payer" => [
            "email" => "doador@donate-tts.com",
            "first_name" => $safe_donor
        ],
        "transactions" => [
            "payments" => [
                [
                    "amount" => number_format($valor, 2, '.', ''),
                    "description" => "Doação TTS: " . $safe_message, // Descrição movida para cá
                    "payment_method" => [
                        "id" => "pix",
                        "type" => "bank_transfer"
                    ]
                ]
            ]
        ],
        "metadata" => [
            "voice_id" => $voiceId,
            "voice_label" => $voiceLabel,
            "message" => $safe_message,
            "donor" => $safe_donor,
            "created_at" => date('Y-m-d H:i:s')
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/orders');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // --- IMPORTANTE: FALSE para rodar em Localhost (XAMPP/WAMP) ---
    // Em produção (servidor real com HTTPS), mude para true.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
        'X-Idempotency-Key: ' . $idempotency_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro na comunicação: ' . $curl_error]);
        exit;
    }
    
    $mp_response = json_decode($response, true);
    
    if ($http_code !== 200 && $http_code !== 201) {
        http_response_code($http_code);
        echo json_encode(['error' => 'Erro ao criar pagamento', 'details' => $mp_response]);
        exit;
    }
    
    $pix_data = null;
    if (isset($mp_response['transactions']['payments'][0]['point_of_interaction']['transaction_data'])) {
        $transaction_data = $mp_response['transactions']['payments'][0]['point_of_interaction']['transaction_data'];
        $pix_data = [
            'qr_code' => $transaction_data['qr_code'] ?? null,
            'qr_code_base64' => $transaction_data['qr_code_base64'] ?? null,
            'ticket_url' => $transaction_data['ticket_url'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'order_id' => $mp_response['id'] ?? null,
        'external_reference' => $external_reference,
        'status' => $mp_response['status'] ?? 'pending',
        'pix' => $pix_data,
        'donation' => [
            'donor' => $safe_donor,
            'amount' => $valor,
            'message' => $safe_message,
            'voice' => $voiceLabel
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Handler para verificar pagamento
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'verificar_pagamento') {
    header('Content-Type: application/json; charset=utf-8');
    
    $order_id = $_GET['order_id'] ?? '';
    
    if (empty($order_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'order_id é obrigatório']);
        exit;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/orders/{$order_id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // --- IMPORTANTE: FALSE para rodar em Localhost ---
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        http_response_code($http_code);
        echo json_encode(['error' => 'Pedido não encontrado']);
        exit;
    }
    
    $order_data = json_decode($response, true);
    $status = $order_data['status'] ?? 'unknown';
    $payment_status = 'pending';
    $payment_id = null;
    
    if (isset($order_data['transactions']['payments'][0])) {
        $payment = $order_data['transactions']['payments'][0];
        $payment_status = $payment['status'] ?? 'pending';
        $payment_id = $payment['id'] ?? null;
    }
    
    echo json_encode([
        'order_id' => $order_data['id'] ?? null,
        'external_reference' => $order_data['external_reference'] ?? null,
        'status' => $status,
        'payment_status' => $payment_status,
        'payment_id' => $payment_id,
        'paid' => in_array($payment_status, ['approved', 'authorized']),
        'metadata' => $order_data['metadata'] ?? [],
        'checked_at' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Donate (TTS)</title>

  <style>
    :root{
      --bg: #0b0f14;
      --card: rgba(255,255,255,.06);
      --card2: rgba(255,255,255,.08);
      --border: rgba(255,255,255,.12);
      --text: rgba(255,255,255,.92);
      --muted: rgba(255,255,255,.65);
      --danger: rgba(255,90,90,.95);
      --ok: rgba(80,220,140,.95);

      --shadow: 0 24px 80px rgba(0,0,0,.55);
      --r: 18px;
      --r2: 14px;

      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
    }

    *{ box-sizing:border-box; }
    html,body{ height:100%; }
    body{
      margin:0;
      color: var(--text);
      background:
        radial-gradient(1100px 700px at 12% 8%, rgba(120,170,255,.14), transparent 55%),
        radial-gradient(900px 600px at 85% 18%, rgba(255,120,200,.12), transparent 55%),
        var(--bg);
    }

    .wrap{
      min-height:100%;
      display:flex;
      align-items:center;
      justify-content:center;
      padding: 28px 16px;
    }

    .shell{
      width: 100%;
      max-width: 760px;
      display:grid;
      gap: 14px;
    }

    .brand{
      display:flex;
      align-items:center;
      justify-content:center;
      gap: 12px;
      opacity: .95;
      user-select:none;
    }

    .brand img{
      width: 42px;
      height: 42px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(0,0,0,.25);
      padding: 6px;
    }

    .brand .t{
      display:flex;
      flex-direction:column;
      line-height:1.05;
    }

    .brand .t b{ font-size: 14px; letter-spacing:.2px; }
    .brand .t span{ font-size: 12px; color: var(--muted); }

    .card{
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--r);
      box-shadow: var(--shadow);
      padding: 18px;
    }

    .cardHeader{
      display:flex;
      align-items:baseline;
      justify-content:space-between;
      gap: 10px;
      margin-bottom: 10px;
    }
    .cardHeader h1{
      margin:0;
      font-size: 16px;
      letter-spacing:.2px;
    }
    .cardHeader p{
      margin:0;
      font-size: 12px;
      color: var(--muted);
    }

    label{
      display:block;
      font-size: 12px;
      color: var(--muted);
      margin: 10px 0 6px;
    }

    input, textarea{
      width:100%;
      border-radius: var(--r2);
      border: 1px solid var(--border);
      background: var(--card2);
      color: var(--text);
      padding: 12px 12px;
      outline:none;
    }

    textarea{
      min-height: 110px;
      resize: vertical;
    }

    .row{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .hint{
      margin-top: 8px;
      font-size: 12px;
      color: rgba(255,255,255,.62);
      line-height:1.35;
    }

    .counter{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap: 10px;
      margin-top: 6px;
      font-size: 12px;
      color: rgba(255,255,255,.62);
    }

    .counter .bad{ color: rgba(255,180,180,.95); font-weight: 800; }
    .counter .ok{ color: rgba(180,255,210,.95); font-weight: 800; }

    .voices{
      margin-top: 10px;
      border-radius: var(--r2);
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(0,0,0,.18);
      padding: 12px;
    }

    .voicesTop{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      margin-bottom: 10px;
    }
    .voicesTop b{ font-size: 12px; color: rgba(255,255,255,.86); }
    .voicesTop span{ font-size: 12px; color: var(--muted); }

    .voicesActions{
      display:flex;
      align-items:center;
      gap: 10px;
    }

    .miniBtn{
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.88);
      padding: 8px 12px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .2px;
      user-select: none;
      transition: transform .06s ease, background .15s ease;
      display:inline-flex;
      align-items:center;
      gap: 8px;
      white-space: nowrap;
    }
    .miniBtn:hover{ background: rgba(255,255,255,.10); }
    .miniBtn:active{ transform: translateY(1px); }

    .carouselRow{
      display:flex;
      align-items:stretch;
      gap: 10px;
    }

    .navBtn{
      width: 42px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.85);
      cursor:pointer;
      user-select:none;
      transition: transform .06s ease, background .15s ease;
      display:grid;
      place-items:center;
      font-weight: 900;
      padding: 0;
    }
    .navBtn:hover{ background: rgba(255,255,255,.10); }
    .navBtn:active{ transform: translateY(1px); }
    .navBtn[disabled]{ opacity:.35; cursor:not-allowed; }

    .voiceCarousel{
      flex: 1;
      overflow-x: auto;
      overflow-y: hidden;
      scroll-snap-type: x mandatory;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;

      border-radius: 14px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(0,0,0,.16);
      padding: 10px;
    }

    .voiceTrack{
      display:grid;
      grid-auto-flow: column;
      grid-auto-columns: calc((100% - 20px) / 3);
      gap: 10px;
      align-items: stretch;
    }

    .voiceCard{
      scroll-snap-align: start;
      border-radius: 16px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(255,255,255,.05);
      padding: 12px;
      cursor:pointer;
      user-select:none;
      transition: transform .06s ease, background .15s ease, border-color .15s ease;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      min-width: 0;
    }
    .voiceCard:hover{ background: rgba(255,255,255,.08); }
    .voiceCard:active{ transform: translateY(1px); }

    .voiceLeft{
      display:flex;
      gap: 10px;
      align-items:center;
      min-width: 0;
      flex: 1;
    }

    .voiceDot{
      width: 38px;
      height: 38px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,.14);
      background: radial-gradient(22px 22px at 30% 30%, rgba(120,170,255,.35), transparent 55%),
                  radial-gradient(22px 22px at 70% 60%, rgba(255,120,200,.25), transparent 55%),
                  rgba(0,0,0,.25);
      display:grid;
      place-items:center;
      font-weight: 900;
      letter-spacing:.2px;
      font-size: 12px;
      color: rgba(255,255,255,.9);
      flex: 0 0 auto;
      overflow:hidden;
    }

    .voiceAvatar{
      width: 100%;
      height: 100%;
      border-radius: 12px;
      object-fit: cover;
      display:block;
    }

    .voiceInfo{
      display:flex;
      flex-direction:column;
      gap: 2px;
      min-width:0;
      flex: 1;
    }
    .voiceInfo .name{
      font-size: 13px;
      font-weight: 900;
      color: rgba(255,255,255,.92);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .voiceInfo .id{
      font-size: 11px;
      color: rgba(255,255,255,.55);
      white-space: nowrap;
      overflow:hidden;
      text-overflow: ellipsis;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }

    .playBtn{
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.88);
      width: 38px;
      height: 38px;
      cursor:pointer;
      user-select:none;
      transition: transform .06s ease, background .15s ease;
      display:grid;
      place-items:center;
      font-weight: 900;
      flex: 0 0 auto;
      line-height: 0.5;
    }
    .playBtn:hover{ background: rgba(255,255,255,.10); }
    .playBtn:active{ transform: translateY(1px); }

    .voiceCard.selected{
      border-color: rgba(120,255,170,.35);
      background: rgba(80,220,140,.10);
    }

    .actions{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 14px;
    }

    button{
      width: 100%;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: rgba(255,255,255,.08);
      color: var(--text);
      padding: 12px 16px;
      cursor:pointer;
      transition: transform .06s ease, background .15s ease;
      user-select:none;
      font-weight: 900;
      letter-spacing:.2px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap: 10px;
    }
    button:hover{ background: rgba(255,255,255,.12); }
    button:active{ transform: translateY(1px); }
    button:disabled{ opacity:.5; cursor:not-allowed; }

    .primary{
      border-color: rgba(120,255,170,.35);
      background: rgba(80,220,140,.12);
    }
    .primary:hover{ background: rgba(80,220,140,.16); }

    .msg{
      margin-top: 10px;
      font-size: 12px;
      color: rgba(255,255,255,.62);
      line-height:1.35;
    }

    .error{
      display:none;
      margin-top: 10px;
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid rgba(255,120,120,.25);
      background: rgba(255,80,80,.10);
      color: rgba(255,200,200,.95);
      font-size: 12px;
      font-weight: 800;
    }

    .okBox{
      display:none;
      margin-top: 10px;
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid rgba(120,255,170,.25);
      background: rgba(80,220,140,.10);
      color: rgba(200,255,225,.95);
      font-size: 12px;
      font-weight: 800;
      white-space: pre-wrap;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }

    .modalBackdrop{
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.60);
      backdrop-filter: blur(6px);
      display:none;
      align-items:center;
      justify-content:center;
      padding: 18px;
      z-index: 999;
    }
    .modalBackdrop.show{ display:flex; }

    .modal{
      width: 100%;
      max-width: 820px;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.16);
      background: rgba(15,18,24,.92);
      box-shadow: 0 30px 100px rgba(0,0,0,.65);
      overflow:hidden;
    }

    .modalHeader{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      padding: 14px 14px;
      border-bottom: 1px solid rgba(255,255,255,.12);
    }
    .modalHeader b{ font-size: 13px; letter-spacing:.2px; }
    .modalHeader .closeBtn{
      width: 40px;
      height: 40px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.88);
      cursor:pointer;
      user-select:none;
      display:grid;
      place-items:center;
      font-weight: 900;
    }
    .modalHeader .closeBtn:hover{ background: rgba(255,255,255,.10); }

    .modalBody{
      padding: 14px;
      display:grid;
      gap: 12px;
    }

    .modalGrid{
      display:grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    /* Modal de Pagamento PIX */
    .pixModal{
      max-width: 480px;
    }

    .pixContent{
      display:flex;
      flex-direction:column;
      align-items:center;
      gap: 16px;
      text-align:center;
    }

    .qrCodeBox{
      width: 280px;
      height: 280px;
      border-radius: 16px;
      border: 1px solid rgba(255,255,255,.14);
      background: white;
      padding: 16px;
      display:grid;
      place-items:center;
    }

    .qrCodeBox img{
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .pixCode{
      width: 100%;
      padding: 12px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: var(--card2);
      color: var(--text);
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: 11px;
      word-break: break-all;
      text-align: center;
    }

    .statusBadge{
      padding: 8px 16px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .2px;
    }

    .statusBadge.pending{
      background: rgba(255,200,100,.15);
      border: 1px solid rgba(255,200,100,.3);
      color: rgba(255,220,150,.95);
    }

    .statusBadge.approved{
      background: rgba(80,220,140,.15);
      border: 1px solid rgba(80,220,140,.3);
      color: rgba(200,255,225,.95);
    }

    @media (max-width: 760px){
      .row{ grid-template-columns: 1fr; }
      .actions{ grid-template-columns: 1fr; }
      .voiceTrack{ grid-auto-columns: calc((100% - 10px) / 2); }
      .modalGrid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .qrCodeBox{ width: 240px; height: 240px; }
    }
  </style>
</head>

<body>
  <main class="wrap">
    <section class="shell">
      <header class="brand">
        <img src="assets/images/vitto.webp" alt="logo" onerror="this.src='https://via.placeholder.com/42'" />
        <div class="t">
          <b>Donate (TTS)</b>
          <span>Pagamento via PIX + voz de IA</span>
        </div>
      </header>

      <section class="card">
        <div class="cardHeader">
          <h1>Envie um donate</h1>
          <p>Mínimo R$ 5,00 • Mensagem até 200 chars</p>
        </div>

        <div class="row">
          <div>
            <label for="donor">Seu nome</label>
            <input id="donor" autocomplete="name" placeholder="Ex: Lucas" maxlength="30" />
          </div>

          <div>
            <label for="amount">Valor (R$)</label>
            <input id="amount" inputmode="decimal" placeholder="Ex: 5,00" />
            <div class="hint">Valor mínimo: <b>R$ 5,00</b></div>
          </div>
        </div>

        <label for="message">Mensagem</label>
        <textarea id="message" maxlength="200" placeholder="Ex: valeu demais, irmão!"></textarea>
        <div class="counter">
          <span id="msgHint">A mensagem será lida por IA.</span>
          <span id="msgCount" class="ok">0/200</span>
        </div>

        <div class="voices" aria-label="Selecionar voz">
          <div class="voicesTop">
            <div>
              <b>Voz</b>
              <span id="voiceSelectedLabel" style="margin-left:10px;">Selecionada: —</span>
            </div>

            <div class="voicesActions">
              <button class="miniBtn" id="previewSelectedBtn" type="button">▶ Preview</button>
              <button class="miniBtn" id="viewAllBtn" type="button">Ver todas</button>
            </div>
          </div>

          <div class="carouselRow">
            <button class="navBtn" id="prevBtn" type="button" aria-label="Anterior">‹</button>

            <div class="voiceCarousel" id="voiceCarousel">
              <div class="voiceTrack" id="voiceTrack"></div>
            </div>

            <button class="navBtn" id="nextBtn" type="button" aria-label="Próximo">›</button>
          </div>

          <div class="hint">
            Clique em ▶ para ouvir um preview da voz selecionada.
          </div>
        </div>

        <div class="actions">
          <button class="primary" id="payBtn" type="button">Ir para pagamento PIX</button>
          <button id="previewBtn" type="button">Gerar JSON (teste)</button>
        </div>

        <div class="msg">
          Após validar, você receberá um QR Code PIX para pagamento.
        </div>

        <div class="error" id="errorBox"></div>
        <div class="okBox" id="okBox"></div>

        <audio id="previewAudio" preload="none"></audio>
      </section>
    </section>
  </main>

  <div class="modalBackdrop" id="modalBackdrop" role="dialog" aria-modal="true" aria-label="Todas as vozes">
    <div class="modal">
      <div class="modalHeader">
        <b>Todas as vozes</b>
        <button class="closeBtn" id="closeModalBtn" type="button" aria-label="Fechar">✕</button>
      </div>
      <div class="modalBody">
        <div class="hint" style="margin:0;">
          Clique para selecionar. Use ▶ dentro do card para preview.
        </div>
        <div class="modalGrid" id="modalGrid"></div>
      </div>
    </div>
  </div>

  <div class="modalBackdrop" id="pixModalBackdrop" role="dialog" aria-modal="true" aria-label="Pagamento PIX">
    <div class="modal pixModal">
      <div class="modalHeader">
        <b>Pagamento PIX</b>
        <button class="closeBtn" id="closePixModalBtn" type="button" aria-label="Fechar">✕</button>
      </div>
      <div class="modalBody">
        <div class="pixContent">
          <div id="statusBadge" class="statusBadge pending">Aguardando pagamento...</div>
          
          <div class="qrCodeBox" id="qrCodeBox">
            <img id="qrCodeImg" src="" alt="QR Code PIX" style="display:none;" />
            <div id="qrCodeLoading" style="color:#333;">Gerando QR Code...</div>
          </div>

          <div style="width:100%;">
            <label style="margin-bottom:8px;">Código PIX Copia e Cola</label>
            <div class="pixCode" id="pixCode">Gerando...</div>
          </div>

          <button class="primary" id="copyPixBtn" type="button" style="width:100%;">
            📋 Copiar código PIX
          </button>

          <div class="hint" style="margin:0;">
            <b>Ordem #<span id="orderIdDisplay">—</span></b><br>
            O pagamento será verificado automaticamente.
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const VOICES = [
      {
        label: "Vitto",
        id: "IwDvd6l61AFDUFrrOmp6",
        image: "assets/images/vitto.webp",
        previewAudio: "assets/voices/vitto.mp3",
      },
      {
        label: "BRKSEDU",
        id: "RpoRH62g4guuflYcCN0i",
        image: "assets/images/brksedu.jpg",
        previewAudio: "assets/voices/brksedu.mp3",
      },
      {
        label: "WaveIGL",
        id: "S90MFu1lps7paZBjXCSO",
        image: "assets/images/wave.jpg",
        previewAudio: "assets/voices/wave.mp3",
      },
      {
        label: "Fallen",
        id: "dncAD6dCMrVEIikmjkOh",
        image: "assets/images/fallen.jpg",
        previewAudio: "assets/voices/fallen.mp3",
      },
    ];

    const MIN_BRL = 5.00;
    const MAX_MESSAGE = 200;

    const donorEl = document.getElementById("donor");
    const amountEl = document.getElementById("amount");
    const messageEl = document.getElementById("message");
    const msgCountEl = document.getElementById("msgCount");

    const voiceSelectedLabelEl = document.getElementById("voiceSelectedLabel");
    const voiceCarouselEl = document.getElementById("voiceCarousel");
    const voiceTrackEl = document.getElementById("voiceTrack");

    const prevBtn = document.getElementById("prevBtn");
    const nextBtn = document.getElementById("nextBtn");
    const viewAllBtn = document.getElementById("viewAllBtn");
    const previewSelectedBtn = document.getElementById("previewSelectedBtn");

    const modalBackdrop = document.getElementById("modalBackdrop");
    const closeModalBtn = document.getElementById("closeModalBtn");
    const modalGridEl = document.getElementById("modalGrid");

    const pixModalBackdrop = document.getElementById("pixModalBackdrop");
    const closePixModalBtn = document.getElementById("closePixModalBtn");
    const qrCodeImg = document.getElementById("qrCodeImg");
    const qrCodeLoading = document.getElementById("qrCodeLoading");
    const pixCode = document.getElementById("pixCode");
    const copyPixBtn = document.getElementById("copyPixBtn");
    const statusBadge = document.getElementById("statusBadge");
    const orderIdDisplay = document.getElementById("orderIdDisplay");

    const errorBox = document.getElementById("errorBox");
    const okBox = document.getElementById("okBox");

    const payBtn = document.getElementById("payBtn");
    const previewBtn = document.getElementById("previewBtn");

    const previewAudio = document.getElementById("previewAudio");

    let selectedVoiceId = VOICES[0]?.id ?? "";
    let currentPreviewUrl = null;
    let currentOrderId = null;
    let paymentCheckInterval = null;

    // ===== Alerts =====
    function showError(msg){
      errorBox.textContent = msg;
      errorBox.style.display = "block";
      okBox.style.display = "none";
    }
    function showOk(payloadObj){
      okBox.textContent = JSON.stringify(payloadObj, null, 2);
      okBox.style.display = "block";
      errorBox.style.display = "none";
    }
    function clearAlerts(){
      errorBox.style.display = "none";
      okBox.style.display = "none";
    }

    // ===== BRL parsing / formatting =====
    function parseBRLToNumber(raw){
      let s = String(raw || "").trim();
      if (!s) return NaN;
      s = s.replace(/r\$\s*/gi, "");
      s = s.replace(/[^\d.,-]/g, "").trim();
      if (!s) return NaN;

      if (s.startsWith("-")) s = s.slice(1);

      const hasComma = s.includes(",");
      const hasDot = s.includes(".");

      let intPart = s;
      let fracPart = "";

      if (hasComma && hasDot) {
        const parts = s.split(",");
        intPart = (parts[0] || "").replace(/\./g, "");
        fracPart = parts[1] || "";
      } else if (hasComma) {
        const parts = s.split(",");
        intPart = (parts[0] || "").replace(/\./g, "");
        fracPart = parts[1] || "";
      } else if (hasDot) {
        const lastDot = s.lastIndexOf(".");
        const after = s.slice(lastDot + 1);
        if (after.length > 0 && after.length <= 2) {
          intPart = s.slice(0, lastDot).replace(/\./g, "");
          fracPart = after;
        } else {
          intPart = s.replace(/\./g, "");
          fracPart = "";
        }
      }

      intPart = (intPart || "").replace(/\D/g, "");
      fracPart = (fracPart || "").replace(/\D/g, "");

      const reais = parseInt(intPart || "0", 10);
      let cents = 0;
      if (!fracPart) cents = 0;
      else if (fracPart.length === 1) cents = parseInt(fracPart + "0", 10);
      else cents = parseInt(fracPart.slice(0, 2), 10);

      if (!Number.isFinite(reais) || !Number.isFinite(cents)) return NaN;
      return reais + (cents / 100);
    }

    function formatNumberToBRLInput(n){
      return n.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatAmountField(){
      const raw = amountEl.value;
      if (!raw.trim()) return;
      const n = parseBRLToNumber(raw);
      if (!Number.isFinite(n)) return;
      amountEl.value = formatNumberToBRLInput(n);
    }

    // ===== Message counter =====
    function updateCounter(){
      const n = (messageEl.value || "").length;
      msgCountEl.textContent = `${n}/${MAX_MESSAGE}`;
      if (n > MAX_MESSAGE - 10) {
        msgCountEl.classList.remove("ok");
        msgCountEl.classList.add("bad");
      } else {
        msgCountEl.classList.add("ok");
        msgCountEl.classList.remove("bad");
      }
    }

    // ===== Voice selection =====
    function getSelectedVoice(){
      return VOICES.find(v => v.id === selectedVoiceId) || VOICES[0] || null;
    }

    function setSelectedVoice(voiceId){
      selectedVoiceId = voiceId;
      const v = getSelectedVoice();
      voiceSelectedLabelEl.textContent = "Selecionada: " + (v?.label ?? "—");

      document.querySelectorAll("[data-voice-card='carousel']").forEach(el => {
        el.classList.toggle("selected", el.dataset.voiceId === selectedVoiceId);
      });

      document.querySelectorAll("[data-voice-card='modal']").forEach(el => {
        el.classList.toggle("selected", el.dataset.voiceId === selectedVoiceId);
      });
    }

    function makeVoiceCard(v, idx, scope){
      const card = document.createElement("div");
      card.className = "voiceCard";
      card.dataset.voiceId = v.id;
      card.dataset.voiceCard = scope;
      card.setAttribute("role", "radio");
      card.setAttribute("tabindex", "0");

      const left = document.createElement("div");
      left.className = "voiceLeft";
      left.innerHTML = `
        <div class="voiceDot">
          ${
            v.image
              ? `<img class="voiceAvatar" src="${v.image}" alt="${v.label}">`
              : `${String(idx+1).padStart(2,"0")}`
          }
        </div>
        <div class="voiceInfo">
          <div class="name">${v.label}</div>
          <div class="id">${v.id}</div>
        </div>
      `;

      const play = document.createElement("button");
      play.className = "playBtn";
      play.type = "button";
      play.title = "Preview";
      play.textContent = "▶";

      card.addEventListener("click", (e) => {
        if (e.target === play) return;
        setSelectedVoice(v.id);
      });

      card.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          setSelectedVoice(v.id);
        }
      });

      play.addEventListener("click", async (e) => {
        e.preventDefault();
        e.stopPropagation();
        setSelectedVoice(v.id);
        await previewVoice(v.id, v.label);
      });

      card.appendChild(left);
      card.appendChild(play);

      if (v.id === selectedVoiceId) card.classList.add("selected");
      return card;
    }

    function renderVoices(){
      voiceTrackEl.innerHTML = "";
      modalGridEl.innerHTML = "";

      VOICES.forEach((v, idx) => {
        voiceTrackEl.appendChild(makeVoiceCard(v, idx, "carousel"));
        modalGridEl.appendChild(makeVoiceCard(v, idx, "modal"));
      });

      setSelectedVoice(selectedVoiceId);

      const navDisabled = VOICES.length <= 3;
      prevBtn.disabled = navDisabled;
      nextBtn.disabled = navDisabled;
    }

    // ===== Carousel navigation =====
    function scrollCarouselBy(direction){
      const amount = voiceCarouselEl.clientWidth * 0.95;
      voiceCarouselEl.scrollBy({ left: direction * amount, behavior: "smooth" });
    }
    prevBtn.addEventListener("click", () => scrollCarouselBy(-1));
    nextBtn.addEventListener("click", () => scrollCarouselBy(1));

    // ===== Modal Ver Todas =====
    function openModal(){
      modalBackdrop.classList.add("show");
      document.body.style.overflow = "hidden";
      closeModalBtn.focus();
    }
    function closeModal(){
      modalBackdrop.classList.remove("show");
      document.body.style.overflow = "";
    }

    viewAllBtn.addEventListener("click", openModal);
    closeModalBtn.addEventListener("click", closeModal);
    modalBackdrop.addEventListener("click", (e) => {
      if (e.target === modalBackdrop) closeModal();
    });

    // ===== Modal PIX =====
    function openPixModal(){
      pixModalBackdrop.classList.add("show");
      document.body.style.overflow = "hidden";
    }
    
    function closePixModal(){
      pixModalBackdrop.classList.remove("show");
      document.body.style.overflow = "";
      if (paymentCheckInterval) {
        clearInterval(paymentCheckInterval);
        paymentCheckInterval = null;
      }
      currentOrderId = null;
    }

    closePixModalBtn.addEventListener("click", closePixModal);
    pixModalBackdrop.addEventListener("click", (e) => {
      if (e.target === pixModalBackdrop) closePixModal();
    });

    window.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        if (modalBackdrop.classList.contains("show")) closeModal();
        if (pixModalBackdrop.classList.contains("show")) closePixModal();
      }
    });

    // ===== Preview voice =====
    function cleanupPreviewUrl(){
      try {
        if (currentPreviewUrl) URL.revokeObjectURL(currentPreviewUrl);
      } catch {}
      currentPreviewUrl = null;
      previewAudio.pause();
      previewAudio.removeAttribute("src");
      previewAudio.load();
    }

    async function tryFetchPreviewAudio(voiceId, text){
      if (window.DonateTTS?.previewVoice) {
        const res = await window.DonateTTS.previewVoice({ voiceId, text });
        if (res?.b64) {
          const bin = atob(res.b64);
          const bytes = new Uint8Array(bin.length);
          for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
          return new Blob([bytes], { type: res.mime || "audio/mpeg" });
        }
        if (res instanceof Blob) return res;
      }

      try {
        const r = await fetch("/api/tts-preview", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ voiceId, text, lang: "pt-BR" })
        });
        if (!r.ok) throw new Error("preview endpoint not ok");
        const blob = await r.blob();
        if (!blob || blob.size === 0) throw new Error("empty blob");
        return blob;
      } catch {
        return null;
      }
    }

    function fallbackSpeechSynthesis(text){
      if (!("speechSynthesis" in window)) return false;

      const utter = new SpeechSynthesisUtterance(text);
      utter.lang = "pt-BR";
      utter.rate = 1.0;
      utter.pitch = 1.0;

      const voices = speechSynthesis.getVoices?.() || [];
      const pt = voices.find(v => (v.lang || "").toLowerCase().startsWith("pt"));
      if (pt) utter.voice = pt;

      try {
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utter);
        return true;
      } catch {
        return false;
      }
    }

    async function previewVoice(voiceId, voiceLabel){
      const v = VOICES.find(x => x.id === voiceId);
      const sample = `Olá! Eu sou a ${voiceLabel}. Obrigado pelo donate!`;

      cleanupPreviewUrl();

      if (v?.previewAudio) {
        previewAudio.src = v.previewAudio;
        previewAudio.currentTime = 0;
        try {
          await previewAudio.play();
        } catch {
          showError("Não consegui tocar o preview local. Verifique o path do arquivo previewAudio.");
        }
        return;
      }

      const blob = await tryFetchPreviewAudio(voiceId, sample);
      if (blob) {
        currentPreviewUrl = URL.createObjectURL(blob);
        previewAudio.src = currentPreviewUrl;
        previewAudio.currentTime = 0;
        try {
          await previewAudio.play();
        } catch {
          fallbackSpeechSynthesis(sample);
        }
        return;
      }

      const ok = fallbackSpeechSynthesis(sample);
      if (!ok) showError("Preview indisponível (sem previewAudio, sem endpoint/preload e sem SpeechSynthesis).");
    }

    previewSelectedBtn.addEventListener("click", async () => {
      const v = getSelectedVoice();
      if (!v) return;
      await previewVoice(v.id, v.label);
    });

    // ===== Payload + validação =====
    function buildPayload(){
      const donor = (donorEl.value || "").trim() || "Alguém";
      const msg = (messageEl.value || "").trim();
      const amountRaw = (amountEl.value || "").trim();

      const valueNumber = parseBRLToNumber(amountRaw);

      if (!msg) throw new Error("Mensagem vazia.");
      if (msg.length > MAX_MESSAGE) throw new Error("Mensagem acima de 200 caracteres.");
      if (!Number.isFinite(valueNumber)) throw new Error("Valor inválido.");
      if (valueNumber < MIN_BRL) throw new Error("Valor mínimo é R$ 5,00.");

      const voice = getSelectedVoice();
      if (!voice) throw new Error("Nenhuma voz selecionada.");

      return {
        donor,
        amountRaw: formatNumberToBRLInput(valueNumber),
        amountValue: Number(valueNumber.toFixed(2)),
        message: msg,
        voiceId: voice.id,
        voiceLabel: voice.label,
        createdAt: Date.now()
      };
    }

    // ===== Criar Pagamento =====
    async function criarPagamento(payload){
      const response = await fetch('?action=criar_pagamento', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!response.ok) {
        const errorText = await response.text();
        let errorJson;
        try {
            errorJson = JSON.parse(errorText);
        } catch (e) {
            throw new Error('Erro na resposta do servidor: ' + errorText);
        }
        throw new Error(errorJson.error || 'Erro ao criar pagamento');
      }

      return await response.json();
    }

    // ===== Verificar Pagamento =====
    async function verificarPagamento(orderId){
      const response = await fetch(`?action=verificar_pagamento&order_id=${orderId}`);
      
      if (!response.ok) {
        throw new Error('Erro ao verificar pagamento');
      }

      return await response.json();
    }

    // ===== Iniciar verificação automática =====
    function startPaymentCheck(orderId){
      if (paymentCheckInterval) {
        clearInterval(paymentCheckInterval);
      }

      paymentCheckInterval = setInterval(async () => {
        try {
          const status = await verificarPagamento(orderId);
          
          if (status.paid) {
            statusBadge.textContent = '✓ Pagamento aprovado!';
            statusBadge.className = 'statusBadge approved';
            clearInterval(paymentCheckInterval);
            paymentCheckInterval = null;
            
            setTimeout(() => {
              closePixModal();
              showOk({
                message: 'Pagamento confirmado!',
                order: status
              });
            }, 2000);
          }
        } catch (error) {
          console.error('Erro ao verificar pagamento:', error);
        }
      }, 3000); // Verifica a cada 3 segundos
    }

    // ===== Copiar código PIX =====
    copyPixBtn.addEventListener("click", async () => {
      const code = pixCode.textContent;
      if (code === "Gerando...") return;

      try {
        await navigator.clipboard.writeText(code);
        const originalText = copyPixBtn.textContent;
        copyPixBtn.textContent = '✓ Copiado!';
        setTimeout(() => {
          copyPixBtn.textContent = originalText;
        }, 2000);
      } catch {
        showError("Não foi possível copiar. Copie manualmente.");
      }
    });

    // ===== Handler Pagamento =====
    async function handlePay(){
      clearAlerts();
      try {
        formatAmountField();
        const payload = buildPayload();
        
        payBtn.disabled = true;
        payBtn.textContent = 'Gerando pagamento...';

        const result = await criarPagamento(payload);

        if (!result.success) {
          throw new Error(result.error || 'Erro ao criar pagamento');
        }

        // Resetar status
        statusBadge.textContent = 'Aguardando pagamento...';
        statusBadge.className = 'statusBadge pending';
        
        // Exibir QR Code
        if (result.pix?.qr_code_base64) {
          qrCodeImg.src = `data:image/png;base64,${result.pix.qr_code_base64}`;
          qrCodeImg.style.display = 'block';
          qrCodeLoading.style.display = 'none';
        } else {
          qrCodeLoading.textContent = 'QR Code não disponível';
        }

        // Exibir código PIX
        if (result.pix?.qr_code) {
          pixCode.textContent = result.pix.qr_code;
        } else {
          pixCode.textContent = 'Código não disponível';
        }

        // Exibir order ID
        orderIdDisplay.textContent = result.order_id || '—';
        currentOrderId = result.order_id;

        // Abrir modal
        openPixModal();

        // Iniciar verificação automática
        if (currentOrderId) {
          startPaymentCheck(currentOrderId);
        }

      } catch (e) {
        showError(String(e?.message || e));
      } finally {
        payBtn.disabled = false;
        payBtn.textContent = 'Ir para pagamento PIX';
      }
    }

    async function handlePreview(){
      clearAlerts();
      try {
        formatAmountField();
        const payload = buildPayload();
        showOk(payload);
      } catch (e) {
        showError(String(e?.message || e));
      }
    }

    // ===== Amount formatting behavior =====
    amountEl.addEventListener("blur", () => formatAmountField());
    amountEl.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        formatAmountField();
      }
    });

    amountEl.addEventListener("input", () => {
      const s = amountEl.value;
      amountEl.value = s.replace(/[^0-9.,\sR$r$]/g, "");
    });

    // ===== Bindings =====
    messageEl.addEventListener("input", updateCounter);
    payBtn.addEventListener("click", handlePay);
    previewBtn.addEventListener("click", handlePreview);

    // init
    renderVoices();
    updateCounter();
    setSelectedVoice(selectedVoiceId);
  </script>
</body>
</html>