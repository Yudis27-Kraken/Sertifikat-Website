<?php
/**
 * controllers/BarangController.php
 *
 * Logika bisnis CRUD barang inventaris.
 * Memanfaatkan: Database, KoleksiBarang, buatBarang().
 *
 * @package InvV5\Controllers
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Barang.php';

/**
 * Class BarangController
 *
 * Semua operasi Create-Read-Update-Delete untuk tabel barang.
 * Menerapkan prosedur dan method (kriteria e).
 *
 * @package InvV5\Controllers
 */
class BarangController
{
    /** @var Database Instance database */
    private Database $db;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Ambil semua barang dengan filter opsional.
     * Menggunakan array, foreach loop (kriteria d, f).
     *
     * @param  string $cari    Kata kunci nama/kode/lokasi
     * @param  string $kondisi Filter kondisi
     * @param  string $idKat   Filter ID kategori
     * @return array           Array data barang (JSON-friendly)
     */
    public function getAll(string $cari = '', string $kondisi = '', string $idKat = ''): array
    {
        $sql    = "SELECT b.*, k.nama AS nama_kategori
                   FROM barang b
                   JOIN kategori k ON b.id_kategori = k.id
                   WHERE 1=1";
        $params = [];

        // Bangun query dinamis — if/else (kriteria d)
        if (!empty($cari)) {
            $sql .= " AND (b.nama LIKE ? OR b.kode LIKE ? OR b.lokasi LIKE ?)";
            $params = array_merge($params, ["%$cari%", "%$cari%", "%$cari%"]);
        }

        if (!empty($kondisi)) {
            $sql .= " AND b.kondisi = ?";
            $params[] = $kondisi;
        }

        if (!empty($idKat)) {
            $sql .= " AND b.id_kategori = ?";
            $params[] = (int)$idKat;
        }

        $sql .= " ORDER BY b.id DESC";

        $rows    = $this->db->ambilSemua($sql, $params);
        $koleksi = new KoleksiBarang();

        // Konversi baris DB → objek — foreach (kriteria d)
        foreach ($rows as $row) {
            $koleksi->tambah(buatBarang($row));
        }

        // Bangun array hasil — foreach (kriteria d, f)
        $hasil = [];
        foreach ($koleksi->semua() as $i => $b) {
            $data               = $b->toArray();
            $data['dibuat']     = $rows[$i]['dibuat']     ?? '';
            $data['diperbarui'] = $rows[$i]['diperbarui'] ?? '';
            $hasil[]            = $data;
        }

        return $hasil;
    }

    /**
     * Ambil satu barang berdasarkan ID.
     *
     * @param  int        $id
     * @return array|null Null jika tidak ditemukan
     */
    public function getById(int $id): ?array
    {
        $row = $this->db->ambilSatu(
            "SELECT b.*, k.nama AS nama_kategori
             FROM barang b
             JOIN kategori k ON b.id_kategori = k.id
             WHERE b.id = ?",
            [$id]
        );

        if (!$row) return null;   // if/else

        $data               = buatBarang($row)->toArray();
        $data['dibuat']     = $row['dibuat']     ?? '';
        $data['diperbarui'] = $row['diperbarui'] ?? '';
        return $data;
    }

    /**
     * Tambah barang baru ke database.
     *
     * @param  array      $d Data barang
     * @return array|null    Data baru atau null jika gagal
     */
    public function tambah(array $d): ?array
    {
        // Generate kode otomatis
        $kode = !empty($d['kode'])
            ? $d['kode']
            : $this->generateKode((int)($d['id_kategori'] ?? 5));

        $this->db->jalankan(
            "INSERT INTO barang (kode, nama, id_kategori, lokasi, jumlah, kondisi, keterangan, tanggal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $kode,
                $d['nama'],
                (int)$d['id_kategori'],
                $d['lokasi'],
                (int)$d['jumlah'],
                $d['kondisi'],
                $d['keterangan'] ?? '',
                $d['tanggal'],
            ]
        );

        return $this->getById((int)$this->db->idTerakhir());
    }

    /**
     * Update data barang yang sudah ada.
     *
     * @param  int        $id ID barang
     * @param  array      $d  Data baru
     * @return array|null
     */
    public function update(int $id, array $d): ?array
    {
        if (!$this->getById($id)) return null;   // if/else

        $this->db->jalankan(
            "UPDATE barang
             SET nama=?, id_kategori=?, lokasi=?, jumlah=?, kondisi=?, keterangan=?, tanggal=?
             WHERE id=?",
            [
                $d['nama'],
                (int)$d['id_kategori'],
                $d['lokasi'],
                (int)$d['jumlah'],
                $d['kondisi'],
                $d['keterangan'] ?? '',
                $d['tanggal'],
                $id,
            ]
        );

        return $this->getById($id);
    }

    /**
     * Hapus barang dari database.
     *
     * @param  int  $id
     * @return bool
     */
    public function hapus(int $id): bool
    {
        if (!$this->getById($id)) return false;
        $this->db->jalankan("DELETE FROM barang WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Ambil semua kategori untuk dropdown.
     *
     * @return array
     */
    public function getKategori(): array
    {
        return $this->db->ambilSemua("SELECT * FROM kategori ORDER BY id");
    }

    /**
     * Statistik ringkas inventaris.
     *
     * @return array
     */
    public function statistik(): array
    {
        $rows    = $this->db->ambilSemua(
            "SELECT b.*, k.nama AS nama_kategori FROM barang b JOIN kategori k ON b.id_kategori = k.id"
        );
        $koleksi = new KoleksiBarang();

        foreach ($rows as $row) {
            $koleksi->tambah(buatBarang($row));
        }

        return $koleksi->ringkasan();
    }

    /**
     * Validasi data barang sebelum disimpan.
     * Prosedur validasi — kriteria (e).
     *
     * @param  array $d Data yang akan divalidasi
     * @return array    [bool $valid, string $pesan]
     */
    public function validasi(array $d): array
    {
        // Field wajib — array (kriteria f)
        $wajib = ['nama', 'id_kategori', 'lokasi', 'jumlah', 'kondisi', 'tanggal'];

        // Loop cek field wajib — foreach + if (kriteria d)
        foreach ($wajib as $field) {
            if (empty($d[$field])) {
                return [false, "Field '{$field}' wajib diisi."];
            }
        }

        // Validasi kondisi — if/else (kriteria d)
        if (!in_array($d['kondisi'], ItemBase::KONDISI_VALID, true)) {
            return [false, 'Kondisi tidak valid.'];
        }

        // Validasi jumlah — if/else (kriteria d)
        if (!is_numeric($d['jumlah']) || (int)$d['jumlah'] < 1) {
            return [false, 'Jumlah minimal 1.'];
        }

        return [true, ''];
    }

    /**
     * Generate kode barang otomatis berdasarkan ID kategori.
     * Contoh: ELK-003, FRN-005
     *
     * @param  int    $idKategori
     * @return string Kode unik
     */
    private function generateKode(int $idKategori): string
    {
        // Mapping prefix per id kategori — array (kriteria f)
        $prefix = [
            1 => 'ELK',
            2 => 'FRN',
            3 => 'ATK',
            4 => 'KDR',
            5 => 'LAN',
        ];

        $pfx = $prefix[$idKategori] ?? 'LAN';

        // Cari nomor urut terakhir
        $last = $this->db->ambilSatu(
            "SELECT kode FROM barang WHERE kode LIKE ? ORDER BY id DESC LIMIT 1",
            ["{$pfx}-%"]
        );

        $no = 1;

        // if/else — ada data sebelumnya atau tidak
        if ($last) {
            $parts = explode('-', $last['kode']);
            $no    = ((int)end($parts)) + 1;
        }

        return sprintf('%s-%03d', $pfx, $no);
    }
}
