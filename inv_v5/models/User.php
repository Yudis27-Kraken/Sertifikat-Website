<?php
/**
 * models/User.php
 *
 * Model User untuk autentikasi satu akun admin.
 *
 * @package InvV5\Models
 * @version 1.0
 */

/**
 * Class User
 *
 * Representasi pengguna sistem dengan enkapsulasi penuh.
 *
 * @package InvV5\Models
 */
class User
{
    /**
     * Constructor User.
     *
     * @param int    $id       ID user
     * @param string $nama     Nama lengkap
     * @param string $username Username login
     */
    public function __construct(
        private int    $id,
        private string $nama,
        private string $username
    ) {}

    /** @return int    ID user */
    public function getId(): int       { return $this->id; }

    /** @return string Nama lengkap */
    public function getNama(): string  { return $this->nama; }

    /** @return string Username */
    public function getUsername(): string { return $this->username; }

    /**
     * Konversi ke array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'nama'     => $this->nama,
            'username' => $this->username,
        ];
    }
}
