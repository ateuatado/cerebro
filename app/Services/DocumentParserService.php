<?php

namespace App\Services;

use App\Models\EntityModel;

/**
 * DocumentParserService — Leitura e Transcrição Técnica de Arquivos
 * Gerencia a conversão de PDF para imagens paginadas, rotação, recorte regional e OCR local.
 */
class DocumentParserService
{
    private EntityModel $entityModel;

    public function __construct()
    {
        $this->entityModel = new EntityModel();
    }

    /**
     * Executa OCR usando o Tesseract nativo ou extração fallback
     */
    public function performOcr(string $filePath, string $extension): string
    {
        $filePath = str_replace('\\', '/', $filePath);
        if (!file_exists($filePath)) {
            return '';
        }

        // Executar Tesseract OCR se disponível no sistema
        $tesseractCmd = "tesseract " . escapeshellarg($filePath) . " stdout -l por 2>&1";
        exec($tesseractCmd, $outputLines, $returnVar);

        if ($returnVar === 0 && !empty($outputLines)) {
            $extractedText = trim(implode("\n", $outputLines));
            if (!empty($extractedText)) {
                return $extractedText;
            }
        }

        // Fallback via script Python com pytesseract ou pdfplumber se for PDF
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])) {
            $pyCode = <<<PYTHON
import os, sys
try:
    from PIL import Image
    import pytesseract
    text = pytesseract.image_to_string(Image.open("{$filePath}"), lang='por')
    print(text)
except Exception as e:
    print("")
PYTHON;
            $tmpPy = WRITEPATH . 'uploads/ocr_tmp_' . uniqid() . '.py';
            file_put_contents($tmpPy, $pyCode);
            exec("python " . escapeshellarg($tmpPy) . " 2>&1", $pyOut, $pyRet);
            @unlink($tmpPy);

            $pyText = trim(implode("\n", $pyOut));
            if (!empty($pyText) && strpos($pyText, 'Error') === false) {
                return $pyText;
            }
        }

        return "[PDF Escaneado sem texto - " . basename($filePath) . "]";
    }

    /**
     * Renderiza as páginas de um PDF em arquivos JPEG na pasta de cache do documento
     */
    public function renderPdfPagesToCache(string $pdfPath, string $cacheDir): array
    {
        $pdfPath  = str_replace('\\', '/', $pdfPath);
        $cacheDir = str_replace('\\', '/', $cacheDir);

        if (!file_exists($pdfPath)) {
            return ['totalPages' => 0, 'pages' => []];
        }

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        // Se já houver páginas renderizadas no cache, reutilizar
        $existing = glob($cacheDir . '/page_*.jpg');
        if (!empty($existing)) {
            sort($existing, SORT_NATURAL);
            return [
                'totalPages' => count($existing),
                'pages'      => $existing,
            ];
        }

        // Script Python inline com fitz (PyMuPDF) para renderização a 150 DPI
        $pyCode = <<<PYTHON
import os, fitz
doc = fitz.open("{$pdfPath}")
for idx in range(len(doc)):
    page = doc[idx]
    pix = page.get_pixmap(dpi=150)
    img_p = os.path.join("{$cacheDir}", f"page_{idx+1}.jpg")
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
     * Rotaciona uma imagem física (JPEG/PNG) em um determinado ângulo (ex: 90, 180, 270 graus).
     * Usa o motor Python PIL (infalível em qualquer tamanho de imagem) com fallback em PHP GD.
     */
    public function rotateImageFile(string $imgPath, int $degrees): bool
    {
        $imgPath = str_replace('\\', '/', $imgPath);
        if (!file_exists($imgPath)) {
            return false;
        }

        // 1. Rotação via Python PIL (com expand=True para manter a proporção completa sem cortes)
        $pyCode = <<<PYTHON
import os
from PIL import Image
try:
    p = "{$imgPath}"
    img = Image.open(p)
    rotated = img.rotate(-{$degrees}, expand=True)
    rotated.save(p, quality=95)
    print("SUCCESS")
except Exception as e:
    print(f"ERROR: {e}")
PYTHON;

        $tmpDir = dirname($imgPath);
        $scriptPath = $tmpDir . '/rotate_' . uniqid() . '.py';
        file_put_contents($scriptPath, $pyCode);

        exec("python " . escapeshellarg($scriptPath) . " 2>&1", $out, $ret);
        @unlink($scriptPath);

        $outputStr = implode("\n", $out);
        if (strpos($outputStr, 'SUCCESS') !== false) {
            return true;
        }

        // 2. Fallback via PHP GD com fundo branco
        if (function_exists('imagerotate')) {
            $info = @getimagesize($imgPath);
            if ($info) {
                $mime = $info['mime'];
                $src  = match ($mime) {
                    'image/jpeg' => @imagecreatefromjpeg($imgPath),
                    'image/png'  => @imagecreatefrompng($imgPath),
                    'image/webp' => @imagecreatefromwebp($imgPath),
                    default      => null,
                };

                if ($src) {
                    $angle   = (360 - ($degrees % 360)) % 360;
                    $bg      = imagecolorallocate($src, 255, 255, 255);
                    $rotated = imagerotate($src, $angle, $bg);

                    if ($rotated) {
                        imagejpeg($rotated, $imgPath, 92);
                        imagedestroy($src);
                        imagedestroy($rotated);
                        return true;
                    }
                    imagedestroy($src);
                }
            }
        }

        return false;
    }

    /**
     * Recorta uma área específica da imagem conforme coordenadas do canvas [x, y, w, h].
     * Retorna o caminho do arquivo temporário recortado.
     */
    public function cropImageRegion(string $imgPath, int $x, int $y, int $w, int $h, int $canvasW = 0, int $canvasH = 0): ?string
    {
        $imgPath = str_replace('\\', '/', $imgPath);
        if (!file_exists($imgPath)) {
            return null;
        }

        $info = @getimagesize($imgPath);
        if (!$info) return null;

        $origW = (int) $info[0];
        $origH = (int) $info[1];

        $scaleX = ($canvasW > 0) ? ($origW / (float)$canvasW) : 1.0;
        $scaleY = ($canvasH > 0) ? ($origH / (float)$canvasH) : 1.0;

        $realX = (int) round($x * $scaleX);
        $realY = (int) round($y * $scaleY);
        $realW = (int) round($w * $scaleX);
        $realH = (int) round($h * $scaleY);

        // Garantir limites perfeitamente dentro da imagem
        $realX = max(0, min($realX, $origW - 10));
        $realY = max(0, min($realY, $origH - 10));
        $realW = max(10, min($realW, $origW - $realX));
        $realH = max(10, min($realH, $origH - $realY));

        $cropFile = WRITEPATH . 'uploads/crop_' . uniqid() . '.jpg';
        $cropFile = str_replace('\\', '/', $cropFile);

        // 1. Recorte via Python PIL (Infalível e de alta qualidade)
        $pyCode = <<<PYTHON
import os
from PIL import Image
try:
    src_p = "{$imgPath}"
    dst_p = "{$cropFile}"
    img = Image.open(src_p)
    left = {$realX}
    upper = {$realY}
    right = {$realX} + {$realW}
    lower = {$realY} + {$realH}
    cropped = img.crop((left, upper, right, lower))
    cropped.save(dst_p, quality=95)
    print("SUCCESS")
except Exception as e:
    print(f"ERROR: {e}")
PYTHON;

        $tmpPy = WRITEPATH . 'uploads/py_crop_' . uniqid() . '.py';
        file_put_contents($tmpPy, $pyCode);

        exec("python " . escapeshellarg($tmpPy) . " 2>&1", $out, $ret);
        @unlink($tmpPy);

        if (file_exists($cropFile) && filesize($cropFile) > 0) {
            return $cropFile;
        }

        // 2. Fallback via PHP GD
        if (function_exists('imagecrop')) {
            $mime = $info['mime'];
            $src  = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($imgPath),
                'image/png'  => @imagecreatefrompng($imgPath),
                'image/webp' => @imagecreatefromwebp($imgPath),
                default      => null,
            };

            if ($src) {
                $cropped = imagecrop($src, ['x' => $realX, 'y' => $realY, 'width' => $realW, 'height' => $realH]);
                if ($cropped) {
                    imagejpeg($cropped, $cropFile, 92);
                    imagedestroy($src);
                    imagedestroy($cropped);
                    return $cropFile;
                }
                imagedestroy($src);
            }
        }

        return null;
    }
}
