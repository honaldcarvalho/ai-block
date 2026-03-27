#!/bin/bash

# Ensure script stops on first error
set -e

echo "=== AI-Block Linux Installer ==="

echo "1. Updating package list..."
sudo apt update

echo "2. Installing dependencies (build-essential, pkg-config, libgtk-3-dev, curl)..."
sudo apt install -y build-essential pkg-config libgtk-3-dev curl

echo "3. Compiling AI-Block..."
if make; then
    echo "Compilation successful."
    
    echo "4. Creating application directory at /opt/ai-block/..."
    sudo mkdir -p /opt/ai-block
    
    echo "5. Installing binary to /opt/ai-block/..."
    sudo cp ai_block /opt/ai-block/
    
    echo "6. Downloading newest JSON list from Croacworks..."
    sudo curl -s -o /opt/ai-block/ai_list.json https://croacworks.com.br/ai_list.json
    
    echo "7. Creating a shortcut in /usr/local/bin/ para acesso global..."
    sudo ln -sf /opt/ai-block/ai_block /usr/local/bin/ai_block
    
    echo ""
    echo "✅ Installation complete!"
    echo "Você pode agora executar 'sudo ai_block' no seu terminal para abrir a interface gráfica."
    echo "Ou use 'sudo ai_block --block' para ativar os bloqueios silenciosamente no CLI."
else
    echo "❌ Error: Compilation failed. Please check if all dependencies are installed."
    exit 1
fi
