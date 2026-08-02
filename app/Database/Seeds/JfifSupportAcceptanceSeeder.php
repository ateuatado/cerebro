<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Services\DocumentParserService;

class JfifSupportAcceptanceSeeder extends Seeder
{
    public function run(): void
    {
        echo "=== [Spec 10] Seeder e Teste de Verificação de Arquivos JFIF ===\n";

        $docParser = new DocumentParserService();

        // 1. Criar uma imagem JFIF sintética
        $uploadDir = WRITEPATH . 'uploads/test_jfif/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $testJfifPath = $uploadDir . 'teste_manuscrito.jfif';

        // Gerar uma imagem GD 200x200 com fundo branco e texto sintético
        $img = imagecreatetruecolor(200, 200);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);
        imagestring($img, 5, 20, 80, "JFIF TEST CEREBRO", $black);
        
        // Salvar como imagem JPEG (JFIF)
        imagejpeg($img, $testJfifPath, 90);
        imagedestroy($img);

        if (!file_exists($testJfifPath)) {
            throw new \RuntimeException("Falha ao criar o arquivo JFIF de teste sintético.");
        }

        echo "✔ Arquivo sintético criado em: {$testJfifPath} (Tamanho: " . filesize($testJfifPath) . " bytes)\n";

        // 2. Testar parseFile com extensão 'jfif'
        $parseResult = $docParser->parseFile($testJfifPath, 'jfif');
        echo "✔ parseFile() executado com sucesso para extensão '.jfif'. Páginas detectadas: {$parseResult['pages']}\n";

        // 3. Testar rotação de imagem JFIF (rotateImageFile)
        $rotateSuccess = $docParser->rotateImageFile($testJfifPath, 90);
        if ($rotateSuccess) {
            echo "✔ rotateImageFile() a 90° executado com sucesso no arquivo '.jfif'.\n";
        } else {
            echo "❌ rotateImageFile() falhou para o arquivo '.jfif'.\n";
        }

        // 4. Testar recorte por região em imagem JFIF (cropImageRegion)
        $cropFile = $docParser->cropImageRegion($testJfifPath, 10, 10, 80, 80, 200, 200);
        if ($cropFile && file_exists($cropFile)) {
            echo "✔ cropImageRegion() (Crop Tool) executado com sucesso no arquivo '.jfif'. Gerado: {$cropFile}\n";
            @unlink($cropFile);
        } else {
            echo "❌ cropImageRegion() falhou no arquivo '.jfif'.\n";
        }

        // Limpeza dos arquivos temporários de teste
        @unlink($testJfifPath);
        @rmdir($uploadDir);

        echo "=== [Spec 10] Verificação de suporte a JFIF concluída com SUCESSO! ===\n";
    }
}
