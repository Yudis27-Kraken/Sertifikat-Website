<?php
/**
 * models/Barang.php
 *
 * Hierarki model OOP untuk inventaris kantor.
 *
 * Menerapkan semua kriteria OOP (h):
 *   - Interface          : Inventarisable
 *   - Abstract Class     : ItemBase
 *   - Inheritance        : ItemBase → Barang → BarangElektronik / BarangFurniture
 *   - Polymorphism       : override deskripsi() dan toArray() di tiap subclass
 *   - Encapsulation      : semua property private, akses via getter/setter
 *   - Properties         : getter + setter dengan validasi
 *   - Overloading sim.   : konstruktor dengan parameter default (PHP approach)
 *   - Array              : KoleksiBarang memakai array $items[]
 *
 * @package InvV5\Models
 * @version 1.0
 */

// ============================================================
// INTERFACE — kriteria (h)
// ============================================================

/**
 * Interface Inventarisable
 *
 * Kontrak yang wajib dipenuhi semua objek inventaris.
 *
 * @package InvV5\Models
 */
interface Inventarisable
{
    /** @return string Deskripsi teks item */
    public function deskripsi(): string;

    /** @return array  Konversi objek ke array */
    public function toArray(): array;

    /** @return bool   True jika kondisi = 'Rusak' */
    public function isRusak(): bool;
}

// ============================================================
// ABSTRACT CLASS — kriteria (h)
// ============================================================

/**
 * Abstract Class ItemBase
 *
 * Kelas dasar abstrak untuk semua item inventaris.
 * Tidak bisa di-instantiasi langsung (new ItemBase() → error).
 *
 * @package InvV5\Models
 */
abstract class ItemBase implements Inventarisable
{
    /** @var string[] Nilai kondisi yang diizinkan */
    const KONDISI_VALID = ['Baik', 'Rusak', 'Dalam Servis'];

    /**
     * Constructor ItemBase.
     *
     * @param int    $id      ID item (private — encapsulation)
     * @param string $nama    Nama item
     * @param string $tanggal Tanggal pengadaan (Y-m-d)
     */
    public function __construct(
        protected int    $id,
        protected string $nama,
        protected string $tanggal
    ) {}

    // ── Getters (Properties) ──────────────────────
    /** @return int */
    public function getId(): int { return $this->id; }

    /** @return string */
    public function getNama(): string { return $this->nama; }

    /** @return string */
    public function getTanggal(): string { return $this->tanggal; }

    // ── Setter dengan validasi ────────────────────
    /**
     * Set nama item.
     *
     * @param  string $nama
     * @throws InvalidArgumentException
     */
    public function setNama(string $nama): void
    {
        if (empty(trim($nama))) {
            throw new InvalidArgumentException('Nama tidak boleh kosong.');
        }
        $this->nama = trim($nama);
    }

    // ── Abstract methods ──────────────────────────
    /** Method abstrak — wajib diimplementasikan subclass */
    abstract public function deskripsi(): string;
    abstract public function toArray(): array;
}

// ============================================================
// CLASS Barang — Inheritance Level 1
// ============================================================

/**
 * Class Barang
 *
 * Representasi satu barang inventaris.
 * Extends ItemBase, mengimplementasikan Inventarisable.
 *
 * Menerapkan:
 * - Inheritance dari ItemBase (level 1)
 * - Encapsulation: semua properti private
 * - Properties: getter + setter dengan validasi
 * - Polymorphism: override deskripsi() dan toArray()
 *
 * @package InvV5\Models
 */
class Barang extends ItemBase
{
    /**
     * Constructor Barang.
     *
     * @param int    $id          ID barang
     * @param string $kode        Kode unik (ELK-001 dst.)
     * @param string $nama        Nama barang
     * @param int    $idKategori  ID kategori
     * @param string $namaKategori Nama kategori (join)
     * @param string $lokasi      Lokasi penyimpanan
     * @param int    $jumlah      Jumlah unit
     * @param string $kondisi     Kondisi barang
     * @param string $tanggal     Tanggal pengadaan
     * @param string $keterangan  Keterangan tambahan
     */
    public function __construct(
        int    $id,
        private string $kode,
        string $nama,
        private int    $idKategori,
        private string $namaKategori,
        private string $lokasi,
        private int    $jumlah,
        private string $kondisi,
        string $tanggal,
        private string $keterangan = ''
    ) {
        parent::__construct($id, $nama, $tanggal);

        // Validasi kondisi — if/else (kriteria d)
        if (!in_array($this->kondisi, self::KONDISI_VALID, true)) {
            $this->kondisi = 'Baik';
        }

        // Validasi jumlah — if/else (kriteria d)
        if ($this->jumlah < 1) {
            $this->jumlah = 1;
        }
    }

    // ── Getters ───────────────────────────────────
    public function getKode(): string          { return $this->kode; }
    public function getIdKategori(): int       { return $this->idKategori; }
    public function getNamaKategori(): string  { return $this->namaKategori; }
    public function getLokasi(): string        { return $this->lokasi; }
    public function getJumlah(): int           { return $this->jumlah; }
    public function getKondisi(): string       { return $this->kondisi; }
    public function getKeterangan(): string    { return $this->keterangan; }

    // ── Setters dengan validasi ───────────────────
    /**
     * @throws InvalidArgumentException
     */
    public function setLokasi(string $v): void
    {
        if (empty(trim($v))) throw new InvalidArgumentException('Lokasi wajib diisi.');
        $this->lokasi = trim($v);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setJumlah(int $v): void
    {
        if ($v < 1) throw new InvalidArgumentException('Jumlah minimal 1.');
        $this->jumlah = $v;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setKondisi(string $v): void
    {
        if (!in_array($v, self::KONDISI_VALID, true)) {
            throw new InvalidArgumentException('Kondisi tidak valid.');
        }
        $this->kondisi = $v;
    }

    public function setKeterangan(string $v): void { $this->keterangan = trim($v); }

    /**
     * Cek apakah barang rusak — implementasi Inventarisable.
     *
     * @return bool
     */
    public function isRusak(): bool { return $this->kondisi === 'Rusak'; }

    /**
     * Cek apakah barang dalam servis.
     *
     * @return bool
     */
    public function isDalamServis(): bool { return $this->kondisi === 'Dalam Servis'; }

    /**
     * Deskripsi barang — override ItemBase (Polymorphism).
     *
     * @return string
     */
    public function deskripsi(): string
    {
        return "[{$this->kode}] {$this->nama} | {$this->namaKategori} | Qty: {$this->jumlah} | {$this->kondisi}";
    }

    /**
     * Konversi ke array — override ItemBase.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'kode'          => $this->kode,
            'nama'          => $this->nama,
            'id_kategori'   => $this->idKategori,
            'nama_kategori' => $this->namaKategori,
            'lokasi'        => $this->lokasi,
            'jumlah'        => $this->jumlah,
            'kondisi'       => $this->kondisi,
            'tanggal'       => $this->tanggal,
            'keterangan'    => $this->keterangan,
        ];
    }
}

// ============================================================
// CLASS BarangElektronik — Inheritance Level 2
// ============================================================

/**
 * Class BarangElektronik
 *
 * Barang kategori elektronik dengan info tambahan garansi.
 * Extends Barang (Inheritance level 2).
 * Menerapkan Polymorphism: override deskripsi() dan toArray().
 *
 * @package InvV5\Models
 */
class BarangElektronik extends Barang
{
    /**
     * Constructor — overloading simulation via default params.
     *
     * @param int $garansiBulan Masa garansi dalam bulan (default 12)
     */
    public function __construct(
        int $id, string $kode, string $nama, int $idKat, string $nmKat,
        string $lokasi, int $jumlah, string $kondisi, string $tanggal,
        string $keterangan = '',
        private int $garansiBulan = 12
    ) {
        parent::__construct($id, $kode, $nama, $idKat, $nmKat, $lokasi, $jumlah, $kondisi, $tanggal, $keterangan);
    }

    /** @return int Masa garansi dalam bulan */
    public function getGaransiBulan(): int { return $this->garansiBulan; }

    /**
     * Override deskripsi — Polymorphism level 2.
     *
     * @return string
     */
    public function deskripsi(): string
    {
        return parent::deskripsi() . " | Garansi: {$this->garansiBulan} bln";
    }

    /**
     * Override toArray — tambah field garansi.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'garansi_bulan' => $this->garansiBulan,
            'tipe_objek'    => 'elektronik',
        ]);
    }
}

// ============================================================
// CLASS BarangFurniture — Inheritance Level 2
// ============================================================

/**
 * Class BarangFurniture
 *
 * Barang kategori furniture dengan info tambahan bahan.
 * Extends Barang (Inheritance level 2).
 *
 * @package InvV5\Models
 */
class BarangFurniture extends Barang
{
    /**
     * Constructor — overloading simulation via default params.
     *
     * @param string $bahan Material/bahan furniture (default '')
     */
    public function __construct(
        int $id, string $kode, string $nama, int $idKat, string $nmKat,
        string $lokasi, int $jumlah, string $kondisi, string $tanggal,
        string $keterangan = '',
        private string $bahan = ''
    ) {
        parent::__construct($id, $kode, $nama, $idKat, $nmKat, $lokasi, $jumlah, $kondisi, $tanggal, $keterangan);
    }

    /** @return string Material/bahan furniture */
    public function getBahan(): string { return $this->bahan; }

    /**
     * Override deskripsi — Polymorphism level 2.
     *
     * @return string
     */
    public function deskripsi(): string
    {
        $bhn = $this->bahan ? " | Bahan: {$this->bahan}" : '';
        return parent::deskripsi() . $bhn;
    }

    /**
     * Override toArray — tambah field bahan.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'bahan'      => $this->bahan,
            'tipe_objek' => 'furniture',
        ]);
    }
}

// ============================================================
// CLASS KoleksiBarang — Array (kriteria f)
// ============================================================

/**
 * Class KoleksiBarang
 *
 * Koleksi objek Barang. Menerapkan penggunaan Array (kriteria f)
 * dan berbagai loop (kriteria d): foreach, for, while.
 *
 * @package InvV5\Models
 */
class KoleksiBarang
{
    /** @var Barang[] Array koleksi — kriteria (f) */
    private array $items = [];

    /**
     * Tambah barang ke koleksi.
     *
     * @param Barang $b
     */
    public function tambah(Barang $b): void
    {
        $this->items[] = $b;
    }

    /** @return Barang[] Semua item */
    public function semua(): array { return $this->items; }

    /** @return int Jumlah jenis barang */
    public function jumlahJenis(): int { return count($this->items); }

    /**
     * Hitung total unit — foreach loop (kriteria d).
     *
     * @return int
     */
    public function totalUnit(): int
    {
        $total = 0;
        foreach ($this->items as $item) {   // foreach
            $total += $item->getJumlah();
        }
        return $total;
    }

    /**
     * Hitung barang rusak — for loop + if (kriteria d).
     *
     * @return int
     */
    public function jumlahRusak(): int
    {
        $n = 0;
        for ($i = 0; $i < count($this->items); $i++) {  // for
            if ($this->items[$i]->isRusak()) {            // if
                $n++;
            }
        }
        return $n;
    }

    /**
     * Hitung per kondisi — while loop (kriteria d).
     *
     * @param  string $kondisi
     * @return int
     */
    public function hitungKondisi(string $kondisi): int
    {
        $n = 0;
        $i = 0;
        while ($i < count($this->items)) {                      // while
            if ($this->items[$i]->getKondisi() === $kondisi) {  // if/else
                $n++;
            }
            $i++;
        }
        return $n;
    }

    /**
     * Statistik ringkas koleksi.
     *
     * @return array
     */
    public function ringkasan(): array
    {
        return [
            'total_jenis' => $this->jumlahJenis(),
            'total_unit'  => $this->totalUnit(),
            'baik'        => $this->hitungKondisi('Baik'),
            'rusak'       => $this->hitungKondisi('Rusak'),
            'servis'      => $this->hitungKondisi('Dalam Servis'),
        ];
    }
}

// ============================================================
// FACTORY FUNCTION
// ============================================================

/**
 * Buat objek Barang yang sesuai berdasarkan nama kategori.
 * Menerapkan if/elseif/else (kriteria d).
 *
 * @param  array  $d Data dari database (hasil JOIN)
 * @return Barang
 */
function buatBarang(array $d): Barang
{
    $id   = (int)($d['id']          ?? 0);
    $kode = $d['kode']              ?? '';
    $nama = $d['nama']              ?? '';
    $idK  = (int)($d['id_kategori'] ?? 1);
    $nmK  = $d['nama_kategori']     ?? '';
    $lok  = $d['lokasi']            ?? '';
    $jml  = (int)($d['jumlah']      ?? 1);
    $kond = $d['kondisi']           ?? 'Baik';
    $tgl  = $d['tanggal']           ?? date('Y-m-d');
    $ket  = $d['keterangan']        ?? '';

    // if / elseif / else — kriteria (d)
    if ($nmK === 'Elektronik') {
        return new BarangElektronik($id, $kode, $nama, $idK, $nmK, $lok, $jml, $kond, $tgl, $ket);
    } elseif ($nmK === 'Furniture') {
        return new BarangFurniture($id, $kode, $nama, $idK, $nmK, $lok, $jml, $kond, $tgl, $ket);
    } else {
        return new Barang($id, $kode, $nama, $idK, $nmK, $lok, $jml, $kond, $tgl, $ket);
    }
}
