#!/usr/bin/env bash
set -euo pipefail

RAW_URL="https://raw.githubusercontent.com/garangan417/kiosmafazaPOS/main/thermal/print-thermal.py"
PY_TARGET="$HOME/print-thermal.py"
SERVICE_DIR="$HOME/.config/systemd/user"
SERVICE_FILE="$SERVICE_DIR/kios-thermal.service"

echo "=== Kios Mafaza Thermal Installer (Arch Linux) ==="

# 1. Unduh print-thermal.py terbaru dari branch main
echo "[1/4] Mengunduh print-thermal.py..."
curl -fsSL "$RAW_URL" -o "$PY_TARGET"
chmod +x "$PY_TARGET"

# 2. Aturan udev, tmpfiles.d, dan modul usblp agar /dev/usb/lp* mode 0666
echo "[2/4] Mengatur izin device /dev/usb/lp* (butuh sudo)..."
sudo tee /etc/udev/rules.d/99-kios-thermal-universal.rules > /dev/null <<'EOF'
SUBSYSTEM=="usbmisc", KERNEL=="lp[0-9]*", MODE="0666", RUN+="/usr/bin/chmod 0666 /dev/usb/%k /dev/%k"
KERNEL=="lp[0-9]*", MODE="0666", RUN+="/usr/bin/chmod 0666 /dev/usb/%k /dev/%k"
EOF

sudo tee /etc/tmpfiles.d/kios-thermal.conf > /dev/null <<'EOF'
z /dev/usb/lp* 0666 root lp -
EOF

echo "usblp" | sudo tee /etc/modules-load.d/usblp.conf > /dev/null
sudo modprobe usblp 2>/dev/null || true
sudo udevadm control --reload-rules
sudo udevadm trigger --action=add /dev/usb/lp* 2>/dev/null || true
sudo systemd-tmpfiles --create

# 3. Pasang & jalankan systemd user service
echo "[3/4] Memasang systemd user service kios-thermal.service..."
mkdir -p "$SERVICE_DIR"
cat > "$SERVICE_FILE" <<'EOF'
[Unit]
Description=Kios Mafaza Thermal Print Bridge
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/python3 %h/print-thermal.py
Restart=always
RestartSec=2

[Install]
WantedBy=default.target
EOF

systemctl --user daemon-reload
systemctl --user enable --now kios-thermal.service
loginctl enable-linger "$USER" 2>/dev/null || true

# 4. Cek status service
echo "[4/4] Mengecek status service..."
sleep 1
systemctl --user --no-pager --full status kios-thermal.service | head -n 12

echo ""
echo "Selesai bro! Service aktif di http://127.0.0.1:9100"
