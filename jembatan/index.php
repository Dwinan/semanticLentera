<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/export_rdf.php';

$dbStatus = Database::checkStatus();
$exportResult = null;
$errorMsg = '';

// Jika tombol sinkronisasi diklik
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync') {
    $exportResult = jalankanExport();
}

$outputDir = __DIR__ . '/output';
$kasiraTtlExists = file_exists("{$outputDir}/kasira.ttl");
$stokaraTtlExists = file_exists("{$outputDir}/stokara.ttl");
$penyelarasanTtlExists = file_exists("{$outputDir}/penyelarasan.ttl");

// Tab preview berkas RDF
$viewFile = $_GET['view'] ?? 'ontologi';
$contentPreview = '';
$previewTitle = '';

switch ($viewFile) {
    case 'kasira':
        $previewTitle = 'kasira.ttl (Hasil Ekstraksi Kasira)';
        $contentPreview = $kasiraTtlExists ? file_get_contents("{$outputDir}/kasira.ttl") : 'Belum diekspor. Klik tombol di atas.';
        break;
    case 'stokara':
        $previewTitle = 'stokara.ttl (Hasil Ekstraksi Stokara)';
        $contentPreview = $stokaraTtlExists ? file_get_contents("{$outputDir}/stokara.ttl") : 'Belum diekspor. Klik tombol di atas.';
        break;
    case 'penyelarasan':
        $previewTitle = 'penyelarasan.ttl (Hasil owl:sameAs Berbasis Barcode)';
        $contentPreview = $penyelarasanTtlExists ? file_get_contents("{$outputDir}/penyelarasan.ttl") : 'Belum diekspor. Klik tombol di atas.';
        break;
    case 'skos':
        $previewTitle = 'kategori_skos.ttl (Skema Konsep Kategori SKOS)';
        $contentPreview = file_get_contents(__DIR__ . '/kategori_skos.ttl');
        break;
    case 'ontologi':
    default:
        $previewTitle = 'ontologi.ttl (Ontologi OWL & Aksioma Jembatan)';
        $contentPreview = file_get_contents(__DIR__ . '/ontologi.ttl');
        break;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jembatan Semantik - Toko Lentera</title>
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
            <a href="index.php" class="active">Jembatan Semantik</a>
            <a href="restock.php">Daftar Restock</a>
            <a href="sparql_runner.php">Kueri SPARQL</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <span class="badge badge-bridge" style="font-size: 0.85rem; margin-bottom: 0.5rem;">Semantic Web Bridge</span>
            <h1 class="page-title">Pusat Kendali Jembatan Semantik Toko Lentera</h1>
            <p class="page-desc">Integrasi heterogenitas data Kasira dan Stokara menjadi graf pengetahuan berstandar W3C (RDF, SKOS, OWL, SPARQL).</p>
        </div>

        <?php if ($exportResult): ?>
            <?php if ($exportResult['status'] === 'success'): ?>
                <div class="alert alert-success" data-auto-dismiss>
                    <strong>Sukses Sinkronisasi RDF!</strong> Berhasil menghasilkan berkas <code>kasira.ttl</code>, <code>stokara.ttl</code>, dan <code>penyelarasan.ttl</code> (menyelaraskan <?php echo $exportResult['aligned_count']; ?> produk ber-barcode sama).
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <strong>Gagal Ekspor:</strong> <?php echo htmlspecialchars($exportResult['message']); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Kartu Status & Aksi -->
        <div class="grid grid-3" style="margin-bottom: 2rem;">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Status Sumber Data</h2>
                </div>
                <div style="font-size: 0.9rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Kasira (Port 5433):</span>
                        <?php if ($dbStatus['kasira']): ?>
                            <span class="badge badge-success">Terhubung</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Tidak Aktif</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Stokara (Port 5434):</span>
                        <?php if ($dbStatus['stokara']): ?>
                            <span class="badge badge-success">Terhubung</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Tidak Aktif</span>
                        <?php endif; ?>
                    </div>
                </div>
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="sync">
                    <button type="submit" class="btn btn-bridge" style="width: 100%; justify-content: center;"
                            <?php echo (!$dbStatus['kasira'] || !$dbStatus['stokara']) ? 'disabled' : ''; ?>>
                        Sinkronkan & Generate RDF (.ttl)
                    </button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Aplikasi Restock</h2>
                </div>
                <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 1.25rem;">
                    Lihat hasil evaluasi keputusan bisnis: produk kritis di bawah stok minimum dan margin per unit tertinggi.
                </p>
                <a href="restock.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Buka Dashboard Restock &rarr;
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Kueri SPARQL W3C</h2>
                </div>
                <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 1.25rem;">
                    Eksplorasi 5 kueri SPARQL standar (Agregasi, Hierarki SKOS, CONSTRUCT, ASK, SERVICE Wikidata) + komparasi UNION.
                </p>
                <a href="sparql_runner.php" class="btn btn-outline" style="width: 100%; justify-content: center;">
                    Lihat Koleksi SPARQL &rarr;
                </a>
            </div>
        </div>

        <!-- Preview Berkas Semantik -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?php echo htmlspecialchars($previewTitle); ?></h2>
                    <small style="color: #64748b;">Pilih tab di bawah untuk memeriksa sintaks RDF Turtle</small>
                </div>
                <span class="badge badge-bridge">Turtle Format (.ttl)</span>
            </div>

            <div class="tabs">
                <a href="?view=ontologi" class="tab-link <?php echo $viewFile === 'ontologi' ? 'active' : ''; ?>">ontologi.ttl (OWL)</a>
                <a href="?view=skos" class="tab-link <?php echo $viewFile === 'skos' ? 'active' : ''; ?>">kategori_skos.ttl (SKOS)</a>
                <a href="?view=penyelarasan" class="tab-link <?php echo $viewFile === 'penyelarasan' ? 'active' : ''; ?>">penyelarasan.ttl (sameAs)</a>
                <a href="?view=kasira" class="tab-link <?php echo $viewFile === 'kasira' ? 'active' : ''; ?>">kasira.ttl</a>
                <a href="?view=stokara" class="tab-link <?php echo $viewFile === 'stokara' ? 'active' : ''; ?>">stokara.ttl</a>
            </div>

            <pre><code><?php echo htmlspecialchars($contentPreview); ?></code></pre>
        </div>

        <div class="footer-note">
            Toko Lentera &bull; Arsitektur Jembatan Semantik Berbasis W3C RDF, OWL, dan SKOS
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
