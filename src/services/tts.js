// src/services/tts.js
import https from "node:https";
import Config from "../config";

// ALTERAÇÃO: Agora aceita 'apiKey' como parâmetro opcional
export function elevenTTS({ voiceId, text, apiKey }) {
  return new Promise((resolve, reject) => {
    // Prioriza a chave passada (do settings), senão usa a do config
    const finalApiKey = apiKey || Config.ELEVEN_API_KEY;

    if (!finalApiKey || finalApiKey.includes("COLE_SUA")) {
      return reject(new Error("API Key da ElevenLabs não configurada."));
    }

    const payload = JSON.stringify({
      text,
      model_id: Config.ELEVEN_MODEL_ID,
      language_code: "pt",
      apply_text_normalization: "on",
      voice_settings: Config.ELEVEN_VOICE_SETTINGS,
    });

    const reqPath = `/v1/text-to-speech/${encodeURIComponent(voiceId)}?output_format=${encodeURIComponent(Config.ELEVEN_OUTPUT_FORMAT)}`;

    const req = https.request(
      {
        hostname: "api.elevenlabs.io",
        port: 443,
        path: reqPath,
        method: "POST",
        headers: {
          "xi-api-key": finalApiKey,
          "Content-Type": "application/json",
          "Content-Length": Buffer.byteLength(payload),
          Accept: "audio/mpeg",
        },
      },
      (res) => {
        const chunks = [];
        res.on("data", (d) => chunks.push(d));
        res.on("end", () => {
          const buf = Buffer.concat(chunks);
          if (res.statusCode >= 200 && res.statusCode < 300) {
            resolve(buf);
          } else {
            const errText = buf.toString("utf8").slice(0, 500);
            reject(new Error(`ElevenLabs error ${res.statusCode}: ${errText}`));
          }
        });
      }
    );

    req.on("error", reject);
    req.write(payload);
    req.end();
  });
}