<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * DocumentParserService — Extrai texto legível de múltiplos formatos: TXT, PDF, JPG, JPEG, PNG, WEBP, BMP.
 * Suporta OCR de múltiplas páginas para PDFs escaneados e imagens.
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

                // Se o PDF tiver camada de texto legível nativa
                if (!empty($text) && strlen($text) > 30) {
                    return [
                        'text'     => $text,
                        'is_image' => false,
                        'mime'     => 'application/pdf',
                    ];
                }

                // Se for PDF escaneado (sem texto nativo), usa OCR por páginas
                $ocrText = $this->performPdfPageOcr($filePath);
                return [
                    'text'     => !empty(trim($ocrText)) ? $ocrText : "[PDF Escaneado sem texto - " . basename($filePath) . "]",
                    'is_image' => false,
                    'mime'     => 'application/pdf',
                ];

            } catch (\Exception $e) {
                $ocrText = $this->performPdfPageOcr($filePath);
                return [
                    'text'     => !empty(trim($ocrText)) ? $ocrText : "[PDF Importado - " . basename($filePath) . "]",
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
     * Executa OCR página a página para PDFs escaneados via Python/PyMuPDF + OCR.Space API
     */
    private function performPdfPageOcr(string $pdfPath): string
    {
        $tempDir = WRITEPATH . 'uploads/temp_pdf_ocr_' . time() . '/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Script Python inline para renderizar páginas do PDF em JPEG de 130 DPI
        $pyCode = <<<PYTHON
import os, fitz
doc = fitz.open(r"{$pdfPath}")
for idx in range(len(doc)):
    page = doc[idx]
    pix = page.get_pixmap(dpi=130)
    img_p = os.path.join(r"{$tempDir}", f"page_{idx+1}.jpg")
    pix.save(img_p, jpg_quality=85)
PYTHON;

        $scriptPath = $tempDir . 'render.py';
        file_put_contents($scriptPath, $pyCode);

        // Executar renderização em Python
        exec("python " . escapeshellarg($scriptPath) . " 2>&1", $out, $ret);

        $pageTexts = [];
        $pageFiles = glob($tempDir . 'page_*.jpg');
        sort($pageFiles, SORT_NATURAL);

        if (!empty($pageFiles)) {
            foreach ($pageFiles as $pIdx => $imgPath) {
                $pText = $this->performOcr($imgPath, 'jpg');
                if (!empty($pText)) {
                    $pageTexts[] = "--- PÁGINA " . ($pIdx + 1) . " ---\n" . $pText;
                }
            }
        } else {
            // Fallback direto se o Python/PyMuPDF não estiver disponível
            $directText = $this->performOcr($pdfPath, 'pdf');
            if (!empty($directText)) {
                $pageTexts[] = $directText;
            }
        }

        // Limpar temporários
        @unlink($scriptPath);
        foreach ($pageFiles as $f) {
            @unlink($f);
        }
        @rmdir($tempDir);

        return implode("\n\n", $pageTexts);
    }

    /**
     * Executa transcrição OCR na imagem/PDF utilizando a API do OCR.Space
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
                'language'  => 'por',
                'isTable'   => 'false',
                'OCREngine' => '2',
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

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
            // Falha graciosa
        }

        return '';
    }

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
