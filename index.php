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
    <title>Lentera &bull; Gateway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <span class="brand-dot"></span>
            Lentera
        </a>
        <div class="nav-menu">
            <span class="text-muted" style="font-size: 0.85rem;">Sistem Aktif</span>
        </div>
    </nav>

    <main class="container">
        <!-- Header Tanpa Garis Pembelah -->
        <header class="hero-header">
            <h1 class="hero-title">Sistem Informasi Lentera</h1>
            <p class="hero-desc">
                Portal operasional kasir ritel, logistik gudang, dan integrasi semantik.
            </p>
        </header>

        <?php if (!$dbStatus['kasira'] || !$dbStatus['stokara']): ?>
            <div class="callout callout-warning">
                Basis data PostgreSQL belum terhubung. Periksa konfigurasi di <code>config/database.php</code>.
            </div>
        <?php endif; ?>

        <!-- Grid 2 Kolom: Card Kasira & Stokara -->
        <div class="grid grid-cols-2" style="margin-bottom: 1.75rem;">
            <!-- Card Kasira POS -->
            <section class="card">
                <div>
                    <div class="card-header">
                        <h2 class="card-title">Kasira POS</h2>
                        <span class="text-muted" style="font-size: 0.82rem;">
                            <?php echo $dbStatus['kasira'] ? 'Online (5433)' : 'Offline'; ?>
                        </span>
                    </div>
                    <p style="margin-bottom: 1.5rem;">
                        Terminal kasir toko fisik untuk pencatatan transaksi belanja harian.
                    </p>

                    <!-- Mini Grid Angka di Atas Label -->
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="metric-value"><?php echo $totalProdukKasira; ?></div>
                            <div class="metric-label">Katalog Produk</div>
                        </div>
                        <div class="stat-item">
                            <div class="metric-value"><?php echo $totalTransaksi; ?></div>
                            <div class="metric-label">Total Transaksi</div>
                        </div>
                    </div>
                </div>

                <a href="kasira/index.php" class="btn btn-primary" style="width: 100%;">
                    Buka Kasira &rarr;
                </a>
            </section>

            <!-- Card Stokara WMS -->
            <section class="card">
                <div>
                    <div class="card-header">
                        <h2 class="card-title">Stokara WMS</h2>
                        <span class="text-muted" style="font-size: 0.82rem;">
                            <?php echo $dbStatus['stokara'] ? 'Online (5434)' : 'Offline'; ?>
                        </span>
                    </div>
                    <p style="margin-bottom: 1.5rem;">
                        Sistem pergudangan untuk logistik pasokan dan master inventaris.
                    </p>

                    <!-- Mini Grid Angka di Atas Label -->
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="metric-value"><?php echo $totalBarangGudang; ?></div>
                            <div class="metric-label">Master Barang</div>
                        </div>
                        <div class="stat-item">
                            <div class="metric-value"><?php echo $totalPenerimaan; ?></div>
                            <div class="metric-label">Log Penerimaan</div>
                        </div>
                    </div>
                </div>

                <a href="stokara/index.php" class="btn btn-primary" style="width: 100%;">
                    Buka Stokara &rarr;
                </a>
            </section>
        </div>

        <!-- Card Data Hub Semantik (Tanpa <hr> Pembelah) -->
        <section class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                <div style="max-width: 600px;">
                    <h2 class="card-title" style="margin-bottom: 0.4rem;">Data Hub Semantik</h2>
                    <p>Integrasi data penjualan dan gudang untuk evaluasi restock serta analisis margin keuntungan.</p>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <a href="jembatan/restock.php" class="btn btn-primary">
                        Daftar Restock
                    </a>
                    <a href="jembatan/index.php" class="btn btn-secondary">
                        Pusat Kendali
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content" style="padding-top: 0; padding-bottom: 0;">
            <div>Toko Lentera</div>
            <div>W3C Linked Data Standard</div>
        </div>
    </footer>
    <script src="assets/js/main.js"></script>
</body>
</html>
