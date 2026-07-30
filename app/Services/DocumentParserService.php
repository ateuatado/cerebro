<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * DocumentParserService — Extrai texto legível de múltiplos formatos: TXT, PDF, JPG, JPEG, PNG, WEBP, BMP.
 * Integra OCR (OCR.Space API) para transcrição de fotos e documentos digitalizados em imagem.
 */
class DocumentParserService
{
    private string $ocrApiKey;

    public function __construct()
    {
        $this->ocrApiKey = getenv('OCR_SPACE_API_KEY') ?: 'K88673738888957';

        if (empty($this->ocrApiKey) && file_exists(ROOTPATH . '.env')) {
            $lines = file(ROOTPATH . '.env');
            foreach ($lines as $line) {
                if (strpos(trim($line), 'OCR_SPACE_API_KEY') === 0) {
                    $parts = explode('=', $line, 2);
                    $this->ocrApiKey = trim($parts[1] ?? '');
                    break;
                }
            }
        }

        if (empty($this->ocrApiKey)) {
            $this->ocrApiKey = 'K88673738888957';
        }
    }

    /**
     * Extrai o conteúdo textual a partir do caminho do arquivo e extensão.
     *
     * @param string $filePath Caminho completo do arquivo
     * @param string $extension Extensão em minúsculo (ex: pdf, jpg, png, txt)
     * @return array ['text' => string, 'is_image' => bool, 'mime' => string]
     */
    public function parseFile(string $filePath, string $extension): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Arquivo não encontrado: {$filePath}");
        }

        $extension = strtolower($extension);

        // 1. Arquivos de Texto Nativos (.txt, .md, .json, .csv, .log)
        if (in_array($extension, ['txt', 'md', 'json', 'csv', 'log'])) {
            $content = file_get_contents($filePath);
            return [
                'text'     => $content ?: '',
                'is_image' => false,
                'mime'     => 'text/plain',
            ];
        }

        // 2. Arquivos PDF (.pdf)
        if ($extension === 'pdf') {
            try {
                $parser = new PdfParser();
                $pdf    = $parser->parseFile($filePath);
                $text   = trim($pdf->getText());

                // Se o PDF tiver camada de texto legível
                if (!empty($text) && strlen($text) > 30) {
                    return [
                        'text'     => $text,
                        'is_image' => false,
                        'mime'     => 'application/pdf',
                    ];
                }

                // Se o PDF for digitalização / imagem sem camada de texto, envia para OCR
                $ocrText = $this->performOcr($filePath, 'pdf');
                return [
                    'text'     => !empty($ocrText) ? $ocrText : "[PDF Escaneado - " . basename($filePath) . "]",
                    'is_image' => false,
                    'mime'     => 'application/pdf',
                ];

            } catch (\Exception $e) {
                // Fallback via OCR se o parser nativo falhar
                $ocrText = $this->performOcr($filePath, 'pdf');
                return [
                    'text'     => !empty($ocrText) ? $ocrText : "[PDF Importado - " . basename($filePath) . "]",
                    'is_image' => false,
                    'mime'     => 'application/pdf',
                ];
            }
        }

        // 3. Imagens (.jpg, .jpeg, .png, .webp, .bmp)
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])) {
            $ocrText  = $this->performOcr($filePath, $extension);
            $mimeType = $this->getMimeType($extension);

            $finalText = !empty(trim($ocrText))
                ? "Transcrição OCR da imagem (" . basename($filePath) . "):\n" . $ocrText
                : "[Imagem de Documento: " . basename($filePath) . "]";

            return [
                'text'     => $finalText,
                'is_image' => true,
                'mime'     => $mimeType,
            ];
        }

        // Formato não reconhecido
        return [
            'text'     => file_get_contents($filePath) ?: '',
            'is_image' => false,
            'mime'     => 'application/octet-stream',
        ];
    }

    /**
     * Executa transcrição OCR na imagem/PDF utilizando OCR.Space API
     */
    private function performOcr(string $filePath, string $extension): string
    {
        try {
            $ch = curl_init('https://api.ocr.space/parse/image');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $this->ocrApiKey]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'file'      => new \CURLFile($filePath),
                'language'  => 'por', // Português
                'isTable'   => 'false',
                'OCREngine' => '2',
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($response)) {
                $json = json_decode($response, true);
                if (isset($json['ParsedResults'][0]['ParsedText'])) {
                    return trim($json['ParsedResults'][0]['ParsedText']);
                }
            }
        } catch (\Exception $e) {
            // Em caso de offline/timeout
        }

        return '';
    }

    /**
     * Retorna o MIME Type correspondente para imagens
     */
    private function getMimeType(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            default       => 'image/jpeg',
        };
    }
}
