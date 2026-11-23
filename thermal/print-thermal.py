#!/usr/bin/env python3
import glob
import json
from http.server import BaseHTTPRequestHandler, HTTPServer

WIDTH = 32
ESC = b"\x1b"
GS = b"\x1d"

INIT = ESC + b"@"
ALIGN_LEFT = ESC + b"a\x00"
ALIGN_CEN = ESC + b"a\x01"
BOLD_ON = ESC + b"E\x01"
BOLD_OFF = ESC + b"E\x00"
DOUBLE_ON = GS + b"!\x11"
NORMAL_SIZE = GS + b"!\x00"


def fmt_num(n):
    try:
        val = int(round(float(n)))
    except Exception:
        val = 0
    return f"{val:,}".replace(",", ".")


def fmt_qty(q):
    try:
        qf = float(q)
        if qf.is_integer():
            return str(int(qf))
        return f"{qf:g}".replace(".", ",")
    except Exception:
        return "1"


def wrap_lines(text, max_len=32):
    words = str(text or "").strip().split()
    if not words:
        return []
    lines, cur = [], ""
    for w in words:
        if not cur:
            cur = w
        elif len(cur) + 1 + len(w) <= max_len:
            cur += " " + w
        else:
            lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines


def line_lr(left, right):
    left = str(left)[:WIDTH]
    right = str(right)[:WIDTH]
    sp = WIDTH - len(left) - len(right)
    if sp < 1:
        left = left[: max(1, WIDTH - len(right) - 1)]
        sp = 1
    return (left + (" " * sp) + right + "\n").encode("cp437", errors="replace")


def find_printer():
    devs = sorted(set(glob.glob("/dev/usb/lp*") + glob.glob("/dev/lp*")))
    devs.sort(key=lambda d: (0 if d == "/dev/usb/lp0" else 1, d))
    for d in devs:
        try:
            with open(d, "ab"):
                return d
        except Exception:
            continue
    return None


def append_header(buf, toko, alamat, telp):
    buf += INIT + ALIGN_CEN + BOLD_ON + DOUBLE_ON
    for ln in wrap_lines(toko, 16):
        buf += (ln + "\n").encode("cp437", errors="replace")
    buf += NORMAL_SIZE + BOLD_OFF
    for ln in wrap_lines(alamat, WIDTH):
        buf += (ln + "\n").encode("cp437", errors="replace")
    if telp:
        buf += f"Telp/WA: {telp}\n".encode("cp437", errors="replace")
    buf += ALIGN_LEFT


def build_receipt(d):
    toko = str(d.get("toko_nama") or "KIOS MAFAZA")
    alamat = str(d.get("toko_alamat") or "Jl. Jend Sudirman , no.3")
    telp = str(d.get("toko_telp") or "0852-0530-7492")
    nota = str(d.get("nota") or "TRX-000")
    tgl = str(d.get("tanggal") or "")
    metode = str(d.get("metode") or "TUNAI").upper()
    plg = str(d.get("pelanggan") or "").strip()
    items = d.get("items") or []

    total = d.get("total", 0)
    bayar = d.get("bayar", 0)
    kembali = d.get("kembalian", 0)

    eq = ("=" * WIDTH + "\n").encode("ascii")
    dash = ("-" * WIDTH + "\n").encode("ascii")

    buf = bytearray()
    append_header(buf, toko, alamat, telp)

    buf += eq
    buf += f"No : {nota[:WIDTH-5]}\n".encode("cp437", errors="replace")
    buf += f"Tgl: {tgl[:WIDTH-5]}\n".encode("cp437", errors="replace")
    buf += f"Byr: {metode[:WIDTH-5]}\n".encode("cp437", errors="replace")
    if metode == "UTANG" and plg:
        for ln in wrap_lines("Plg: " + plg, WIDTH):
            buf += (ln + "\n").encode("cp437", errors="replace")
    buf += eq

    for it in items:
        nama = str(it.get("nama") or "Item").strip()
        try:
            qty_f = float(it.get("qty", 1))
        except Exception:
            qty_f = 1.0
        qty_str = fmt_qty(qty_f)

        try:
            harga_f = float(it.get("harga", 0) or 0)
        except Exception:
            harga_f = 0.0

        if it.get("subtotal") is not None:
            try:
                sub_f = float(it.get("subtotal"))
            except Exception:
                sub_f = qty_f * harga_f
        else:
            sub_f = qty_f * harga_f

        if harga_f == 0 and sub_f > 0 and qty_f > 0:
            harga_f = sub_f / qty_f

        buf += BOLD_ON
        for ln in wrap_lines(nama, WIDTH):
            buf += (ln + "\n").encode("cp437", errors="replace")
        buf += BOLD_OFF
        buf += line_lr(f"{qty_str} x {fmt_num(harga_f)}", f"Rp {fmt_num(sub_f)}")

    buf += dash
    buf += BOLD_ON + line_lr("TOTAL", f"Rp {fmt_num(total)}") + BOLD_OFF
    buf += line_lr("BAYAR", f"Rp {fmt_num(bayar)}")
    buf += line_lr("KEMBALI", f"Rp {fmt_num(kembali)}")
    buf += eq

    buf += ALIGN_CEN + b"-- Terima Kasih --\n"
    for ln in wrap_lines("Barang yang sudah dibeli tidak dapat ditukar/dikembalikan", WIDTH):
        buf += (ln + "\n").encode("cp437", errors="replace")
    buf += b"\n\n\n"
    return bytes(buf)


def build_utang_receipt(d):
    toko = str(d.get("toko_nama") or "KIOS MAFAZA")
    alamat = str(d.get("toko_alamat") or "Jl. Jend Sudirman ,no.3")
    telp = str(d.get("toko_telp") or "0852-0530-7492")

    plg = d.get("pelanggan") or {}
    nama_plg = str(plg.get("nama") or "-")
    hp_plg = str(plg.get("no_hp") or "-")
    tgl_cetak = str(d.get("tgl_cetak") or "")

    riwayat_belanja = d.get("riwayat_belanja") or []
    daftar_cicilan = d.get("daftar_cicilan") or []

    total_utang = d.get("total_utang_sesi", 0)
    total_bayar = d.get("total_bayar_sesi", 0)
    sisa_utang = d.get("sisa_utang", 0)

    eq = ("=" * WIDTH + "\n").encode("ascii")
    dash = ("-" * WIDTH + "\n").encode("ascii")
    dot = ("." * WIDTH + "\n").encode("ascii")

    buf = bytearray()
    append_header(buf, toko, alamat, telp)

    buf += dash
    for ln in wrap_lines(f"Nama     : {nama_plg}", WIDTH):
        buf += (ln + "\n").encode("cp437", errors="replace")
    for ln in wrap_lines(f"No. HP   : {hp_plg}", WIDTH):
        buf += (ln + "\n").encode("cp437", errors="replace")
    for ln in wrap_lines(f"Tgl Cetak: {tgl_cetak}", WIDTH):
        buf += (ln + "\n").encode("cp437", errors="replace")
    buf += dash

    buf += BOLD_ON + b"RINCIAN PENGAMBILAN BARANG\n" + BOLD_OFF
    buf += dash

    if not riwayat_belanja:
        buf += b"Tidak ada rincian barang.\n"
    else:
        for idx, trx in enumerate(riwayat_belanja):
            tgl_trx = str(trx.get("tanggal") or "")
            if tgl_trx:
                buf += BOLD_ON + f"[{tgl_trx}]\n".encode("cp437", errors="replace") + BOLD_OFF

            for it in (trx.get("items") or []):
                nama_brg = str(it.get("nama_barang") or "Item").strip()
                qty_str = fmt_qty(it.get("qty", 1))
                satuan = str(it.get("satuan") or "PCS").strip()
                harga_f = float(it.get("harga_jual", 0) or 0)
                sub_f = float(it.get("subtotal", 0) or 0)

                buf += BOLD_ON
                for ln in wrap_lines(nama_brg, WIDTH):
                    buf += (ln + "\n").encode("cp437", errors="replace")
                buf += BOLD_OFF

                kiri = f"{qty_str} {satuan} x {fmt_num(harga_f)}"
                kanan = f"Rp {fmt_num(sub_f)}"
                buf += line_lr(kiri, kanan)

            if idx < len(riwayat_belanja) - 1:
                buf += dot

    buf += dash
    buf += line_lr("Total Sesi", f"Rp {fmt_num(total_utang)}")
    if float(total_bayar or 0) > 0:
        buf += line_lr("Dibayar", f"-Rp {fmt_num(total_bayar)}")
    buf += BOLD_ON + line_lr("SISA TAGIHAN", f"Rp {fmt_num(sisa_utang)}") + BOLD_OFF
    buf += dash

    if daftar_cicilan:
        buf += BOLD_ON + b"RIWAYAT PEMBAYARAN\n" + BOLD_OFF
        for c in daftar_cicilan:
            tgl_c = str(c.get("tanggal") or "")
            ket_c = str(c.get("keterangan") or "Pembayaran").strip()
            nom_c = float(c.get("nominal", 0) or 0)
            label_c = f"{tgl_c} {ket_c}".strip()
            for ln in wrap_lines(label_c, WIDTH - 12):
                buf += (ln + "\n").encode("cp437", errors="replace")
            buf += line_lr("  Cicilan", f"-Rp {fmt_num(nom_c)}")
        buf += dash

    buf += ALIGN_CEN + b"-- Terima Kasih --\n"
    for ln in wrap_lines("Barang yang sudah dibeli tidak dapat ditukar/dikembalikan", WIDTH):
        buf += (ln + "\n").encode("cp437", errors="replace")
    buf += b"\n\n\n"
    return bytes(buf)


class Handler(BaseHTTPRequestHandler):
    def _cors(self):
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")

    def do_OPTIONS(self):
        self.send_response(204)
        self._cors()
        self.end_headers()

    def do_POST(self):
        if self.path not in ("/print", "/print-utang"):
            self.send_response(404)
            self._cors()
            self.end_headers()
            return

        length = int(self.headers.get("Content-Length", "0"))
        raw = self.rfile.read(length)
        try:
            payload = json.loads(raw.decode("utf-8"))
            dev = find_printer()
            if not dev:
                raise RuntimeError("Device /dev/usb/lp* tidak ditemukan")

            if self.path == "/print-utang":
                data = build_utang_receipt(payload)
            else:
                data = build_receipt(payload)

            with open(dev, "wb") as f:
                f.write(data)
            body = json.dumps({"ok": True, "message": f"Tercetak ke {dev}"}).encode("utf-8")
            self.send_response(200)
        except Exception as e:
            body = json.dumps({"ok": False, "message": str(e)}).encode("utf-8")
            self.send_response(500)

        self._cors()
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format, *args):
        return


if __name__ == "__main__":
    server = HTTPServer(("127.0.0.1", 9100), Handler)
    print("Thermal print bridge aktif di http://127.0.0.1:9100 (/print & /print-utang)")
    server.serve_forever()
