<?php

require_once __DIR__ . '/criar_pagamento.php';

function containsOffensiveWords($text) {
    $offensive = [
        ' nazista ', ' comunista ', ' fascista ', ' nigger ', ' nigga ', ' mongol ', ' retardado ', 
        ' pênis ', 'penis', ' preto fudido ', ' preto macaco ', 'estupro', 'estuprado', 
        ' estuprei ', 'estuprar', 'estuprada', 'estuprarei', 'estupru', 'puta', 
        ' buceta ', ' pau ', 'filho da puta', 'preto de merda', 'viado', 'viadinho', 
        ' bicha ', ' bixa ', 'bichona', 'travesti', 'traveco', 'mongoloide', 'suicídio', 
        ' suicidio ', 'suicidar', 'suicida', 'pornô', 'porn', 'pornhub', 'xvideos', 
        ' xhamster ', 'youporn', 'uporn', 'erome', 'fapello', 'brazzers', 'sexo ao vivo', 
        ' pedófilo ', 'pedofilo', 'bct', 'zoofilia', 'incesto', 'neguinho de merda', 
        ' cu ', ' ku ', 'se enforca'
    ];
    
    $textLower = mb_strtolower($text, 'UTF-8');
    
    foreach ($offensive as $word) {
        if (mb_strpos($textLower, $word) !== false) {
            return true;
        }
    }
    return false;
}

function processarDoacao($nome, $valor, $mensagem, $voiceId) {
    
    // ATUALIZADO: Passando $mensagem e $voiceId para a criação do Pix
    $resultadoMP = criarPedidoPix($valor, $nome, $mensagem, $voiceId);

    if ($resultadoMP['erro']) {
        return [
            'status' => 'erro',
            'msg' => $resultadoMP['msg']
        ];
    }

    return [
        'status' => 'sucesso',
        'msg' => 'Pagamento criado! Escaneie o QR Code abaixo.',
        'order_id' => $resultadoMP['order_id'],
        'qr_code_base64' => $resultadoMP['qr_code_base64'],
        'qr_code_copia_cola' => $resultadoMP['qr_code_copia_cola'],
        'mensagem' => $mensagem,
        'voice_id' => $voiceId
    ];
}

function verificarStatusPagamento($payment_id) {
    // Carrega a chave segura
    $dotenv = parse_ini_file(__DIR__ . '/.env');
    $access_token = $dotenv['MP_ACCESS_KEY'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/{$payment_id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['status'] ?? 'pending';
    }

    return null; // Erro ou não encontrado
}
