<?php
$queries = [
    'q1' => [
        'title' => 'Kueri 1: Agregasi Lintas-Sumber (GROUP BY + HAVING)',
        'file' => 'q1_restock_agregasi.rq',
        'desc' => 'Menemukan produk yang sisa stoknya (penerimaan di Stokara - penjualan di Kasira) berada di bawah ambang batas minimum.',
        'target' => 'Menjawab Pertanyaan Bisnis 1 (Ketersediaan Stok). Menghasilkan 7 produk kritis.'
    ],
    'q2' => [
        'title' => 'Kueri 2: Lintasan Properti Taksonomi (skos:broader+)',
        'file' => 'q2_kategori_hierarki.rq',
        'desc' => 'Mengambil seluruh produk di bawah kategori "Makanan" beserta seluruh subkategorinya secara rekursif.',
        'target' => 'Mendukung kedalaman hierarki kategori dinamis tanpa mengubah kueri.'
    ],
    'q3' => [
        'title' => 'Kueri 3: Pembentukan Graf Baru (CONSTRUCT ex:marginPerUnit)',
        'file' => 'q3_construct_margin.rq',
        'desc' => 'Membentuk graf RDF baru yang menyematkan margin keuntungan per unit ke setiap produk.',
        'target' => 'Menjawab Pertanyaan Bisnis 2 (Margin Tertinggi). Menghasilkan 12 triple margin.'
    ],
    'q4' => [
        'title' => 'Kueri 4: Pemeriksa Boolean (ASK Guard)',
        'file' => 'q4_ask_stok_kritis.rq',
        'desc' => 'Memeriksa secara boolean apakah produk tertentu saat ini berada di bawah stok minimum.',
        'target' => 'Berfungsi sebagai guard validasi di aplikasi. Mengembalikan true.'
    ],
    'q5' => [
        'title' => 'Kueri 5: Kueri Terfederasi Luar (SERVICE Wikidata)',
        'file' => 'q5_wikidata_service.rq',
        'desc' => 'Memanggil endpoint Wikidata untuk memperkaya label bahasa Inggris dari kategori produk.',
        'target' => 'Mendemonstrasikan keterhubungan data terbuka (Linked Open Data W3C).'
    ],
    'q6' => [
        'title' => 'Kueri 6: Komparasi UNION vs Eliminasi OWL',
        'file' => 'q6_union_vs_owl.rq',
        'desc' => 'Memperlihatkan efisiensi ontologi OWL yang meniadakan kebutuhan blok UNION yang repetitif.',
        'target' => 'Membuktikan reduksi kompleksitas kueri SPARQL melalui pemodelan semantik.'
    ]
];

$activeKey = $_GET['q'] ?? 'q1';
if (!isset($queries[$activeKey])) $activeKey = 'q1';
$activeQ = $queries[$activeKey];
$code = file_get_contents(__DIR__ . '/sparql/' . $activeQ['file']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kueri SPARQL &bull; Lentera Data Hub</title>
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
            <a href="restock.php" class="nav-link">Evaluasi Restock</a>
            <a href="sparql_runner.php" class="nav-link active">Kueri SPARQL</a>
        </div>
    </nav>

    <main class="container">
        <header class="hero-header">
            <h1 class="hero-title">Koleksi Kueri SPARQL</h1>
            <p class="hero-desc">Spesifikasi kueri W3C SPARQL 1.1 untuk evaluasi graf data terdistribusi.</p>
        </header>

        <!-- Clean Tabs Navigation -->
        <div class="filter-tabs">
            <?php foreach ($queries as $k => $q): ?>
                <a href="?q=<?php echo $k; ?>" class="filter-tab <?php echo $k === $activeKey ? 'active' : ''; ?>">
                    <?php echo strtoupper($k); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <section class="card" style="margin-bottom: 2rem;">
            <div>
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?php echo htmlspecialchars($activeQ['title']); ?></h2>
                        <span class="text-muted" style="font-size: 0.8rem;">
                            Berkas: <code>jembatan/sparql/<?php echo $activeQ['file']; ?></code>
                        </span>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <p style="margin-bottom: 0.5rem;">
                        <strong>Deskripsi:</strong> <?php echo htmlspecialchars($activeQ['desc']); ?>
                    </p>
                    <p style="font-size: 0.85rem; color: var(--ink-secondary);">
                        <strong>Target:</strong> <?php echo htmlspecialchars($activeQ['target']); ?>
                    </p>
                </div>

                <div class="code-frame">
                    <div class="code-header">
                        <span><?php echo htmlspecialchars($activeQ['file']); ?></span>
                        <span>SPARQL 1.1</span>
                    </div>
                    <div class="code-body">
                        <pre><code><?php echo htmlspecialchars($code); ?></code></pre>
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
