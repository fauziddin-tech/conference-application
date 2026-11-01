# Conference Application

Aplikasi website & manajemen konferensi ilmiah (contoh data: **PICOE — Pahlawan International Conference on Education**).
Ditulis dengan PHP + MySQL tanpa framework, sehingga bisa langsung diunggah ke shared hosting (cPanel).

## Fitur

**Website publik**
- Hero dengan hitung mundur, tombol *Add to Calendar* (.ics), meta SEO/Open Graph, dan data terstruktur schema.org `Event`
- Pengumuman, tentang acara, **Important Dates** (timeline otomatis menandai yang sudah lewat)
- Pembicara (dikelompokkan per peran, dengan foto), **Komite**, **Sponsor & Mitra** (per tingkat)
- **Call for Papers**: topik/track, unduhan template & panduan
- **Program** per hari (tab), biaya registrasi dengan **early bird** otomatis, langkah pendaftaran
- Lokasi + peta, jurnal publikasi, **FAQ**, kontak WhatsApp/email
- Halaman **verifikasi dokumen** publik (QR pada sertifikat/LoA)
- Tampilan responsif, mode gelap otomatis, aksesibilitas dasar (skip link, aria)

**Peserta**
- Daftar akun (popup), login, **lupa / reset password via email**
- Formulir registrasi: data peserta, ORCID, kategori & kehadiran (offline/online), naskah untuk presenter
  (track, abstrak dengan penghitung kata, keywords, file), pembayaran (boleh **bayar nanti**)
- Dashboard dengan **progres pendaftaran**, catatan panitia, komentar reviewer
- Unggah **naskah revisi** dan **camera-ready**
- Dokumen siap cetak/PDF: **Invoice, Kwitansi, E-ticket & name badge (QR), Invitation letter (visa), Letter of Acceptance, E-Certificate**
- Link sesi online khusus peserta online terverifikasi
- Notifikasi email pada setiap tahap

**Naskah & review (tanpa OJS, alur ala MyThesis)**
- Setiap unggahan naskah tersimpan sebagai **versi** (v1, v2, …) — riwayat tidak terhapus
- Review berjalan per **ronde**; admin menugaskan reviewer dengan **tenggat**
- Reviewer memberi skor, rekomendasi, komentar, dan boleh **mengunggah file Word berisi komentar** (Comment/Track Changes)
- Penulis melihat review (anonim) setelah keputusan panitia, lalu mengunggah **revisi + tanggapan untuk reviewer**;
  revisi otomatis menjadi ronde baru dan diteruskan ke reviewer yang sama
- Data naskah dikunci di formulir setelah review dimulai; naskah final (camera-ready) setelah diterima
- Opsi naskah **Word saja** (DOC/DOCX) agar reviewer bisa berkomentar langsung di file
- **Notifikasi di aplikasi** (ikon lonceng) untuk peserta, reviewer, dan admin, selain email

**Admin**
- **Ringkasan statistik** (kategori, negara, kehadiran, status naskah, track, tren bulanan)
- Kelola peserta (filter & pencarian), verifikasi pembayaran, keputusan naskah + email otomatis
- Penugasan reviewer, rekap skor & rekomendasi
- **Check-in** di lokasi dengan memindai QR e-ticket
- Kelola akun (peserta, reviewer, admin), reset password
- Kelola pembicara (foto, urutan), jurnal, agenda, dan konten (tanggal penting, topik, komite, sponsor, FAQ, pengumuman, unduhan)
- **Email massal** per kelompok penerima + log email; pengiriman via **Resend API** atau `mail()` hosting, dengan tombol kirim email uji
- Pengaturan: buka/tutup pendaftaran, buka sertifikat, wajib check-in, early bird, logo, penandatangan, link online, dll.
- Ekspor CSV (registrasi, akun, review) — aman dibuka di Excel

**Keamanan**
- Password hash bcrypt, CSRF token di semua form, sesi `HttpOnly`/`SameSite`, regenerasi sesi saat login
- Pembatasan percobaan login/daftar/reset (anti brute force), honeypot anti-bot
- File unggahan privat (bukti bayar, naskah) di luar akses publik, validasi tipe file (magic bytes)
- Header keamanan (X-Frame-Options, nosniff, Referrer-Policy), proteksi CSV injection

## Instalasi (cPanel)

1. Buat database MySQL + user di cPanel.
2. Unggah semua file ke `public_html` (atau subfolder).
3. Pastikan folder `uploads/` dan root aplikasi dapat ditulis (untuk `config.php`).
4. Buka `https://domainanda/install/` lalu isi data database, konferensi, dan akun admin.
5. **Hapus folder `install/`** setelah selesai.
6. Masuk sebagai admin → **Pengaturan**: isi email pengirim (gunakan email ber-domain website Anda agar tidak masuk spam),
   instruksi pembayaran, penandatangan, logo, dsb.

Disarankan: PHP 7.4+ (diuji di PHP 8.3) dan MySQL 5.7+/MariaDB 10.3+, ekstensi `pdo_mysql`, `mbstring`, `gd`.
Batas unggah file 10 MB per file — sesuaikan `upload_max_filesize`/`post_max_size` di *MultiPHP INI Editor* bila perlu.

## Email via Resend (disarankan)

1. Di [resend.com](https://resend.com) → **Domains** → *Add domain* (mis. `picoe.org`), lalu tambahkan record DNS yang diminta
   (di Cloudflare: DNS → Records, set **DNS only** / awan abu-abu) sampai statusnya **Verified**.
2. **API Keys** → *Create API key* (izin *Sending access* cukup).
3. Panel admin → **Pengaturan** → *Pengiriman Email*: pilih **Resend API**, tempel API key, isi email pengirim
   dengan domain yang sudah verified (mis. `noreply@picoe.org`), lalu **Simpan**.
4. Di bagian **Uji Pengiriman Email**, kirim email uji. Hasil dan pesan error tercatat di tab **Email → Log Email**.

Email massal dikirim lewat endpoint batch Resend (maks. 100 email per permintaan). Perhatikan kuota paket Resend Anda.
Hosting harus mengaktifkan ekstensi PHP **cURL**.

## Upgrade dari versi lama

Cukup timpa file aplikasi (jangan hapus `config.php` dan folder `uploads/`). Saat halaman pertama dibuka,
skema database diperbarui otomatis (`lib/schema.php`, versi tersimpan di `settings.db_version`); data lama tetap utuh
dan setiap pendaftar lama otomatis mendapat kode tiket.

## Alur kerja singkat

1. Peserta membuat akun → mengisi formulir (presenter mengunggah naskah) → membayar dan mengunggah bukti.
2. Admin menugaskan reviewer → reviewer mengirim penilaian → admin memutuskan (revisi/diterima/ditolak); peserta menerima email.
3. Admin memverifikasi pembayaran → peserta dapat mengunduh kwitansi, e-ticket, dan surat undangan; presenter yang diterima mendapat LoA.
4. Hari H: admin/panitia memindai QR e-ticket di `checkin.php`.
5. Setelah acara: aktifkan **Buka unduhan e-certificate** di Pengaturan.

## Struktur

```
index.php            beranda publik
register.php / login.php / forgot.php / reset.php / logout.php
registrasi.php       formulir pendaftaran konferensi
dashboard.php        dashboard peserta
reviewer.php         area reviewer
doc.php              dokumen cetak (invoice, kwitansi, badge, undangan, LoA, sertifikat)
verify.php           verifikasi dokumen publik
checkin.php          check-in peserta (admin)
calendar.php         file kalender .ics
file.php             unduhan file privat
admin/index.php      panel admin (aksi) + admin/views/*.php (tampilan)
inc.php              bootstrap, helper, layout
lib/schema.php       skema database & migrasi otomatis
assets/qrcode.min.js QRCode.js (MIT)
uploads/             foto pembicara/logo (publik) dan uploads/private (naskah & bukti bayar)
```
