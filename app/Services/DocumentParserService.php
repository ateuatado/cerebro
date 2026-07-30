<?php

namespace App\Services;

use App\Models\EntityModel;

// Garantir acesso às funções GD no namespace global
use function imagecreatefromjpeg;
use function imagecreatefrompng;
use function imagecreatefromwebp;
use function imagerotate;
use function imagecolorallocate;
use function imagesx;
use function imagesy;
use function imagecreatetruecolor;
use function imagefill;
use function imagecolorat;
use function imagesetpixel;
use function imagejpeg;
use function imagedestroy;
use function imagecopy;
use function getimagesize;

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
     * Analisa um arquivo e extrai seu conteúdo textual.
     * Para PDFs: tenta extrair texto direto ou converte páginas em imagens e faz OCR.
     * Para imagens: faz OCR via Tesseract.
     * Para texto: lê diretamente.
     *
     * @return array ['text' => string, 'pages' => int]
     */
    public function parseFile(string $filePath, string $extension): array
    {
        $filePath = str_replace('\\', '/', $filePath);

        if (!file_exists($filePath)) {
            return ['text' => '', 'pages' => 0];
        }

        $ext = strtolower($extension);

        // PDF: tentar extrair texto diretamente
        if ($ext === 'pdf') {
            return $this->parsePdf($filePath);
        }

        // Imagens: OCR
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff', 'tif'])) {
            $ocrText = $this->performOcr($filePath, $ext);
            return ['text' => $ocrText, 'pages' => 1];
        }

        // Texto puro: ler diretamente
        if (in_array($ext, ['txt', 'csv', 'md', 'html', 'htm', 'xml', 'json'])) {
            $content = @file_get_contents($filePath);
            if ($content === false) {
                return ['text' => '', 'pages' => 0];
            }
            // Se for HTML, tenta extrair só o texto
            if (in_array($ext, ['html', 'htm'])) {
                $content = strip_tags($content);
                $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            return ['text' => trim($content), 'pages' => 1];
        }

        return ['text' => '', 'pages' => 0];
    }

    /**
     * Extrai texto de um arquivo PDF.
     * Tenta extração nativa de texto primeiro, depois fallback para OCR por página.
     */
    private function parsePdf(string $pdfPath): array
    {
        // Tentar extrair texto nativo do PDF via pdftotext (Poppler)
        $text = $this->extractPdfText($pdfPath);
        if (!empty(trim($text))) {
            $pages = substr_count($text, "\f") + 1;
            return ['text' => $text, 'pages' => max(1, $pages)];
        }

        // Fallback: converter páginas para imagens e fazer OCR
        $cacheDir = str_replace('\\', '/', WRITEPATH . 'uploads/pdf_ocr_' . uniqid());
        $renderResult = $this->renderPdfPagesToCache($pdfPath, $cacheDir);
        $pages = $renderResult['totalPages'] ?? 0;

        if ($pages === 0) {
            return ['text' => '', 'pages' => 0];
        }

        $allText = '';
        foreach ($renderResult['pages'] as $i => $pageImg) {
            $pageNum = $i + 1;
            $pageText = $this->performOcr($pageImg, 'jpg');
            $allText .= "\n--- Página {$pageNum} ---\n" . $pageText;
        }

        // Limpar cache
        foreach ($renderResult['pages'] as $pageImg) {
            @unlink($pageImg);
        }
        @rmdir($cacheDir);

        return ['text' => trim($allText), 'pages' => $pages];
    }

    /**
     * Tenta extrair texto nativo de um PDF usando pdftotext (Poppler).
     */
    private function extractPdfText(string $pdfPath): string
    {
        $pdfPath = str_replace('\\', '/', $pdfPath);

        // Tentar pdftotext (Poppler) — comum em Linux e pode estar no PATH do Windows
        $cmd = 'pdftotext ' . escapeshellarg($pdfPath) . ' - 2>&1';
        exec($cmd, $output, $returnVar);
        if ($returnVar === 0 && !empty($output)) {
            $text = trim(implode("\n", $output));
            if (!empty($text)) {
                return $text;
            }
        }

        // Tentar caminhos comuns do Poppler no Windows
        $popplerPaths = [
            'C:\\Program Files\\Poppler\\bin\\pdftotext.exe',
            'C:\\Program Files (x86)\\Poppler\\bin\\pdftotext.exe',
            'C:\\Poppler\\bin\\pdftotext.exe',
        ];

        foreach ($popplerPaths as $pdftotext) {
            if (file_exists($pdftotext)) {
                $cmd = escapeshellarg($pdftotext) . ' ' . escapeshellarg($pdfPath) . ' - 2>&1';
                exec($cmd, $output2, $returnVar2);
                if ($returnVar2 === 0 && !empty($output2)) {
                    $text = trim(implode("\n", $output2));
                    if (!empty($text)) {
                        return $text;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Executa OCR usando o Tesseract nativo
     * SEM DEPENDÊNCIA DE PYTHON — apenas Tesseract via exec()
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

        // Fallback: tentar com caminhos comuns do Tesseract no Windows
        $tesseractPaths = [
            'C:\Program Files\Tesseract-OCR\tesseract.exe',
            'C:\Program Files (x86)\Tesseract-OCR\tesseract.exe',
            getenv('LOCALAPPDATA') . '\Tesseract-OCR\tesseract.exe',
        ];

        foreach ($tesseractPaths as $tesseractExe) {
            if (file_exists($tesseractExe)) {
                $cmd = escapeshellarg($tesseractExe) . ' ' . escapeshellarg($filePath) . ' stdout -l por 2>&1';
                exec($cmd, $outputLines2, $returnVar2);
                if ($returnVar2 === 0 && !empty($outputLines2)) {
                    $extractedText = trim(implode("\n", $outputLines2));
                    if (!empty($extractedText)) {
                        return $extractedText;
                    }
                }
            }
        }

        return '';
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

        // Encontrar o executável do Ghostscript
        $gsBin = $this->findGhostscript();
        if ($gsBin === null) {
            log_message('warning', 'Ghostscript não encontrado. Instale em https://ghostscript.com/releases/gsdnld.html');
            return ['totalPages' => 0, 'pages' => []];
        }

        // Obter número de páginas (Ghostscript 10+ requer -dNOSAFER para acesso a arquivos via PostScript)
        $pagesCmd = escapeshellarg($gsBin)
            . ' -dNOPAUSE -dBATCH -dNODISPLAY -dNOSAFER'
            . ' -c "(' . escapeshellarg($pdfPath) . ') (r) file runpdfbegin pdfpagecount = quit" 2>&1';
        exec($pagesCmd, $pagesOutput, $pagesReturn);
        $totalPages = intval(implode('', $pagesOutput) ?: 1);
        if ($totalPages < 1) $totalPages = 1;

        // Renderizar cada página como JPEG
        $pages = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            $outputFile = $cacheDir . '/page_' . $i . '.jpg';
            $gsCmd = escapeshellarg($gsBin)
                . ' -dNOPAUSE -dBATCH -dSAFER'
                . ' -sDEVICE=jpeg'
                . ' -dJPEGQ=85'
                . ' -r200'
                . ' -dFirstPage=' . $i
                . ' -dLastPage=' . $i
                . ' -sOutputFile=' . escapeshellarg($outputFile)
                . ' ' . escapeshellarg($pdfPath)
                . ' 2>&1';
            exec($gsCmd, $gsOutput, $gsReturn);

            if (file_exists($outputFile) && filesize($outputFile) > 0) {
                $pages[] = $outputFile;
            }
        }

        return [
            'totalPages' => count($pages),
            'pages'      => $pages,
        ];
    }

    /**
     * Localiza o executável do Ghostscript no sistema.
     */
    private function findGhostscript(): ?string
    {
        // Tentar no PATH
        $which = 'where gswin64c 2>nul';
        exec($which, $out, $ret);
        if ($ret === 0 && !empty($out[0]) && file_exists(trim($out[0]))) {
            return trim($out[0]);
        }

        // Tentar gswin64 (sem console)
        $which2 = 'where gswin64 2>nul';
        exec($which2, $out2, $ret2);
        if ($ret2 === 0 && !empty($out2[0]) && file_exists(trim($out2[0]))) {
            return trim($out2[0]);
        }

        // Caminhos comuns de instalação
        $paths = [
            'C:\\Program Files\\gs\\gs10.07.1\\bin\\gswin64c.exe',
            'C:\\Program Files\\gs\\gs10.07.0\\bin\\gswin64c.exe',
            'C:\\Program Files\\gs\\gs10.06.0\\bin\\gswin64c.exe',
            'C:\\Program Files\\gs\\gs10.05.0\\bin\\gswin64c.exe',
            'C:\\Program Files\\gs\\gs10.04.0\\bin\\gswin64c.exe',
            'C:\\Program Files (x86)\\gs\\gs10.07.1\\bin\\gswin32c.exe',
            'C:\\Program Files (x86)\\gs\\gs10.07.0\\bin\\gswin32c.exe',
        ];

        // Também buscar por padrão no Program Files
        $gsDirs = glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe');
        if (!empty($gsDirs)) {
            $paths = array_merge($paths, $gsDirs);
        }
        $gsDirs86 = glob('C:\\Program Files (x86)\\gs\\gs*\\bin\\gswin32c.exe');
        if (!empty($gsDirs86)) {
            $paths = array_merge($paths, $gsDirs86);
        }

        foreach ($paths as $p) {
            if (file_exists($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Rotaciona uma imagem física (JPEG/PNG) em um determinado ângulo (ex: 90, 180, 270 graus).
     * Usa o motor Python PIL (infalível em qualquer tamanho de imagem) com fallback em PHP GD nativo.
     */
    public function rotateImageFile(string $imgPath, int $degrees): bool
    {
        $imgPath = str_replace('\\', '/', $imgPath);
        if (!file_exists($imgPath)) {
            return false;
        }

        // Normalizar ângulo para 0, 90, 180, 270
        $degrees = (($degrees % 360) + 360) % 360;

        // Rotação via PHP GD — fallback manual para 90°, 180°, 270°
        $info = @getimagesize($imgPath);
        if (!$info) return false;

        $mime = $info['mime'];
        $src  = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($imgPath),
            'image/png'  => @imagecreatefrompng($imgPath),
            'image/webp' => @imagecreatefromwebp($imgPath),
            default      => null,
        };

        if (!$src) return false;

        // 2a. Usar imagerotate se disponível (PHP GD nativo)
        if (function_exists('imagerotate')) {
            $angle   = (360 - $degrees) % 360;
            $bg      = imagecolorallocate($src, 255, 255, 255);
            $rotated = imagerotate($src, $angle, $bg);

            if ($rotated) {
                imagejpeg($rotated, $imgPath, 92);
                imagedestroy($src);
                imagedestroy($rotated);
                return true;
            }
        }

        // 2b. Fallback manual para 90°, 180°, 270° sem imagerotate
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        if ($degrees === 90 || $degrees === 270) {
            $dstW = $srcH;
            $dstH = $srcW;
        } else {
            $dstW = $srcW;
            $dstH = $srcH;
        }

        $rotated = imagecreatetruecolor($dstW, $dstH);
        if (!$rotated) {
            imagedestroy($src);
            return false;
        }

        // Fundo branco
        $white = imagecolorallocate($rotated, 255, 255, 255);
        imagefill($rotated, 0, 0, $white);

        for ($x = 0; $x < $srcW; $x++) {
            for ($y = 0; $y < $srcH; $y++) {
                $rgb = imagecolorat($src, $x, $y);
                switch ($degrees) {
                    case 90:
                        imagesetpixel($rotated, $dstW - 1 - $y, $x, $rgb);
                        break;
                    case 180:
                        imagesetpixel($rotated, $dstW - 1 - $x, $dstH - 1 - $y, $rgb);
                        break;
                    case 270:
                        imagesetpixel($rotated, $y, $dstH - 1 - $x, $rgb);
                        break;
                    default:
                        imagesetpixel($rotated, $x, $y, $rgb);
                        break;
                }
            }
        }

        imagejpeg($rotated, $imgPath, 92);
        imagedestroy($src);
        imagedestroy($rotated);
        return true;
    }

    /**
     * Recorta uma área específica da imagem conforme coordenadas do canvas [x, y, w, h].
     * Retorna o caminho do arquivo temporário recortado.
     * Usa recorte nativo via PHP GD (ImageCopy) que funciona em qualquer PHP 5.6+ sem dependências externas.
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

        // Recorte via PHP GD (funciona sem Python, usando imagecopy)
        $mime = $info['mime'];
        $src  = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($imgPath),
            'image/png'  => @imagecreatefrompng($imgPath),
            'image/webp' => @imagecreatefromwebp($imgPath),
            default      => null,
        };

        if ($src) {
            $cropped = imagecreatetruecolor($realW, $realH);
            if ($cropped) {
                imagecopy($cropped, $src, 0, 0, $realX, $realY, $realW, $realH);
                imagejpeg($cropped, $cropFile, 92);
                imagedestroy($src);
                imagedestroy($cropped);

                if (file_exists($cropFile) && filesize($cropFile) > 0) {
                    return $cropFile;
                }
            }
            imagedestroy($src);
        }

        return null;
    }
}
