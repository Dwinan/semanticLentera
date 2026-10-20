<?php
require_once __DIR__ . '/config/database.php';

$dbStatus = Database::checkStatus();
$kasiraConn = Database::getKasiraConnection();
$stokaraConn = Database::getStokaraConnection();

$totalProdukKasira = 0;
$totalTransaksi = 0;
$totalBarangGudang = 0;
$totalPenerimaan = 0;

if ($kasiraConn) {
    try {
        $totalProdukKasira = $kasiraConn->query("SELECT COUNT(*) FROM produk_jual")->fetchColumn();
        $totalTransaksi = $kasiraConn->query("SELECT COUNT(*) FROM transaksi")->fetchColumn();
    } catch (Exception $e) {}
}

if ($stokaraConn) {
    try {
        $totalBarangGudang = $stokaraConn->query("SELECT COUNT(*) FROM barang")->fetchColumn();
        $totalPenerimaan = $stokaraConn->query("SELECT COUNT(*) FROM penerimaan_barang")->fetchColumn();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toko Lentera - Portal Integrasi Jembatan Semantik</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            Toko Lentera <span>| Portal Semantik Web</span>
        </a>
        <div class="nav-links">
            <a href="index.php" class="active">Portal Utama</a>
            <a href="kasira/index.php">Kasira (Kasir)</a>
            <a href="stokara/index.php">Stokara (Gudang)</a>
            <a href="jembatan/index.php">Jembatan Semantik</a>
            <a href="jembatan/restock.php">Daftar Restock</a>
            <a href="jembatan/sparql_runner.php">Kueri SPARQL</a>
        </div>
    </nav>

    <div class="container">
        <!-- Hero Section -->
        <div class="page-header" style="text-align: center; margin: 2rem 0 3rem;">
            <span class="badge badge-bridge" style="font-size: 0.9rem; padding: 0.35rem 0.85rem; margin-bottom: 0.85rem;">
                Arsitektur Semantik Web W3C (RDF, SKOS, OWL, SPARQL)
            </span>
            <h1 style="font-size: 2.35rem; font-weight: 900; color: #0f172a; margin-bottom: 0.6rem;">
                Jembatan Semantik Toko Lentera
            </h1>
            <p style="font-size: 1.05rem; color: #475569; max-width: 720px; margin: 0 auto; line-height: 1.6;">
                Menghubungkan dua sistem informasi independen (<strong>Kasira</strong> untuk kasir dan <strong>Stokara</strong> untuk inventaris gudang) tanpa memodifikasi skema basis data aslinya.
            </p>
        </div>

        <?php if (!$dbStatus['kasira'] || !$dbStatus['stokara']): ?>
            <div class="alert alert-warning">
                <div>
                    <strong>Petunjuk Koneksi Database PostgreSQL:</strong><br>
                    Basis data belum terhubung. Pastikan layanan PostgreSQL di Windows sudah aktif dan pengaturan kredensial di berkas <code>config/database.php</code> sudah sesuai.
                </div>
            </div>
        <?php endif; ?>

        <!-- Tiga Modul Utama -->
        <div class="grid grid-3" style="margin-bottom: 2.5rem;">
            <!-- Sistem A: Kasira -->
            <div class="card" style="border-top: 4px solid var(--kasira);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <span class="badge badge-kasira">Sistem A</span>
                    <?php if ($dbStatus['kasira']): ?>
                        <span class="badge badge-success">Online (5433)</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Offline</span>
                    <?php endif; ?>
                </div>
                <h2 style="font-size: 1.35rem; margin-bottom: 0.4rem; color: #0f172a;">Kasira (Kasir)</h2>
                <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 1.25rem;">
                    Sistem Point of Sale untuk mencatat transaksi penjualan ritel. Format tanggal <code>DD/MM/YYYY</code> dan harga teks bertitik.
                </p>
                <div style="background: #f8fafc; padding: 0.75rem; border-radius: var(--radius); margin-bottom: 1.25rem; font-size: 0.85rem;">
                    <div>Katalog Produk: <strong><?php echo $totalProdukKasira; ?> produk</strong></div>
                    <div>Riwayat Transaksi: <strong><?php echo $totalTransaksi; ?> transaksi</strong></div>
                </div>
                <a href="kasira/index.php" class="btn btn-kasira" style="width: 100%; justify-content: center;">
                    Buka Aplikasi Kasir &rarr;
                </a>
            </div>

            <!-- Sistem B: Stokara -->
            <div class="card" style="border-top: 4px solid var(--stokara);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <span class="badge badge-stokara">Sistem B</span>
                    <?php if ($dbStatus['stokara']): ?>
                        <span class="badge badge-success">Online (5434)</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Offline</span>
                    <?php endif; ?>
                </div>
                <h2 style="font-size: 1.35rem; margin-bottom: 0.4rem; color: #0f172a;">Stokara (Gudang)</h2>
                <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 1.25rem;">
                    Sistem inventaris gudang untuk mencatat master barang, pemasok, dan penerimaan stok. Format tanggal <code>YYYY-MM-DD</code>.
                </p>
                <div style="background: #f8fafc; padding: 0.75rem; border-radius: var(--radius); margin-bottom: 1.25rem; font-size: 0.85rem;">
                    <div>Master Barang: <strong><?php echo $totalBarangGudang; ?> barang</strong></div>
                    <div>Riwayat Terima: <strong><?php echo $totalPenerimaan; ?> penerimaan</strong></div>
                </div>
                <a href="stokara/index.php" class="btn btn-stokara" style="width: 100%; justify-content: center;">
                    Buka Aplikasi Gudang &rarr;
                </a>
            </div>

            <!-- Jembatan Semantik -->
            <div class="card" style="border-top: 4px solid var(--bridge);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <span class="badge badge-bridge">Inti Proyek</span>
                    <span class="badge badge-bridge">RDF &bull; OWL &bull; SPARQL</span>
                </div>
                <h2 style="font-size: 1.35rem; margin-bottom: 0.4rem; color: #0f172a;">Jembatan Semantik</h2>
                <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 1.25rem;">
                    Menyelaraskan data kedua sistem lewat <code>owl:sameAs</code> barcode, normalisasi SKOS, dan perhitungan keputusan stok kritis.
                </p>
                <div style="background: #f8fafc; padding: 0.75rem; border-radius: var(--radius); margin-bottom: 1.25rem; font-size: 0.85rem;">
                    <div>Domain IRI: <code>https://data.lentera.example/id/</code></div>
                    <div>Aksioma: <strong>8 Istilah ex:, SKOS, OWL</strong></div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="jembatan/index.php" class="btn btn-bridge" style="flex: 1; justify-content: center;">
                        Pusat Kendali
                    </a>
                    <a href="jembatan/restock.php" class="btn btn-primary" style="flex: 1; justify-content: center;">
                        Daftar Restock
                    </a>
                </div>
            </div>
        </div>

        <!-- Tabel 6 Normalisasi Ketidakcocokan -->
        <div class="card" style="margin-bottom: 2.5rem;">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Peta 6 Jenis Ketidakcocokan Antar-Sumber & Strategi Semantik</h2>
                    <small style="color: #64748b;">Perbedaan format data di kedua sistem dan bagaimana jembatan semantik menormalkannya</small>
                </div>
                <span class="badge badge-bridge">W3C Best Practices</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Jenis Ketidakcocokan</th>
                            <th>Contoh di Kasira (Sistem A)</th>
                            <th>Contoh di Stokara (Sistem B)</th>
                            <th>Solusi Jembatan Semantik</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><strong>Format Tanggal</strong></td>
                            <td><code>tanggal = '14/03/2026'</code> (VARCHAR DD/MM/YYYY)</td>
                            <td><code>tgl_terima = '2026-03-14'</code> (DATE YYYY-MM-DD)</td>
                            <td>Dinormalkan ke tipe literal ISO-8601: <code>"2026-03-14"^^xsd:date</code></td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td><strong>Penulisan Nama Produk</strong></td>
                            <td><code>nama_produk = 'Kopi Susu 250ml'</code></td>
                            <td><code>nama_barang = 'KOPI SUSU BOTOL 250 ML'</code></td>
                            <td>Disimpan apa adanya dengan tag bahasa <code>@id</code>; disatukan via <code>owl:sameAs</code> barcode</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td><strong>Penamaan Kolom Berbeda</strong></td>
                            <td><code>barcode</code>, <code>nama_produk</code>, <code>kode_kategori</code></td>
                            <td><code>kode_barcode</code>, <code>nama_barang</code>, <code>kategori</code></td>
                            <td>Dipetakan ke properti W3C (<code>schema:gtin13</code>, <code>dcterms:identifier</code>) dan disetarakan lewat OWL</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><strong>Kode vs Nama Kategori</strong></td>
                            <td><code>kode_kategori = 'MNM'</code>, <code>'SNK'</code></td>
                            <td><code>kategori = 'Minuman'</code>, <code>'Makanan Ringan'</code></td>
                            <td>Dipetakan ke skema konsep SKOS bersama (<code>.../kategori/minuman-dingin</code>)</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td><strong>Tipe & Format Harga</strong></td>
                            <td><code>harga_jual = '15.000'</code> (VARCHAR bertitik)</td>
                            <td><code>harga_beli = 10500.00</code> (NUMERIC desimal)</td>
                            <td>Titik dihilangkan, dikonversi ke numerik bertipe: <code>"15000.00"^^xsd:decimal</code></td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td><strong>Nilai Kosong</strong></td>
                            <td><code>nama_kasir = '-'</code> atau <code>''</code></td>
                            <td><code>kota = '-'</code> pada pemasok</td>
                            <td>Disaring oleh fungsi <code>apakahKosong()</code>; triple tidak dibuat sama sekali (Open World Assumption)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer-note">
            Toko Lentera &bull; Arsitektur Jembatan Semantik Berbasis W3C RDF, OWL, dan SKOS &bull; Didukung Apache & PostgreSQL di Laragon
        </div>
    </div>
    <script src="assets/js/main.js"></script>
</body>
</html>
