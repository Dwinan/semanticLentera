<?php
/**
 * Skrip PHP Generator RDF (ETL Relasional ke RDF Turtle)
 * Menerapkan 4 kaidah W3C RDB2RDF & 6 normalisasi ketidakcocokan
 */

require_once __DIR__ . '/../config/database.php';

$outputDir = __DIR__ . '/output';
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$petaKategori = [
    // Kasira (Kode singkat)
    "MNM" => "minuman-dingin",
    "SDH" => "minuman-seduh",
    "SNK" => "makanan-ringan",
    "PKK" => "bahan-pokok",
    "BRS" => "pembersih",
    // Stokara (Nama teks)
    "Minuman" => "minuman-dingin",
    "Minuman Dingin" => "minuman-dingin",
    "Minuman Seduh" => "minuman-seduh",
    "Makanan Ringan" => "makanan-ringan",
    "Bahan Pokok" => "bahan-pokok",
    "Pembersih" => "pembersih",
];

function apakahKosong($nilai) {
    if ($nilai === null) return true;
    $teks = trim(strval($nilai));
    return ($teks === "" || $teks === "-");
}

function normalkanTanggalKasira($tglTeks) {
    // DD/MM/YYYY -> YYYY-MM-DD
    $parts = explode('/', trim($tglTeks));
    if (count($parts) === 3) {
        return sprintf("%04d-%02d-%02d", $parts[2], $parts[1], $parts[0]);
    }
    return trim($tglTeks);
}

function normalkanHargaKasira($hargaTeks) {
    // "15.000" -> "15000.00"
    $bersih = str_replace('.', '', trim($hargaTeks));
    $bersih = str_replace(',', '.', $bersih);
    return number_format(floatval($bersih), 2, '.', '');
}

function jalankanExport() {
    global $petaKategori, $outputDir;

    $connKasira = Database::getKasiraConnection();
    $connStokara = Database::getStokaraConnection();

    if (!$connKasira || !$connStokara) {
        return [
            'status' => 'error',
            'message' => 'Gagal terhubung ke salah satu atau kedua basis data PostgreSQL (Kasira: 5433, Stokara: 5434).'
        ];
    }

    $baseIri = "https://data.lentera.example/id/";
    $ttlPrefixes = "@prefix dcterms: <http://purl.org/dc/terms/> .\n"
                 . "@prefix ex: <https://data.lentera.example/def#> .\n"
                 . "@prefix owl: <http://www.w3.org/2002/07/owl#> .\n"
                 . "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n"
                 . "@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n"
                 . "@prefix schema: <https://schema.org/> .\n"
                 . "@prefix skos: <http://www.w3.org/2004/02/skos/core#> .\n"
                 . "@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n\n";

    // 1. EKSTRAKSI KASIRA
    $ttlKasira = $ttlPrefixes;
    $barcodesKasira = [];

    // produk_jual
    $stmt = $connKasira->query("SELECT barcode, nama_produk, harga_jual, kode_kategori FROM produk_jual ORDER BY barcode ASC");
    while ($row = $stmt->fetch()) {
        $bc = trim($row['barcode']);
        $barcodesKasira[] = $bc;
        $subj = "<{$baseIri}kasira/produk/{$bc}>";

        $ttlKasira .= "{$subj} a ex:ProdukJual ;\n";
        $ttlKasira .= "    schema:gtin13 \"{$bc}\"^^xsd:string ;\n";
        if (!apakahKosong($row['nama_produk'])) {
            $nama = addcslashes(trim($row['nama_produk']), '"\\');
            $ttlKasira .= "    schema:name \"{$nama}\"@id ;\n";
        }
        if (!apakahKosong($row['harga_jual'])) {
            $hargaDec = normalkanHargaKasira($row['harga_jual']);
            $ttlKasira .= "    schema:price {$hargaDec} ;\n";
        }
        if (!apakahKosong($row['kode_kategori'])) {
            $slug = $petaKategori[trim($row['kode_kategori'])] ?? null;
            if ($slug) {
                $ttlKasira .= "    schema:category <{$baseIri}kategori/{$slug}> ;\n";
            }
        }
        // Ganti titik koma terakhir dengan titik
        $ttlKasira = rtrim($ttlKasira, " ;\n") . " .\n\n";
    }

    // transaksi
    $stmt = $connKasira->query("SELECT id, tanggal, nama_kasir FROM transaksi ORDER BY id ASC");
    while ($row = $stmt->fetch()) {
        $idTrx = trim($row['id']);
        $subj = "<{$baseIri}transaksi/{$idTrx}>";

        $ttlKasira .= "{$subj} a schema:Order ;\n";
        $ttlKasira .= "    dcterms:identifier \"{$idTrx}\"^^xsd:string ;\n";
        if (!apakahKosong($row['tanggal'])) {
            $tglIso = normalkanTanggalKasira($row['tanggal']);
            $ttlKasira .= "    dcterms:date \"{$tglIso}\"^^xsd:date ;\n";
        }
        if (!apakahKosong($row['nama_kasir'])) {
            $kasir = addcslashes(trim($row['nama_kasir']), '"\\');
            $ttlKasira .= "    dcterms:creator \"{$kasir}\"@id ;\n";
        }
        $ttlKasira = rtrim($ttlKasira, " ;\n") . " .\n\n";
    }

    // item_transaksi
    $stmt = $connKasira->query("SELECT id, id_transaksi, barcode, qty FROM item_transaksi ORDER BY id_transaksi ASC, id ASC");
    $trxCounter = [];
    while ($row = $stmt->fetch()) {
        $idTrx = trim($row['id_transaksi']);
        $bc = trim($row['barcode']);
        $trxCounter[$idTrx] = ($trxCounter[$idTrx] ?? 0) + 1;
        $noUrut = $trxCounter[$idTrx];

        $subj = "<{$baseIri}item/{$idTrx}-{$noUrut}>";
        $trxIri = "<{$baseIri}transaksi/{$idTrx}>";
        $prodIri = "<{$baseIri}kasira/produk/{$bc}>";

        $ttlKasira .= "{$subj} a schema:OrderItem ;\n";
        $ttlKasira .= "    dcterms:isPartOf {$trxIri} ;\n";
        $ttlKasira .= "    schema:orderedItem {$prodIri} ;\n";
        if (!apakahKosong($row['qty'])) {
            $qty = intval($row['qty']);
            $ttlKasira .= "    schema:orderQuantity {$qty} ;\n";
        }
        $ttlKasira = rtrim($ttlKasira, " ;\n") . " .\n\n";
    }

    // 2. EKSTRAKSI STOKARA
    $ttlStokara = $ttlPrefixes;
    $barcodesStokara = [];

    // barang
    $stmt = $connStokara->query("SELECT kode_barcode, nama_barang, kategori, stok_minimum FROM barang ORDER BY kode_barcode ASC");
    while ($row = $stmt->fetch()) {
        $bc = trim($row['kode_barcode']);
        $barcodesStokara[] = $bc;
        $subj = "<{$baseIri}stokara/barang/{$bc}>";

        $ttlStokara .= "{$subj} a ex:Barang ;\n";
        $ttlStokara .= "    dcterms:identifier \"{$bc}\"^^xsd:string ;\n";
        if (!apakahKosong($row['nama_barang'])) {
            $nama = addcslashes(trim($row['nama_barang']), '"\\');
            $ttlStokara .= "    rdfs:label \"{$nama}\"@id ;\n";
        }
        if (!apakahKosong($row['kategori'])) {
            $slug = $petaKategori[trim($row['kategori'])] ?? null;
            if ($slug) {
                $ttlStokara .= "    schema:category <{$baseIri}kategori/{$slug}> ;\n";
            }
        }
        if (!apakahKosong($row['stok_minimum'])) {
            $min = intval($row['stok_minimum']);
            $ttlStokara .= "    ex:stokMinimum {$min} ;\n";
        }
        $ttlStokara = rtrim($ttlStokara, " ;\n") . " .\n\n";
    }

    // pemasok
    $stmt = $connStokara->query("SELECT id, nama_pemasok, kota FROM pemasok ORDER BY id ASC");
    while ($row = $stmt->fetch()) {
        $idSup = trim($row['id']);
        $subj = "<{$baseIri}pemasok/{$idSup}>";

        $ttlStokara .= "{$subj} a schema:Organization ;\n";
        $ttlStokara .= "    dcterms:identifier \"{$idSup}\"^^xsd:string ;\n";
        if (!apakahKosong($row['nama_pemasok'])) {
            $nama = addcslashes(trim($row['nama_pemasok']), '"\\');
            $ttlStokara .= "    schema:name \"{$nama}\"@id ;\n";
        }
        if (!apakahKosong($row['kota'])) {
            $kota = addcslashes(trim($row['kota']), '"\\');
            $ttlStokara .= "    schema:addressLocality \"{$kota}\"@id ;\n";
        }
        $ttlStokara = rtrim($ttlStokara, " ;\n") . " .\n\n";
    }

    // penerimaan_barang
    $stmt = $connStokara->query("SELECT id, kode_barcode, id_pemasok, tgl_terima, jumlah, harga_beli FROM penerimaan_barang ORDER BY id ASC");
    while ($row = $stmt->fetch()) {
        $idPb = trim($row['id']);
        $bc = trim($row['kode_barcode']);
        $idSup = trim($row['id_pemasok']);

        $subj = "<{$baseIri}penerimaan/{$idPb}>";
        $barangIri = "<{$baseIri}stokara/barang/{$bc}>";
        $supIri = "<{$baseIri}pemasok/{$idSup}>";

        $ttlStokara .= "{$subj} a ex:PenerimaanBarang ;\n";
        $ttlStokara .= "    schema:orderedItem {$barangIri} ;\n";
        $ttlStokara .= "    ex:dipasokOleh {$supIri} ;\n";
        if (!apakahKosong($row['tgl_terima'])) {
            $tgl = trim($row['tgl_terima']);
            $ttlStokara .= "    dcterms:date \"{$tgl}\"^^xsd:date ;\n";
        }
        if (!apakahKosong($row['jumlah'])) {
            $jml = intval($row['jumlah']);
            $ttlStokara .= "    rdf:value {$jml} ;\n";
        }
        if (!apakahKosong($row['harga_beli'])) {
            $hb = number_format(floatval($row['harga_beli']), 2, '.', '');
            $ttlStokara .= "    ex:hargaBeli {$hb} ;\n";
        }
        $ttlStokara = rtrim($ttlStokara, " ;\n") . " .\n\n";
    }

    // 3. PENYELARASAN IDENTITAS (owl:sameAs pada irisan barcode)
    $ttlPenyelarasan = "@prefix owl: <http://www.w3.org/2002/07/owl#> .\n\n";
    $irisan = array_values(array_intersect($barcodesKasira, $barcodesStokara));
    sort($irisan);
    foreach ($irisan as $bc) {
        $iriKasira = "<{$baseIri}kasira/produk/{$bc}>";
        $iriStokara = "<{$baseIri}stokara/barang/{$bc}>";
        $ttlPenyelarasan .= "{$iriKasira} owl:sameAs {$iriStokara} .\n";
    }

    // Simpan ketiga berkas
    file_put_contents("{$outputDir}/kasira.ttl", $ttlKasira);
    file_put_contents("{$outputDir}/stokara.ttl", $ttlStokara);
    file_put_contents("{$outputDir}/penyelarasan.ttl", $ttlPenyelarasan);

    return [
        'status' => 'success',
        'kasira_triples' => count($barcodesKasira),
        'stokara_triples' => count($barcodesStokara),
        'aligned_count' => count($irisan),
        'files' => [
            'kasira.ttl' => "{$outputDir}/kasira.ttl",
            'stokara.ttl' => "{$outputDir}/stokara.ttl",
            'penyelarasan.ttl' => "{$outputDir}/penyelarasan.ttl"
        ]
    ];
}

// Eksekusi jika dipanggil langsung via browser atau AJAX
if (isset($_GET['run']) || (isset($_POST['action']) && $_POST['action'] === 'export')) {
    header('Content-Type: application/json');
    echo json_encode(jalankanExport());
    exit;
}
