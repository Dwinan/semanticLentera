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
    <title>Daftar Restock - Jembatan Semantik Toko Lentera</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="../index.php" class="navbar-brand">
            Toko Lentera <span>| Jembatan Semantik</span>
        </a>
        <div class="nav-links">
            <a href="../index.php">Portal Utama</a>
            <a href="../kasira/index.php">Kasira (Kasir)</a>
            <a href="../stokara/index.php">Stokara (Gudang)</a>
            <a href="index.php">Jembatan Semantik</a>
            <a href="restock.php" class="active">Daftar Restock</a>
            <a href="sparql_runner.php">Kueri SPARQL</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <span class="badge badge-bridge" style="font-size: 0.85rem; margin-bottom: 0.5rem;">Cross-Source Decision Dashboard</span>
            <h1 class="page-title">Daftar Restock & Evaluasi Margin Toko Lentera</h1>
            <p class="page-desc">Integrasi semantik data penjualan <strong>Kasira</strong> dan data inventaris <strong>Stokara</strong> yang menjawab 3 pertanyaan kompetensi bisnis.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger">
                <strong>Peringatan Sistem:</strong> <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>

        <!-- Highlight 3 Pertanyaan Lintas-Sumber -->
        <div class="grid grid-3" style="margin-bottom: 2rem;">
            <div class="card" style="border-top: 4px solid var(--danger);">
                <span class="badge badge-danger" style="margin-bottom: 0.5rem;">Pertanyaan 1</span>
                <h3 style="font-size: 1rem; margin-bottom: 0.4rem;">Produk Kritis Perlu Restock</h3>
                <p style="font-size: 0.88rem; color: #475569; margin-bottom: 0.75rem;">Total penerimaan (Stokara) dikurangi total terjual (Kasira) di bawah stok minimum.</p>
                <div style="font-size: 1.6rem; font-weight: 800; color: var(--danger);">
                    <?php 
                    $kritisCount = count(array_filter($daftarRestock, fn($item) => $item['perlu_restock']));
                    echo $kritisCount;
                    ?> Produk
                </div>
            </div>

            <div class="card" style="border-top: 4px solid var(--success);">
                <span class="badge badge-success" style="margin-bottom: 0.5rem;">Pertanyaan 2</span>
                <h3 style="font-size: 1rem; margin-bottom: 0.4rem;">Margin Unit Tertinggi</h3>
                <p style="font-size: 0.88rem; color: #475569; margin-bottom: 0.75rem;">Harga jual (Kasira) dikurangi harga beli terakhir (Stokara).</p>
                <?php 
                $maxMarginItem = null;
                foreach ($daftarRestock as $it) {
                    if (!$maxMarginItem || $it['margin'] > $maxMarginItem['margin']) $maxMarginItem = $it;
                }
                ?>
                <div style="font-size: 1.25rem; font-weight: 800; color: var(--success);">
                    <?php echo $maxMarginItem ? htmlspecialchars($maxMarginItem['nama_produk']) : '-'; ?>
                </div>
                <small style="color: #64748b;">Margin: Rp <?php echo $maxMarginItem ? number_format($maxMarginItem['margin'], 2, ',', '.') : '0'; ?> / unit</small>
            </div>

            <div class="card" style="border-top: 4px solid var(--bridge);">
                <span class="badge badge-bridge" style="margin-bottom: 0.5rem;">Pertanyaan 3</span>
                <h3 style="font-size: 1rem; margin-bottom: 0.4rem;">Pemasok Omzet Terbesar</h3>
                <p style="font-size: 0.88rem; color: #475569; margin-bottom: 0.75rem;">Volume penjualan kasir dikalikan harga jual per pemasok.</p>
                <?php 
                $topSup = key($analisisPemasok);
                $topOmzet = current($analisisPemasok);
                ?>
                <div style="font-size: 1.15rem; font-weight: 800; color: var(--bridge);">
                    <?php echo $topSup ? htmlspecialchars($topSup) : '-'; ?>
                </div>
                <small style="color: #64748b;">Omzet: Rp <?php echo $topOmzet ? number_format($topOmzet, 0, ',', '.') : '0'; ?></small>
            </div>
        </div>

        <!-- Tabel Utama Daftar Restock -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Tabel Produk & Analisis Kebutuhan Restock</h2>
                    <small style="color: #64748b;">Baris dengan latar merah menandakan stok perkiraan sudah di bawah batas aman stok minimum</small>
                </div>
                <a href="export_rdf.php?run=1" target="_blank" class="btn btn-outline" style="font-size: 0.82rem;">
                    Unduh / Ekspor RDF (.ttl)
                </a>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Barcode (EAN-13)</th>
                            <th>Nama Produk</th>
                            <th class="angka">Total Masuk (Stokara)</th>
                            <th class="angka">Terjual (Kasira)</th>
                            <th class="angka">Sisa Stok</th>
                            <th class="angka">Stok Min</th>
                            <th>Status</th>
                            <th>Pemasok Utama</th>
                            <th class="angka">Harga Jual</th>
                            <th class="angka">Harga Beli Akhir</th>
                            <th class="angka">Margin / Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daftarRestock)): ?>
                            <tr><td colspan="11" style="text-align: center; color: #94a3b8; padding: 2rem;">Tidak ada data atau basis data belum terhubung.</td></tr>
                        <?php else: ?>
                            <?php foreach ($daftarRestock as $row): ?>
                            <tr style="<?php echo $row['perlu_restock'] ? 'background-color: #fff1f2;' : ''; ?>">
                                <td><code><?php echo htmlspecialchars($row['barcode']); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['nama_produk']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($row['nama_barang']); ?></small>
                                </td>
                                <td class="angka">+<?php echo $row['total_masuk']; ?></td>
                                <td class="angka">-<?php echo $row['total_keluar']; ?></td>
                                <td class="angka <?php echo $row['perlu_restock'] ? 'bahaya' : ''; ?>">
                                    <strong><?php echo $row['sisa_stok']; ?></strong>
                                </td>
                                <td class="angka"><?php echo $row['stok_min']; ?></td>
                                <td>
                                    <?php if ($row['perlu_restock']): ?>
                                        <span class="badge badge-danger">Restock Segera (Defisit <?php echo abs($row['selisih_kritis']); ?>)</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Aman (+<?php echo $row['selisih_kritis']; ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['pemasok']); ?></td>
                                <td class="angka">Rp <?php echo number_format($row['harga_jual'], 0, ',', '.'); ?></td>
                                <td class="angka">Rp <?php echo number_format($row['harga_beli'], 0, ',', '.'); ?></td>
                                <td class="angka <?php echo $row['margin'] > 0 ? 'sukses' : ''; ?>">
                                    Rp <?php echo number_format($row['margin'], 2, ',', '.'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Penjelasan Semantik -->
        <div class="card" style="background: #f8fafc;">
            <h3 style="font-size: 1.05rem; margin-bottom: 0.6rem; color: #0f172a;">Mengapa Satu Sumber Basis Data Tidak Cukup?</h3>
            <ul style="padding-left: 1.25rem; font-size: 0.9rem; color: #334155; line-height: 1.7;">
                <li><strong>Kasira</strong> hanya mencatat transaksi ritel (kuantitas terjual dan harga jual), tetapi buta terhadap stok fisik gudang, ambang stok minimum, maupun histori harga beli.</li>
                <li><strong>Stokara</strong> hanya mencatat log penerimaan gudang dan harga beli dari pemasok, tetapi tidak mengetahui volume barang yang telah terjual di mesin kasir setiap harinya.</li>
                <li><strong>Jembatan Semantik</strong> menyelaraskan entitas produk kedua sistem lewat <code>owl:sameAs</code> berbasis barcode EAN-13, menormalkan format tanggal dan harga, sehingga keputusan pengadaan dapat dihitung secara akurat tanpa manipulasi basis data asli.</li>
            </ul>
        </div>

        <div class="footer-note">
            Toko Lentera &bull; Arsitektur Jembatan Semantik Berbasis W3C RDF, OWL, dan SKOS
        </div>
    </div>
</body>
</html>
