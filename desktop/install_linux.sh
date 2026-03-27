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
    # Encerra processos rodando para evitar "Text file busy"
    sudo pkill -9 ai_block 2>/dev/null || true
    sudo cp ai_block /opt/ai-block/
    sudo cp iablock-ico.png /opt/ai-block/
    
    echo "6. Downloading newest JSON list from Croacworks..."
    sudo curl -s -o /opt/ai-block/ai_list.json https://croacworks.com.br/ai_list.json
    
    echo "7. Creating shortcut in Menu Application..."
    cat <<EOF | sudo tee /usr/share/applications/ai-block.desktop > /dev/null
[Desktop Entry]
Name=AI Block
Comment=Bloqueador de Inteligência Artificial
Exec=ai_block
Icon=/opt/ai-block/iablock-ico.png
Terminal=false
Type=Application
Categories=Security;System;
EOF

    echo "8. Creating a shortcut in /usr/local/bin/ para acesso global..."
    sudo ln -sf /opt/ai-block/ai_block /usr/local/bin/ai_block

    echo "9. Automating DoH Disabling (Chrome, Chromium, Brave & Firefox Policies)..."
    # Google Chrome Policy
    sudo mkdir -p /etc/opt/chrome/policies/managed
    cat <<EOF | sudo tee /etc/opt/chrome/policies/managed/ai-block-chrome.json > /dev/null
{
  "BuiltInDnsClientEnabled": false,
  "DnsOverHttpsMode": "off"
}
EOF

    # Chromium Policy
    sudo mkdir -p /etc/chromium/policies/managed
    sudo cp /etc/opt/chrome/policies/managed/ai-block-chrome.json /etc/chromium/policies/managed/ai-block-chrome.json

    # Brave Policy
    sudo mkdir -p /etc/brave/policies/managed
    sudo cp /etc/opt/chrome/policies/managed/ai-block-chrome.json /etc/brave/policies/managed/ai-block-chrome.json

    # Firefox Policy (standard location)
    sudo mkdir -p /usr/lib/firefox/distribution
    cat <<EOF | sudo tee /usr/lib/firefox/distribution/policies.json > /dev/null
{
  "policies": {
    "DNSOverHTTPS": {
      "Enabled": false,
      "Locked": true
    }
  }
}
EOF

    # Alternative Firefox path for some distros
    sudo mkdir -p /etc/firefox/policies
    sudo cp /usr/lib/firefox/distribution/policies.json /etc/firefox/policies/policies.json 2>/dev/null || true
    
    echo ""
    echo "✅ Installation complete!"
    echo "Você pode agora executar 'sudo ai_block' no seu terminal para abrir a interface gráfica."
    echo "Ou use 'sudo ai_block --block' para ativar os bloqueios silenciosamente no CLI."
else
    echo "❌ Error: Compilation failed. Please check if all dependencies are installed."
    exit 1
fi
