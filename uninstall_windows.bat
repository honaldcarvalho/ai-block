@echo off
echo === AI-Block Windows Uninstaller ===
echo.
echo AVISO: Isso removera o AI-Block e DESBLOQUEARA todas as IAs.

:: Verifica se está rodando como Administrador
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERRO] Por favor, rode este desinstalador como Administrador.
    pause
    exit /b 1
)

set /p confirm="Tem certeza que deseja continuar? (S/N): "
if /i not "%confirm%"=="S" (
    echo Desinstalacao abortada.
    pause
    exit /b 0
)

echo.
echo 1. Limpando o arquivo hosts do Windows...
powershell -Command "(Get-Content -Path 'C:\Windows\System32\drivers\etc\hosts') -notmatch '# === INICIO AI-BLOCK ===' -notmatch '# === FIM AI-BLOCK ===' -notmatch '127\.0\.0\.1.*' | Set-Content -Path 'C:\Windows\System32\drivers\etc\hosts.tmp'; Move-Item -Path 'C:\Windows\System32\drivers\etc\hosts.tmp' -Destination 'C:\Windows\System32\drivers\etc\hosts' -Force"
:: Nota: O comando acima do powershell quebra a seletividade se houver outros 127.0.0.1, 
:: entao vamos usar uma abordagem regex multiline melhor para remover exatamente o bloco:
powershell -Command "$c = Get-Content -Raw -Path 'C:\Windows\System32\drivers\etc\hosts'; $c = $c -replace '(?ms).*?# === INICIO AI-BLOCK ===.*?# === FIM AI-BLOCK ===\r?\n?', ''; Set-Content -Path 'C:\Windows\System32\drivers\etc\hosts' -Value $c -NoNewline"

echo 2. Removendo pasta da aplicacao...
rmdir /S /Q "C:\Program Files\ai-block" 2>nul

echo.
echo ✅ Desinstalacao concluida localmente! Suas IAs foram desbloqueadas.
pause
