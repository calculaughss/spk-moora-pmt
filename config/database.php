<?php
/**
 * config/database.php
 * -----------------------------------------------------------
 * Koneksi database menggunakan PDO + Prepared Statement.
 * Sesuaikan DB_HOST, DB_NAME, DB_USER, DB_PASS dengan
 * konfigurasi XAMPP/Laragon Anda (default XAMPP: user root, pass kosong).
 * -----------------------------------------------------------
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'pmt2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Base URL aplikasi - sesuaikan dengan folder project Anda di htdocs/www
define('BASE_URL', '/pmt-v2/');

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Jangan tampilkan detail koneksi mentah ke user di production
            die('Koneksi database gagal: ' . $e->getMessage());
        }
    }

    return $pdo;
}
