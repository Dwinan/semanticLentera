<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getStokaraConnection();
$pesanSukses = '';
$pesanError = '';

// Proses Simpan Penerimaan Barang Masuk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_penerimaan') {
    if (!$pdo) {
        $pesanError = "Gagal koneksi ke PostgreSQL Stokara.";
    } else {
        $idPb = trim($_POST['id_pb'] ?? '');
        $kodeBarcode = trim($_POST['kode_barcode'] ?? '');
        $idPemasok = trim($_POST['id_pemasok'] ?? '');
        $jumlah = intval($_POST['jumlah'] ?? 0);
        $hargaBeli = floatval($_POST['harga_beli'] ?? 0);
        $tglTerima = trim($_POST['tgl_terima'] ?? date('Y-m-d')); // Format Stokara: YYYY-MM-DD

        if (empty($idPb) || empty($kodeBarcode) || empty($idPemasok) || $jumlah < 1 || $hargaBeli <= 0) {
            $pesanError = "Mohon lengkapi ID Penerimaan, Barang, Pemasok, Jumlah (minimal 1), dan Harga Beli.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO penerimaan_barang (id, kode_barcode, id_pemasok, tgl_terima, jumlah, harga_beli)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$idPb, $kodeBarcode, $idPemasok, $tglTerima, $jumlah, $hargaBeli]);
                $pesanSukses = "Penerimaan barang <strong>$idPb</strong> berhasil dicatat ke sistem Stokara!";
            } catch (Exception $e) {
                $pesanError = "Gagal mencatat penerimaan: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Ambil data barang, pemasok, dan penerimaan
$daftarBarang = [];
$daftarPemasok = [];
$daftarPenerimaan = [];
if ($pdo) {
    try {
        $daftarBarang = $pdo->query("SELECT * FROM barang ORDER BY kode_barcode ASC")->fetchAll();
        $daftarPemasok = $pdo->query("SELECT * FROM pemasok ORDER BY id ASC")->fetchAll();
        $daftarPenerimaan = $pdo->query("
            SELECT pb.id, pb.tgl_terima, pb.jumlah, pb.harga_beli, b.nama_barang, b.kode_barcode, p.nama_pemasok
            FROM penerimaan_barang pb
            JOIN barang b ON pb.kode_barcode = b.kode_barcode
            JOIN pemasok p ON pb.id_pemasok = p.id
            ORDER BY pb.tgl_terima DESC, pb.id DESC
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
    <title>Stokara - Sistem Gudang & Inventaris Toko Lentera</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="../index.php" class="navbar-brand">
            Toko Lentera <span>| Stokara</span>
        </a>
        <div class="nav-links">
            <a href="../index.php">Portal Utama</a>
            <a href="../kasira/index.php">Kasira (Kasir)</a>
            <a href="index.php" class="active">Stokara (Gudang)</a>
            <a href="../jembatan/index.php">Jembatan Semantik</a>
            <a href="../jembatan/restock.php">Daftar Restock</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <span class="badge badge-stokara" style="font-size: 0.85rem; margin-bottom: 0.5rem;">Sistem B: Inventaris Gudang</span>
            <h1 class="page-title">Stokara - Penerimaan Barang & Master Gudang</h1>
            <p class="page-desc">Data disimpan di PostgreSQL <code>stokara_db</code> (Port 5434). Menggunakan format tanggal <code>YYYY-MM-DD</code>, penamaan kapital, dan harga beli numerik desimal.</p>
        </div>

        <?php if (!$pdo): ?>
            <div class="alert alert-danger">
                <div>
                    <strong>PostgreSQL Stokara Tidak Terhubung!</strong><br>
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
            <!-- Form Input Penerimaan Barang -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Catat Penerimaan Barang Masuk</h2>
                </div>
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="simpan_penerimaan">

                    <div class="form-group">
                        <label for="id_pb">ID Penerimaan</label>
                        <input type="text" id="id_pb" name="id_pb" 
                               value="PB<?php echo str_pad(rand(23, 999), 2, '0', STR_PAD_LEFT); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="kode_barcode">Pilih Barang Gudang</label>
                        <select id="kode_barcode" name="kode_barcode" required>
                            <option value="">-- Pilih Barang --</option>
                            <?php foreach ($daftarBarang as $b): ?>
                                <option value="<?php echo htmlspecialchars($b['kode_barcode']); ?>">
                                    <?php echo htmlspecialchars($b['nama_barang']); ?> (Min Stok: <?php echo $b['stok_minimum']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_pemasok">Pilih Pemasok</label>
                        <select id="id_pemasok" name="id_pemasok" required>
                            <option value="">-- Pilih Pemasok --</option>
                            <?php foreach ($daftarPemasok as $sup): ?>
                                <option value="<?php echo htmlspecialchars($sup['id']); ?>">
                                    [<?php echo htmlspecialchars($sup['id']); ?>] <?php echo htmlspecialchars($sup['nama_pemasok']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-2" style="gap: 1rem;">
                        <div class="form-group">
                            <label for="jumlah">Jumlah Unit Masuk</label>
                            <input type="number" id="jumlah" name="jumlah" min="1" value="20" required>
                        </div>
                        <div class="form-group">
                            <label for="harga_beli">Harga Beli Satuan (Rp)</label>
                            <input type="number" id="harga_beli" name="harga_beli" step="100" min="500" value="10000" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tgl_terima">Tanggal Terima (YYYY-MM-DD)</label>
                        <input type="date" id="tgl_terima" name="tgl_terima" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-stokara" style="width: 100%; justify-content: center;" <?php echo !$pdo ? 'disabled' : ''; ?>>
                        Simpan Penerimaan Gudang
                    </button>
                </form>
            </div>

            <!-- Master Barang Gudang -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Master Barang Gudang (<?php echo count($daftarBarang); ?> Item)</h2>
                </div>
                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode Barcode</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th class="angka">Stok Min</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftarBarang)): ?>
                                <tr><td colspan="4" style="text-align:center; color:#94a3b8;">Belum ada master barang.</td></tr>
                            <?php else: ?>
                                <?php foreach ($daftarBarang as $b): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($b['kode_barcode']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($b['nama_barang']); ?></strong></td>
                                    <td><span class="badge badge-stokara"><?php echo htmlspecialchars($b['kategori'] ?: '-'); ?></span></td>
                                    <td class="angka"><?php echo htmlspecialchars($b['stok_minimum']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tabel Riwayat Penerimaan Terakhir -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Riwayat 20 Penerimaan Barang Masuk Terakhir</h2>
                <span class="badge badge-stokara">Format Tanggal: YYYY-MM-DD</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID Penerimaan</th>
                            <th>Tanggal Terima</th>
                            <th>Barang</th>
                            <th>Pemasok</th>
                            <th class="angka">Jumlah Masuk</th>
                            <th class="angka">Harga Beli Satuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daftarPenerimaan)): ?>
                            <tr><td colspan="6" style="text-align:center; color:#94a3b8;">Belum ada riwayat penerimaan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($daftarPenerimaan as $pb): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($pb['id']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($pb['tgl_terima']); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($pb['nama_barang']); ?></strong><br>
                                    <small style="color: #64748b;"><code><?php echo htmlspecialchars($pb['kode_barcode']); ?></code></small>
                                </td>
                                <td><?php echo htmlspecialchars($pb['nama_pemasok']); ?></td>
                                <td class="angka sukses">+<?php echo htmlspecialchars($pb['jumlah']); ?> unit</td>
                                <td class="angka">Rp <?php echo number_format($pb['harga_beli'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer-note">
            Sistem Informasi Stokara &bull; Menggunakan PostgreSQL Port 5434 &bull; Basis Data: <code>stokara_db</code>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
