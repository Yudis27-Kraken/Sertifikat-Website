/**
 * assets/js/app.js
 *
 * Frontend JavaScript untuk InvKantor v5.
 * Menangani: autentikasi, CRUD barang, render UI.
 *
 * Menggunakan Fetch API untuk komunikasi dengan api/index.php.
 *
 * @package InvV5\Assets
 * @version 1.0
 */

'use strict';

/* =====================================================
   KONSTANTA & STATE
===================================================== */

/** Base URL API */
const API = 'api/index.php';

/** State aplikasi */
let editId   = null;   // ID barang yang sedang diedit (null = tambah baru)
let hapusId  = null;   // ID barang yang akan dihapus
let cariTimer= null;   // Timer debounce pencarian
let curUser  = null;   // Data user yang sedang login
let daftarKat= [];     // Cache daftar kategori

/* =====================================================
   UTILITY FUNCTIONS  (kriteria e: fungsi)
===================================================== */

/**
 * Shortcut getElementById.
 *
 * @param  {string} id - ID elemen
 * @returns {HTMLElement}
 */
const $ = id => document.getElementById(id);

/**
 * Escape karakter HTML untuk mencegah XSS.
 *
 * @param  {*}      s - Input
 * @returns {string}
 */
const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

/**
 * Format angka dengan pemisah ribuan (ID locale).
 *
 * @param  {number} n
 * @returns {string}
 */
const fmt = n => Number(n).toLocaleString('id-ID');

/**
 * Kirim request ke API dan kembalikan JSON.
 *
 * @param  {string} action  - Nama action
 * @param  {string} method  - HTTP method (GET/POST)
 * @param  {Object} body    - Body JSON (untuk POST)
 * @param  {Object} params  - Query params tambahan
 * @returns {Promise<Object>}
 */
async function api(action, method = 'GET', body = null, params = {}) {
    let url = `${API}?action=${action}`;

    // Tambah query params — for...of loop (kriteria d)
    for (const [k, v] of Object.entries(params)) {
        url += `&${encodeURIComponent(k)}=${encodeURIComponent(v)}`;
    }

    const opt = { method };
    if (body) {
        opt.headers = { 'Content-Type': 'application/json' };
        opt.body    = JSON.stringify(body);
    }

    const res  = await fetch(url, opt);
    const json = await res.json();
    return json;
}

/**
 * Tampilkan toast notifikasi.
 *
 * @param {string} pesan - Pesan yang ditampilkan
 * @param {string} type  - 'ok' atau 'err'
 */
function notif(pesan, type = 'ok') {
    const wrap = $('toastWrap');
    const el   = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span>${type === 'ok' ? '✓' : '✕'}</span><span>${esc(pesan)}</span>`;
    wrap.appendChild(el);

    // Hapus setelah 2.8 detik — setTimeout (kriteria d)
    setTimeout(() => {
        el.classList.add('out');
        setTimeout(() => el.remove(), 250);
    }, 2800);
}

/**
 * Buat badge HTML untuk kategori.
 *
 * @param  {string} nama - Nama kategori
 * @returns {string}     HTML badge
 */
function badgeKategori(nama) {
    // if / else if / else — kriteria (d)
    let cls = 'b-ln';
    if      (nama === 'Elektronik') cls = 'b-el';
    else if (nama === 'Furniture')  cls = 'b-fr';
    else if (nama === 'Alat Tulis') cls = 'b-at';
    else if (nama === 'Kendaraan')  cls = 'b-kd';

    return `<span class="badge ${cls}">${esc(nama)}</span>`;
}

/**
 * Buat badge HTML untuk kondisi.
 *
 * @param  {string} k - Kondisi barang
 * @returns {string}  HTML badge
 */
function badgeKondisi(k) {
    const clsMap = {
        'Baik':         'c-ok',
        'Rusak':        'c-rs',
        'Dalam Servis': 'c-sv',
    };
    const icoMap = { 'Baik': '✓', 'Rusak': '✕', 'Dalam Servis': '⚙' };
    const cls    = clsMap[k] ?? 'c-ok';
    return `<span class="cond ${cls}">${icoMap[k] ?? ''} ${esc(k)}</span>`;
}

/* =====================================================
   LOGIN / LOGOUT  (kriteria e: method/fungsi)
===================================================== */

/**
 * Proses login: ambil credential, kirim ke API.
 */
async function doLogin() {
    const username = ($('loginUser').value ?? '').trim();
    const password = $('loginPass').value ?? '';
    const errEl    = $('loginErr');
    const btn      = $('loginBtn');

    // Validasi kosong — if/else (kriteria d)
    if (!username || !password) {
        errEl.textContent = 'Username dan password wajib diisi.';
        errEl.classList.add('show');
        return;
    }

    btn.disabled     = true;
    btn.textContent  = 'Memverifikasi…';
    errEl.classList.remove('show');

    try {
        const j = await api('login', 'POST', { username, password });

        if (j.sukses && j.data) {
            curUser = j.data;
            masukApp();
        } else {
            errEl.textContent = j.pesan || 'Login gagal.';
            errEl.classList.add('show');
        }
    } catch (e) {
        errEl.textContent = 'Tidak dapat terhubung ke server.';
        errEl.classList.add('show');
    } finally {
        btn.disabled    = false;
        btn.textContent = 'Masuk →';
    }
}

/**
 * Konfirmasi dan proses logout.
 */
async function doLogout() {
    if (!confirm('Yakin ingin keluar?')) return;
    await api('logout', 'POST');
    curUser = null;
    $('app').style.display        = 'none';
    $('loginPage').style.display  = 'flex';
    $('loginUser').value = '';
    $('loginPass').value = '';
}

/**
 * Tampilkan halaman utama setelah login berhasil.
 */
function masukApp() {
    $('loginPage').style.display = 'none';
    $('app').style.display       = 'flex';

    // Set info user di navbar
    $('navNama').textContent    = curUser.nama;
    $('navAvatar').textContent  = curUser.nama.charAt(0).toUpperCase();

    // Muat data
    muatKategori();
    muatBarang();
}

/**
 * Cek sesi aktif saat halaman dimuat.
 */
async function cekSesi() {
    try {
        const j = await api('me');
        if (j.sukses && j.data) {
            curUser = j.data;
            masukApp();
        }
    } catch (_) {}
}

/* =====================================================
   LOAD DATA  (kriteria e, g: baca dari media penyimpan)
===================================================== */

/**
 * Muat daftar kategori dan isi dropdown filter + form.
 * Menyimpan hasil ke array daftarKat (kriteria f).
 */
async function muatKategori() {
    try {
        const j = await api('kategori');
        if (!j.sukses) return;

        // Simpan ke array (kriteria f)
        daftarKat = Array.isArray(j.data) ? j.data : [];

        // Isi dropdown filter
        const flt = $('filterKat');
        if (flt) {
            flt.innerHTML = '<option value="">Semua Kategori</option>';
            // for loop (kriteria d)
            for (let i = 0; i < daftarKat.length; i++) {
                const k = daftarKat[i];
                flt.innerHTML += `<option value="${k.id}">${esc(k.nama)}</option>`;
            }
        }

        // Isi dropdown form
        const fKat = $('fKat');
        if (fKat) {
            fKat.innerHTML = '<option value="">— Pilih Kategori —</option>';
            daftarKat.forEach(k => {    // forEach (kriteria d)
                fKat.innerHTML += `<option value="${k.id}">${esc(k.nama)}</option>`;
            });
        }
    } catch (_) {}
}

/**
 * Muat dan tampilkan daftar barang.
 * Menerapkan baca dari media penyimpan (kriteria g).
 */
async function muatBarang() {
    const cari   = ($('cariInput')  || {}).value?.trim() ?? '';
    const kondisi= ($('filterKond') || {}).value ?? '';
    const idKat  = ($('filterKat')  || {}).value ?? '';
    const tbody  = $('tblBody');

    if (!tbody) return;

    // Tampilkan loading
    tbody.innerHTML = `<tr><td colspan="8" class="loading-cell">
        <span class="spinner"></span>Memuat data…
    </td></tr>`;

    try {
        const j = await api('list', 'GET', null, { cari, kondisi, id_kategori: idKat });

        if (!j.sukses) {
            tbody.innerHTML = `<tr><td colspan="8" class="loading-cell" style="color:var(--c-err)">
                Gagal: ${esc(j.pesan)}
            </td></tr>`;
            return;
        }

        // Hasil sebagai array (kriteria f)
        const arr = Array.isArray(j.data) ? j.data : [];
        $('tblCount').textContent = `${arr.length} item`;

        // Kosong
        if (!arr.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="empty-cell">
                <div style="font-size:1.8rem;margin-bottom:.5rem;opacity:.3">📦</div>
                <p>Belum ada barang${cari ? ` dengan kata kunci "<strong>${esc(cari)}</strong>"` : ''}.</p>
            </td></tr>`;
            return;
        }

        // Render baris — forEach loop (kriteria d)
        tbody.innerHTML = arr.map((d, i) => `
            <tr class="fade-in">
                <td class="td-no">${i + 1}</td>
                <td><span class="td-mono">${esc(d.kode)}</span></td>
                <td>
                    <div class="td-nama">${esc(d.nama)}</div>
                    ${d.keterangan ? `<div class="td-sub">${esc(d.keterangan.slice(0, 48))}${d.keterangan.length > 48 ? '…' : ''}</div>` : ''}
                </td>
                <td>${badgeKategori(d.nama_kategori)}</td>
                <td><strong>${fmt(d.jumlah)}</strong> <small style="color:var(--c-text3)">unit</small></td>
                <td style="color:var(--c-text2)"><small>📍</small> ${esc(d.lokasi)}</td>
                <td>${badgeKondisi(d.kondisi)}</td>
                <td style="color:var(--c-text3);font-size:.78rem">${d.tanggal ?? ''}</td>
                <td>
                    <div class="act-group">
                        <button class="btn btn-edit" onclick="bukaEdit(${d.id})">✏ Edit</button>
                        <button class="btn btn-del"  onclick="tanyaHapus(${d.id})">🗑</button>
                    </div>
                </td>
            </tr>`).join('');

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8" class="loading-cell" style="color:var(--c-err)">
            Error: ${esc(e.message)}
        </td></tr>`;
    }
}

/**
 * Debounce: tunda pencarian 350ms setelah ketik berhenti.
 */
function cariDelay() {
    clearTimeout(cariTimer);
    cariTimer = setTimeout(muatBarang, 350);
}

/* =====================================================
   FORM MODAL  (kriteria e: fungsi/method)
===================================================== */

/**
 * Bersihkan semua field form.
 */
function bersihForm() {
    const fields = ['fNama', 'fKode', 'fJml', 'fLok', 'fTgl', 'fKet'];
    // for loop (kriteria d)
    for (const id of fields) {
        const el = $(id);
        if (el) el.value = '';
    }
    const fKat = $('fKat'); if (fKat) fKat.value = '';
    const fKond= $('fKond');if (fKond) fKond.value= 'Baik';
    $('fTgl') && ($('fTgl').value = new Date().toISOString().split('T')[0]);
    muatKategori();   // refresh dropdown
}

/**
 * Buka modal tambah barang baru.
 */
function bukaForm() {
    editId = null;
    $('modalTitle').textContent  = 'Tambah Barang Baru';
    $('simpanBtn').textContent   = '＋ Simpan';
    bersihForm();
    $('modalOv').classList.add('open');
    setTimeout(() => $('fNama')?.focus(), 120);
}

/**
 * Buka modal edit barang.
 *
 * @param {number} id - ID barang
 */
async function bukaEdit(id) {
    editId = id;
    $('modalTitle').textContent = 'Edit Data Barang';
    $('simpanBtn').textContent  = '✓ Update';
    bersihForm();
    $('modalOv').classList.add('open');

    try {
        const j = await api('get', 'GET', null, { id });
        if (j.sukses && j.data) {
            const d = j.data;
            $('fNama').value = d.nama         ?? '';
            $('fKode').value = d.kode         ?? '';
            $('fKat').value  = d.id_kategori  ?? '';
            $('fKond').value = d.kondisi      ?? 'Baik';
            $('fJml').value  = d.jumlah       ?? 1;
            $('fTgl').value  = d.tanggal      ?? '';
            $('fLok').value  = d.lokasi       ?? '';
            $('fKet').value  = d.keterangan   ?? '';
        }
    } catch (e) {
        notif('Gagal memuat data barang.', 'err');
    }
}

/** Tutup modal form. */
function tutupModal() {
    $('modalOv').classList.remove('open');
    bersihForm();
    editId = null;
}

/**
 * Simpan atau update barang.
 * Menerapkan simpan ke media penyimpan (kriteria g).
 */
async function simpan() {
    const body = {
        nama:        ($('fNama').value ?? '').trim(),
        kode:        ($('fKode').value ?? '').trim(),
        id_kategori: parseInt($('fKat').value)  || 0,
        kondisi:     $('fKond').value,
        jumlah:      parseInt($('fJml').value)  || 0,
        tanggal:     $('fTgl').value,
        lokasi:      ($('fLok').value ?? '').trim(),
        keterangan:  ($('fKet').value ?? '').trim(),
    };

    // Validasi sisi klien — if/else (kriteria d)
    if (!body.nama || !body.id_kategori || !body.jumlah || !body.tanggal || !body.lokasi) {
        notif('Lengkapi semua field yang wajib (*).', 'err');
        return;
    }

    const btn = $('simpanBtn');
    btn.disabled    = true;
    btn.textContent = '⏳ Menyimpan…';

    try {
        const j = editId
            ? await api('update', 'POST', body, { id: editId })
            : await api('create', 'POST', body);

        if (j.sukses) {
            notif(editId ? 'Data berhasil diperbarui.' : 'Barang berhasil ditambahkan.');
            tutupModal();
            muatBarang();
        } else {
            notif(j.pesan || 'Gagal menyimpan.', 'err');
        }
    } catch (e) {
        notif(e.message, 'err');
    } finally {
        btn.disabled    = false;
        btn.textContent = editId ? '✓ Update' : '＋ Simpan';
    }
}

/* =====================================================
   HAPUS BARANG
===================================================== */

/**
 * Tampilkan konfirmasi hapus.
 *
 * @param {number} id - ID barang yang akan dihapus
 */
function tanyaHapus(id) {
    hapusId = id;
    $('confOv').classList.add('open');
}

/** Tutup dialog konfirmasi. */
function tutupConf() {
    $('confOv').classList.remove('open');
    hapusId = null;
}

/**
 * Eksekusi penghapusan barang.
 */
async function eksekusiHapus() {
    try {
        const j = await api('delete', 'POST', null, { id: hapusId });
        if (j.sukses) {
            notif('Barang berhasil dihapus.');
            tutupConf();
            muatBarang();
        } else {
            notif(j.pesan || 'Gagal menghapus.', 'err');
        }
    } catch (e) {
        notif(e.message, 'err');
    }
}

/* =====================================================
   EVENT LISTENERS & INIT
===================================================== */

// Keyboard shortcut: Enter di field login
document.addEventListener('DOMContentLoaded', () => {
    const loginUser = $('loginUser');
    const loginPass = $('loginPass');

    if (loginUser) loginUser.addEventListener('keydown', e => e.key === 'Enter' && loginPass?.focus());
    if (loginPass) loginPass.addEventListener('keydown', e => e.key === 'Enter' && doLogin());

    // Tutup modal dengan Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            tutupModal();
            tutupConf();
        }
    });

    // Cek sesi aktif saat load
    cekSesi();
});
