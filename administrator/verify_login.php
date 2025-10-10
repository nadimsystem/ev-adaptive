<?php
// File: administrator/verify_login.php
// Skrip ini HANYA untuk debugging, bisa dihapus setelah masalah selesai.

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>"; // Menggunakan tag <pre> agar output lebih mudah dibaca

// 1. Masukkan koneksi database
require 'db_connect.php';
echo "1. Koneksi database berhasil disertakan.\n\n";

// 2. Tentukan kredensial yang ingin kita uji
$email_to_test = 'annie.yusuf@bmw.de';
$password_to_test = 'admin123'; // Password plain-text yang Anda ketik di form

echo "2. Kredensial yang akan diuji:\n";
echo "   - Email: " . htmlspecialchars($email_to_test) . "\n";
echo "   - Password: " . htmlspecialchars($password_to_test) . "\n\n";

// 3. Ambil data admin dari database berdasarkan email
$stmt = $conn->prepare("SELECT password FROM admins WHERE email = ?");
if (!$stmt) {
    die("3. GAGAL: Query Gagal Disiapkan (prepare failed): " . $conn->error);
}

$stmt->bind_param("s", $email_to_test);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$admin) {
    die("3. GAGAL: Tidak ada pengguna admin yang ditemukan dengan email '" . htmlspecialchars($email_to_test) . "' di database.\nPeriksa kembali isi tabel `admins` Anda.");
}

echo "3. Berhasil menemukan pengguna dengan email '" . htmlspecialchars($email_to_test) . "'.\n\n";

// 4. Ambil hash password dari database
$hash_from_db = $admin['password'];
echo "4. Hash password yang tersimpan di database untuk pengguna ini adalah:\n";
echo "   " . htmlspecialchars($hash_from_db) . "\n\n";

// 5. Lakukan verifikasi
echo "5. Memverifikasi password '" . htmlspecialchars($password_to_test) . "' dengan hash di atas...\n\n";
$is_password_correct = password_verify($password_to_test, $hash_from_db);

// 6. Tampilkan hasil akhir
echo "================ HASIL AKHIR ================\n";
if ($is_password_correct) {
    echo "<strong>BERHASIL: Password COCOK!</strong>\n";
    echo "Ini berarti masalahnya bukan pada password atau hash, melainkan pada bagaimana data dikirim dari form login ke api.php.\n";
} else {
    echo "<strong>GAGAL: Password TIDAK COCOK!</strong>\n";
    echo "Ini 99% berarti hash di database Anda salah. Silakan ulangi langkah membuat hash baru dengan `create_hash.php` dengan SANGAT TELITI, lalu salin dan tempel hash yang baru ke kolom password di database.\n";
}
echo "==========================================\n";

echo "</pre>";
?>