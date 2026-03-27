@echo off
echo === AI-Block Windows Installer ===
echo.

:: Verifica se está rodando como Administrador (necessário para criar pasta no Program Files)
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERRO] Por favor, rode este instalador como Administrador.
    pause
    exit /b 1
)

echo 1. Criando diretorio de instalacao na pasta Program Files...
mkdir "C:\Program Files\ai-block" 2>nul

echo 2. Copiando executavel e arquivos...
copy /Y ai_block.exe "C:\Program Files\ai-block\"
copy /Y manual.html "C:\Program Files\ai-block\"

echo 3. Baixando a mega-lista de bloqueio atualizada da Croacworks...
curl -s -o "C:\Program Files\ai-block\ai_list.json" "https://croacworks.com.br/ai_list.json"

echo.
echo ✅ Instalacao concluida com sucesso!
echo.
echo Para rodar a aplicacao:
echo - Va ate "C:\Program Files\ai-block\"
echo - Voce pode abrir o "manual.html" para ler as instrucoes.
echo - Execute ai_block.exe sempre como Administrador para poder alterar o hosts!
echo.
pause
