<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * DocumentParserService — Extrai texto legível de múltiplos formatos: TXT, PDF, JPG, JPEG, PNG, WEBP, BMP.
 * Suporta OCR paginado, rotação física de imagem via PHP GD e recorte de região para HTR com IA.
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
     * Renderiza as páginas de um PDF em arquivos JPEG na pasta de cache do documento.
     * Retorna o total de páginas e os caminhos das imagens.
     */
    public function renderPdfPagesToCache(string $pdfPath, string $cacheDir): array
    {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $existingPages = glob($cacheDir . '/page_*.jpg');
        if (!empty($existingPages)) {
            sort($existingPages, SORT_NATURAL);
            return [
                'totalPages' => count($existingPages),
                'pages'      => $existingPages,
            ];
        }

        // Script Python inline com fitz (PyMuPDF) para renderização a 150 DPI
        $pyCode = <<<PYTHON
import os, fitz
doc = fitz.open(r"{$pdfPath}")
for idx in range(len(doc)):
    page = doc[idx]
    pix = page.get_pixmap(dpi=150)
    img_p = os.path.join(r"{$cacheDir}", f"page_{idx+1}.jpg")
    pix.save(img_p, jpg_quality=90)
PYTHON;

        $scriptPath = $cacheDir . '/render.py';
        file_put_contents($scriptPath, $pyCode);

        exec("python " . escapeshellarg($scriptPath) . " 2>&1", $out, $ret);
        @unlink($scriptPath);

        $pages = glob($cacheDir . '/page_*.jpg');
        sort($pages, SORT_NATURAL);

        return [
            'totalPages' => count($pages),
            'pages'      => $pages,
        ];
    }

    /**
     * Rotaciona uma imagem física (JPEG/PNG) em um determinado ângulo (ex: 90, 180, 270 graus) usando PHP GD.
     */
    public function rotateImageFile(string $imgPath, int $degrees): bool
    {
        if (!file_exists($imgPath) || !function_exists('imagerotate')) {
            return false;
        }

        $info = getimagesize($imgPath);
        if (!$info) return false;

        $mime = $info['mime'];
        $src  = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($imgPath),
            'image/png'  => @imagecreatefrompng($imgPath),
            'image/webp' => @imagecreatefromwebp($imgPath),
            default      => null,
        };

        if (!$src) return false;

        // PHP imagerotate rotaciona no sentido anti-horário; invertemos para bater com o padrão relógio
        $angle   = (360 - ($degrees % 360)) % 360;
        $rotated = imagerotate($src, $angle, 0);

        if ($rotated) {
            imagejpeg($rotated, $imgPath, 92);
            imagedestroy($src);
            imagedestroy($rotated);
            return true;
        }

        imagedestroy($src);
        return false;
    }

    /**
     * Recorta uma área específica da imagem conforme coordenadas do canvas [x, y, w, h].
     * Retorna o caminho do arquivo temporário recortado.
     */
    public function cropImageRegion(string $imgPath, int $x, int $y, int $w, int $h, int $canvasW = 0, int $canvasH = 0): ?string
    {
        if (!file_exists($imgPath) || !function_exists('imagecrop')) {
            return null;
        }

        $info = getimagesize($imgPath);
        if (!$info) return null;

        $origW = $info[0];
        $origH = $info[1];

        // Se o canvas do navegador enviou dimensões escaladas, calcula a proporção real
        if ($canvasW > 0 && $canvasH > 0) {
            $scaleX = $origW / $canvasW;
            $scaleY = $origH / $canvasH;

            $x = (int) round($x * $scaleX);
            $y = (int) round($y * $scaleY);
            $w = (int) round($w * $scaleX);
            $h = (int) round($h * $scaleY);
        }

        // Limita os limites dentro da imagem original
        $x = max(0, min($x, $origW - 10));
        $y = max(0, min($y, $origH - 10));
        $w = max(10, min($w, $origW - $x));
        $h = max(10, min($h, $origH - $y));

        $src = @imagecreatefromjpeg($imgPath) ?: @imagecreatefrompng($imgPath);
        if (!$src) return null;

        $cropRect = ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
        $cropped  = imagecrop($src, $cropRect);

        if ($cropped) {
            $tempCropPath = WRITEPATH . 'uploads/temp_crop_' . time() . '_' . rand(100, 999) . '.jpg';
            imagejpeg($cropped, $tempCropPath, 95);
            imagedestroy($src);
            imagedestroy($cropped);
            return $tempCropPath;
        }

        imagedestroy($src);
        return null;
    }

    /**
     * Submete uma imagem (ou recorte) para OCR e HTR via OCR.Space
     */
    public function performOcr(string $filePath, string $extension = 'jpg'): string
    {
        try {
            $ch = curl_init('https://api.ocr.space/parse/image');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $this->ocrApiKey]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'file'      => new \CURLFile($filePath),
                'language'  => 'por',
                'isTable'   => 'true',
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

    private function performPdfPageOcr(string $pdfPath): string
    {
        $tempDir = WRITEPATH . 'uploads/temp_pdf_ocr_' . time() . '/';
        $res     = $this->renderPdfPagesToCache($pdfPath, $tempDir);
        $pageFiles = $res['pages'] ?? [];

        $pageTexts = [];
        if (!empty($pageFiles)) {
            foreach ($pageFiles as $pIdx => $imgPath) {
                $pText = $this->performOcr($imgPath, 'jpg');
                if (!empty($pText)) {
                    $pageTexts[] = "--- PÁGINA " . ($pIdx + 1) . " ---\n" . $pText;
                }
                @unlink($imgPath);
            }
        } else {
            $directText = $this->performOcr($pdfPath, 'pdf');
            if (!empty($directText)) {
                $pageTexts[] = $directText;
            }
        }

        @rmdir($tempDir);
        return implode("\n\n", $pageTexts);
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
