<?php

namespace App\Services;

/**
 * GeminiVisionService — Leitura de manuscritos históricos via Google Gemini
 * 
 * O Gemini tem capacidade de visão superior para caligrafia cursiva antiga.
 * Usa a API Gemini 2.0 Flash para ler imagens de recortes de manuscritos.
 * Inclui retry automático com backoff exponencial para rate limit (429).
 * 
 * Configuração: adicionar GEMINI_API_KEY ao .env
 */
class GeminiVisionService
{
    private string $apiKey;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';

    public function __construct()
    {
        $this->apiKey = getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_API_KEY') ?: '';

        $envPath = defined('ROOTPATH') ? ROOTPATH . '.env' : __DIR__ . '/../../.env';
        if (empty($this->apiKey) && file_exists($envPath)) {
            $lines = file($envPath);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (strpos($trimmed, 'GEMINI_API_KEY') === 0 || strpos($trimmed, 'GOOGLE_API_KEY') === 0) {
                    $parts = explode('=', $trimmed, 2);
                    $this->apiKey = trim($parts[1] ?? '');
                    break;
                }
            }
        }
    }

    /**
     * Retorna true se a chave de API estiver configurada
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Lê um recorte de imagem de manuscrito via Gemini Vision
     * e retorna o texto transcrito.
     * Inclui retry automático com backoff exponencial para rate limit (429).
     *
     * @param string $docTitle
     * @param string $imageBase64
     * @return string
     */
    public function transcribeImage(string $docTitle, string $imageBase64): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('A chave GEMINI_API_KEY não está configurada no arquivo .env.');
        }

        $mimeType = 'image/jpeg';
        $base64Data = $imageBase64;
        if (preg_match('/^data:image\/(\w+);base64,/', $imageBase64, $matches)) {
            $base64Data = substr($imageBase64, strpos($imageBase64, ',') + 1);
        }

        $prompt = <<<PROMPT
Você é um paleógrafo especializado em documentos manuscritos brasileiros das décadas de 1920-1930.
A imagem abaixo é um recorte de um documento de arquivo histórico. 
Faça a transcrição COMPLETA e FIEL de TODO o texto manuscrito visível na imagem, 
mantendo a ortografia original e expandindo abreviações entre parênteses quando possível.

Documento: {$docTitle}

Transcreva exatamente o que está escrito, incluindo números, siglas e nomes próprios.
PROMPT;

        $data = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inlineData' => ['mimeType' => $mimeType, 'data' => $base64Data]]
                ]
            ]],
            'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 4096]
        ];

        $url = $this->apiUrl . '?key=' . urlencode($this->apiKey);
        $maxRetries = 5;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $ch = \curl_init($url);
            \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, \CURLOPT_POST, true);
            \curl_setopt($ch, \CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            \curl_setopt($ch, \CURLOPT_POSTFIELDS, \json_encode($data));
            \curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, false);
            \curl_setopt($ch, \CURLOPT_TIMEOUT, 60);

            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
            $error = \curl_error($ch);
            \curl_close($ch);

            if ($httpCode === 429 && $attempt < $maxRetries) {
                $waitTime = pow(2, $attempt + 1);
                \sleep($waitTime);
                continue;
            }

            if ($error) {
                throw new \RuntimeException('Erro na chamada da API Gemini: ' . $error);
            }

            if ($httpCode === 429) {
                throw new \RuntimeException('Limite de requisições excedido (429). Tente novamente em alguns minutos.');
            }

            if ($httpCode !== 200) {
                throw new \RuntimeException('Gemini retornou status ' . $httpCode);
            }

            $json = \json_decode($response, true);
            return trim($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
        }

        return '';
    }

    /**
     * Lê a imagem e retorna transcrição + entidades + relações.
     * Gemini lê a imagem, DeepSeek extrai entidades.
     * Se Gemini falhar, retorna vazio para fallback do controller.
     */
    public function extractFromImage(string $docTitle, string $imageBase64): array
    {
        try {
            $transcription = $this->transcribeImage($docTitle, $imageBase64);
        } catch (\Throwable $e) {
            return ['transcription' => '', 'entities' => [], 'relationships' => []];
        }

        if (empty($transcription)) {
            return ['transcription' => '', 'entities' => [], 'relationships' => []];
        }

        try {
            $deepSeek = new DeepSeekService();
            $result = $deepSeek->extractFromCropText($docTitle, $transcription);
            return [
                'transcription' => $transcription,
                'entities' => $result['entities'] ?? [],
                'relationships' => $result['relationships'] ?? [],
            ];
        } catch (\Throwable $e) {
            return ['transcription' => $transcription, 'entities' => [], 'relationships' => []];
        }
    }
}
