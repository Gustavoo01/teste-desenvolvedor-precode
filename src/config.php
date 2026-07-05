<?php

ini_set('display_errors', '0');

const PRECODE_URL = 'https://www.replicade.com.br/api/';
// Adicionar token de autenticação
const PRECODE_TOKEN = '';

function bd(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // Configurar crednciais para conexão com banco de dados
        $pdo = new PDO('pgsql:host=localhost;dbname=precode', 'postgres', 'postgres', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}

function precode(string $metodo, string $rota, ?array $dados = null): array
{
    $ch = curl_init(PRECODE_URL . $rota);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . PRECODE_TOKEN,
            'Content-Type: application/json',
        ],
    ]);

    if ($dados !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados, JSON_UNESCAPED_UNICODE));
    }

    $resposta = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return [
        'status' => $status,
        'corpo' => $resposta ? json_decode($resposta, true) : null,
        'erro' => $erroCurl ?: null,
    ];
}

function responder(int $status, array $corpo): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($corpo, JSON_UNESCAPED_UNICODE);
    exit;
}