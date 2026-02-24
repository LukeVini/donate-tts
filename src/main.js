// src/main.js
import { app, BrowserWindow, ipcMain, screen, globalShortcut } from "electron";
import path from "node:path";
import fs from "node:fs";
import fsp from "node:fs/promises";

import Config from "./config";
import { elevenTTS } from "./services/tts";
import { fetchDonations } from "./services/poller";
import { CONTROL_HTML, OVERLAY_HTML } from "./ui/html";

// Variáveis de Estado
let controlWin = null;
let overlayWin = null;
let playing = false;
let isPaused = false;
let isGenerating = false;
let pollingInterval = null;

let isFirstLoad = true; // Controle para importação silenciosa

const SPEED_OPTIONS = [1.0, 1.25, 1.5, 1.75, 2.0];
let currentSpeedIndex = 0;

const queue = [];
const library = [];
const processedIds = new Set();

// --- CONFIGURAÇÕES ---
let appSettings = {
  elevenApiKey: "",
  overlayPosition: "bottom-left",
  alwaysOnTop: true,
  monitorId: null,
  shortcuts: {
    toggle: "F9",
    skip: "F10",
    rewind: "F7",
    forward: "F8"
  }
};

const ARCHIVE_ROOT_NAME = Config.ARCHIVE_ROOT_NAME;
let sessionDir = null;
let databasePath = null;
let settingsPath = null;

// ========================= UTILS =========================
function normalizeDonor(d) { return String(d || "Anônimo").trim(); }
function amountToSpeech(raw) { return raw.replace("R$", "").trim() + " reais"; }
function getCurrentSpeed() { return SPEED_OPTIONS[currentSpeedIndex]; }

// ========================= WINDOW MANAGEMENT =========================

function updateOverlaySettings() {
  if (!overlayWin) return;

  // 1. Always On Top
  const onTop = (appSettings.alwaysOnTop !== undefined) ? appSettings.alwaysOnTop : true;
  overlayWin.setAlwaysOnTop(onTop, "screen-saver");

  // 2. Seleção de Monitor
  const displays = screen.getAllDisplays();
  let display = displays.find(d => d.id === parseInt(appSettings.monitorId));
  if (!display) {
    display = screen.getPrimaryDisplay();
  }

  const { x, y, width, height } = display.workArea;
  const winW = 600;
  const winH = 250;
  const padding = 20;

  let finalX = x + padding;
  let finalY = y + height - winH - padding;

  switch (appSettings.overlayPosition) {
    case 'top-left':
      finalX = x + padding;
      finalY = y + padding;
      break;
    case 'top-right':
      finalX = x + width - winW - padding;
      finalY = y + padding;
      break;
    case 'bottom-left':
      finalX = x + padding;
      finalY = y + height - winH - padding;
      break;
    case 'bottom-right':
      finalX = x + width - winW - padding;
      finalY = y + height - winH - padding;
      break;
  }

  overlayWin.setBounds({
    x: Math.round(finalX),
    y: Math.round(finalY),
    width: winW,
    height: winH
  });
}

// ========================= SHORTCUTS =========================
function registerShortcuts() {
  globalShortcut.unregisterAll();

  if (!appSettings.shortcuts) return;
  const { toggle, skip, rewind, forward } = appSettings.shortcuts;

  try {
    if (toggle) {
      globalShortcut.register(toggle, () => {
        if (playing) {
          isPaused = !isPaused;
          broadcastState();
          if (overlayWin) overlayWin.webContents.send("overlay:toggle");
        }
      });
    }

    if (skip) {
      globalShortcut.register(skip, () => {
        if (overlayWin) overlayWin.webContents.send("overlay:stop");
      });
    }

    if (rewind) {
      globalShortcut.register(rewind, () => {
        if (overlayWin) overlayWin.webContents.send("overlay:seek", -3);
      });
    }

    if (forward) {
      globalShortcut.register(forward, () => {
        if (overlayWin) overlayWin.webContents.send("overlay:seek", 3);
      });
    }
    console.log("Atalhos registrados:", appSettings.shortcuts);
  } catch (err) {
    console.error("Erro ao registrar atalhos:", err);
  }
}

// ========================= DATA & SETTINGS =========================
async function initData() {
  const userData = app.getPath("userData");

  // --- Configurações ---
  settingsPath = path.join(userData, "settings.json");
  try {
    if (fs.existsSync(settingsPath)) {
      const data = await fsp.readFile(settingsPath, 'utf8');
      const loaded = JSON.parse(data);
      appSettings = { ...appSettings, ...loaded };
    } else {
      appSettings.elevenApiKey = Config.ELEVEN_API_KEY || "";
    }
  } catch (e) { console.error("Erro carregando settings:", e); }

  // Registra atalhos logo após carregar as configurações
  registerShortcuts();

  // --- Banco de Dados ---
  databasePath = path.join(userData, "donations_db.json");
  try {
    if (fs.existsSync(databasePath)) {
      const data = await fsp.readFile(databasePath, 'utf8');
      const savedItems = JSON.parse(data);
      savedItems.forEach(item => {
        processedIds.add(item.id);
        library.push(item);
      });
    } else {
      await fsp.writeFile(databasePath, "[]", 'utf8');
      console.log("Banco de dados recriado: donations_db.json");
    }
  } catch (e) {
    console.error("Erro carregando/criando DB:", e);
    try { await fsp.writeFile(databasePath, "[]", 'utf8'); } catch (z) { }
  }
}

async function persistDonation(item) {
  library.push(item);
  processedIds.add(item.id);
  try {
    await fsp.writeFile(databasePath, JSON.stringify(library, null, 2), 'utf8');
  } catch (e) { console.error("Erro salvando no DB:", e); }
}

async function saveSettings(newSettings) {
  appSettings = { ...appSettings, ...newSettings };
  try {
    await fsp.writeFile(settingsPath, JSON.stringify(appSettings, null, 2), 'utf8');
    updateOverlaySettings();
    registerShortcuts(); // Atualiza atalhos imediatamente
    return true;
  } catch (e) { return false; }
}

// ========================= AUDIO FILES =========================
async function initFileSystem() {
  const docsDir = app.getPath("documents");
  sessionDir = path.join(docsDir, ARCHIVE_ROOT_NAME, "AudioLibrary");
  await fsp.mkdir(sessionDir, { recursive: true });
}

async function saveAudioFile(item, buffer) {
  if (!sessionDir) return;
  const fileName = `${item.id}.mp3`;
  const filePath = path.join(sessionDir, fileName);
  await fsp.writeFile(filePath, buffer);
  item.filePath = filePath;
  item.fileName = fileName;
  await persistDonation(item);
}

// ========================= QUEUE LOGIC =========================
function broadcastState() {
  if (controlWin) {
    const thirtyDaysAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);
    const history30Days = library
      .filter(item => (item.createdAt || Date.now()) > thirtyDaysAgo)
      .reverse();

    controlWin.webContents.send("control:state", {
      queueLength: queue.length,
      playing: playing,
      isPaused: isPaused,
      currentSpeed: getCurrentSpeed(),
      queuePreview: queue.slice(0, 10),
      historyPreview: history30Days
    });
  }
}

async function processQueue() {
  if (isGenerating) return;

  const nextItem = queue.find(x => x.status === 'queued');
  if (!nextItem) return;

  isGenerating = true;

  const probablePath = path.join(sessionDir, `${nextItem.id}.mp3`);

  // Verifica se o áudio já existe no disco
  if (fs.existsSync(probablePath)) {
    nextItem.filePath = probablePath;
    nextItem.fileName = `${nextItem.id}.mp3`;
    nextItem.status = 'ready';

    // Verifica se REALMENTE está na biblioteca (histórico). Se não estiver, salva.
    const alreadyInHistory = library.some(i => i.id === nextItem.id);
    if (!alreadyInHistory) {
      await persistDonation(nextItem);
    }

    isGenerating = false;
    broadcastState();
    maybePlayNext();
    return;
  }

  nextItem.status = 'generating';
  broadcastState();

  try {
    const spokenText = `${nextItem.donor} enviou ${nextItem.amountSpoken}: "${nextItem.message}"`;
    const audioBuffer = await elevenTTS({
      voiceId: nextItem.voiceId,
      text: spokenText,
      apiKey: appSettings.elevenApiKey
    });

    await saveAudioFile(nextItem, audioBuffer);
    nextItem.status = 'ready';
    broadcastState();
    maybePlayNext();

  } catch (error) {
    console.error("Erro gerando audio:", error);
    nextItem.status = 'failed';
    nextItem.error = error.message;
    broadcastState();
  } finally {
    isGenerating = false;
    setTimeout(processQueue, 500);
  }
}

async function maybePlayNext() {
  if (playing || !overlayWin) return;

  const nextReady = queue.find(x => x.status === 'ready');
  if (!nextReady) return;

  playing = true;
  isPaused = false;
  broadcastState();

  try {
    const fileToPlay = nextReady.filePath || path.join(sessionDir, `${nextReady.id}.mp3`);
    const buffer = await fsp.readFile(fileToPlay);

    overlayWin.webContents.send("overlay:play", {
      meta: nextReady,
      audioB64: buffer.toString("base64"),
      speed: getCurrentSpeed(),
      preShowMs: Config.OVERLAY_PRE_SHOW_MS,
      postHideMs: Config.OVERLAY_POST_HIDE_MS
    });
  } catch (err) {
    console.error("Erro tocando:", err);
    playing = false;
    isPaused = false;
    nextReady.status = 'failed';
    broadcastState();
  }
}

// ========================= POLLING =========================
async function checkNewDonations() {
  if (controlWin) controlWin.webContents.send("poller:status", "Verificando...");

  try {
    const donations = await fetchDonations();
    let addedCount = 0;

    // Detecta se é uma "restauração": Primeira carga E banco de dados vazio
    const isRestoring = isFirstLoad && processedIds.size === 0;

    for (const donation of donations) {
      if (processedIds.has(donation.id)) continue;
      if (donation.status !== 'approved') continue;

      processedIds.add(donation.id);

      const voiceConfig = Config.VOICES.find(v => v.id === donation.voice_id) || Config.VOICES[0];

      const queueItem = {
        id: donation.id,
        donor: normalizeDonor(donation.name),
        amountRaw: "R$ " + parseFloat(donation.amount).toFixed(2).replace('.', ','),
        amountSpoken: amountToSpeech(donation.amount.toString()),
        message: donation.message,
        voiceId: voiceConfig.id,
        voiceLabel: voiceConfig.label,
        status: isRestoring ? 'ready' : 'queued',
        createdAt: Date.now()
      };

      if (isRestoring) {
        // MODO SILENCIOSO: Joga direto pro histórico, não põe na fila
        library.push(queueItem);
        // Cria um caminho de arquivo falso só para constar no registro até ser tocado um dia (opcional)
        // Ou deixa null para ser gerado se for replay
        queueItem.filePath = null;
        queueItem.fileName = null;
      } else {
        queue.push(queueItem);
        addedCount++;
      }
    }

    if (isRestoring && library.length > 0) {
      await fsp.writeFile(databasePath, JSON.stringify(library, null, 2), 'utf8');
      if (controlWin) controlWin.webContents.send("poller:status", "Histórico restaurado!");
    } else if (controlWin) {
      const totalStored = library.length;
      const msg = addedCount > 0 ? `+ ${addedCount} novos!` : `Histórico: ${totalStored} itens.`;
      controlWin.webContents.send("poller:status", msg);
    }

    if (addedCount > 0) {
      broadcastState();
      processQueue();
    }

    isFirstLoad = false;

  } catch (error) {
    console.error("Erro Poller:", error);
    if (controlWin) controlWin.webContents.send("poller:status", "Erro: " + error.message);
  }
}

// ========================= WINDOWS =========================
function createWindows() {
  controlWin = new BrowserWindow({
    width: 600, height: 850,
    title: "Donate TTS - Monitor",
    autoHideMenuBar: true,
    webPreferences: { nodeIntegration: true, contextIsolation: false }
  });

  controlWin.setMenu(null); // Remove a barra de menu
  controlWin.loadURL("data:text/html;charset=utf-8," + encodeURIComponent(CONTROL_HTML));

  overlayWin = new BrowserWindow({
    width: 600, height: 250,
    frame: false,
    transparent: true,
    resizable: false,
    alwaysOnTop: true,
    skipTaskbar: true,
    hasShadow: false,
    webPreferences: { nodeIntegration: true, contextIsolation: false }
  });

  overlayWin.setIgnoreMouseEvents(true, { forward: true });
  overlayWin.loadURL("data:text/html;charset=utf-8," + encodeURIComponent(OVERLAY_HTML));

  updateOverlaySettings();

  controlWin.on("closed", () => { app.quit(); });
  controlWin.webContents.on('did-finish-load', () => broadcastState());
}

// ========================= START =========================
app.whenReady().then(async () => {
  await initFileSystem();
  await initData();
  createWindows();

  pollingInterval = setInterval(checkNewDonations, Config.POLL_INTERVAL_MS);
  checkNewDonations();
});

app.on("window-all-closed", () => app.quit());
app.on('will-quit', () => {
  globalShortcut.unregisterAll();
});

// ========================= IPC =========================
ipcMain.on("player:skip", () => {
  if (overlayWin) overlayWin.webContents.send("overlay:stop");
});

ipcMain.on("player:toggle", () => {
  if (playing) {
    isPaused = !isPaused;
    broadcastState();
    if (overlayWin) overlayWin.webContents.send("overlay:toggle");
  }
});

ipcMain.on("player:seek", (e, amount) => {
  if (overlayWin) overlayWin.webContents.send("overlay:seek", amount);
});

ipcMain.on("player:speed", () => {
  currentSpeedIndex = (currentSpeedIndex + 1) % SPEED_OPTIONS.length;
  const newSpeed = getCurrentSpeed();
  broadcastState();
  if (overlayWin) overlayWin.webContents.send("overlay:speed", newSpeed);
});

ipcMain.on("player:replay", (e, id) => {
  const item = library.find(x => x.id === id);
  if (item) {
    const fullPath = item.filePath || path.join(sessionDir, `${item.id}.mp3`);
    // Verifica se arquivo existe, se não, tocará e gerará novamente (processQueue lida com isso)
    const replayItem = { ...item, status: 'queued' };
    queue.unshift(replayItem);
    broadcastState();
    processQueue();
  }
});

ipcMain.handle("alerts:clear", () => {
  queue.length = 0;
  broadcastState();
  if (overlayWin) overlayWin.webContents.send("overlay:stop");
});

ipcMain.on("poller:checkNow", () => {
  checkNewDonations();
});

ipcMain.on("overlay:ended", () => {
  playing = false;
  isPaused = false;
  const playedIndex = queue.findIndex(x => x.status === 'ready');
  if (playedIndex !== -1) {
    queue.splice(playedIndex, 1);
  }
  broadcastState();
  maybePlayNext();
});

// --- SETTINGS IPC ---
ipcMain.handle("settings:get", () => { return appSettings; });

ipcMain.handle("settings:get-displays", () => {
  return screen.getAllDisplays().map(d => ({
    id: d.id,
    label: `Monitor ${d.id}`,
    bounds: d.bounds,
    isPrimary: d.id === screen.getPrimaryDisplay().id
  }));
});

ipcMain.handle("settings:save", async (e, s) => { return await saveSettings(s); });