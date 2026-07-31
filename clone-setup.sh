#!/usr/bin/env bash
# ==============================================================================
# Script de Instalação e Clonagem Automática do Projeto Cerebro (Linux Ubuntu / VPS)
# Execução: bash clone-setup.sh
# ==============================================================================

set -e

echo "============================================================"
echo "   PROJETO CEREBRO - SETUP E CLONAGEM (LINUX UBUNTU / VPS)   "
echo "============================================================"

# 1. Instalar dependências do sistema via APT (Ghostscript, Tesseract OCR em Português)
echo -e "\n[1/6] Instalando dependências de sistema (Ghostscript, Tesseract OCR, Poppler)..."
if command -v apt-get &> /dev/null; then
    sudo apt-get update -y
    sudo apt-get install -y php-cli php-curl php-pgsql php-mbstring php-xml php-zip \
                            composer ghostscript poppler-utils tesseract-ocr tesseract-ocr-por curl
fi

# 2. Restaurar dependências PHP do Composer
echo -e "\n[2/6] Instalando dependências PHP via Composer..."
composer install --no-interaction --prefer-dist

# 3. Criar arquivo de configuração .env se não existir
echo -e "\n[3/6] Verificando arquivo .env..."
if [ ! -f ".env" ]; then
    cat << 'EOF' > .env
#--------------------------------------------------------------------
# ENVIRONMENT
#--------------------------------------------------------------------
CI_ENVIRONMENT = development

#--------------------------------------------------------------------
# APP
#--------------------------------------------------------------------
app.baseURL = 'http://localhost/'
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
EOF
    echo "  -> Arquivo .env criado a partir do modelo!"
fi

# 4. Criar estrutura de pastas writable e ajustar permissões
echo -e "\n[4/6] Configurando diretórios writable..."
mkdir -p writable/cache writable/logs writable/session writable/tessdata writable/uploads/documents writable/uploads/page_cache
chmod -R 777 writable/

if [ ! -f "writable/cache/index.html" ]; then
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > writable/cache/index.html
fi

# 5. Baixar por.traineddata se necessário
echo -e "\n[5/6] Verificando modelo de OCR em Português..."
if [ ! -f "writable/tessdata/por.traineddata" ]; then
    echo "  -> Baixando por.traineddata..."
    curl -sSL "https://github.com/tesseract-ocr/tessdata_fast/raw/main/por.traineddata" -o writable/tessdata/por.traineddata
fi

# 6. Rodar Migrações do Banco de Dados
echo -e "\n[6/6] Executando migrações do PostgreSQL..."
php spark migrate || echo "  -> [AVISO] Certifique-se de que o banco de dados 'cerebro' foi criado no PostgreSQL."

echo -e "\n============================================================"
echo "     CLONAGEM E CONFIGURAÇÃO CONCLUÍDAS COM SUCESSO!     "
echo "============================================================"
echo "Para rodar o servidor embutido:"
echo "  php spark serve --host 0.0.0.0 --port 8080"
echo ""
