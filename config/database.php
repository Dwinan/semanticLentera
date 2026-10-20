<?php
/**
 * Konfigurasi Koneksi Basis Data PostgreSQL
 * Sistem A: Kasira (Port 5433)
 * Sistem B: Stokara (Port 5434)
 */

class Database {
    private static $kasiraHost = '127.0.0.1';
    private static $kasiraPort = '5433';
    private static $kasiraDb   = 'kasira_db';
    private static $kasiraUser = 'lentera';
    private static $kasiraPass = 'lentera123';

    private static $stokaraHost = '127.0.0.1';
    private static $stokaraPort = '5434';
    private static $stokaraDb   = 'stokara_db';
    private static $stokaraUser = 'lentera';
    private static $stokaraPass = 'lentera123';

    public static function getKasiraConnection() {
        try {
            $dsn = "pgsql:host=" . self::$kasiraHost . ";port=" . self::$kasiraPort . ";dbname=" . self::$kasiraDb;
            $pdo = new PDO($dsn, self::$kasiraUser, self::$kasiraPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 3
            ]);
            return $pdo;
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function getStokaraConnection() {
        try {
            $dsn = "pgsql:host=" . self::$stokaraHost . ";port=" . self::$stokaraPort . ";dbname=" . self::$stokaraDb;
            $pdo = new PDO($dsn, self::$stokaraUser, self::$stokaraPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 3
            ]);
            return $pdo;
        } catch (PDOException $e) {
            return null;
        }
    }

    public static function checkStatus() {
        return [
            'kasira' => (self::getKasiraConnection() !== null),
            'stokara' => (self::getStokaraConnection() !== null)
        ];
    }
}
