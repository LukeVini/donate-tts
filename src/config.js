// src/config.js
export default {
  // --- INTEGRAÇÃO PHP ---
  DONATION_API_URL: "https://vittozao.com/doar/api_donations.php",
  
  // CORREÇÃO: Apenas a senha, sem "API_SECRET_KEY="
  DONATION_API_KEY: "dsakldh34321jsalkhdui317826312vittozao", 
  
  POLL_INTERVAL_MS: 30000, // 30 segundos

  // --- ELEVENLABS ---
  ELEVEN_API_KEY: process.env.ELEVEN_API_KEY || "sk_c656e71b2b2e084a7a464fb9022c97b3d498c6d8720cc830",
  ELEVEN_MODEL_ID: "eleven_multilingual_v2",
  ELEVEN_OUTPUT_FORMAT: "mp3_44100_128",
  
  ELEVEN_VOICE_SETTINGS: {
    stability: 0.5,
    similarity_boost: 0.75,
    style: 0.0,
    use_speaker_boost: true,
  },

  VOICES: [
    { label: "Voz do Vitto", id: "IwDvd6l61AFDUFrrOmp6" },
    { label: "Voz do BRKSEDU", id: "RpoRH62g4guuflYcCN0i" },
    { label: "Voz do Wave", id: "S90MFu1lps7paZBjXCSO" },
    { label: "Voz do Fallen", id: "dncAD6dCMrVEIikmjkOh" },
    { label: "Voz do Gaules", id: "EbzzoumZLebjZvINHHpE" },
    { label: "Voz do Cariani", id: "2ifFbhWSEH0kwEltBD3h" },
  ],

  MAX_QUEUE_ITEMS: 50,
  MAX_DONOR_CHARS: 30,
  MAX_AMOUNT_CHARS: 20,
  MAX_MESSAGE_CHARS: 200,

  OVERLAY_PRE_SHOW_MS: 1500,
  OVERLAY_POST_HIDE_MS: 1500,
  
  ARCHIVE_ROOT_NAME: "DonateTTS_Audios",
};