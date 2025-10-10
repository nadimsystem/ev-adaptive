<?php
// File: administrator/logout.php
session_start();    // Mulai sesi untuk mengaksesnya
session_unset();    // Hapus semua variabel sesi
session_destroy();  // Hancurkan sesi
header('Location: login.html'); // Arahkan kembali ke halaman login
exit;
?>