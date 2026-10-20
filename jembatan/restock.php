<?php
require_once __DIR__ . '/../config/database.php';

$connKasira = Database::getKasiraConnection();
$connStokara = Database::getStokaraConnection();

$daftarRestock = [];
$analisisPemasok = [];
$errorMsg = '';

if (!$connKasira || !$connStokara) {
    $errorMsg = "Kedua basis data PostgreSQL (Kasira: port 5433 & Stokara: port 5434) harus aktif untuk menjalankan kueri lintas-sumber.";
} else {
    try {
        // 1. Ambil data penjualan per barcode dari Kasira (qty keluar & harga jual)
        $penjualan = [];
        $stmtJual = $connKasira->query("
            SELECT p.barcode, p.nama_produk, p.harga_jual, COALESCE(SUM(it.qty), 0) AS total_terjual
            FROM produk_jual p
            LEFT JOIN item_transaksi it ON p.barcode = it.barcode
            GROUP BY p.barcode, p.nama_produk, p.harga_jual
        ");
        while ($r = $stmtJual->fetch()) {
            // Normalisasi harga jual Kasira: "15.000" -> 15000.00
            $hargaJualBersih = floatval(str_replace(['.', ','], ['', '.'], $r['harga_jual']));
            $penjualan[$r['barcode']] = [
                'nama_kasira' => $r['nama_produk'],
                'harga_jual' => $hargaJualBersih,
                'total_terjual' => intval($r['total_terjual'])
            ];
        }

        // 2. Ambil data stok masuk, harga beli terakhir, dan pemasok dari Stokara
        $stmtStok = $connStokara->query("
            SELECT b.kode_barcode, b.nama_barang, b.stok_minimum,
                   COALESCE(SUM(pb.jumlah), 0) AS total_diterima
            FROM barang b
            LEFT JOIN penerimaan_barang pb ON b.kode_barcode = pb.kode_barcode
            GROUP BY b.kode_barcode, b.nama_barang, b.stok_minimum
        ");
        $gudang = [];
        while ($r = $stmtStok->fetch()) {
            $bc = $r['kode_barcode'];
            $gudang[$bc] = [
                'nama_stokara' => $r['nama_barang'],
                'stok_minimum' => intval($r['stok_minimum']),
                'total_diterima' => intval($r['total_diterima']),
                'harga_beli_akhir' => 0.0,
                'pemasok' => '-'
            ];
        }

        // 3. Ambil penerimaan terakhir per barang untuk harga beli & pemasok
        $stmtAkhir = $connStokara->query("
            SELECT DISTINCT ON (pb.kode_barcode) 
                   pb.kode_barcode, pb.harga_beli, p.nama_pemasok, pb.tgl_terima
            FROM penerimaan_barang pb
            JOIN pemasok p ON pb.id_pemasok = p.id
            ORDER BY pb.kode_barcode, pb.tgl_terima DESC, pb.id DESC
        ");
        while ($r = $stmtAkhir->fetch()) {
            $bc = $r['kode_barcode'];
            if (isset($gudang[$bc])) {
                $gudang[$bc]['harga_beli_akhir'] = floatval($r['harga_beli']);
                $gudang[$bc]['pemasok'] = $r['nama_pemasok'];
            }
        }

        // 4. PENYELARASAN LINTAS-SUMBER (owl:sameAs pada barcode = kode_barcode)
        foreach ($gudang as $bc => $stokData) {
            if (isset($penjualan[$bc])) {
                $jualData = $penjualan[$bc];
                $masuk = $stokData['total_diterima'];
                $keluar = $jualData['total_terjual'];
                $sisaStok = $masuk - $keluar;
                $stokMin = $stokData['stok_minimum'];
                $marginUnit = $jualData['harga_jual'] - $stokData['harga_beli_akhir'];
                $perluRestock = ($sisaStok < $stokMin);

                $daftarRestock[] = [
                    'barcode' => $bc,
                    'nama_produk' => $jualData['nama_kasira'], // Kasira label (mixed-case)
                    'nama_barang' => $stokData['nama_stokara'], // Stokara label (UPPERCASE)
                    'total_masuk' => $masuk,
                    'total_keluar' => $keluar,
                    'sisa_stok' => $sisaStok,
                    'stok_min' => $stokMin,
                    'selisih_kritis' => $sisaStok - $stokMin,
                    'pemasok' => $stokData['pemasok'],
                    'harga_jual' => $jualData['harga_jual'],
                    'harga_beli' => $stokData['harga_beli_akhir'],
                    'margin' => $marginUnit,
                    'perlu_restock' => $perluRestock
                ];

                // Hitung omzet per pemasok (Pertanyaan 3)
                $sup = $stokData['pemasok'];
                $omzet = $keluar * $jualData['harga_jual'];
                $analisisPemasok[$sup] = ($analisisPemasok[$sup] ?? 0) + $omzet;
            }
        }

        // Urutkan daftar restock berdasarkan tingkat kekritisan (paling defisit dulu)
        usort($daftarRestock, function ($a, $b) {
            return $a['selisih_kritis'] <=> $b['selisih_kritis'];
        });

        // Urutkan pemasok berdasarkan omzet terbesar
        arsort($analisisPemasok);

    } catch (Exception $e) {
        $errorMsg = "Kesalahan eksekusi data: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Restock &bull; Lentera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <span class="brand-dot"></span>
            Lentera Data Hub
        </a>
        <div class="nav-menu">
            <a href="index.php" class="nav-link">Pusat Kendali</a>
            <a href="restock.php" class="nav-link active">Evaluasi Restock</a>
            <a href="sparql_runner.php" class="nav-link">Kueri SPARQL</a>
        </div>
    </nav>

    <main class="container">
        <header class="hero-header">
            <h1 class="hero-title">Evaluasi Restock &amp; Margin</h1>
            <p class="hero-desc">Penyelarasan semantik transaksi kasir dan stok fisik gudang.</p>
        </header>

        <?php if ($errorMsg): ?>
            <div class="callout callout-critical"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <!-- 3 KPI Bisnis: Card Container Terstruktur -->
        <div class="grid grid-cols-3" style="margin-bottom: 2rem;">
            <div class="card">
                <div class="stat-item">
                    <div class="metric-value" style="color: var(--critical);">
                        <?php 
                        $kritisCount = count(array_filter($daftarRestock, fn($item) => $item['perlu_restock']));
                        echo $kritisCount;
                        ?>
                    </div>
                    <div class="metric-label">Produk Kritis</div>
                    <div class="metric-sub">Sisa stok di bawah batas aman.</div>
                </div>
            </div>

            <?php 
            $maxMarginItem = null;
            foreach ($daftarRestock as $it) {
                if (!$maxMarginItem || $it['margin'] > $maxMarginItem['margin']) $maxMarginItem = $it;
            }
            ?>
            <div class="card">
                <div class="stat-item">
                    <div class="metric-value" style="font-size: 1.55rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $maxMarginItem ? htmlspecialchars($maxMarginItem['nama_produk']) : '-'; ?>">
                        <?php echo $maxMarginItem ? htmlspecialchars($maxMarginItem['nama_produk']) : '-'; ?>
                    </div>
                    <div class="metric-label">Margin Tertinggi</div>
                    <div class="metric-sub">
                        Rp <?php echo $maxMarginItem ? number_format($maxMarginItem['margin'], 0, ',', '.') : '0'; ?> per unit.
                    </div>
                </div>
            </div>

            <?php 
            $topSup = key($analisisPemasok);
            $topOmzet = current($analisisPemasok);
            ?>
            <div class="card">
                <div class="stat-item">
                    <div class="metric-value" style="font-size: 1.55rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $topSup ? htmlspecialchars($topSup) : '-'; ?>">
                        <?php echo $topSup ? htmlspecialchars($topSup) : '-'; ?>
                    </div>
                    <div class="metric-label">Pemasok Omzet Terbesar</div>
                    <div class="metric-sub">
                        Rp <?php echo $topOmzet ? number_format($topOmzet, 0, ',', '.') : '0'; ?> total omzet.
                    </div>
                </div>
            </div>
        </div>

        <!-- Matriks Evaluasi Restock: Card Container -->
        <section class="card" style="margin-bottom: 2rem;">
            <div>
                <div class="card-header">
                    <h2 class="card-title">Matriks Pengadaan</h2>
                    <a href="export_rdf.php?run=1" target="_blank" class="btn btn-secondary">
                        Unduh TTL &darr;
                    </a>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Barcode</th>
                                <th>Nama Produk</th>
                                <th class="num">Masuk</th>
                                <th class="num">Terjual</th>
                                <th class="num">Sisa</th>
                                <th class="num">Min</th>
                                <th>Status</th>
                                <th>Pemasok</th>
                                <th class="num">Harga Jual</th>
                                <th class="num">Harga Beli</th>
                                <th class="num">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftarRestock)): ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; color: var(--ink-secondary); padding: 3rem;">
                                        Data belum tersedia.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftarRestock as $row): ?>
                                <tr>
                                    <td><span class="code-tag"><?php echo htmlspecialchars($row['barcode']); ?></span></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['nama_produk']); ?></strong>
                                        <span class="text-muted" style="display: block; font-size: 0.78rem;"><?php echo htmlspecialchars($row['nama_barang']); ?></span>
                                    </td>
                                    <td class="num">+<?php echo $row['total_masuk']; ?></td>
                                    <td class="num">-<?php echo $row['total_keluar']; ?></td>
                                    <td class="num" style="font-weight: 600;"><?php echo $row['sisa_stok']; ?></td>
                                    <td class="num"><?php echo $row['stok_min']; ?></td>
                                    <td>
                                        <?php if ($row['perlu_restock']): ?>
                                            <span class="tag-critical">Restock (<?php echo $row['selisih_kritis']; ?>)</span>
                                        <?php else: ?>
                                            <span class="tag-safe">Aman</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['pemasok']); ?></td>
                                    <td class="num">Rp <?php echo number_format($row['harga_jual'], 0, ',', '.'); ?></td>
                                    <td class="num">Rp <?php echo number_format($row['harga_beli'], 0, ',', '.'); ?></td>
                                    <td class="num" style="font-weight: 600;">
                                        Rp <?php echo number_format($row['margin'], 0, ',', '.'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Rationale: Card Container Bersih Tanpa <hr> -->
        <section class="card">
            <div>
                <h2 class="card-title" style="margin-bottom: 0.5rem;">Penyelarasan Data</h2>
                <p>
                    Kasira mengelola transaksi harian. Stokara mengelola logistik gudang. Jembatan semantik menyelaraskan relasi produk menggunakan <code>owl:sameAs</code> berbasis barcode EAN-13 tanpa mengubah skema basis data operasional.
                </p>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content" style="padding-top: 0; padding-bottom: 0;">
            <div>Lentera Data Hub</div>
            <div>W3C Semantic Standards</div>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>
