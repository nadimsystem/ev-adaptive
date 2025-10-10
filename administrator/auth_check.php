<?php
// File: administrator/auth_check.php
// Memulai atau melanjutkan sesi yang sudah ada.
session_start();

// Memeriksa apakah session 'admin_id' telah di-set saat login.
// Jika tidak ada, artinya pengguna belum login.
if (!isset($_SESSION['admin_id'])) {
    // Arahkan pengguna kembali ke halaman login.
    header('Location: login.html');
    // Hentikan eksekusi skrip agar konten halaman admin tidak ditampilkan.
    exit;
}
?>