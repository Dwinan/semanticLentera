<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getKasiraConnection();
$pesanSukses = '';
$pesanError = '';

// Proses Simpan Transaksi Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_transaksi') {
    if (!$pdo) {
        $pesanError = "Koneksi ke basis data Kasira terputus. Pastikan service basis data aktif.";
    } else {
        $idTrx = trim($_POST['id_transaksi'] ?? '');
        $namaKasir = trim($_POST['nama_kasir'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $qty = intval($_POST['qty'] ?? 1);
        $tanggalKasira = date('d/m/Y'); // Format Kasira: DD/MM/YYYY

        if (empty($idTrx) || empty($barcode) || $qty < 1) {
            $pesanError = "Lengkapi ID Struk, pilihan produk, dan jumlah kuantitas valid.";
        } else {
            try {
                $pdo->beginTransaction();

                // Simpan transaksi
                $stmtTrx = $pdo->prepare("INSERT INTO transaksi (id, tanggal, nama_kasir) VALUES (?, ?, ?)");
                $stmtTrx->execute([$idTrx, $tanggalKasira, $namaKasir]);

                // Simpan item transaksi
                $stmtItem = $pdo->prepare("INSERT INTO item_transaksi (id_transaksi, barcode, qty) VALUES (?, ?, ?)");
                $stmtItem->execute([$idTrx, $barcode, $qty]);

                $pdo->commit();
                $pesanSukses = "Transaksi <strong>$idTrx</strong> berhasil dicetak dan dibukukan.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $pesanError = "Gagal memproses transaksi: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Ambil data produk & transaksi
$daftarProduk = [];
$daftarTransaksi = [];
if ($pdo) {
    try {
        $daftarProduk = $pdo->query("SELECT * FROM produk_jual ORDER BY barcode ASC")->fetchAll();
        $daftarTransaksi = $pdo->query("
            SELECT t.id, t.tanggal, t.nama_kasir, p.nama_produk, p.harga_jual, it.qty, it.barcode
            FROM transaksi t
            JOIN item_transaksi it ON t.id = it.id_transaksi
            JOIN produk_jual p ON it.barcode = p.barcode
            ORDER BY t.id DESC, it.id ASC
            LIMIT 25
        ")->fetchAll();
    } catch (Exception $e) {
        $pesanError = "Kueri gagal: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasira &bull; Kasir Ritel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <span class="brand-dot"></span>
            Kasira POS
        </a>
        <div class="nav-menu">
            <span class="text-muted" style="font-size: 0.85rem;"><?php echo date('d/m/Y'); ?></span>
        </div>
    </nav>

    <main class="container">
        <header class="hero-header">
            <h1 class="hero-title">Terminal Kasir</h1>
            <p class="hero-desc">Pencatatan transaksi kasir toko ritel.</p>
        </header>

        <?php if (!$pdo): ?>
            <div class="callout callout-critical">
                Koneksi database kasira_db (Port 5433) terputus.
            </div>
        <?php endif; ?>

        <?php if ($pesanSukses): ?>
            <div class="callout" data-auto-dismiss><?php echo $pesanSukses; ?></div>
        <?php endif; ?>
        <?php if ($pesanError): ?>
            <div class="callout callout-critical"><?php echo $pesanError; ?></div>
        <?php endif; ?>

        <!-- Grid Input & Katalog dalam Card Terstruktur -->
        <div class="grid grid-split-4-8" style="margin-bottom: 2rem;">
            <!-- Card Form Input Transaksi -->
            <section class="card">
                <div>
                    <div class="card-header">
                        <h2 class="card-title">Transaksi Baru</h2>
                    </div>

                    <form action="index.php" method="POST">
                        <input type="hidden" name="action" value="simpan_transaksi">
                        
                        <div class="form-field">
                            <label class="field-label" for="id_transaksi">Nomor Struk</label>
                            <input type="text" class="input-control" id="id_transaksi" name="id_transaksi" 
                                   value="T<?php echo str_pad(rand(26, 999), 3, '0', STR_PAD_LEFT); ?>" required>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="nama_kasir">Petugas Kasir</label>
                            <select class="input-control" id="nama_kasir" name="nama_kasir">
                                <option value="Rina">Rina</option>
                                <option value="Budi">Budi</option>
                                <option value="Sari">Sari</option>
                                <option value="-">- (Anonim)</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="barcode">Produk</label>
                            <select class="input-control" id="barcode" name="barcode" required>
                                <option value="">-- Pilih Produk --</option>
                                <?php foreach ($daftarProduk as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['barcode']); ?>">
                                        <?php echo htmlspecialchars($p['nama_produk']); ?> (Rp <?php echo htmlspecialchars($p['harga_jual']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="qty">Jumlah Beli</label>
                            <input type="number" class="input-control" id="qty" name="qty" min="1" value="1" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;" <?php echo !$pdo ? 'disabled' : ''; ?>>
                            Simpan Transaksi
                        </button>
                    </form>
                </div>
            </section>

            <!-- Card Katalog Produk -->
            <section class="card">
                <div>
                    <div class="card-header">
                        <h2 class="card-title">Katalog Produk (<?php echo count($daftarProduk); ?> Item)</h2>
                    </div>

                    <div class="table-wrap" style="max-height: 440px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Barcode</th>
                                    <th>Nama Produk</th>
                                    <th class="num">Harga Jual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($daftarProduk)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: var(--ink-secondary); padding: 2rem;">
                                            Belum ada data produk.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($daftarProduk as $p): ?>
                                    <tr>
                                        <td><span class="code-tag"><?php echo htmlspecialchars($p['barcode']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($p['nama_produk']); ?></strong></td>
                                        <td class="num">Rp <?php echo htmlspecialchars($p['harga_jual']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- Card Riwayat Transaksi -->
        <section class="card">
            <div>
                <div class="card-header">
                    <h2 class="card-title">Riwayat Penjualan Terakhir</h2>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>No. Struk</th>
                                <th>Tanggal</th>
                                <th>Kasir</th>
                                <th>Produk</th>
                                <th class="num">Qty</th>
                                <th class="num">Harga Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftarTransaksi)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--ink-secondary); padding: 2.5rem;">
                                        Belum ada catatan penjualan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftarTransaksi as $t): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($t['tanggal']); ?></td>
                                <td><?php echo htmlspecialchars($t['nama_kasir'] ?: '-'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($t['nama_produk']); ?></strong>
                                    <span class="code-tag" style="margin-left: 0.35rem;"><?php echo htmlspecialchars($t['barcode']); ?></span>
                                </td>
                                <td class="num"><?php echo htmlspecialchars($t['qty']); ?></td>
                                <td class="num">Rp <?php echo htmlspecialchars($t['harga_jual']); ?></td>
                            </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content" style="padding-top: 0; padding-bottom: 0;">
            <div>Kasira POS &bull; Terminal Ritel</div>
            <div>Format Tanggal: DD/MM/YYYY</div>
        </div>
    </footer>
    <script src="../assets/js/main.js"></script>
</body>
</html>
