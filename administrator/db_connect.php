<?php
// File: administrator/db_connect.php
// File ini bertanggung jawab untuk membuat dan mengelola koneksi ke database MySQL.

// Konfigurasi Kredensial Database
// $host = 'localhost';      // Alamat server database. Biasanya 'localhost' atau '127.0.0.1'.
// $username = 'root';       // Username untuk mengakses database. Ganti sesuai konfigurasi Anda.
// $password = '';           // Password untuk username database. Ganti sesuai konfigurasi Anda.
// $database = 'bildungmitwirkung'; // Nama database yang kita buat sebelumnya.
// Konfigurasi Kredensial Database
$host = 'localhost';      // Alamat server database. Biasanya 'localhost' atau '127.0.0.1'.
$username = 'root';       // Username untuk mengakses database. Ganti sesuai konfigurasi Anda.
$password = '';           // Password untuk username database. Ganti sesuai konfigurasi Anda.
$database = 'bildungmitwirkung'; // Nama database yang kita buat sebelumnya.

// $koneksi = mysqli_connect("localhost","nusf6699_zta","ztapass1998","nusf6699_zta");
// Membuat objek koneksi baru menggunakan class mysqli.
$conn = new mysqli($host, $username, $password, $database);

// Memeriksa apakah koneksi gagal. Jika ya, hentikan eksekusi skrip dan tampilkan pesan error.
if ($conn->connect_error) {
    // Menghentikan eksekusi dan mengirimkan response error dalam format JSON.
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Mengatur set karakter koneksi ke utf8mb4 untuk mendukung berbagai karakter internasional.
$conn->set_charset("utf8mb4");

// Variabel $conn sekarang siap digunakan di file lain yang meng-include file ini.
?>