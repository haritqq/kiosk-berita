# Plugin Kios untuk SLiMS 9 Bulian (v9.6.1)

Plugin tambahan (*add-on*) untuk sistem otomasi perpustakaan **SLiMS (Senayan Library Management System) 9 Bulian versi 9.6.1**. Plugin ini dirancang untuk menyediakan antarmuka layanan mandiri (*self-service kiosk*) bagi pemustaka di perpustakaan.

---

## Fitur Utama
* **Antarmuka Kios Mandiri:** Memudahkan pemustaka mengakses layanan perpustakaan secara mandiri melalui layar kios.
* **Kompatibel dengan SLiMS 9.6.1:** Disesuaikan dengan struktur *hook* dan sistem plugin bawaan SLiMS 9 Bulian versi 9.6.1.
* **Ringan & Mudah Disesuaikan:** Dibangun agar mudah diintegrasikan tanpa mengubah kode inti (*core*) SLiMS.

## Prasyarat Sistem
* **SLiMS:** Versi 9 Bulian (teruji pada versi **9.6.1**)
* **PHP:** Versi 8.1 atau versi yang direkomendasikan oleh SLiMS 9.6.1
* **Database:** MySQL / MariaDB sesuai standar instalasi SLiMS

## Cara Instalasi
1. Unduh repositori ini dalam format `.zip` (atau lakukan `git clone`).
2. Ekstrak file zip tersebut hingga mendapatkan folder utama plugin.
3. Unggah atau pindahkan folder plugin tersebut ke dalam direktori `plugins/` pada instalasi SLiMS Anda (contoh: `slims9/plugins/nama-folder-plugin`).
4. Masuk ke halaman **Admin SLiMS** menggunakan akun pustakawan/administrator.
5. Buka menu **System** > **Plugins**.
6. Cari nama plugin kios ini pada daftar, lalu klik tombol **Enable** (Aktifkan).
7. Plugin siap digunakan.

---

## Kontribusi
Kontribusi dari komunitas pustakawan dan pengembang SLiMS sangat terbuka. Jika Anda menemukan *bug* atau ingin menambahkan fitur baru:
1. Lakukan *Fork* pada repositori ini.
2. Buat *branch* fitur baru (`git checkout -b fitur-baru`).
3. Lakukan *Commit* pada perubahan Anda (`git commit -m 'Menambahkan fitur baru'`).
4. Lakukan *Push* ke *branch* tersebut (`git push origin fitur-baru`).
5. Ajukan **Pull Request**.

---

## Lisensi & Ketentuan Penggunaan

Proyek ini didistribusikan secara gratis untuk mendukung komunitas perpustakaan dan pengguna SLiMS di Indonesia maupun global.

Plugin ini dilisensikan di bawah ketentuan **[GNU General Public License v3.0 (GNU GPLv3)](https://gnu.org)**.

### Aturan Penting Terkait Modifikasi:
* **Wajib Terbuka (*Copyleft*):** Anda bebas menggunakan, mempelajari, membagikan, dan memodifikasi kode sumber plugin ini. Namun, jika Anda mendistribusikan versi yang telah dimodifikasi, **Anda wajib merilis seluruh kode sumber modifikasinya secara terbuka di bawah lisensi GNU GPLv3 yang sama**.
* **Dilarang Menjadikan Produk Tertutup (*Closed-Source*):** Tidak ada pihak yang diizinkan mengambil kode ini, memodifikasinya, lalu menguncinya menjadi perangkat lunak komersial tertutup (*proprietary*) untuk diperjualbelikan tanpa menyertakan kode sumber terbukanya.
* **Pertahankan Kredit:** Setiap salinan atau turunan dari plugin ini wajib tetap mencantumkan informasi hak cipta pembuat asli dan catatan perubahan yang dilakukan.

Untuk membaca teks lengkap aturan lisensi ini, silakan merujuk pada file `LICENSE` di dalam repositori ini atau kunjungi halaman resmi **[GNU General Public License v3.0](https://gnu.org)**.
