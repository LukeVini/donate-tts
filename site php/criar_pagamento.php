<?php
// Arquivo: criar_pagamento.php


function criarPedidoPix($valor, $donor, $message, $voiceId) {
    // SEU TOKEN (O mesmo de antes)
    $dotenv = parse_ini_file(__DIR__ . '/.env');
    $access_token = $dotenv['MP_ACCESS_KEY'];

    $idempotency_key = uniqid('pay_', true);
    $email_pagador = "doador_" . time() . "@email.com";
    $valor_float = (float) $valor;

    $payload = [
        "transaction_amount" => $valor_float,
        "description" => "Doacao de " . mb_substr($donor, 0, 30),
        "payment_method_id" => "pix",
        "payer" => [
            "email" => $email_pagador,
            "first_name" => mb_substr($donor, 0, 30)
        ],
        "metadata" => [
            "donor_name" => mb_substr($donor, 0, 50),
            "voice_id" => $voiceId,
            "message" => mb_substr($message, 0, 200)
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/payments');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
        'X-Idempotency-Key: ' . $idempotency_key
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // --- NOVO: SALVA O LOG PARA VOCÊ VER O ID ---
    // Vai criar/atualizar o arquivo log_mp.txt na pasta doar/
    $logData = "--- NOVO PAGAMENTO (" . date('H:i:s') . ") ---\n";
    $logData .= "Response MP: " . $response . "\n\n";
    file_put_contents(__DIR__ . '/log_mp.txt', $logData, FILE_APPEND);
    // --------------------------------------------

    if ($curl_error) {
        return ['erro' => true, 'msg' => 'Erro cURL: ' . $curl_error];
    }

    $mp_response = json_decode($response, true);

    if ($http_code !== 201) {
        $msgErro = $mp_response['message'] ?? 'Erro desconhecido';
        return ['erro' => true, 'msg' => 'Erro MP: ' . $msgErro ];
    }

    $qr_code_base64 = $mp_response['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null;
    $qr_code_copia_cola = $mp_response['point_of_interaction']['transaction_data']['qr_code'] ?? null;

    if (!$qr_code_base64) {
        return ['erro' => true, 'msg' => 'O Mercado Pago não retornou o QR Code.'];
    }

    return [
        'erro' => false,
        'order_id' => $mp_response['id'],
        'qr_code_base64' => $qr_code_base64,
        'qr_code_copia_cola' => $qr_code_copia_cola
    ];
}
?>