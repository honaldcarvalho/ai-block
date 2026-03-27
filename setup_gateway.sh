#!/bin/bash
# AI-Block: Gateway Mode Setup Script
# Este script transforma esta maquina em um "DNS Blackhole" para a sua rede.

echo "=== AI-Block: Configurando Modo Gateway (DNS Blackhole) ==="

# 1. Verifica root
if [ "$EUID" -ne 0 ]; then
  echo "Por favor, execute este script como root (sudo ./setup_gateway.sh)"
  exit 1
fi

# 2. Habilita o encaminhamento de pacotes IPv4 (Routing)
echo "1. Habilitando encaminhamento de pacotes (IP Forwarding)..."
sysctl -w net.ipv4.ip_forward=1
echo "net.ipv4.ip_forward=1" > /etc/sysctl.d/99-ai-block-gateway.conf

# 3. Instala dnsmasq (se nao existir)
if ! command -v dnsmasq &> /dev/null; then
    echo "2. Instalando dnsmasq..."
    apt-get update && apt-get install -y dnsmasq
fi

# 4. Configura dnsmasq para ler o arquivo hosts do AI-Block
echo "3. Configurando dnsmasq..."
cat <<EOF > /etc/dnsmasq.d/ai-block.conf
# AI-Block Gateway Configuration
interface=eth0
interface=wlan0
listen-address=127.0.0.1
bind-interfaces
expand-hosts
domain-needed
bogus-priv
# Faz o dnsmasq ler as regras do /etc/hosts que o AI-Block ja gerencia
no-hosts
addn-hosts=/etc/hosts
EOF

# Reinicia o dnsmasq
systemctl restart dnsmasq

# 5. Configura Iptables para capturar vazamentos de DNS
echo "4. Configurando Firewall (Iptables) para interceptar DNS..."
# Redireciona qualquer consulta DNS vinda da rede para o nosso dnsmasq local
iptables -t nat -A PREROUTING -p udp --dport 53 -j REDIRECT --to-ports 53
iptables -t nat -A PREROUTING -p tcp --dport 53 -j REDIRECT --to-ports 53

# Persistencia das regras (opcional, requer iptables-persistent)
if command -v iptables-save &> /dev/null; then
    mkdir -p /etc/iptables/
    iptables-save > /etc/iptables/rules.v4
fi

echo ""
echo "✅ MODO GATEWAY ATIVADO!"
echo "--------------------------------------------------------"
echo "Esta maquina agora e um 'Filtro de IA' para a sua rede."
echo ""
echo "COMO USAR NO CLIENTE (Outra maquina/celular):"
echo "1. Nas configuracoes de rede do outro dispositivo,"
echo "   mude o GATEWAY e o DNS para o IP desta maquina."
echo "2. Todas as IAs bloqueadas aqui serao bloqueadas la tambem,"
echo "   independentemente do navegador!"
echo "--------------------------------------------------------"
