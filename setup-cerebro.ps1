# Script de scaffolding do projeto Cerebro
# Roda a partir da raiz do projeto CodeIgniter (ex: C:\xampp\htdocs\cerebro)
# Cria a estrutura de assets locais e coloca o AGENTS.md na raiz.

$ErrorActionPreference = "Stop"

Write-Host "Criando estrutura de pastas do projeto Cerebro..." -ForegroundColor Cyan

$folders = @(
    "public\assets\css",
    "public\assets\js",
    "public\assets\img",
    "public\assets\vendor\bootstrap\css",
    "public\assets\vendor\bootstrap\js",
    "public\assets\vendor\bootstrap\icons"
)

foreach ($folder in $folders) {
    if (-not (Test-Path $folder)) {
        New-Item -Path $folder -ItemType Directory -Force | Out-Null
        Write-Host "  Criada: $folder"
    } else {
        Write-Host "  Ja existe: $folder" -ForegroundColor DarkGray
    }
}

# .gitkeep para manter pastas vazias rastreadas pelo git
foreach ($folder in $folders) {
    $gitkeep = Join-Path $folder ".gitkeep"
    if (-not (Test-Path $gitkeep)) {
        New-Item -Path $gitkeep -ItemType File -Force | Out-Null
    }
}

Write-Host ""
Write-Host "Estrutura criada. Proximos passos manuais:" -ForegroundColor Yellow
Write-Host "  1. Baixe o Bootstrap (https://getbootstrap.com) e extraia css/js"
Write-Host "     para public\assets\vendor\bootstrap\ (NAO usar CDN)."
Write-Host "  2. Copie o AGENTS.md para a raiz do projeto, se ainda nao estiver la."
Write-Host "  3. Rode 'pi' na raiz do projeto para carregar o contexto."
Write-Host ""
Write-Host "Concluido." -ForegroundColor Green
