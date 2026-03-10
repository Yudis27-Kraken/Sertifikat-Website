<?php
/**
 * controllers/AuthController.php
 *
 * Mengelola autentikasi: login, logout, cek sesi.
 * Hanya satu akun admin yang diizinkan login.
 *
 * @package InvV5\Controllers
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Class AuthController
 *
 * Proses login/logout dan manajemen session PHP.
 *
 * @package InvV5\Controllers
 */
class AuthController
{
    /** @var Database Instance database */
    private Database $db;

    /**
     * Constructor — mulai session jika belum aktif.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();

        // Mulai session jika belum aktif — if/else (kriteria d)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Proses login: verifikasi username + password.
     *
     * @param  string $username
     * @param  string $password Password plain text
     * @return array            [bool $ok, string $pesan, User|null $user]
     */
    public function login(string $username, string $password): array
    {
        // Validasi input kosong — if/else (kriteria d)
        if (empty(trim($username)) || empty($password)) {
            return [false, 'Username dan password wajib diisi.', null];
        }

        // Cari user aktif
        $row = $this->db->ambilSatu(
            "SELECT * FROM users WHERE username = ? AND aktif = 1",
            [trim($username)]
        );

        // if/else — user tidak ditemukan
        if (!$row) {
            return [false, 'Username tidak ditemukan.', null];
        }

        // Verifikasi bcrypt password
        if (!password_verify($password, $row['password'])) {
            return [false, 'Password salah.', null];
        }

        // Buat objek User
        $user = new User((int)$row['id'], $row['nama'], $row['username']);

        // Simpan ke session
        $_SESSION['uid']      = $user->getId();
        $_SESSION['unama']    = $user->getNama();
        $_SESSION['uname']    = $user->getUsername();
        $_SESSION['loggedin'] = true;

        // Catat log aktivitas
        $this->catatLog($user->getUsername(), 'LOGIN', 'Login berhasil');

        return [true, 'Selamat datang, ' . $user->getNama() . '!', $user];
    }

    /**
     * Proses logout: hapus sesi.
     */
    public function logout(): void
    {
        // Catat log sebelum hapus sesi
        if (!empty($_SESSION['uname'])) {
            $this->catatLog($_SESSION['uname'], 'LOGOUT', '');
        }
        session_unset();
        session_destroy();
    }

    /**
     * Cek apakah ada sesi login aktif.
     *
     * @return bool
     */
    public function sudahLogin(): bool
    {
        return !empty($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
    }

    /**
     * Ambil data user dari sesi aktif.
     *
     * @return array|null Null jika belum login
     */
    public function userAktif(): ?array
    {
        if (!$this->sudahLogin()) return null;
        return [
            'id'       => $_SESSION['uid']   ?? 0,
            'nama'     => $_SESSION['unama'] ?? '',
            'username' => $_SESSION['uname'] ?? '',
        ];
    }

    /**
     * Catat aktivitas ke tabel log_aktivitas.
     *
     * @param string $username Username yang beraksi
     * @param string $aksi     Jenis aksi (LOGIN/LOGOUT/TAMBAH/dll.)
     * @param string $ket      Keterangan tambahan
     */
    public function catatLog(string $username, string $aksi, string $ket): void
    {
        try {
            $this->db->jalankan(
                "INSERT INTO log_aktivitas (username, aksi, keterangan) VALUES (?, ?, ?)",
                [$username, $aksi, $ket]
            );
        } catch (Exception $e) {
            // Log gagal tidak boleh menghentikan proses utama
        }
    }
}
