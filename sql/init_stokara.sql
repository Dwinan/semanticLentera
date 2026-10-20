CREATE TABLE barang (
    kode_barcode VARCHAR(20) PRIMARY KEY,
    nama_barang VARCHAR(100) NOT NULL,
    kategori VARCHAR(50),
    stok_minimum INTEGER NOT NULL
);

CREATE TABLE pemasok (
    id VARCHAR(15) PRIMARY KEY,
    nama_pemasok VARCHAR(100) NOT NULL,
    kota VARCHAR(60)
);

CREATE TABLE penerimaan_barang (
    id VARCHAR(15) PRIMARY KEY,
    kode_barcode VARCHAR(20) NOT NULL REFERENCES barang(kode_barcode),
    id_pemasok VARCHAR(15) NOT NULL REFERENCES pemasok(id),
    tgl_terima DATE NOT NULL,
    jumlah INTEGER NOT NULL,
    harga_beli NUMERIC(12, 2) NOT NULL
);

-- 5 Pemasok (P05 memiliki kota = '-' sebagai contoh nilai kosong)
INSERT INTO pemasok (id, nama_pemasok, kota) VALUES
('P01', 'PT Tirta Segar Nusantara', 'Bandar Lampung'),
('P02', 'CV Pangan Makmur Jaya', 'Metro'),
('P03', 'UD Camilan Nusantara', 'Palembang'),
('P04', 'PT Bersih Kilau Mandiri', 'Jakarta'),
('P05', 'CV Kopi Bukit Barisan', '-');

-- 15 Barang di Stokara (12 beririsan dengan Kasira, 1 beda barcode kemasan 1L, 2 hanya ada di Stokara)
INSERT INTO barang (kode_barcode, nama_barang, kategori, stok_minimum) VALUES
('8991001000011', 'KOPI SUSU BOTOL 250 ML', 'Minuman', 30),
('8991001000028', 'TEH MELATI KOTAK 300 ML', 'Minuman Dingin', 40),
('8991001000035', 'AIR MINERAL LENTERA 600 ML', 'Minuman Dingin', 50),
('8991001000042', 'KOPI BUBUK ROBUSTA 200 GR', 'Minuman Seduh', 25),
('8991001000059', 'KERIPIK SINGKONG BALADO 150 GR', 'Makanan Ringan', 25),
('8991001000066', 'BISKUIT GANDUM COKELAT 120 GR', 'Makanan Ringan', 20),
('8991001000073', 'KACANG PANGGANG BAWANG 200 GR', 'Makanan Ringan', 20),
('8991001000080', 'BERAS RAMOS SUPER 5 KG', 'Bahan Pokok', 15),
('8991001000097', 'MINYAK GORENG SAWIT POUCH 1 L', 'Bahan Pokok', 30),
('8991001000103', 'GULA PASIR PUTIH 1 KG', 'Bahan Pokok', 25),
('8991001000110', 'SABUN CUCI PIRING JERUK 750 ML', 'Pembersih', 20),
('8991001000127', 'DETERJEN BUBUK FLORAL 800 GR', 'Pembersih', 20),
('8991001000936', 'SUSU UHT COKELAT', 'Minuman Dingin', 15),
('8991001000172', 'KARBOL PINUS LANTAI 800 ML', 'Pembersih', 15),
('8991001000189', 'TEH HIJAU CELUP ISI 25', '', 20);

-- 22 Penerimaan Barang (Format tanggal YYYY-MM-DD, harga_beli NUMERIC)
INSERT INTO penerimaan_barang (id, kode_barcode, id_pemasok, tgl_terima, jumlah, harga_beli) VALUES
('PB01', '8991001000011', 'P05', '2026-02-25', 20, 10200.00),
('PB02', '8991001000011', 'P05', '2026-03-10', 20, 10500.00),
('PB03', '8991001000028', 'P01', '2026-02-26', 25, 4300.00),
('PB04', '8991001000028', 'P01', '2026-03-11', 25, 4500.00),
('PB05', '8991001000035', 'P01', '2026-02-27', 40, 2400.00),
('PB06', '8991001000035', 'P01', '2026-03-12', 40, 2500.00),
('PB07', '8991001000042', 'P05', '2026-02-28', 15, 16500.00),
('PB08', '8991001000042', 'P05', '2026-03-12', 15, 17000.00),
('PB09', '8991001000059', 'P03', '2026-02-28', 20, 8200.00),
('PB10', '8991001000059', 'P03', '2026-03-13', 15, 8500.00),
('PB11', '8991001000066', 'P03', '2026-03-01', 35, 7000.00),
('PB12', '8991001000073', 'P03', '2026-03-02', 30, 13000.00),
('PB13', '8991001000080', 'P02', '2026-02-25', 12, 60000.00),
('PB14', '8991001000080', 'P02', '2026-03-14', 10, 61000.00),
('PB15', '8991001000097', 'P02', '2026-02-26', 20, 15800.00),
('PB16', '8991001000097', 'P02', '2026-03-14', 20, 16000.00),
('PB17', '8991001000103', 'P02', '2026-03-03', 40, 14500.00),
('PB18', '8991001000110', 'P04', '2026-03-04', 30, 11500.00),
('PB19', '8991001000127', 'P04', '2026-03-05', 25, 15500.00),
('PB20', '8991001000936', 'P01', '2026-03-06', 20, 15000.00),
('PB21', '8991001000172', 'P04', '2026-03-07', 20, 12000.00),
('PB22', '8991001000189', 'P05', '2026-03-08', 25, 9500.00);
