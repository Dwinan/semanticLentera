<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getStokaraConnection();
$pesanSukses = '';
$pesanError = '';

// Proses Simpan Penerimaan Barang Masuk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_penerimaan') {
    if (!$pdo) {
        $pesanError = "Gagal koneksi ke server basis data Stokara.";
    } else {
        $idPb = trim($_POST['id_pb'] ?? '');
        $kodeBarcode = trim($_POST['kode_barcode'] ?? '');
        $idPemasok = trim($_POST['id_pemasok'] ?? '');
        $jumlah = intval($_POST['jumlah'] ?? 0);
        $hargaBeli = floatval($_POST['harga_beli'] ?? 0);
        $tglTerima = trim($_POST['tgl_terima'] ?? date('Y-m-d')); // Format Stokara: YYYY-MM-DD

        if (empty($idPb) || empty($kodeBarcode) || empty($idPemasok) || $jumlah < 1 || $hargaBeli <= 0) {
            $pesanError = "Mohon lengkapi ID Penerimaan, Barang, Pemasok, Jumlah, dan Harga Beli.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO penerimaan_barang (id, kode_barcode, id_pemasok, tgl_terima, jumlah, harga_beli)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$idPb, $kodeBarcode, $idPemasok, $tglTerima, $jumlah, $hargaBeli]);
                $pesanSukses = "Penerimaan barang <strong>" . htmlspecialchars($idPb) . "</strong> berhasil disimpan.";
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
            LIMIT 25
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
    <title>Stokara &bull; Logistik Gudang</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <span class="brand-dot"></span>
            Stokara WMS
        </a>
        <div class="nav-menu">
            <span class="text-muted" style="font-size: 0.85rem;"><?php echo date('Y-m-d'); ?></span>
        </div>
    </nav>

    <main class="container">
        <header class="hero-header">
            <h1 class="hero-title">Logistik &amp; Inventaris Gudang</h1>
            <p class="hero-desc">Penerimaan pasokan barang masuk dan master stok gudang.</p>
        </header>

        <?php if (!$pdo): ?>
            <div class="callout callout-critical">
                Koneksi database stokara_db (Port 5434) terputus.
            </div>
        <?php endif; ?>

        <?php if ($pesanSukses): ?>
            <div class="callout" data-auto-dismiss><?php echo $pesanSukses; ?></div>
        <?php endif; ?>
        <?php if ($pesanError): ?>
            <div class="callout callout-critical"><?php echo $pesanError; ?></div>
        <?php endif; ?>

        <!-- Grid Input & Master Barang dalam Card Terstruktur -->
        <div class="grid grid-split-4-8" style="margin-bottom: 2rem;">
            <!-- Card Form Input Penerimaan -->
            <section class="card">
                <div>
                    <div class="card-header">
                        <h2 class="card-title">Penerimaan Barang</h2>
                    </div>

                    <form action="index.php" method="POST">
                        <input type="hidden" name="action" value="simpan_penerimaan">

                        <div class="form-field">
                            <label class="field-label" for="id_pb">Nomor Surat (ID PB)</label>
                            <input type="text" class="input-control" id="id_pb" name="id_pb" 
                                   value="PB<?php echo str_pad(rand(23, 999), 2, '0', STR_PAD_LEFT); ?>" required>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="kode_barcode">Barang Masuk</label>
                            <select class="input-control" id="kode_barcode" name="kode_barcode" required>
                                <option value="">-- Pilih Barang --</option>
                                <?php foreach ($daftarBarang as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b['kode_barcode']); ?>">
                                        <?php echo htmlspecialchars($b['nama_barang']); ?> (Min: <?php echo $b['stok_minimum']; ?> unit)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="id_pemasok">Pemasok</label>
                            <select class="input-control" id="id_pemasok" name="id_pemasok" required>
                                <option value="">-- Pilih Pemasok --</option>
                                <?php foreach ($daftarPemasok as $sup): ?>
                                    <option value="<?php echo htmlspecialchars($sup['id']); ?>">
                                        [<?php echo htmlspecialchars($sup['id']); ?>] <?php echo htmlspecialchars($sup['nama_pemasok']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2" style="gap: 1rem;">
                            <div class="form-field">
                                <label class="field-label" for="jumlah">Jumlah</label>
                                <input type="number" class="input-control" id="jumlah" name="jumlah" min="1" value="20" required>
                            </div>
                            <div class="form-field">
                                <label class="field-label" for="harga_beli">Harga Beli</label>
                                <input type="number" class="input-control" id="harga_beli" name="harga_beli" step="100" min="500" value="10000" required>
                            </div>
                        </div>

                        <div class="form-field">
                            <label class="field-label" for="tgl_terima">Tanggal Terima</label>
                            <input type="date" class="input-control" id="tgl_terima" name="tgl_terima" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;" <?php echo !$pdo ? 'disabled' : ''; ?>>
                            Simpan Penerimaan
                        </button>
                    </form>
                </div>
            </section>

            <!-- Card Master Barang Gudang -->
            <section class="card">
                <div>
                    <div class="card-header">
                        <h2 class="card-title">Master Barang (<?php echo count($daftarBarang); ?> Item)</h2>
                    </div>

                    <div class="table-wrap" style="max-height: 480px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Barcode</th>
                                    <th>Nama Barang</th>
                                    <th>Kategori</th>
                                    <th class="num">Batas Minimum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($daftarBarang)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--ink-secondary); padding: 2rem;">
                                            Belum ada data barang.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($daftarBarang as $b): ?>
                                    <tr>
                                        <td><span class="code-tag"><?php echo htmlspecialchars($b['kode_barcode']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($b['nama_barang']); ?></strong></td>
                                        <td><span class="text-muted"><?php echo htmlspecialchars($b['kategori'] ?: '-'); ?></span></td>
                                        <td class="num"><?php echo htmlspecialchars($b['stok_minimum']); ?> unit</td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- Card Histori Penerimaan -->
        <section class="card">
            <div>
                <div class="card-header">
                    <h2 class="card-title">Histori Penerimaan Terakhir</h2>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>No. Surat</th>
                                <th>Tanggal</th>
                                <th>Barang</th>
                                <th>Pemasok</th>
                                <th class="num">Jumlah</th>
                                <th class="num">Harga Beli</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftarPenerimaan)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--ink-secondary); padding: 2.5rem;">
                                        Belum ada log penerimaan pasokan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftarPenerimaan as $pb): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($pb['id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pb['tgl_terima']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($pb['nama_barang']); ?></strong>
                                        <span class="code-tag" style="margin-left: 0.35rem;"><?php echo htmlspecialchars($pb['kode_barcode']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($pb['nama_pemasok']); ?></td>
                                    <td class="num">+<?php echo htmlspecialchars($pb['jumlah']); ?> unit</td>
                                    <td class="num">Rp <?php echo number_format($pb['harga_beli'], 0, ',', '.'); ?></td>
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
            <div>Stokara WMS &bull; Sistem Inventaris Gudang</div>
            <div>Format Tanggal: YYYY-MM-DD</div>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>
