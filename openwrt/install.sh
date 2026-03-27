#!/bin/sh
# AI-Block: OpenWRT Multi-Group Sync Script
# Configure o IP do seu servidor Docker abaixo:
SERVER_URL="http://192.168.1.100:8080"

echo "=== AI-Block: Sincronizando Regras do Painel Web ==="

# 1. Busca a lista de grupos ativos
GROUPS=$(uclient-fetch -q -O - "$SERVER_URL/api.php?action=groups&type=text")

if [ -z "$GROUPS" ]; then
    echo "Erro: Nao foi possivel obter a lista de grupos do servidor."
    exit 1
fi

# Limpa regras anteriores de IPSET e IPTABLES para evitar duplicatas
echo "Limpando configuracoes anteriores..."
iptables -F FORWARD
ipset destroy ai_block_all 2>/dev/null

for GROUP in $GROUPS; do
    echo "Processando Grupo: $GROUP"
    
    # Cria o IPSET para este grupo
    IPSET_NAME="ai_block_$GROUP"
    ipset create "$IPSET_NAME" hash:ip 2>/dev/null || ipset flush "$IPSET_NAME"
    
    # Configura o dnsmasq para este grupo
    # Busca dominios do grupo
    DOMAINS=$(uclient-fetch -q -O - "$SERVER_URL/api.php?action=config&group=$GROUP&target=domains&type=text")
    
    # Limpa config antiga do dnsmasq para este grupo
    CONF_FILE="/etc/dnsmasq.d/ai_block_$GROUP.conf"
    echo "# AI-Block Group: $GROUP" > "$CONF_FILE"
    
    for DOMAIN in $DOMAINS; do
        echo "ipset=/$DOMAIN/$IPSET_NAME" >> "$CONF_FILE"
    done
    
    # Aplica bloqueio para os MACs deste grupo
    MACS=$(uclient-fetch -q -O - "$SERVER_URL/api.php?action=config&group=$GROUP&target=macs&type=text")
    for MAC in $MACS; do
        echo "Bloqueando MAC $MAC para o Grupo $GROUP"
        iptables -I FORWARD -m mac --mac-source "$MAC" -m set --match-set "$IPSET_NAME" dst -j REJECT
    done
done

# Reinicia dnsmasq para aplicar novos dominios
/etc/init.d/dnsmasq restart

echo "✅ Sincronizacao concluida com sucesso!"

# Adiciona ao Crontab se nao existir (Sincroniza a cada 6 horas)
if ! grep -q "openwrt_sync.sh" /etc/crontabs/root; then
    echo "0 */6 * * * /root/openwrt_sync.sh" >> /etc/crontabs/root
    /etc/init.d/cron restart
fi
