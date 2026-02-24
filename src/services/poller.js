// src/services/poller.js
import https from "node:https";
import Config from "../config";

export function fetchDonations() {
  return new Promise((resolve, reject) => {
    const { DONATION_API_URL, DONATION_API_KEY } = Config;
    
    // Monta a URL com a chave de segurança
    const url = `${DONATION_API_URL}?key=${DONATION_API_KEY}`;

    https.get(url, (res) => {
      const chunks = [];
      res.on("data", (d) => chunks.push(d));
      res.on("end", () => {
        try {
          if (res.statusCode !== 200) {
            throw new Error(`API retornou status ${res.statusCode}`);
          }
          const body = Buffer.concat(chunks).toString("utf8");
          // Verifica se o corpo está vazio
          if (!body || body.trim() === "") {
             resolve([]);
             return;
          }
          
          const json = JSON.parse(body);
          
          if (json.error) throw new Error(json.error);
          
          resolve(Array.isArray(json) ? json : []);
        } catch (e) {
          reject(e);
        }
      });
    }).on("error", (e) => reject(e));
  });
}