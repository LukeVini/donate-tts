<?php
// Arquivo: doar/api_donations.php

// Define que a resposta será sempre JSON
header('Content-Type: application/json; charset=utf-8');
// Permite que sites externos (como um widget local do OBS) acessem
header('Access-Control-Allow-Origin: *');

// 1. Carrega as configurações
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Arquivo .env não encontrado']);
    exit;
}
$dotenv = parse_ini_file($envPath);

// 2. Verifica a segurança (A "Key")
$senhaEsperada = $dotenv['API_SECRET_KEY'] ?? null;
$senhaRecebida = $_GET['key'] ?? '';

// Se não tiver senha configurada ou a senha estiver errada
if (!$senhaEsperada || $senhaRecebida !== $senhaEsperada) {
    http_response_code(403); // Proibido
    echo json_encode(['error' => 'Acesso negado. Key inválida ou ausente.']);
    exit;
}

// 3. Lê e retorna o JSON de doações
$arquivoJson = __DIR__ . '/donations.json';

if (file_exists($arquivoJson)) {
    // Lê o conteúdo do arquivo
    $conteudo = file_get_contents($arquivoJson);
    
    // Verifica se o arquivo está vazio
    if (empty($conteudo)) {
        echo '[]';
    } else {
        echo $conteudo;
    }
} else {
    // Se o arquivo ainda não existe (nenhuma doação feita), retorna array vazio
    echo '[]';
}
