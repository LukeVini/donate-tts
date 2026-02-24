# 🎯 Sistema de Doações TTS com PIX

## 📁 Estrutura de Arquivos

```
seu-projeto/
├── index.html              (já atualizado com integração PIX)
├── criar_pagamento.php     (API para criar pagamento)
├── verificar_pagamento.php (API para verificar status)
└── assets/
    ├── images/
    │   ├── vitto.webp
    │   ├── brksedu.jpg
    │   ├── wave.jpg
    │   └── fallen.jpg
    └── voices/
        ├── vitto.mp3
        ├── brksedu.mp3
        ├── wave.mp3
        └── fallen.mp3
```

## 🔧 Configuração

### 1. Token do Mercado Pago

Nos arquivos `criar_pagamento.php` e `verificar_pagamento.php`, substitua o token:

```php
$access_token = 'SEU_ACCESS_TOKEN_AQUI';
```

**⚠️ IMPORTANTE:** 
- O token atual é de **TESTE**
- Para produção, use um token **PRODUÇÃO**
- Nunca compartilhe seu token

### 2. Endpoint da API

No arquivo `index.html`, ajuste o caminho da API se necessário:

```javascript
const API_ENDPOINT = 'criar_pagamento.php'; // ou '/api/criar_pagamento.php'
```

## 🚀 Como Funciona

### Fluxo de Pagamento:

1. **Usuário preenche o formulário:**
   - Nome do doador
   - Valor (mínimo R$ 5,00)
   - Mensagem (até 200 caracteres)
   - Seleciona uma voz

2. **Clica em "Pagar com PIX":**
   - Sistema valida os dados
   - Envia requisição para `criar_pagamento.php`
   - API Mercado Pago retorna código PIX

3. **Modal PIX é exibido:**
   - QR Code gerado automaticamente
   - Código PIX Copia e Cola
   - Botão para copiar código
   - Informações da doação

4. **Usuário paga:**
   - Escaneia QR Code no app do banco
   - Ou copia e cola o código PIX
   - Pagamento é processado pelo Mercado Pago

## 🔍 Verificar Status do Pagamento

Para verificar se um pagamento foi aprovado:

```javascript
fetch('verificar_pagamento.php?order_id=SEU_ORDER_ID')
  .then(res => res.json())
  .then(data => {
    if (data.paid) {
      console.log('Pagamento aprovado!');
      // Processar doação aqui
    }
  });
```

### Exemplo de resposta:

```json
{
  "order_id": "123456789",
  "external_reference": "donate_1234567890_abc123",
  "status": "paid",
  "payment_status": "approved",
  "payment_id": "987654321",
  "paid": true,
  "metadata": {
    "voice_id": "IwDvd6l61AFDUFrrOmp6",
    "voice_label": "Vitto",
    "message": "valeu demais!",
    "donor": "Lucas"
  }
}
```

## 🎨 Personalização

### Alterar valores mínimos:

No `index.html`:
```javascript
const MIN_BRL = 5.00; // Valor mínimo em reais
```

No `criar_pagamento.php`:
```php
if ($valor < 5.00) {
    // Altere aqui também
}
```

### Adicionar mais vozes:

```javascript
const VOICES = [
  {
    label: "Nova Voz",
    id: "voice_id_elevenlabs",
    image: "assets/images/nova_voz.jpg",
    previewAudio: "assets/voices/nova_voz.mp3"
  },
  // ... outras vozes
];
```

## 🔐 Segurança

### ⚠️ Recomendações:

1. **Nunca exponha seu Access Token no frontend**
2. **Use HTTPS em produção**
3. **Valide todos os dados no backend**
4. **Implemente rate limiting**
5. **Configure webhooks do Mercado Pago para confirmação automática**

### Webhooks (Recomendado):

Crie `webhook.php` para receber notificações do Mercado Pago:

```php
<?php
// Recebe notificação do Mercado Pago quando pagamento é aprovado
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data['type'] === 'payment') {
    $payment_id = $data['data']['id'];
    // Verifique o pagamento e processe a doação
}
?>
```

Configure no painel do Mercado Pago: https://www.mercadopago.com.br/developers/panel/webhooks

## 📊 Status de Pagamento

| Status | Descrição |
|--------|-----------|
| `pending` | Aguardando pagamento |
| `approved` | Pagamento aprovado ✅ |
| `authorized` | Pagamento autorizado ✅ |
| `in_process` | Em processamento |
| `rejected` | Rejeitado ❌ |
| `cancelled` | Cancelado ❌ |
| `refunded` | Reembolsado |

## 🐛 Troubleshooting

### QR Code não aparece:
- Verifique se o token do Mercado Pago está correto
- Confira os logs no console do navegador
- Teste a API diretamente no browser

### Erro de CORS:
Adicione no PHP:
```php
header('Access-Control-Allow-Origin: *');
```

### Código PIX muito longo:
É normal! Códigos PIX têm +200 caracteres.

## 📱 Testar Pagamento

### Ambiente de Teste:

Use os dados de teste do Mercado Pago:
- CPF: `12345678909`
- Aprovado: Nome `APRO`
- Recusado: Nome `OTHE`

### Ambiente de Produção:

1. Troque para token de produção
2. Configure webhook
3. Teste com valor mínimo real
4. Monitore os logs

## 🎯 Próximos Passos

1. **Implementar webhook** para confirmação automática
2. **Salvar doações** em banco de dados
3. **Fila de processamento** para TTS
4. **Painel administrativo** para gerenciar doações
5. **Histórico** de doações por usuário
6. **Integração com OBS** para exibir doações ao vivo

## 💡 Dicas

- Use **idempotency keys únicas** para evitar duplicatas
- **Salve o external_reference** para rastrear doações
- **Implemente retry logic** para APIs instáveis
- **Monitore** taxas de conversão (cliques → pagamentos)
- **Teste exaustivamente** antes de ir para produção

## 📞 Suporte

- Documentação Mercado Pago: https://www.mercadopago.com.br/developers
- Status API: https://status.mercadopago.com/
- Comunidade: https://www.mercadopago.com.br/developers/pt/community

---

**Versão:** 1.0.0  
**Última atualização:** Janeiro 2026