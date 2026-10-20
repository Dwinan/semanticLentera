<?php
/**
 * Template Konfigurasi Basis Data PostgreSQL Toko Lentera
 * Salin (copy) berkas ini menjadi `config/database.php` lalu sesuaikan kredensial Anda.
 */

class Database {
    private static $kasiraHost = '127.0.0.1';
    private static $kasiraPort = '5432';
    private static $kasiraDb   = 'kasira_db';
    private static $kasiraUser = 'postgres';
    private static $kasiraPass = 'password_anda';

    private static $stokaraHost = '127.0.0.1';
    private static $stokaraPort = '5432';
    private static $stokaraDb   = 'stokara_db';
    private static $stokaraUser = 'postgres';
    private static $stokaraPass = 'password_anda';

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
