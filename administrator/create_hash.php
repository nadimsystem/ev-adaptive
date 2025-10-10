<?php
// File: administrator/create_hash.php

// Masukkan password yang ingin Anda gunakan di sini.
$plain_password = 'nadima123';

// Hasilkan hash yang aman menggunakan algoritma BCRYPT default.
$password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

// Tampilkan hasilnya dalam format yang mudah dibaca dan disalin.
echo "<h1>Password Hash Generator</h1>";
echo "<p>Password Plain Text: <strong>" . htmlspecialchars($plain_password) . "</strong></p>";
echo "<p>Password Hash (salin teks di bawah ini):</p>";
echo "<textarea rows='3' cols='80' readonly>" . htmlspecialchars($password_hash) . "</textarea>";

?>