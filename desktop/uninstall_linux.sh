#!/bin/bash

# Ensure script stops on first error
set -e

echo "=== AI-Block Linux Uninstaller ==="
echo "WARNING: This will remove AI-Block and UNBLOCK all AIs in your hosts file."
read -p "Are you sure you want to proceed? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    echo "Uninstallation aborted."
    exit 1
fi

echo "1. Cleaning /etc/hosts..."
# Use sed to safely remove the block between INICIO and FIM (including the markers)
sudo sed -i '/# === INICIO AI-BLOCK ===/,/# === FIM AI-BLOCK ===/d' /etc/hosts

echo "2. Removing AI-Block from /opt/ai-block/..."
sudo rm -rf /opt/ai-block/

echo "3. Removing global shortcut..."
sudo rm -f /usr/local/bin/ai_block

echo "4. Removing desktop entry..."
sudo rm -f /usr/share/applications/ai-block.desktop

echo "5. Restoring Browser DNS Settings (Deleting Policies)..."
sudo rm -f /etc/opt/chrome/policies/managed/ai-block-chrome.json
sudo rm -f /etc/chromium/policies/managed/ai-block-chrome.json
sudo rm -f /etc/brave/policies/managed/ai-block-chrome.json
sudo rm -f /usr/lib/firefox/distribution/policies.json
sudo rm -f /etc/firefox/policies/policies.json

echo "6. Reverting Gateway Mode Settings..."
sudo rm -f /etc/sysctl.d/99-ai-block-gateway.conf
sudo sysctl -w net.ipv4.ip_forward=0
sudo rm -f /etc/dnsmasq.d/ai-block.conf
sudo systemctl restart dnsmasq 2>/dev/null || true

echo "7. Flushing Iptables DNS Redirect Rules..."
sudo iptables -t nat -D PREROUTING -p udp --dport 53 -j REDIRECT --to-ports 53 2>/dev/null || true
sudo iptables -t nat -D PREROUTING -p tcp --dport 53 -j REDIRECT --to-ports 53 2>/dev/null || true

echo "✅ Uninstallation complete. All AIs have been unblocked and files removed."
