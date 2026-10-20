<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getKasiraConnection();
$pesanSukses = '';
$pesanError = '';

// Proses Simpan Transaksi Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_transaksi') {
    if (!$pdo) {
        $pesanError = "Gagal koneksi ke PostgreSQL Kasira.";
    } else {
        $idTrx = trim($_POST['id_transaksi'] ?? '');
        $namaKasir = trim($_POST['nama_kasir'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $qty = intval($_POST['qty'] ?? 1);
        $tanggalKasira = date('d/m/Y'); // Format tanggal khas Kasira: DD/MM/YYYY

        if (empty($idTrx) || empty($barcode) || $qty < 1) {
            $pesanError = "Mohon lengkapi ID Transaksi, pilihan Produk, dan Kuantitas (minimal 1).";
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
                $pesanSukses = "Transaksi <strong>$idTrx</strong> berhasil dicatat ke Kasira!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $pesanError = "Gagal mencatat transaksi: " . htmlspecialchars($e->getMessage());
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
            LIMIT 20
        ")->fetchAll();
    } catch (Exception $e) {
        $pesanError = "Gagal mengambil data dari database: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasira - Sistem Kasir Toko Lentera</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="../index.php" class="navbar-brand">
            Toko Lentera <span>| Kasira</span>
        </a>
        <div class="nav-links">
            <a href="../index.php">Portal Utama</a>
            <a href="index.php" class="active">Kasira (Kasir)</a>
            <a href="../stokara/index.php">Stokara (Gudang)</a>
            <a href="../jembatan/index.php">Jembatan Semantik</a>
            <a href="../jembatan/restock.php">Daftar Restock</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <span class="badge badge-kasira" style="font-size: 0.85rem; margin-bottom: 0.5rem;">Sistem A: Point of Sale</span>
            <h1 class="page-title">Kasira - Pencatatan Kasir & Penjualan</h1>
            <p class="page-desc">Data disimpan di PostgreSQL <code>kasira_db</code> (Port 5433). Menggunakan format tanggal <code>DD/MM/YYYY</code> dan harga teks bertitik.</p>
        </div>

        <?php if (!$pdo): ?>
            <div class="alert alert-danger">
                <div>
                    <strong>PostgreSQL Kasira Tidak Terhubung!</strong><br>
                    Pastikan layanan PostgreSQL di Windows sudah menyala dan kredensial di <code>config/database.php</code> sudah sesuai.
                </div>
            </div>
        <?php endif; ?>

        <?php if ($pesanSukses): ?>
            <div class="alert alert-success" data-auto-dismiss><?php echo $pesanSukses; ?></div>
        <?php endif; ?>
        <?php if ($pesanError): ?>
            <div class="alert alert-danger"><?php echo $pesanError; ?></div>
        <?php endif; ?>

        <div class="grid grid-2" style="margin-bottom: 2rem;">
            <!-- Form Input Transaksi -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Input Transaksi Kasir Baru</h2>
                </div>
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="simpan_transaksi">
                    
                    <div class="form-group">
                        <label for="id_transaksi">ID Transaksi</label>
                        <input type="text" id="id_transaksi" name="id_transaksi" 
                               value="T<?php echo str_pad(rand(26, 999), 3, '0', STR_PAD_LEFT); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nama_kasir">Nama Petugas Kasir</label>
                        <select id="nama_kasir" name="nama_kasir">
                            <option value="Rina">Rina</option>
                            <option value="Budi">Budi</option>
                            <option value="Sari">Sari</option>
                            <option value="-">- (Contoh Kosong)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="barcode">Pilih Produk</label>
                        <select id="barcode" name="barcode" required>
                            <option value="">-- Pilih Produk Jual --</option>
                            <?php foreach ($daftarProduk as $p): ?>
                                <option value="<?php echo htmlspecialchars($p['barcode']); ?>">
                                    <?php echo htmlspecialchars($p['nama_produk']); ?> (Rp <?php echo htmlspecialchars($p['harga_jual']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="qty">Kuantitas (Qty)</label>
                        <input type="number" id="qty" name="qty" min="1" value="1" required>
                    </div>

                    <button type="submit" class="btn btn-kasira" style="width: 100%; justify-content: center;" <?php echo !$pdo ? 'disabled' : ''; ?>>
                        Simpan Transaksi Kasir
                    </button>
                </form>
            </div>

            <!-- Katalog Produk Kasira -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Katalog Produk Jual Kasira (<?php echo count($daftarProduk); ?> Item)</h2>
                </div>
                <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Barcode</th>
                                <th>Nama Produk</th>
                                <th class="angka">Harga Jual</th>
                                <th>Kat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftarProduk)): ?>
                                <tr><td colspan="4" style="text-align:center; color:#94a3b8;">Belum ada data produk.</td></tr>
                            <?php else: ?>
                                <?php foreach ($daftarProduk as $p): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($p['barcode']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($p['nama_produk']); ?></strong></td>
                                    <td class="angka">Rp <?php echo htmlspecialchars($p['harga_jual']); ?></td>
                                    <td><span class="badge badge-kasira"><?php echo htmlspecialchars($p['kode_kategori']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tabel Transaksi Terakhir -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Riwayat 20 Transaksi Kasir Terakhir</h2>
                <span class="badge badge-kasira">Format Tanggal: DD/MM/YYYY</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID Trx</th>
                            <th>Tanggal</th>
                            <th>Kasir</th>
                            <th>Barcode</th>
                            <th>Produk</th>
                            <th class="angka">Qty</th>
                            <th class="angka">Harga Jual Satuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daftarTransaksi)): ?>
                            <tr><td colspan="7" style="text-align:center; color:#94a3b8;">Belum ada riwayat transaksi.</td></tr>
                        <?php else: ?>
                            <?php foreach ($daftarTransaksi as $t): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($t['id']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($t['tanggal']); ?></code></td>
                                <td><?php echo htmlspecialchars($t['nama_kasir'] ?: '-'); ?></td>
                                <td><code><?php echo htmlspecialchars($t['barcode']); ?></code></td>
                                <td><?php echo htmlspecialchars($t['nama_produk']); ?></td>
                                <td class="angka"><strong><?php echo htmlspecialchars($t['qty']); ?></strong></td>
                                <td class="angka">Rp <?php echo htmlspecialchars($t['harga_jual']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer-note">
            Sistem Informasi Kasira &bull; Menggunakan PostgreSQL Port 5433 &bull; Basis Data: <code>kasira_db</code>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
