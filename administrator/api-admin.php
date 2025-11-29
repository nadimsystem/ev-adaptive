<?php
// File: administrator/api-admin.php
// API Khusus untuk Super Admin mengelola Konten Dinamis (Teks & Gambar)

// 1. Konfigurasi Awal
session_start();
ini_set('display_errors', 0); // Matikan error display agar JSON aman
error_reporting(E_ALL);

require 'db_connect.php';
header('Content-Type: application/json');

// 2. SECURITY: Cek Login Admin
// Jika tidak login, tolak akses
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access. Please login first.']);
    exit;
}

// Ambil Action dari GET atau POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// =================================================================================
//                               FUNGSI BANTUAN
// =================================================================================

/**
 * Fungsi untuk mengupload gambar setting
 * Fitur: Validasi, Rename Unik, dan Hapus File Lama (Opsional)
 */
function handleSettingUpload($file, $conn, $settingName) {
    $targetDir = __DIR__ . '/uploads/settings/'; // Folder fisik
    $dbPathPrefix = 'administrator/uploads/settings/'; // Path untuk database
    
    // Buat folder jika belum ada
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            return ['error' => 'Gagal membuat folder upload. Cek permission server.'];
        }
    }

    // Validasi Error PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload error code: ' . $file['error']];
    }

    // Validasi Ekstensi
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
    
    if (!in_array($ext, $allowed)) {
        return ['error' => 'Format file tidak diizinkan. Hanya JPG, PNG, GIF, SVG, WEBP.'];
    }

    // Validasi Ukuran (Max 5MB)
    if ($file['size'] > 5000000) {
        return ['error' => 'Ukuran file terlalu besar (Maksimal 5MB).'];
    }

    // 1. HAPUS GAMBAR LAMA (Opsional - Agar server hemat space)
    // Kita cari dulu path gambar lama di database
    $stmtCheck = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_name = ?");
    $stmtCheck->bind_param("s", $settingName);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if ($row = $resCheck->fetch_assoc()) {
        $oldPath = $row['setting_value'];
        // Bersihkan path database untuk mendapatkan path fisik
        $physicalOldPath = str_replace('administrator/uploads/settings/', '', $oldPath);
        $fullOldPath = $targetDir . $physicalOldPath;
        
        // Hapus jika file ada dan bukan file default
        if (file_exists($fullOldPath) && is_file($fullOldPath) && !strpos($fullOldPath, 'default')) {
            unlink($fullOldPath);
        }
    }
    $stmtCheck->close();

    // 2. UPLOAD GAMBAR BARU
    // Nama file unik: setting_name + timestamp + random string
    $fileName = $settingName . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['path' => $dbPathPrefix . $fileName];
    } else {
        return ['error' => 'Gagal memindahkan file ke folder tujuan.'];
    }
}

// =================================================================================
//                               HANDLE REQUESTS
// =================================================================================

// --- GET: AMBIL SEMUA DATA SETTINGS ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_all_settings') {
    try {
        $data = [];
        // Ambil semua setting
        $sql = "SELECT setting_name, setting_value FROM site_settings";
        $result = $conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[$row['setting_name']] = $row['setting_value'];
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            throw new Exception("Gagal mengambil data dari database.");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- POST: SIMPAN TEKS & UPLOAD GAMBAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. ACTION: SAVE SETTINGS (Simpan semua input teks)
    if ($action === 'save_settings') {
        $inputs = $_POST;
        unset($inputs['action']); // Hapus parameter action

        $conn->begin_transaction();
        try {
            // Gunakan INSERT ... ON DUPLICATE KEY UPDATE
            // Ini kuncinya: Jika setting belum ada, dia Buat Baru. Jika sudah ada, dia Update.
            // Ini mencegah error jika Anda menambah setting baru di HTML tapi lupa di Database.
            $stmt = $conn->prepare("INSERT INTO site_settings (setting_name, setting_value, setting_group) VALUES (?, ?, 'general') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            foreach ($inputs as $key => $value) {
                $val = trim($value);
                // Kita defaultkan group ke 'general' jika insert baru (bisa diabaikan karena kita update by key)
                $stmt->bind_param("ss", $key, $val);
                $stmt->execute();
            }
            
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Semua pengaturan berhasil disimpan!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // 2. ACTION: UPLOAD IMAGE (Logo, Hero, QR, dll)
    if ($action === 'upload_setting_image') {
        $settingName = $_POST['setting_name'] ?? '';
        
        // Validasi Input
        if (empty($settingName)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama setting tidak boleh kosong.']);
            exit;
        }
        if (!isset($_FILES['image'])) {
            echo json_encode(['status' => 'error', 'message' => 'Tidak ada file yang diupload.']);
            exit;
        }

        // Proses Upload
        $uploadResult = handleSettingUpload($_FILES['image'], $conn, $settingName);

        if (isset($uploadResult['error'])) {
            echo json_encode(['status' => 'error', 'message' => $uploadResult['error']]);
        } else {
            // Sukses Upload Fisik, Sekarang Simpan Path ke Database
            $newPath = $uploadResult['path'];
            
            // Gunakan Logic "Insert or Update" agar aman
            $stmt = $conn->prepare("INSERT INTO site_settings (setting_name, setting_value, setting_group, setting_type) VALUES (?, ?, 'uploads', 'image') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param("ss", $settingName, $newPath);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Gambar berhasil diupload dan disimpan.',
                    'path' => $newPath // Kembalikan path agar Frontend bisa update preview
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan path ke database: ' . $stmt->error]);
            }
        }
        exit;
    }
}

// Jika Action Tidak Dikenali
echo json_encode(['status' => 'error', 'message' => 'Invalid API Action Request']);
$conn->close();
?>