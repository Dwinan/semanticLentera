# Dokumen Rancangan Arsitektur Jembatan Semantik Toko Lentera

## 1. Latar Belakang, Tujuan, dan Stakeholder

### Latar Belakang
**Toko Lentera** adalah usaha ritel minimarket mandiri yang memiliki satu area toko fisik dan satu gudang penyimpanan kecil di bagian belakang. Dalam perkembangannya, Toko Lentera mengadopsi dua sistem informasi berbasis PostgreSQL yang dibangun pada waktu berbeda oleh pengembang yang berbeda pula:
- **Kasira**: aplikasi kasir (*Point of Sale*) yang berfokus pada kecepatan pencatatan transaksi penjualan harian.
- **Stokara**: aplikasi inventaris gudang yang mencatat master barang, daftar pemasok, serta riwayat penerimaan stok barang masuk.

Ketiadaan integrasi antara Kasira dan Stokara menimbulkan masalah operasional harian yang merugikan usaha:
1. **Kebutaan Stok Aktual (*Stock Blindness*)**: Staf gudang hanya mengetahui jumlah barang yang pernah masuk dari pemasok tanpa mengetahui berapa unit yang telah terjual di mesin kasir, sehingga perhitungan sisa stok harus dilakukan lewat hitung fisik manual yang lambat. Sebaliknya, kasir sering mendapati barang di rak habis tanpa peringatan dini karena sistem kasir tidak memiliki informasi ambang batas `stok_minimum`.
2. **Ketidakmampuan Evaluasi Profitabilitas dan Pemasok**: Pemilik toko dan akuntan tidak dapat langsung memantau margin keuntungan per produk karena `harga_jual` tersimpan sebagai teks berformat titik di Kasira, sedangkan `harga_beli` tersimpan sebagai angka desimal di Stokara. Pihak manajemen juga tidak mengetahui pemasok mana yang produk-produknya menghasilkan omzet penjualan tertinggi di kasir.

### Tujuan Proyek
- **Tujuan Utama**: Membangun jembatan semantik berbasis RDF, SKOS, OWL, dan SPARQL yang mengintegrasikan data penjualan Kasira dan data inventaris Stokara tanpa mengubah skema basis data relasional maupun kode aplikasi eksisting di kedua sistem.
- **Tujuan Turunan**:
  1. Menyeragamkan representasi data yang heterogen (format tanggal, tipe numerik harga, penamaan atribut, serta skema kategori berhierarki) ke dalam graf pengetahuan berstandar W3C dengan IRI kanonik pada domain `https://data.lentera.example/id/`.
  2. Menyelaraskan identitas produk lintas sistem secara deterministik menggunakan pengenal unik barcode (`owl:sameAs`) dan aksioma ontologi OWL.
  3. Menyediakan antarmuka pemantauan keputusan operasional (*Daftar Restock*) berbasis integrasi semantik di PHP/HTML/CSS/JS untuk mendukung pengadaan barang yang tepat waktu dan terukur marginnya.

### Stakeholder
| No | Peran | Nama Pengguna | Kepentingan terhadap Jembatan Semantik |
|---|---|---|---|
| 1 | Pemilik Toko | [NAMA] | Memantau produk dengan margin tertinggi dan kontribusi omzet per pemasok untuk negosiasi kontrak. |
| 2 | Kepala Gudang | [NAMA] | Mengetahui sisa stok perkiraan secara *real-time* (penerimaan dikurangi penjualan) agar tidak terjadi kekosongan stok. |
| 3 | Kasir Senior | [NAMA] | Memastikan keselarasan katalog produk di mesin kasir dengan barang baru yang sudah masuk di gudang. |
| 4 | Staf Pembelian (*Purchasing*) | [NAMA] | Menggunakan tabel Daftar Restock untuk menyusun pesanan pembelian (*Purchase Order*) ke pemasok yang tepat. |
| 5 | Akuntan Toko | [NAMA] | Menghitung selisih harga jual dan harga beli terakhir (*gross margin*) secara akurat tanpa rekonsiliasi spreadsheet manual. |

---

## 2. Tiga Pertanyaan Lintas-Sumber (*Competency Questions*)

1. **Pertanyaan 1 (Ketersediaan Stok)**:
   *Produk apa yang stok perkiraannya (total penerimaan dikurangi total terjual) sudah berada di bawah stok minimum?*
   - **Mengapa satu sumber tidak cukup**: Angka total barang diterima (`jumlah`) dan ambang `stok_minimum` hanya tercatat di **Stokara**, sedangkan angka total unit keluar karena terjual (`qty`) hanya tercatat di **Kasira**.

2. **Pertanyaan 2 (Analisis Profitabilitas)**:
   *Produk apa yang margin kotor per unitnya (harga jual dikurangi harga beli terakhir) paling tinggi?*
   - **Mengapa satu sumber tidak cukup**: Data `harga_jual` hanya disimpan di tabel `produk_jual` pada **Kasira**, sedangkan riwayat `harga_beli` beserta `tgl_terima` untuk menentukan harga beli terakhir hanya tersimpan di tabel `penerimaan_barang` pada **Stokara**.

3. **Pertanyaan 3 (Kinerja Pemasok)**:
   *Pemasok mana yang produk-produknya menyumbang total omzet penjualan terbesar di kasir?*
   - **Mengapa satu sumber tidak cukup**: Identitas pemasok dan pemetaan barang yang dipasoknya hanya tercatat pada tabel `pemasok` dan `penerimaan_barang` di **Stokara**, sedangkan volume penjualan (`qty`) dan `harga_jual` pembentuk omzet hanya tercatat di **Kasira**.

---

## 3. Peta Ketidakcocokan Antar-Sumber (*Heterogeneity Mapping*)

| No | Jenis Ketidakcocokan | Contoh Nilai Nyata di Kasira (Sistem A) | Contoh Nilai Nyata di Stokara (Sistem B) | Strategi Normalisasi & Semantik |
|---|---|---|---|---|
| 1 | Format Tanggal | `tanggal = '14/03/2026'` (`VARCHAR`, `DD/MM/YYYY`) | `tgl_terima = '2026-03-14'` (`DATE`, `YYYY-MM-DD`) | Skrip ETL PHP mem-parsing format `DD/MM/YYYY` menjadi standar ISO-8601 `"2026-03-14"^^xsd:date`. |
| 2 | Penulisan Nama Produk | `nama_produk = 'Kopi Susu 250ml'` | `nama_barang = 'KOPI SUSU BOTOL 250 ML'` | Disimpan apa adanya dengan tag bahasa `@id` (`schema:name` di Kasira, `rdfs:label` di Stokara); entitas disatukan lewat `owl:sameAs` berbasis barcode, bukan pencocokan string. |
| 3 | Nama Kolom Berbeda untuk Konsep Sama | `barcode`, `nama_produk`, `kode_kategori` | `kode_barcode`, `nama_barang`, `kategori` | Dipetakan ke properti RDF standar (`schema:gtin13`, `dcterms:identifier`, `schema:name`, `rdfs:label`, `schema:category`) dan disetarakan lewat `owl:equivalentProperty`. |
| 4 | Kode vs Nama Kategori | `kode_kategori = 'MNM'`, `'SNK'`, `'PKK'` | `kategori = 'Minuman'`, `'Makanan Ringan'`, `'Bahan Pokok'` | Kamus pemetaan PHP menerjemahkan kode maupun teks dari kedua sistem ke satu IRI `skos:Concept` bersama (`https://data.lentera.example/id/kategori/minuman-dingin`). |
| 5 | Tipe & Format Harga | `harga_jual = '15.000'` (`VARCHAR` pemisah titik) | `harga_beli = 10500.00` (`NUMERIC(12,2)`) | Titik ribuan pada Kasira dihapus lalu dikonversi menjadi literal bertipe `"15000.00"^^xsd:decimal` agar dapat dihitung di SPARQL. |
| 6 | Representasi Nilai Kosong | `nama_kasir = '-'` (T005), `kode_kategori = '-'` (8991001000158) | `kota = '-'` (P05), `kategori = ''` (8991001000189) | Fungsi `apakahKosong()` memeriksa nilai `null`, `""`, dan `"-"`; jika kosong, *triple* tidak dibuat sama sekali (*Open World Assumption*). |

---

## 4. Entitas, Pemodelan Hubungan Ber-atribut, dan Diagram Mermaid

### Pemodelan Hubungan Ber-atribut (*N-ary Relations* / Reifikasi)
1. **ItemTransaksi (`schema:OrderItem`)**: Hubungan antara Transaksi dan Produk memiliki atribut kuantitas (`qty`). Jika Transaksi langsung dihubungkan ke Produk dengan properti `ex:membeliProduk`, kita tidak dapat melekatkan angka `qty` pada produk tertentu di dalam transaksi yang memuat banyak barang. Oleh sebab itu, setiap baris item dimodelkan sebagai sumber daya tersendiri (`/item/{id-transaksi}-{no}`).
2. **PenerimaanBarang (`ex:PenerimaanBarang`)**: Hubungan antara Pemasok dan Barang terjadi berulang kali pada waktu berbeda (`tgl_terima`), dengan kuantitas (`jumlah`) dan `harga_beli` yang berubah di setiap pengiriman. Memodelkannya sebagai sumber daya tersendiri (`/penerimaan/{id}`) menjaga histori pengiriman tetap utuh dan memungkinkan pencarian harga beli terakhir berdasarkan tanggal.

### Diagram Mermaid Struktur Graf RDF
```mermaid
graph LR
    subgraph Kasira [Sistem A: Kasira]
        T[schema:Order<br/>/id/transaksi/T001]
        IT[schema:OrderItem<br/>/id/item/T001-1]
        PK[ex:ProdukJual<br/>/id/kasira/produk/8991001000011]
        T <-- dcterms:isPartOf --- IT
        IT -- schema:orderedItem --> PK
        IT -- schema:orderQuantity --> QTY["2 (xsd:integer)"]
        PK -- schema:price --> HJ["15000.00 (xsd:decimal)"]
        PK -- schema:gtin13 --> BC1["'8991001000011'"]
    end

    subgraph Jembatan [Jembatan Semantik OWL & SKOS]
        KAT[skos:Concept<br/>/id/kategori/minuman-dingin]
        INDUK[skos:Concept<br/>/id/kategori/minuman]
        KAT -- skos:broader --> INDUK
    end

    subgraph Stokara [Sistem B: Stokara]
        BS[ex:Barang<br/>/id/stokara/barang/8991001000011]
        PB[ex:PenerimaanBarang<br/>/id/penerimaan/PB01]
        SUP[schema:Organization<br/>/id/pemasok/P05]
        PB -- schema:orderedItem --> BS
        PB -- ex:dipasokOleh --> SUP
        PB -- ex:hargaBeli --> HB["10500.00 (xsd:decimal)"]
        PB -- rdf:value --> JML["20 (xsd:integer)"]
        BS -- ex:stokMinimum --> SM["30 (xsd:integer)"]
        BS -- dcterms:identifier --> BC2["'8991001000011'"]
    end

    PK <== owl:sameAs ==> BS
    PK -- schema:category --> KAT
    BS -- schema:category --> KAT
```

---

## 5. Rencana Kosakata: Pakai Ulang vs Buat Sendiri (8 Istilah `ex:`)

| Konsep / Atribut Domain | Istilah RDF yang Dipilih | Status | Alasan Keputusan Desain |
|---|---|---|---|
| Produk (Konsep Induk) | `schema:Product` | Pakai Ulang | Standar de-facto global untuk merepresentasikan barang dagangan ritel. |
| Pemasok | `schema:Organization` | Pakai Ulang | Pemasok merupakan badan usaha/organisasi yang sudah terdefinisi di Schema.org. |
| Transaksi Penjualan | `schema:Order` | Pakai Ulang | Mewakili kejadian transaksi pembelian pelanggan di kasir. |
| Item Baris Transaksi | `schema:OrderItem` | Pakai Ulang | Standar Schema.org untuk reifikasi rincian baris pesanan dan kuantitas. |
| Kategori & Skema Kategori | `skos:Concept`, `skos:ConceptScheme`, `skos:broader`, `skos:prefLabel`, `skos:exactMatch` | Pakai Ulang | SKOS dirancang khusus oleh W3C untuk taksonomi dan hierarki konsep. |
| Nama Produk & Pemasok | `schema:name` | Pakai Ulang | Properti standar untuk nama entitas pada `schema:Product` dan `schema:Organization`. |
| Nama Barang (Stokara) | `rdfs:label` | Pakai Ulang | Dipilih untuk sistem kedua guna mencerminkan perbedaan kosakata sumber lalu disetarakan lewat OWL. |
| Barcode & Kode Barcode | `schema:gtin13` & `dcterms:identifier` | Pakai Ulang | `schema:gtin13` spesifik untuk EAN-13; `dcterms:identifier` untuk inventaris; disetarakan via `owl:equivalentProperty`. |
| Tanggal Transaksi & Terima | `dcterms:date` | Pakai Ulang | Properti standar Dublin Core untuk tanggal kejadian berliteral `xsd:date`. |
| Harga Jual Produk | `schema:price` | Pakai Ulang | Properti harga standar pada `schema:Product`. |
| Kuantitas Item & Terima | `schema:orderQuantity` & `rdf:value` | Pakai Ulang | `schema:orderQuantity` untuk item kasir dan `rdf:value` untuk kuantitas penerimaan barang. |
| Kota Pemasok & Nama Kasir | `schema:addressLocality` & `dcterms:creator` | Pakai Ulang | Mewakili lokasi pemasok dan pembuat transaksi tanpa membuat properti baru. |
| Kelas Produk Kasira | `ex:ProdukJual` (Kelas #1) | Buat Sendiri | Membedakan kelas produk dari tabel `produk_jual` Kasira (`rdfs:subClassOf schema:Product`). |
| Kelas Barang Stokara | `ex:Barang` (Kelas #2) | Buat Sendiri | Membedakan kelas barang dari tabel `barang` Stokara (`owl:equivalentClass ex:ProdukJual`). |
| Kelas Penerimaan Barang | `ex:PenerimaanBarang` (Kelas #3) | Buat Sendiri | Pencatatan log barang masuk gudang beserta harga beli per kedatangan. |
| Stok Minimum Gudang | `ex:stokMinimum` (Properti #4) | Buat Sendiri | Ambang batas stok pengaman (*safety stock*) spesifik aturan gudang Toko Lentera. |
| Harga Beli Penerimaan | `ex:hargaBeli` (Properti #5) | Buat Sendiri | Membedakan harga pokok pembelian pengiriman dari harga jual ritel (`schema:price`). |
| Relasi ke Pemasok | `ex:dipasokOleh` (Properti #6) | Buat Sendiri | Menghubungkan catatan `ex:PenerimaanBarang` ke organisasi pemasok. |
| Relasi Kebalikan Pemasok | `ex:memasok` (Properti #7) | Buat Sendiri | Navigasi dua arah (`owl:inverseOf ex:dipasokOleh`) dari pemasok ke riwayat penerimaan barangnya. |
| Margin Kotor per Unit | `ex:marginPerUnit` (Properti #8) | Buat Sendiri | Menyimpan hasil perhitungan graf baru (`CONSTRUCT`) berupa selisih harga jual dan harga beli terakhir. |

---

## 6. Aturan IRI dan Pemisahan Namespace

- **Awalan Tetap**: `https://data.lentera.example/id/`
- **Pola Path per Entitas**:
  - Produk Kasira: `/kasira/produk/{barcode}`
  - Barang Stokara: `/stokara/barang/{kode_barcode}`
  - Kategori SKOS: `/kategori/{slug}`
  - Pemasok: `/pemasok/{id}`
  - Transaksi: `/transaksi/{id}`
  - Item Transaksi: `/item/{id-transaksi}-{no}`
  - Penerimaan: `/penerimaan/{id}`
- **Alasan Pemisahan Namespace Mentah**:
  Pemisahan `/kasira/produk/` dan `/stokara/barang/` menjaga asal-usul data (*provenance*) tetap jelas sehingga setiap sistem dapat mempublikasikan graf RDF-nya secara independen tanpa menimpa atribut sistem lain. Jika kedua sistem dipaksa memakai satu IRI sejak awal, aksioma penyelarasan `owl:sameAs` kehilangan maknanya (*trivial self-identity*) dan kita tidak dapat mendemonstrasikan perbedaan kueri sebelum dan sesudah penyelarasan identitas. Selain itu, seluruh IRI hanya menggunakan kunci pengenal stabil (EAN-13, ID transaksi, slug kategori) tanpa memuat hal yang berubah maupun ekstensi teknologi (`.php`, `?id=`).

---

## 7. Pernyataan Privasi & Keamanan Data (UU No. 27 Tahun 2022)

Dalam proyek jembatan semantik Toko Lentera ini, satu-satunya data pribadi yang tersentuh adalah nama panggilan petugas kasir (`nama_kasir` pada tabel `transaksi` di Kasira) dan tidak ada pencatatan identitas pelanggan maupun nomor kontak individu pemasok. Atribut nama kasir semata-mata digunakan untuk keperluan audit internal operasional toko. Seluruh data yang diproses dalam repositori ini merupakan data sintetis buatan, kolom internal yang tidak relevan tidak diekspor ke dalam graf RDF, dan antarmuka web dijalankan secara tertutup di jaringan lokal (`localhost`) tanpa terekspos ke internet publik sesuai prinsip pembatasan pemrosesan dan pelindungan data pribadi pada **UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP)**. Selain itu, atribut `harga_beli` dari sistem Stokara serta perhitungan `ex:marginPerUnit` merupakan informasi rahasia dagang yang bersifat sensitif secara bisnis, sehingga akses ke halaman Daftar Restock wajib dibatasi hanya untuk manajemen internal.

---

## 8. Daftar Periksa Mandiri terhadap Rubrik (*Self-Assessment Checklist*)

| Dimensi Evaluasi | Kriteria Rubrik | Status | Bukti Implementasi dalam Proyek |
|---|---|---|---|
| **1. Kelengkapan Pemetaan & Penanganan Ketidakcocokan** | Seluruh 6 tabel dari kedua sistem dipetakan; minimal 5 jenis ketidakcocokan dinormalisasi; nilai kosong (`-`, `""`, `NULL`) tidak menghasilkan triple. | **Terpenuhi** | Skrip `jembatan/export_rdf.php` memetakan ke-6 tabel, menormalkan tanggal `DD/MM/YYYY` ke `xsd:date`, harga `"15.000"` ke `xsd:decimal`, kode kategori ke IRI SKOS, dan menyaring nilai kosong lewat `apakahKosong()`. |
| **2. Kebenaran Bentuk RDF (*RDF Well-Formedness*)** | Kunci tamu menjadi `URIRef` (bukan `Literal`), tipe data XSD eksplisit, tag bahasa `@id`, IRI seragam di bawah `https://data.lentera.example/id/`, kosakata buatan `ex:` maksimal 8 istilah. | **Terpenuhi** | Relasi item ke transaksi/produk dan penerimaan ke barang/pemasok semuanya berupa `URIRef`. Istilah `ex:` tepat berjumlah 8 (3 kelas + 5 properti) lengkap dengan `rdfs:label`, `rdfs:comment`, `rdfs:domain`, dan `rdfs:range`. |
| **3. Kualitas Penyelarasan Identitas & Ontologi OWL** | `owl:sameAs` dibangun lewat skrip berdasarkan `barcode = kode_barcode` (bukan nama); ada contoh nyata produk bernama mirip yang sengaja tidak dipadankan; demonstrasi inferensi sebelum vs sesudah. | **Terpenuhi** | Penyelarasan mencocokkan 12 barcode identik dan sengaja membiarkan "Susu UHT Cokelat" 250ml (`8991001000134`) vs 1L (`8991001000936`) terpisah. Halaman restock membuktikan integrasi berjalan dari 0 menjadi terpadu penuh. |
| **4. Kueri SPARQL Lintas-Sumber & Reprodusibilitas** | 5 jenis kueri (`GROUP BY`/`HAVING`, `skos:broader+`, `CONSTRUCT`, `ASK`, `SERVICE` Wikidata) + 1 `UNION`; 3 pertanyaan lintas-sumber terjawab; dapat dijalankan ulang dengan keluaran sama persis. | **Terpenuhi** | Seluruh kueri SQL memakai klausa `ORDER BY` pada kunci utama dan himpunan barcode disortir sebelum diserialisasi ke Turtle, menjamin keluaran `.ttl` dan hasil kueri 100% deterministik setiap kali dijalankan ulang. |
