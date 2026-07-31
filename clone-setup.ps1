# ==============================================================================
# Script de Instalação e Clonagem Automática do Projeto Cerebro (Windows)
# Execução: .\clone-setup.ps1
# ==============================================================================

$ErrorActionPreference = "Continue"

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "     PROJETO CEREBRO - SCRIPT DE CLONAGEM E CONFIGURACAO     " -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Verificar PHP
$phpBin = Get-Command "php" -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source
if (-not $phpBin -and (Test-Path "C:\xampp\php\php.exe")) {
    $phpBin = "C:\xampp\php\php.exe"
    $env:Path += ";C:\xampp\php"
}

if ($phpBin) {
    Write-Host "[OK] PHP encontrado em: $phpBin" -ForegroundColor Green
} else {
    Write-Host "[ERRO] PHP nao encontrado no sistema! Instale o XAMPP ou PHP 8.1+." -ForegroundColor Red
    exit 1
}

# 2. Restaurar Dependencias Composer
Write-Host "`n[1/6] Instalando dependencias PHP via Composer..." -ForegroundColor Yellow
if (Get-Command "composer" -ErrorAction SilentlyContinue) {
    composer install --no-interaction --prefer-dist
} else {
    Write-Host "[AVISO] Composer CLI nao encontrado no PATH. Tentando via php composer.phar..." -ForegroundColor DarkYellow
    if (Test-Path "composer.phar") {
        php composer.phar install --no-interaction --prefer-dist
    } else {
        Write-Host "[ERRO] Composer nao encontrado. Por favor instale o Composer em https://getcomposer.org" -ForegroundColor Red
    }
}

# 3. Criar Arquivo de Configuracao .env se nao existir
Write-Host "`n[2/6] Verificando arquivo de configuracao (.env)..." -ForegroundColor Yellow
if (-not (Test-Path ".env")) {
    Write-Host "  -> Criando arquivo .env a partir do modelo..." -ForegroundColor Cyan
    $envContent = @"
#--------------------------------------------------------------------
# ENVIRONMENT
#--------------------------------------------------------------------
CI_ENVIRONMENT = development

#--------------------------------------------------------------------
# APP
#--------------------------------------------------------------------
app.baseURL = 'http://localhost/cerebro/public/'
app.indexPage = ''
app.forceGlobalSecureRequests = false

#--------------------------------------------------------------------
# DATABASE (PostgreSQL)
#--------------------------------------------------------------------
database.default.hostname = localhost
database.default.database = cerebro
database.default.username = postgres
database.default.password = postgres
database.default.DBDriver = Postgre
database.default.port = 5432
database.default.schema = public

#--------------------------------------------------------------------
# API KEYS & SERVICES
#--------------------------------------------------------------------
DEEPSEEK_API_KEY = SUA_CHAVE_DEEPSEEK_AQUI
GEMINI_API_KEY = SUA_CHAVE_GEMINI_AQUI
"@
    Set-Content -Path ".env" -Value $envContent -Encoding UTF8
    Write-Host "  -> Arquivo .env criado! Atualize as chaves DEEPSEEK_API_KEY e GEMINI_API_KEY se necessario." -ForegroundColor Green
} else {
    Write-Host "  -> Arquivo .env ja existe." -ForegroundColor DarkGray
}

# 4. Criar Estrutura de Pastas Writable
Write-Host "`n[3/6] Criando estrutura de pastas writable..." -ForegroundColor Yellow
$writableFolders = @(
    "writable\cache",
    "writable\logs",
    "writable\session",
    "writable\tessdata",
    "writable\uploads\documents",
    "writable\uploads\page_cache"
)

foreach ($folder in $writableFolders) {
    if (-not (Test-Path $folder)) {
        New-Item -Path $folder -ItemType Directory -Force | Out-Null
        Write-Host "  -> Criada pasta: $folder" -ForegroundColor Cyan
    }
}

# Prevenir CacheException no CodeIgniter
if (-not (Test-Path "writable\cache\index.html")) {
    Set-Content -Path "writable\cache\index.html" -Value '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>'
}

# 5. Baixar por.traineddata para Tesseract em Portugues
Write-Host "`n[4/6] Verificando modelo de OCR em Portugues (tessdata/por.traineddata)..." -ForegroundColor Yellow
$porFile = "writable\tessdata\por.traineddata"
if (-not (Test-Path $porFile)) {
    Write-Host "  -> Baixando por.traineddata do repositório oficial Tesseract..." -ForegroundColor Cyan
    try {
        $url = "https://github.com/tesseract-ocr/tessdata_fast/raw/main/por.traineddata"
        Invoke-WebRequest -Uri $url -OutFile $porFile -UseBasicParsing
        Write-Host "  -> [OK] por.traineddata baixado com sucesso!" -ForegroundColor Green
    } catch {
        Write-Host "  -> [AVISO] Falha ao baixar por.traineddata automaticamente. Baixe manualmente de: https://github.com/tesseract-ocr/tessdata_fast/raw/main/por.traineddata para $porFile" -ForegroundColor Red
    }
} else {
    Write-Host "  -> [OK] por.traineddata ja esta presente." -ForegroundColor Green
}

# 6. Rodar Migrações do Banco de Dados
Write-Host "`n[5/6] Executando migracoes do banco de dados PostgreSQL..." -ForegroundColor Yellow
try {
    php spark migrate
    Write-Host "  -> [OK] Migraçoes concluídas!" -ForegroundColor Green
} catch {
    Write-Host "  -> [AVISO] Nao foi possivel executar 'php spark migrate'. Verifique se o PostgreSQL esta rodando e o banco 'cerebro' foi criado." -ForegroundColor Red
}

# 7. Diagnosticar Softwares Externos (Ghostscript e Tesseract)
Write-Host "`n[6/6] Verificando softwares externos recomendados (OCR e PDF)..." -ForegroundColor Yellow

# Ghostscript
$gsInstalled = $false
if ((Get-Command "gswin64c" -ErrorAction SilentlyContinue) -or (Get-Command "gs" -ErrorAction SilentlyContinue)) {
    $gsInstalled = $true
} else {
    $gsPaths = Get-ChildItem "C:\Program Files\gs" -ErrorAction SilentlyContinue
    if ($gsPaths) { $gsInstalled = $true }
}

if ($gsInstalled) {
    Write-Host "  -> [OK] Ghostscript instalado!" -ForegroundColor Green
} else {
    Write-Host "  -> [PENDENTE] Ghostscript nao encontrado. Baixe e instale de: https://ghostscript.com/releases/gsdnld.html" -ForegroundColor DarkYellow
}

# Tesseract
$tessInstalled = $false
if (Get-Command "tesseract" -ErrorAction SilentlyContinue) {
    $tessInstalled = $true
} elseif (Test-Path "C:\Program Files\Tesseract-OCR\tesseract.exe") {
    $tessInstalled = $true
}

if ($tessInstalled) {
    Write-Host "  -> [OK] Tesseract OCR instalado!" -ForegroundColor Green
} else {
    Write-Host "  -> [PENDENTE] Tesseract OCR nao encontrado. Baixe o instalador Windows em: https://github.com/UB-Mannheim/tesseract/wiki" -ForegroundColor DarkYellow
}

Write-Host "`n============================================================" -ForegroundColor Green
Write-Host "     CLONAGEM E CONFIGURACAO CONCLUIDAS COM SUCESSO!     " -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host "Para rodar o projeto localmente:" -ForegroundColor White
Write-Host "  1. Certifique-se que o Apache e PostgreSQL estao ativos no XAMPP." -ForegroundColor White
Write-Host "  2. Acesse no navegador: http://localhost/cerebro/public/" -ForegroundColor White
Write-Host "  3. Ou execute: php spark serve" -ForegroundColor White
Write-Host ""
