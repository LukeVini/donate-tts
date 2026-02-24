// src/ui/html.js

export const CONTROL_HTML = `
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Donate TTS - Monitor</title>
  <style>
    :root { --bg: #0b0f14; --panel: rgba(255,255,255,0.06); --text: #eee; --accent: #60d394; --danger: #ff5a5a; font-family: sans-serif; }
    body { margin: 0; background: var(--bg); color: var(--text); padding: 20px; }
    .card { background: var(--panel); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 16px; margin-bottom: 20px; }
    h1 { margin: 0 0 10px 0; font-size: 18px; color: var(--accent); }
    .status-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; }
    .list { max-height: 300px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 10px; }
    .item { padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
    .item:last-child { border: none; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
    .badge.queued { background: #ffa726; color: #000; }
    .badge.ready { background: #60d394; color: #000; }
    .badge.playing { background: #4fc3f7; color: #000; }
    
    .btn { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 8px 16px; border-radius: 6px; cursor: pointer; flex: 1; text-align: center; transition: all 0.2s; font-weight: bold; }
    .btn:hover { background: rgba(255,255,255,0.2); }
    .btn:disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
    .btn.danger { border-color: var(--danger); color: var(--danger); }
    .btn.small { padding: 4px 8px; font-size: 12px; flex: 0 0 auto; margin-left: 10px; font-weight: normal; }
    
    /* --- MODAL --- */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 100; align-items: center; justify-content: center; }
    .modal.open { display: flex; }
    .modal-content { background: #1a1f26; padding: 25px; border-radius: 12px; width: 90%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 50px rgba(0,0,0,0.5); max-height: 90vh; overflow-y: auto; }
    .input-group { margin-bottom: 20px; }
    .input-group label { display: block; margin-bottom: 8px; font-size: 13px; color: #ccc; font-weight: 600; }
    .input-group input[type="text"], .input-group input[type="password"], .input-group select { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #444; background: #0b0f14; color: #fff; box-sizing: border-box; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

    /* --- POSITION GRID --- */
    .position-selector {
        display: flex;
        gap: 20px;
        align-items: flex-start;
    }
    .screen-mockup {
        width: 120px;
        height: 80px;
        background: #0b0f14;
        border: 2px solid #444;
        border-radius: 6px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        padding: 4px;
        gap: 4px;
    }
    .pos-btn {
        background: #2a2f36;
        border: 1px solid #444;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .pos-btn:hover { background: #3a3f46; }
    .pos-btn.active {
        background: var(--accent);
        border-color: var(--accent);
        box-shadow: 0 0 8px rgba(96,211,148,0.4);
    }
    .pos-desc { font-size: 12px; color: #888; margin-top: 5px; }

    /* --- TOGGLE SWITCH --- */
    .switch-container { display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); }
    .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #444; transition: .4s; border-radius: 24px; }
    .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--accent); }
    input:checked + .slider:before { transform: translateX(20px); }

  </style>
</head>
<body>
  <div class="card">
    <div class="status-row">
      <h1>Monitoramento</h1>
      <div id="connectionStatus" style="font-size:12px; color:#aaa;">Verificando...</div>
    </div>
    
    <div class="status-row">
      <button class="btn" id="rewind">⏪ -3s</button>
      <button class="btn" id="togglePlayer">Pausar</button>
      <button class="btn" id="forward">⏩ +3s</button>
      <button class="btn" id="skip">Pular</button>
    </div>

    <div class="status-row" style="margin-top:10px;">
      <button class="btn" id="speedBtn" style="flex: 0 0 80px; color: var(--accent); border-color: var(--accent);">1.0x</button>
      <button class="btn" id="forceCheck">Atualizar</button>
      <button class="btn" id="configBtn">⚙️ Configs</button>
      <button class="btn danger" id="clear">Limpar</button>
    </div>
  </div>

  <div class="card">
    <h1>Fila de Reprodução (<span id="queueCount">0</span>)</h1>
    <div class="list" id="queueList"></div>
  </div>

  <div class="card">
    <h1>Histórico (Últimos 30 dias)</h1>
    <div class="list" id="historyList"></div>
  </div>

  <div id="configModal" class="modal">
    <div class="modal-content">
      <h2 style="margin-top:0; margin-bottom:20px;">Configurações</h2>
      
      <div class="input-group">
        <label>ElevenLabs API Key</label>
        <input type="password" id="apiKeyInput" placeholder="sk_...">
      </div>
      
      <div class="input-group">
        <label>Monitor (Display)</label>
        <select id="monitorSelect"></select>
      </div>

      <div class="input-group">
        <label>Posição do Overlay</label>
        <div class="position-selector">
            <div class="screen-mockup">
                <div class="pos-btn" data-pos="top-left"></div>
                <div class="pos-btn" data-pos="top-right"></div>
                <div class="pos-btn" data-pos="bottom-left"></div>
                <div class="pos-btn" data-pos="bottom-right"></div>
            </div>
            <div class="pos-desc" id="posDesc">Canto Inferior Esquerdo</div>
        </div>
      </div>
      
      <div style="border-top:1px solid rgba(255,255,255,0.1); margin: 15px 0; padding-top:10px;">
          <h3 style="font-size:14px; color:#60d394; margin-bottom:10px;">Atalhos Globais (Teclado)</h3>
          
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
              <div class="input-group" style="margin-bottom:10px;">
                  <label>Pausar/Retomar</label>
                  <input type="text" id="bindToggle" placeholder="Ex: F9">
              </div>
              <div class="input-group" style="margin-bottom:10px;">
                  <label>Pular (Skip)</label>
                  <input type="text" id="bindSkip" placeholder="Ex: F10">
              </div>
              <div class="input-group" style="margin-bottom:10px;">
                  <label>Voltar 3s</label>
                  <input type="text" id="bindRewind" placeholder="Ex: F7">
              </div>
              <div class="input-group" style="margin-bottom:10px;">
                  <label>Avançar 3s</label>
                  <input type="text" id="bindForward" placeholder="Ex: F8">
              </div>
          </div>
          <div style="font-size:10px; color:#666;">
              Use: "CommandOrControl+X", "Alt+F1", "F10", etc.
          </div>
      </div>

      <div class="input-group">
        <div class="switch-container">
            <label style="margin:0; cursor:pointer;" for="onTopCheck">Sempre no Topo</label>
            <label class="switch">
                <input type="checkbox" id="onTopCheck">
                <span class="slider"></span>
            </label>
        </div>
        <div style="font-size:11px; color:#666; margin-top:5px;">
           Desligue para ocultar do seu monitor e mostrar apenas no OBS.
        </div>
      </div>
      
      <div class="modal-actions">
        <button class="btn" id="closeConfigBtn" style="flex:0;">Cancelar</button>
        <button class="btn" id="saveConfigBtn" style="background:var(--accent); color:#000; flex:0;">Salvar</button>
      </div>
    </div>
  </div>

  <script>
    const { ipcRenderer } = require("electron");
    
    // UI Elements
    const modal = document.getElementById('configModal');
    const apiKeyInput = document.getElementById('apiKeyInput');
    const monitorSelect = document.getElementById('monitorSelect');
    const onTopCheck = document.getElementById('onTopCheck');
    const posBtns = document.querySelectorAll('.pos-btn');
    const posDesc = document.getElementById('posDesc');
    
    // Bind Inputs
    const bindToggle = document.getElementById('bindToggle');
    const bindSkip = document.getElementById('bindSkip');
    const bindRewind = document.getElementById('bindRewind');
    const bindForward = document.getElementById('bindForward');

    let currentPos = 'bottom-left'; 

    const posLabels = {
        'top-left': 'Superior Esquerdo',
        'top-right': 'Superior Direito',
        'bottom-left': 'Inferior Esquerdo',
        'bottom-right': 'Inferior Direito'
    };

    posBtns.forEach(btn => {
        btn.onclick = () => {
            posBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentPos = btn.dataset.pos;
            posDesc.innerText = posLabels[currentPos];
        };
    });

    document.getElementById('configBtn').onclick = async () => {
      const settings = await ipcRenderer.invoke('settings:get');
      
      // Carregar Key
      apiKeyInput.value = settings.elevenApiKey || "";
      
      // Carregar Monitores
      const displays = await ipcRenderer.invoke('settings:get-displays');
      monitorSelect.innerHTML = '';
      displays.forEach((d, i) => {
          const opt = document.createElement('option');
          opt.value = d.id;
          const isPrimary = d.isPrimary ? ' (Principal)' : '';
          opt.text = \`Display \${i + 1}: \${d.bounds.width}x\${d.bounds.height}\${isPrimary}\`;
          if (settings.monitorId && String(d.id) === String(settings.monitorId)) {
              opt.selected = true;
          }
          monitorSelect.appendChild(opt);
      });
      
      // Carregar Posição
      currentPos = settings.overlayPosition || 'bottom-left';
      posBtns.forEach(b => {
          if(b.dataset.pos === currentPos) b.classList.add('active');
          else b.classList.remove('active');
      });
      posDesc.innerText = posLabels[currentPos];

      // Carregar Always On Top
      onTopCheck.checked = (settings.alwaysOnTop !== undefined) ? settings.alwaysOnTop : true;

      // Carregar Atalhos
      const sc = settings.shortcuts || {};
      bindToggle.value = sc.toggle || "F9";
      bindSkip.value = sc.skip || "F10";
      bindRewind.value = sc.rewind || "F7";
      bindForward.value = sc.forward || "F8";

      modal.classList.add('open');
    };

    document.getElementById('closeConfigBtn').onclick = () => modal.classList.remove('open');

    document.getElementById('saveConfigBtn').onclick = async () => {
      await ipcRenderer.invoke('settings:save', { 
          elevenApiKey: apiKeyInput.value.trim(),
          overlayPosition: currentPos,
          alwaysOnTop: onTopCheck.checked,
          monitorId: monitorSelect.value,
          // Salva os atalhos
          shortcuts: {
              toggle: bindToggle.value.trim(),
              skip: bindSkip.value.trim(),
              rewind: bindRewind.value.trim(),
              forward: bindForward.value.trim()
          }
      });
      modal.classList.remove('open');
    };

    // Botões Principais da UI (click)
    document.getElementById('skip').onclick = () => ipcRenderer.send('player:skip');
    document.getElementById('togglePlayer').onclick = () => ipcRenderer.send('player:toggle');
    document.getElementById('rewind').onclick = () => ipcRenderer.send('player:seek', -3);
    document.getElementById('forward').onclick = () => ipcRenderer.send('player:seek', 3);
    document.getElementById('speedBtn').onclick = () => ipcRenderer.send('player:speed');
    document.getElementById('clear').onclick = () => ipcRenderer.invoke('alerts:clear');
    document.getElementById('forceCheck').onclick = () => ipcRenderer.send('poller:checkNow');

    const btnToggle = document.getElementById('togglePlayer');
    const btnRewind = document.getElementById('rewind');
    const btnForward = document.getElementById('forward');
    const btnSkip = document.getElementById('skip');
    const btnSpeed = document.getElementById('speedBtn');

    ipcRenderer.on('control:state', (e, state) => {
      btnSpeed.innerText = state.currentSpeed + "x";
      const isPlaying = state.playing; 
      
      btnToggle.disabled = !isPlaying;
      btnRewind.disabled = !isPlaying;
      btnForward.disabled = !isPlaying;
      btnSkip.disabled = !isPlaying;

      if (!isPlaying) {
         btnToggle.innerText = "Parado";
      } else {
         btnToggle.innerText = state.isPaused ? "Retomar" : "Pausar";
      }
      
      document.getElementById('queueCount').innerText = state.queueLength;

      const qList = document.getElementById('queueList');
      qList.innerHTML = state.queuePreview.map(item => \`
        <div class="item">
          <div>
            <div><b>\${item.donor}</b> enviou \${item.amountRaw}</div>
            <div style="font-size:11px; opacity:0.7;">\${item.message}</div>
          </div>
          <div class="badge \${item.status}">\${item.status}</div>
        </div>
      \`).join('') || '<div style="padding:10px; opacity:0.5; text-align:center;">Fila vazia</div>';

      const hList = document.getElementById('historyList');
      hList.innerHTML = state.historyPreview.map(item => \`
        <div class="item">
          <div>
            <div style="font-size:12px;"><b>\${item.donor}</b> (\${item.amountRaw})</div>
            <div style="font-size:11px; opacity:0.5;">\${item.message.substring(0, 30)}...</div>
          </div>
          <button class="btn small" onclick="ipcRenderer.send('player:replay', '\${item.id}')">Replay</button>
        </div>
      \`).join('') || '<div style="padding:10px; opacity:0.5; text-align:center;">Sem histórico</div>';
    });

    ipcRenderer.on('poller:status', (e, msg) => {
      document.getElementById('connectionStatus').innerText = msg;
    });
  </script>
</body>
</html>
`;

export const OVERLAY_HTML = `
<!doctype html>
<html lang="pt-br">
<head>
  <style>
    body { 
      margin: 0; 
      padding: 0; 
      overflow: hidden; 
      font-family: sans-serif;
      width: 100vw; 
      height: 100vh;
      display: flex;
      align-items: flex-end; 
      justify-content: flex-start;
      background: transparent;
    }
    
    .toast {
      width: 100%;
      box-sizing: border-box;
      margin: 10px;
      
      background: rgba(10, 14, 20, 0.95); 
      border: 1px solid rgba(255,255,255,0.2);
      color: white; 
      padding: 20px; 
      border-radius: 16px;
      
      transform: translateY(120%); 
      opacity: 0; 
      transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .toast.show { transform: translateY(0); opacity: 1; }
    
    .title { font-size: 24px; font-weight: bold; color: #60d394; margin-bottom: 5px; }
    .sub { font-size: 16px; opacity: 0.8; margin-bottom: 10px; }
    .msg { font-size: 18px; line-height: 1.4; word-wrap: break-word; }
  </style>
</head>
<body>
  <div class="toast" id="toast">
    <div class="title" id="donor"></div>
    <div class="sub" id="details"></div>
    <div class="msg" id="message"></div>
  </div>
  <audio id="audio"></audio>
  <script>
    const { ipcRenderer } = require("electron");
    const toast = document.getElementById('toast');
    const audio = document.getElementById('audio');
    
    ipcRenderer.on('overlay:play', (e, data) => {
      document.getElementById('donor').innerText = data.meta.donor;
      document.getElementById('details').innerText = \`enviou \${data.meta.amountRaw} • \${data.meta.voiceLabel}\`;
      document.getElementById('message').innerText = \`"\${data.meta.message}"\`;
      
      audio.playbackRate = data.speed || 1.0;
      
      toast.classList.add('show');
      audio.src = "data:audio/mp3;base64," + data.audioB64;
      
      setTimeout(() => {
        audio.playbackRate = data.speed || 1.0;
        audio.play().catch(err => ipcRenderer.send('overlay:ended'));
      }, data.preShowMs || 1000);
    });

    ipcRenderer.on('overlay:speed', (e, newSpeed) => {
       audio.playbackRate = newSpeed;
    });

    ipcRenderer.on('overlay:toggle', () => {
      if(audio.paused) audio.play();
      else audio.pause();
    });

    ipcRenderer.on('overlay:seek', (e, amount) => {
      if(audio.duration) {
        audio.currentTime = Math.max(0, audio.currentTime + amount);
      }
    });

    audio.onended = () => {
      setTimeout(() => {
        toast.classList.remove('show');
        ipcRenderer.send('overlay:ended');
      }, 1500); 
    };
    
    ipcRenderer.on('overlay:stop', () => {
      audio.pause();
      audio.currentTime = 0;
      toast.classList.remove('show');
      ipcRenderer.send('overlay:ended');
    });
  </script>
</body>
</html>
`;