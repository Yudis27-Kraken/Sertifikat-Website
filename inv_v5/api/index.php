<?php
/**
 * api/index.php
 *
 * REST API Router — titik masuk semua request AJAX dari frontend.
 *
 * Routes (GET/POST ?action=...):
 *   POST  login          Proses login
 *   POST  logout         Proses logout
 *   GET   me             Data user aktif
 *   GET   list           Daftar barang (filter: cari, kondisi, id_kategori)
 *   GET   get&id=N       Detail satu barang
 *   GET   kategori       Semua kategori
 *   GET   stats          Statistik ringkas
 *   POST  create         Tambah barang baru
 *   POST  update&id=N    Edit barang
 *   POST  delete&id=N    Hapus barang
 *
 * Format response: { sukses, data, pesan, timestamp }
 *
 * @package InvV5\API
 * @version 1.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/BarangController.php';

/**
 * Kirim response JSON standar dan hentikan eksekusi.
 *
 * @param  bool   $sukses
 * @param  mixed  $data
 * @param  string $pesan
 * @param  int    $kode   HTTP status code
 * @return never
 */
function res(bool $sukses, mixed $data, string $pesan, int $kode = 200): never
{
    http_response_code($kode);
    echo json_encode([
        'sukses'    => $sukses,
        'data'      => $data,
        'pesan'     => $pesan,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Init ─────────────────────────────────────────────────────
$auth   = new AuthController();
$ctrl   = new BarangController();
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Route: AUTH (bebas login) ────────────────────────────────
if ($action === 'login') {
    [$ok, $pesan, $user] = $auth->login($body['username'] ?? '', $body['password'] ?? '');
    if ($ok) res(true, $user->toArray(), $pesan);
    else     res(false, null, $pesan, 401);
}

if ($action === 'logout') {
    $auth->logout();
    res(true, null, 'Logout berhasil.');
}

if ($action === 'me') {
    $u = $auth->userAktif();
    if ($u) res(true, $u, '');
    else    res(false, null, 'Belum login.', 401);
}

// ── Cek autentikasi untuk semua route berikutnya ─────────────
if (!$auth->sudahLogin()) {
    res(false, null, 'Silakan login terlebih dahulu.', 401);
}

$me = $auth->userAktif();

// ── Route: BARANG ────────────────────────────────────────────
try {

    switch ($action) {

        case 'list':
            $data = $ctrl->getAll(
                trim($_GET['cari']         ?? ''),
                trim($_GET['kondisi']      ?? ''),
                trim($_GET['id_kategori']  ?? '')
            );
            res(true, $data, count($data) . ' barang ditemukan.');

        case 'get':
            $d = $ctrl->getById((int)($_GET['id'] ?? 0));
            if ($d) res(true, $d, '');
            else    res(false, null, 'Barang tidak ditemukan.', 404);

        case 'kategori':
            res(true, $ctrl->getKategori(), '');

        case 'stats':
            res(true, $ctrl->statistik(), '');

        case 'create':
            [$ok, $pesan] = $ctrl->validasi($body);
            if (!$ok) res(false, null, $pesan, 400);

            $baru = $ctrl->tambah($body);
            $auth->catatLog($me['username'], 'TAMBAH', "Tambah: {$baru['nama']} ({$baru['kode']})");
            res(true, $baru, 'Barang berhasil ditambahkan.', 201);

        case 'update':
            $id = (int)($_GET['id'] ?? 0);
            [$ok, $pesan] = $ctrl->validasi($body);
            if (!$ok) res(false, null, $pesan, 400);

            $upd = $ctrl->update($id, $body);
            if (!$upd) res(false, null, 'Barang tidak ditemukan.', 404);
            $auth->catatLog($me['username'], 'EDIT', "Edit ID: $id");
            res(true, $upd, 'Data berhasil diperbarui.');

        case 'delete':
            $id = (int)($_GET['id'] ?? 0);
            if (!$ctrl->hapus($id)) res(false, null, 'Barang tidak ditemukan.', 404);
            $auth->catatLog($me['username'], 'HAPUS', "Hapus ID: $id");
            res(true, null, 'Barang berhasil dihapus.');

        default:
            res(false, null, "Action tidak dikenal: $action", 400);
    }

} catch (PDOException $e) {
    res(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    res(false, null, 'Server error: ' . $e->getMessage(), 500);
}
