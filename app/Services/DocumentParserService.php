<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * DocumentParserService — Extrai texto legível de múltiplos formatos: TXT, PDF, JPG, JPEG, PNG, WEBP, JSON, CSV.
 */
class DocumentParserService
{
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
                $text   = $pdf->getText();

                // Se o PDF não tiver camada de texto pesquisável (imagem digitalizada)
                if (empty(trim($text))) {
                    $text = "[PDF Digitalizado/Imagem - O documento foi importado como PDF sem camada de texto direta. Título do arquivo: " . basename($filePath) . "]";
                }

                return [
                    'text'     => $text,
                    'is_image' => false,
                    'mime'     => 'application/pdf',
                ];
            } catch (\Exception $e) {
                // Fallback em caso de PDF protegido
                return [
                    'text'     => "[PDF Importado - " . basename($filePath) . ". Falha na leitura direta: " . $e->getMessage() . "]",
                    'is_image' => false,
                    'mime'     => 'application/pdf',
                ];
            }
        }

        // 3. Imagens (.jpg, .jpeg, .png, .webp, .bmp)
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])) {
            $imageData = file_get_contents($filePath);
            $mimeType  = $this->getMimeType($extension);
            $base64    = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);

            // Tentar extrair metadados EXIF se disponíveis (JPG/JPEG)
            $exifDetails = '';
            if (function_exists('exif_read_data') && in_array($extension, ['jpg', 'jpeg'])) {
                @$exif = exif_read_data($filePath);
                if ($exif && is_array($exif)) {
                    $exifDetails = " Metadados EXIF: " . json_encode(array_filter($exif, 'is_scalar'), JSON_UNESCAPED_UNICODE);
                }
            }

            $descriptionText = "[Foto/Imagem de Documento Histórico: " . basename($filePath) . ". {$exifDetails}]";

            return [
                'text'     => $descriptionText,
                'is_image' => true,
                'mime'     => $mimeType,
                'base64'   => $base64,
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
