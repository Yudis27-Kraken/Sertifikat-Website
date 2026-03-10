<?php
/**
 * config/database.php
 *
 * Konfigurasi koneksi PDO ke MySQL menggunakan Singleton Pattern.
 * Mendefinisikan Interface KoneksiDatabase dan Class Database.
 *
 * @package    InvV5\Config
 * @version    1.0
 * @since      2025
 */

// ── Konfigurasi server ─────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'inv_v5');          // database baru, tidak nabrak
define('DB_USER',    'root');
define('DB_PASS',    '');                // default XAMPP/Laragon: kosong
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// INTERFACE — kriteria (h): interface
// ============================================================

/**
 * Interface KoneksiDatabase
 *
 * Mendefinisikan kontrak operasi database.
 * Wajib diimplementasikan oleh class koneksi manapun.
 *
 * @package InvV5\Config
 */
interface KoneksiDatabase
{
    /**
     * Jalankan query dan kembalikan PDOStatement.
     *
     * @param  string $sql    Query SQL
     * @param  array  $params Parameter binding
     * @return PDOStatement
     */
    public function jalankan(string $sql, array $params = []): PDOStatement;

    /**
     * Ambil semua baris hasil SELECT.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array
     */
    public function ambilSemua(string $sql, array $params = []): array;

    /**
     * Ambil satu baris hasil SELECT.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array|false
     */
    public function ambilSatu(string $sql, array $params = []): array|false;

    /**
     * Dapatkan ID terakhir yang di-insert.
     *
     * @return string
     */
    public function idTerakhir(): string;
}

// ============================================================
// CLASS Database — Singleton + implementasi KoneksiDatabase
// ============================================================

/**
 * Class Database
 *
 * Singleton PDO wrapper. Hanya ada satu instance koneksi
 * selama runtime, menghemat resource database.
 *
 * Menerapkan:
 * - Singleton Pattern (private constructor + static instance)
 * - Encapsulation (property private)
 * - Interface implementation (KoneksiDatabase)
 *
 * @package InvV5\Config
 */
class Database implements KoneksiDatabase
{
    /** @var Database|null Instance tunggal (Singleton) */
    private static ?Database $instance = null;

    /** @var PDO Koneksi PDO — private (encapsulation) */
    private PDO $pdo;

    /**
     * Constructor private — cegah pembuatan instance dari luar.
     *
     * @throws PDOException Jika koneksi ke MySQL gagal
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Ambil atau buat instance Database (Singleton Pattern).
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function jalankan(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function ambilSemua(string $sql, array $params = []): array
    {
        return $this->jalankan($sql, $params)->fetchAll();
    }

    /**
     * {@inheritdoc}
     */
    public function ambilSatu(string $sql, array $params = []): array|false
    {
        return $this->jalankan($sql, $params)->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function idTerakhir(): string
    {
        return $this->pdo->lastInsertId();
    }

    /** Cegah clone instance */
    private function __clone() {}
}
