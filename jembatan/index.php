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
        $previewTitle = 'kasira.ttl (Ekstraksi Kasira)';
        $contentPreview = $kasiraTtlExists ? file_get_contents("{$outputDir}/kasira.ttl") : 'Belum diekspor.';
        break;
    case 'stokara':
        $previewTitle = 'stokara.ttl (Ekstraksi Stokara)';
        $contentPreview = $stokaraTtlExists ? file_get_contents("{$outputDir}/stokara.ttl") : 'Belum diekspor.';
        break;
    case 'penyelarasan':
        $previewTitle = 'penyelarasan.ttl (Penyelarasan sameAs)';
        $contentPreview = $penyelarasanTtlExists ? file_get_contents("{$outputDir}/penyelarasan.ttl") : 'Belum diekspor.';
        break;
    case 'skos':
        $previewTitle = 'kategori_skos.ttl (Taksonomi SKOS)';
        $contentPreview = file_get_contents(__DIR__ . '/kategori_skos.ttl');
        break;
    case 'ontologi':
    default:
        $previewTitle = 'ontologi.ttl (Ontologi OWL)';
        $contentPreview = file_get_contents(__DIR__ . '/ontologi.ttl');
        break;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Kendali &bull; Lentera Data Hub</title>
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
            <a href="index.php" class="nav-link active">Pusat Kendali</a>
            <a href="restock.php" class="nav-link">Evaluasi Restock</a>
            <a href="sparql_runner.php" class="nav-link">Kueri SPARQL</a>
        </div>
    </nav>

    <main class="container">
        <header class="hero-header">
            <h1 class="hero-title">Pusat Kendali Semantik</h1>
            <p class="hero-desc">Generasi graf pengetahuan W3C dari data relasional Kasira dan Stokara.</p>
        </header>

        <?php if ($exportResult): ?>
            <?php if ($exportResult['status'] === 'success'): ?>
                <div class="callout" data-auto-dismiss>
                    Sinkronisasi berhasil. <?php echo $exportResult['aligned_count']; ?> entitas produk diselaraskan ke dalam graf RDF.
                </div>
            <?php else: ?>
                <div class="callout callout-critical">
                    Sinkronisasi gagal: <?php echo htmlspecialchars($exportResult['message']); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Panel Status & Aksi Utama: 3 Card Containers -->
        <div class="grid grid-cols-3" style="margin-bottom: 2rem;">
            <!-- Card 1: Pipeline Status & CTA Sinkronisasi -->
            <section class="card">
                <div>
                    <div class="card-header">
                        <h2 class="card-title">Status Basis Data</h2>
                    </div>
                    <div style="font-size: 0.88rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--line-subtle);">
                            <span>Kasira (5433)</span>
                            <span class="<?php echo $dbStatus['kasira'] ? '' : 'tag-critical'; ?>">
                                <?php echo $dbStatus['kasira'] ? 'Terhubung' : 'Terputus'; ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                            <span>Stokara (5434)</span>
                            <span class="<?php echo $dbStatus['stokara'] ? '' : 'tag-critical'; ?>">
                                <?php echo $dbStatus['stokara'] ? 'Terhubung' : 'Terputus'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="sync">
                    <button type="submit" class="btn btn-primary" style="width: 100%;"
                            <?php echo (!$dbStatus['kasira'] || !$dbStatus['stokara']) ? 'disabled' : ''; ?>>
                        Sinkronkan RDF (.ttl)
                    </button>
                </form>
            </section>

            <!-- Card 2: Tautan Evaluasi Restock -->
            <section class="card">
                <div>
                    <div class="card-header">
                        <h2 class="card-title">Keputusan Stok</h2>
                    </div>
                    <p style="margin-bottom: 1.5rem;">Evaluasi produk di bawah ambang batas minimum dan peringkat margin keuntungan unit.</p>
                </div>
                <a href="restock.php" class="btn btn-secondary" style="width: 100%;">
                    Buka Evaluasi Restock &rarr;
                </a>
            </section>

            <!-- Card 3: Tautan Koleksi SPARQL -->
            <section class="card">
                <div>
                    <div class="card-header">
                        <h2 class="card-title">Kueri SPARQL</h2>
                    </div>
                    <p style="margin-bottom: 1.5rem;">Koleksi kueri semantik mencakup agregasi, inferensi SKOS rekursif, dan federasi Wikidata.</p>
                </div>
                <a href="sparql_runner.php" class="btn btn-secondary" style="width: 100%;">
                    Buka Koleksi SPARQL &rarr;
                </a>
            </section>
        </div>

        <!-- Berkas Graf Turtle: Grounded Card Container -->
        <section class="card">
            <div>
                <div class="card-header">
                    <h2 class="card-title"><?php echo htmlspecialchars($previewTitle); ?></h2>
                    <span class="text-muted" style="font-size: 0.8rem;">Format Turtle (.ttl)</span>
                </div>

                <div class="filter-tabs">
                    <a href="?view=ontologi" class="filter-tab <?php echo $viewFile === 'ontologi' ? 'active' : ''; ?>">ontologi.ttl</a>
                    <a href="?view=skos" class="filter-tab <?php echo $viewFile === 'skos' ? 'active' : ''; ?>">kategori_skos.ttl</a>
                    <a href="?view=penyelarasan" class="filter-tab <?php echo $viewFile === 'penyelarasan' ? 'active' : ''; ?>">penyelarasan.ttl</a>
                    <a href="?view=kasira" class="filter-tab <?php echo $viewFile === 'kasira' ? 'active' : ''; ?>">kasira.ttl</a>
                    <a href="?view=stokara" class="filter-tab <?php echo $viewFile === 'stokara' ? 'active' : ''; ?>">stokara.ttl</a>
                </div>

                <div class="code-frame">
                    <div class="code-header">
                        <span><?php echo htmlspecialchars($viewFile . '.ttl'); ?></span>
                        <span>W3C Turtle</span>
                    </div>
                    <div class="code-body">
                        <pre><code><?php echo htmlspecialchars($contentPreview); ?></code></pre>
                    </div>
                </div>
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
