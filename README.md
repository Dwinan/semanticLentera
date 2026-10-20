# Jembatan Semantik Toko Lentera (Kasira & Stokara)

Aplikasi web terintegrasi semantik berbasis teknologi **Semantik Web W3C (RDF, SKOS, OWL, dan SPARQL)** antara sistem kasir **Kasira** dan sistem inventaris gudang **Stokara** menggunakan stack **HTML, CSS, JavaScript, PHP, dan basis data PostgreSQL** di Laragon.

---

## 1. Tentang Proyek & Masalah yang Diselesaikan

**Toko Lentera** adalah sebuah minimarket mandiri fiktif dengan satu toko fisik dan satu gudang kecil. Toko ini memiliki dua sistem informasi yang dibangun terpisah oleh pengembang berbeda:

1. **Kasira (Sistem A)**: Aplikasi kasir (*Point of Sale*) untuk mencatat penjualan harian pelanggan.
2. **Stokara (Sistem B)**: Aplikasi inventaris gudang untuk mencatat master barang, daftar pemasok, dan penerimaan stok masuk.

### Masalah Utama
Kedua sistem terisolasi (*silo data*):
* Gudang tidak mengetahui berapa unit barang yang sudah laku di kasir.
* Kasir tidak mengetahui jika stok di gudang sudah menipis di bawah ambang batas aman (*stok minimum*).
* Pemilik toko dan akuntan kesulitan menghitung keuntungan bersih (*gross margin*) karena format harga dan tanggal di kedua sistem tidak cocok.

### Solusi: Jembatan Semantik
Alih-alih merombak basis data kedua sistem, proyek ini membangun **Jembatan Semantik**:
* Mengintegrasikan data secara non-invasif menggunakan graf **RDF**.
* Menyelaraskan identitas produk lewat relasi **`owl:sameAs`** berbasis kode barcode EAN-13.
* Menyeragamkan taksonomi kategori produk menggunakan skema hierarki **SKOS**.
* Menyajikan antarmuka keputusan operasional (**Daftar Restock**) untuk pengadaan barang yang tepat waktu dan terukur keuntungannya.

---

## 2. Peta 6 Ketidakcocokan Data yang Dinormalkan

| No | Ketidakcocokan | Kasira (Sistem A) | Stokara (Sistem B) | Strategi Jembatan Semantik |
|---|---|---|---|---|
| 1 | **Format Tanggal** | `14/03/2026` (DD/MM/YYYY) | `2026-03-14` (YYYY-MM-DD) | Dinormalkan ke standar ISO-8601: `xsd:date`. |
| 2 | **Penulisan Nama** | `Kopi Susu 250ml` | `KOPI SUSU BOTOL 250 ML` | Disimpan apa adanya bertag `@id`, dihubungkan via `owl:sameAs` barcode. |
| 3 | **Penamaan Kolom** | `barcode`, `nama_produk` | `kode_barcode`, `nama_barang` | Dipetakan ke kosakata standar W3C (`schema:gtin13`, `dcterms:identifier`). |
| 4 | **Kode vs Nama Kategori** | `MNM`, `SNK`, `PKK` | `Minuman`, `Makanan Ringan` | Dipetakan ke satu konsep SKOS (`.../kategori/minuman-dingin`). |
| 5 | **Format Harga** | `"15.000"` (Teks bertitik) | `10500.00` (Numerik desimal) | Titik dihilangkan, dikonversi ke tipe `xsd:decimal`. |
| 6 | **Representasi Kosong** | `"-"` atau `""` | `"-"` atau `NULL` | Disaring oleh skrip; nilai kosong tidak dibuat triple RDF (*Open World*). |

---

## 3. Tiga Pertanyaan Bisnis Lintas-Sumber

Aplikasi ini dirancang untuk menjawab 3 pertanyaan operasional yang **wajib membutuhkan gabungan kedua sistem**:

1. **Ketersediaan Stok**: Produk apa yang stok perkiraannya (total penerimaan di Stokara dikurangi total penjualan di Kasira) sudah berada di bawah ambang stok minimum?
2. **Analisis Profitabilitas**: Produk apa yang margin kotor per unitnya (harga jual di Kasira dikurangi harga beli terakhir di Stokara) paling tinggi?
3. **Kinerja Pemasok**: Pemasok mana yang produk-produknya menyumbang omzet penjualan terbesar di kasir?

---

## 4. Struktur Folder Proyek

```text
semanticLentera/
├── .gitignore                # Mengabaikan config/database.php & file sensitif
├── index.php                 # Halaman portal utama & ringkasan status
├── dokumen_rancangan.md      # Laporan lengkap arsitektur semantik & rubrik evaluasi
├── README.md                 # Panduan penggunaan & arsitektur proyek
├── assets/
│   ├── css/style.css         # Styling antarmuka web modern & responsif
│   └── js/main.js            # Skrip pembantu interaktivitas frontend
├── config/
│   ├── database.example.php  # Template konfigurasi basis data (terlacak Git)
│   └── database.php          # Konfigurasi koneksi lokal aktif (diabaikan .gitignore)
├── kasira/
│   └── index.php             # Aplikasi Kasir (Point of Sale)
├── stokara/
│   └── index.php             # Aplikasi Gudang (Inventaris & Penerimaan Barang)
├── sql/
│   ├── init_kasira.sql       # Skema DDL & data buatan awal Kasira
│   └── init_stokara.sql      # Skema DDL & data buatan awal Stokara
└── jembatan/                 # MODUL JEMBATAN SEMANTIK (Inti Proyek)
    ├── index.php             # Pusat kendali semantik & penampil berkas Turtle
    ├── export_rdf.php        # Skrip PHP ETL RDB-ke-RDF standar W3C
    ├── restock.php           # Dashboard Daftar Restock (analisis keputusan bisnis)
    ├── sparql_runner.php     # Repositori 6 kueri SPARQL 1.1 W3C
    ├── ontologi.ttl          # Berkas Ontologi OWL (3 kelas & 5 properti buatan ex:)
    ├── kategori_skos.ttl     # Berkas Taksonomi Kategori SKOS berhierarki
    ├── sparql/               # Berkas kueri SPARQL (.rq)
    │   ├── q1_restock_agregasi.rq
    │   ├── q2_kategori_hierarki.rq
    │   ├── q3_construct_margin.rq
    │   ├── q4_ask_stok_kritis.rq
    │   ├── q5_wikidata_service.rq
    │   └── q6_union_vs_owl.rq
    └── output/               # Berkas Turtle (.ttl) hasil ekspor
        ├── kasira.ttl
        ├── stokara.ttl
        ├── penyelarasan.ttl
        └── inferred.ttl
```

---

## 5. Cara Menyiapkan Basis Data PostgreSQL di Windows

1. **Buat Dua Database**:
   Buka **pgAdmin** atau terminal `psql`, lalu jalankan:
   ```sql
   CREATE DATABASE kasira_db;
   CREATE DATABASE stokara_db;
   ```

2. **Impor Skema & Data Awal**:
   * Buka database `kasira_db`, jalankan seluruh kueri dari berkas `sql/init_kasira.sql`.
   * Buka database `stokara_db`, jalankan seluruh kueri dari berkas `sql/init_stokara.sql`.

3. **Salin & Sesuaikan Konfigurasi Koneksi**:
   Salin berkas template `config/database.example.php` menjadi `config/database.php`:
   ```bash
   cp config/database.example.php config/database.php
   ```
   *(Atau di Windows Explorer, copy berkas `database.example.php` lalu rename menjadi `database.php`)*.

   Buka berkas `config/database.php`, lalu sesuaikan port (biasanya `5432`), user (`postgres`), dan password PostgreSQL lokal Anda:
   ```php
   private static $kasiraHost = '127.0.0.1';
   private static $kasiraPort = '5432';
   private static $kasiraDb   = 'kasira_db';
   private static $kasiraUser = 'postgres';       // sesuaikan
   private static $kasiraPass = 'password_anda';  // sesuaikan

   private static $stokaraHost = '127.0.0.1';
   private static $stokaraPort = '5432';
   private static $stokaraDb   = 'stokara_db';
   private static $stokaraUser = 'postgres';       // sesuaikan
   private static $stokaraPass = 'password_anda';  // sesuaikan
   ```

   > **Keamanan**: Berkas `config/database.php` telah dimasukkan ke `.gitignore`, sehingga kata sandi basis data Anda tidak akan terunggah (*leak*) saat melakukan `git push`.

---

## 6. Cara Menggunakan Aplikasi

1. **Nyalakan Laragon**:
   Buka aplikasi Laragon, lalu klik tombol **Start All** (memastikan Apache menyala).

2. **Buka di Peramban**:
   Akses portal utama proyek melalui URL:
   ```text
   http://localhost/semanticLentera/
   ```

3. **Skenario Alur Pengujian Interaktif**:
   * **Langkah 1 (Aplikasi Kasir)**: Buka menu **Kasira (Kasir)** di `http://localhost/semanticLentera/kasira/`. Masukkan transaksi penjualan baru. Amati penambahan riwayat transaksi.
   * **Langkah 2 (Aplikasi Gudang)**: Buka menu **Stokara (Gudang)** di `http://localhost/semanticLentera/stokara/`. Masukkan catatan penerimaan barang masuk dari pemasok beserta harga belinya.
   * **Langkah 3 (Sinkronisasi Graf Semantik)**: Buka menu **Jembatan Semantik** di `http://localhost/semanticLentera/jembatan/`. Klik tombol **"Sinkronkan & Generate RDF (.ttl)"**. Sistem akan mengekstrak data kedua PostgreSQL, menormalkannya, dan menghasilkan berkas RDF Turtle terbaru.
   * **Langkah 4 (Evaluasi Keputusan Restock)**: Buka menu **Daftar Restock** di `http://localhost/semanticLentera/jembatan/restock.php`. Sistem akan menampilkan tabel terpadu yang membandingkan total barang masuk dengan total barang keluar, memberi tanda merah pada produk kritis di bawah stok minimum, dan menghitung keuntungan per unit.
   * **Langkah 5 (Eksplorasi Kueri SPARQL)**: Buka menu **Kueri SPARQL** di `http://localhost/semanticLentera/jembatan/sparql_runner.php` untuk mempelajari 6 kueri kompetensi W3C (Agregasi, Hierarki SKOS, CONSTRUCT, ASK, SERVICE Wikidata, dan komparasi UNION).

---

## 7. Kepatuhan Privasi Data (UU No. 27 Tahun 2022)

Satu-satunya data pribadi dalam sistem ini adalah nama panggilan petugas kasir (`nama_kasir` pada Kasira) yang digunakan murni untuk keperluan internal audit operasional toko. Seluruh data transaksi dan produk merupakan data sintetis fiktif, kolom internal yang tidak relevan tidak diekspor ke dalam graf RDF publik, dan harga beli bersifat rahasia dagang internal yang hanya diakses oleh pihak manajemen Toko Lentera.
