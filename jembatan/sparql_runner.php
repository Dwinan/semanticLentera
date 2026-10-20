<?php
$queries = [
    'q1' => [
        'title' => 'Kueri 1: Agregasi Lintas-Sumber (GROUP BY + HAVING)',
        'file' => 'q1_restock_agregasi.rq',
        'desc' => 'Menemukan produk yang stok perkiraannya (total penerimaan di Stokara - total penjualan di Kasira) berada di bawah ambang stok minimum.',
        'target' => 'Menjawab Pertanyaan Bisnis 1 (Ketersediaan Stok). Menghasilkan 7 produk kritis.'
    ],
    'q2' => [
        'title' => 'Kueri 2: Lintasan Properti Taksonomi (skos:broader+)',
        'file' => 'q2_kategori_hierarki.rq',
        'desc' => 'Mengambil seluruh produk di bawah kategori "Makanan" beserta seluruh subkategorinya secara rekursif/transitif.',
        'target' => 'Mendukung kedalaman hierarki kategori dinamis tanpa perlu mengubah struktur kueri.'
    ],
    'q3' => [
        'title' => 'Kueri 3: Pembentukan Graf Baru (CONSTRUCT ex:marginPerUnit)',
        'file' => 'q3_construct_margin.rq',
        'desc' => 'Membentuk graf RDF Turtle baru yang menyematkan properti margin per unit ke setiap produk terintegrasi.',
        'target' => 'Menjawab Pertanyaan Bisnis 2 (Margin Tertinggi). Menghasilkan 12 triple margin.'
    ],
    'q4' => [
        'title' => 'Kueri 4: Pemeriksa Boolean (ASK Guard)',
        'file' => 'q4_ask_stok_kritis.rq',
        'desc' => 'Memeriksa secara boolean apakah produk tertentu (Kopi Susu 250ml) saat ini berada di bawah stok minimum.',
        'target' => 'Berfungsi sebagai security/validation guard di aplikasi. Mengembalikan nilai boolean true.'
    ],
    'q5' => [
        'title' => 'Kueri 5: Kueri Terfederasi Luar (SERVICE Wikidata)',
        'file' => 'q5_wikidata_service.rq',
        'desc' => 'Memanggil SPARQL endpoint Wikidata resmi untuk memperkaya label bahasa Inggris dari kategori produk lokal.',
        'target' => 'Mendemonstrasikan keterhubungan data terbuka (Linked Open Data W3C).'
    ],
    'q6' => [
        'title' => 'Kueri 6: Komparasi UNION vs Eliminasi OWL',
        'file' => 'q6_union_vs_owl.rq',
        'desc' => 'Memperlihatkan bahwa setelah aksioma ontologi diterapkan, blok UNION yang rumit tidak diperlukan lagi.',
        'target' => 'Membuktikan nilai efisiensi pemodelan OWL dalam menyederhanakan kueri SPARQL.'
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
    <title>Kueri SPARQL - Jembatan Semantik Toko Lentera</title>
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
            <a href="restock.php">Daftar Restock</a>
            <a href="sparql_runner.php" class="active">Kueri SPARQL</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <span class="badge badge-bridge" style="font-size: 0.85rem; margin-bottom: 0.5rem;">SPARQL 1.1 Competency Queries</span>
            <h1 class="page-title">Repositori Kueri SPARQL Toko Lentera</h1>
            <p class="page-desc">Kumpulan kueri SPARQL standar W3C untuk mengeksplorasi graf pengetahuan gabungan Kasira & Stokara.</p>
        </div>

        <div class="tabs">
            <?php foreach ($queries as $k => $q): ?>
                <a href="?q=<?php echo $k; ?>" class="tab-link <?php echo $k === $activeKey ? 'active' : ''; ?>">
                    <?php echo strtoupper($k); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?php echo htmlspecialchars($activeQ['title']); ?></h2>
                    <p style="font-size: 0.88rem; color: #64748b; margin-top: 0.25rem;">
                        Berkas: <code>jembatan/sparql/<?php echo $activeQ['file']; ?></code>
                    </p>
                </div>
                <span class="badge badge-bridge">W3C SPARQL 1.1</span>
            </div>

            <div style="background: #f1f5f9; padding: 1rem; border-radius: var(--radius); margin-bottom: 1.25rem;">
                <p style="font-size: 0.92rem; color: #1e293b; margin-bottom: 0.4rem;">
                    <strong>Deskripsi & Tujuan:</strong> <?php echo htmlspecialchars($activeQ['desc']); ?>
                </p>
                <p style="font-size: 0.88rem; color: #0f766e;">
                    <strong>Target Kompetensi:</strong> <?php echo htmlspecialchars($activeQ['target']); ?>
                </p>
            </div>

            <label style="font-size: 0.85rem; font-weight: 700; color: #334155; margin-bottom: 0.4rem; display: block;">
                Kode Kueri SPARQL:
            </label>
            <pre><code><?php echo htmlspecialchars($code); ?></code></pre>
        </div>

        <div class="footer-note">
            Toko Lentera &bull; Arsitektur Jembatan Semantik Berbasis W3C RDF, OWL, dan SKOS
        </div>
    </div>
</body>
</html>
