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

echo "✅ Uninstallation complete. All AIs have been unblocked and files removed."
