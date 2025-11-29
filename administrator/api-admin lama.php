<?php
// File: administrator/api-admin.php

// 1. Inisialisasi & Keamanan
session_start();
ini_set('display_errors', 0); // Matikan display error ke output JSON agar tidak merusak format
error_reporting(E_ALL);

require 'db_connect.php'; // Pastikan file koneksi database ada
header('Content-Type: application/json');

// Cek Sesi Login Admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access. Please login first.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// --- FUNGSI BANTUAN UNTUK UPLOAD ---
function handleSettingUpload($file, $settingName) {
    // Tentukan folder tujuan (relatif terhadap file ini)
    // Kita buat subfolder 'settings' agar terpisah dari upload program lain
    $targetDir = __DIR__ . '/uploads/settings/';
    
    // Path yang akan disimpan di database (relatif terhadap root website)
    // Karena 'home.html' ada di root, pathnya harus: administrator/uploads/settings/namafile.jpg
    $dbPathPrefix = 'administrator/uploads/settings/';

    // Buat folder jika belum ada
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            return ['error' => 'Gagal membuat folder upload. Cek permission server.'];
        }
    }

    // Validasi Ekstensi
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
    
    if (!in_array($ext, $allowed)) {
        return ['error' => 'Format file tidak diizinkan. Gunakan JPG, PNG, SVG, atau WEBP.'];
    }

    // Validasi Ukuran (Max 5MB)
    if ($file['size'] > 5000000) {
        return ['error' => 'Ukuran file terlalu besar (Maksimal 5MB).'];
    }

    // Generate Nama Unik (PENTING untuk menghindari browser cache saat ganti gambar)
    // Format: nama_setting + timestamp + ext
    $fileName = $settingName . '_' . time() . '.' . $ext;
    $targetFile = $targetDir . $fileName;

    // Pindahkan File
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        // Hapus file lama jika ada (Opsional, untuk kebersihan server)
        // cleanUpOldFiles($targetDir, $settingName, $fileName); 
        
        return ['path' => $dbPathPrefix . $fileName];
    } else {
        return ['error' => 'Gagal mengupload file ke server.'];
    }
}

// --- ROUTING ACTION ---

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // 2. GET ALL SETTINGS (Untuk mengisi form di content.html)
    if ($action === 'get_all_settings') {
        try {
            $stmt = $conn->prepare("SELECT setting_name, setting_value, setting_group FROM site_settings");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                // Kita format array-nya agar key-nya adalah setting_name
                // Ini memudahkan Vue.js untuk mapping: settings.logo_url
                $data[$row['setting_name']] = $row['setting_value'];
            }
            
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 3. SAVE TEXT SETTINGS (Batch Update)
    if ($action === 'save_settings') {
        // Data dikirim via FormData, jadi kita ambil dari $_POST
        $inputs = $_POST;
        unset($inputs['action']); // Buang key 'action' agar tidak ikut di-query

        $conn->begin_transaction(); // Pakai transaksi agar aman (semua tersimpan atau tidak sama sekali)
        try {
            $stmt = $conn->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_name = ?");
            
            foreach ($inputs as $key => $value) {
                // Trim whitespace untuk teks
                $cleanValue = trim($value);
                
                // Eksekusi update per baris
                $stmt->bind_param("ss", $cleanValue, $key);
                $stmt->execute();
            }
            
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Semua pengaturan berhasil disimpan.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data: ' . $e->getMessage()]);
        }
        exit;
    }

    // 4. UPLOAD GAMBAR SPESIFIK (Logo, QR, Hero BG, Partner)
    if ($action === 'upload_setting_image') {
        $settingName = $_POST['setting_name'] ?? '';
        
        if (empty($settingName) || !isset($_FILES['image'])) {
            echo json_encode(['status' => 'error', 'message' => 'Data upload tidak lengkap.']);
            exit;
        }

        // Proses Upload
        $result = handleSettingUpload($_FILES['image'], $settingName);

        if (isset($result['error'])) {
            echo json_encode(['status' => 'error', 'message' => $result['error']]);
        } else {
            // Sukses Upload, Update Database path-nya
            $newPath = $result['path'];
            
            $stmt = $conn->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_name = ?");
            $stmt->bind_param("ss", $newPath, $settingName);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Gambar berhasil diperbarui.',
                    'path' => $newPath // Kembalikan path baru agar preview di Vue update
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal update database.']);
            }
        }
        exit;
    }
}

// Default response jika action tidak valid
echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
?>