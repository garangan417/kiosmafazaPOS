# Kios Mafaza POS

Aplikasi Point of Sales (Kasir), Manajemen Stok Barang, dan Pencatatan Utang berbasis Web yang ringan dan responsif untuk Kios Mafaza.

---

## 🚀 Fitur Utama
- **Kasir & Transaksi**: Pencarian produk cepat, checkout, penanganan transaksi, dan daftar produk favorit.
- **Manajemen Barang & Stok**: Kelola katalog produk, penyesuaian stok, pencatatan harga, dan pengelompokan kategori barang.
- **Utang & Pelanggan**: Pencatatan riwayat utang pelanggan serta alur pelunasan yang terstruktur.
- **Laporan & Keuangan**: Rekapitulasi transaksi dan laporan keuangan harian/bulanan (termasuk PPOB).

---

## 🛠️ Tech Stack
- **Backend**: PHP (Natif)
- **Database**: MariaDB (Utama) & SQLite (Alternatif)
- **Frontend**: Bootstrap, HTMX (Interaktivitas tanpa reload halaman)
- **Library Tambahan**: SweetAlert2 (Toast Notification)

---

## 🔄 Konfigurasi & Dual Database (MariaDB / SQLite)

Aplikasi ini mendukung dua jenis basis data yang bisa disesuaikan dengan kebutuhan lingkungan (environment):

* **MariaDB (Default / Mode Utama):**
  Secara bawaan aplikasi mengarah ke database MariaDB menggunakan folder `database/`.
* **Mekanisme Switch ke SQLite (Mode Offline / Portabel):**
  Jika ingin menjalankan mode SQLite tanpa server MariaDB:
  1. Ubah/rename folder `database` (MariaDB) saat ini menjadi nama lain (misal: `database_mariadb`).
  2. Ubah/rename folder `database_sqlite` menjadi `database`.

---

## 💻 Cara Instalasi & Penggunaan (Lokal)

1. **Clone Repository**
   ```bash
   git clone [https://github.com/garangan417/kiosmafazaPOS.git](https://github.com/garangan417/kiosmafazaPOS.git)
   cd kiosmafazaPOS

## 👨‍💻 Informasi Pengembang & Lisensi

Proyek ini dirancang dan dikembangkan oleh **Garangan417** sebagai bagian dari pengembangan sistem manajemen kasir (*Point of Sale*) dan portofolio perangkat lunak.

* **Pengembang Utama:** [Garangan417](https://github.com/garangan417)
* **Status Proyek:** *Open for Learning & Personal Use*
* **Tipe Lisensi:** Non-Commercial Public License (NC-PL v1.0.0)

---

### 📜 Aturan Lisensi Ringkas
1. **Penggunaan Bebas:** Anda diperbolehkan mengunduh, mempelajari, menguji, dan memodifikasi kode program ini untuk keperluan pribadi, riset, atau edukasi tanpa dipungut biaya.
2. **Atribusi Wajib:** Setiap penggandaan atau modifikasi kode **wajib menyertakan nama pengembang asli (Garangan417)** dan tautan ke repository utama.
3. **Larangan Komersialisasi:** Dilarang keras menjual, menyewakan, memaketkan ulang (*rebranding*), atau menggunakan aplikasi ini untuk tujuan komersial/bisnis berbayar tanpa izin tertulis dari pengembang.

