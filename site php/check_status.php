<?php
header('Content-Type: application/json');


$payment_id = $_GET['id'] ?? null;

if (!$payment_id) {
    echo json_encode(['status' => 'error', 'message' => 'ID não fornecido']);
    exit;
    }
    
    $dotenv = parse_ini_file(__DIR__ . '/.env');
    $access_token = $dotenv['MP_ACCESS_KEY'];

// 1. Consulta o Mercado Pago
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

if ($http_code !== 200) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao consultar API']);
    exit;
}

$data = json_decode($response, true);
$status = $data['status'] ?? 'unknown';
$status_detail = $data['status_detail'] ?? '';

// 2. Se estiver APROVADO, salva no JSON local
if ($status === 'approved') {
    $arquivoJson = __DIR__ . '/donations.json';
    
    // Lê o arquivo atual (ou cria array vazio)
    $doacoes = file_exists($arquivoJson) ? json_decode(file_get_contents($arquivoJson), true) : [];
    if (!is_array($doacoes)) $doacoes = [];

    // Verifica se esse ID já foi salvo para não duplicar
    $jaSalvo = false;
    foreach ($doacoes as $d) {
        if (isset($d['id']) && $d['id'] == $payment_id) {
            $jaSalvo = true;
            break;
        }
    }

    if (!$jaSalvo) {
        // Pega os dados que enviamos no metadata
        $metadata = $data['metadata'] ?? [];
        
       $novaDoacao = [
            'id' => $payment_id,
            'name' => $metadata['donor_name'] ?? 'Anônimo',
            'amount' => $data['transaction_amount'] ?? 0,
            'voice_id' => $metadata['voice_id'] ?? '',
            'message' => $metadata['message'] ?? '',
            'status' => 'approved', // <--- ADICIONE ESTA LINHA IMPORTANTE
            'date' => date('Y-m-d H:i:s')
        ];

        // Adiciona e salva
        $doacoes[] = $novaDoacao;
        file_put_contents($arquivoJson, json_encode($doacoes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

echo json_encode([
    'status' => $status,
    'payment_id' => $payment_id,
    'detail' => $status_detail
]);
?>