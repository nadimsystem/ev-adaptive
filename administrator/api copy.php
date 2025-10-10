<?php
// File: administrator/api.php
// File ini berfungsi sebagai pusat kendali API (API Endpoint) untuk seluruh aplikasi.
// Ia menangani semua permintaan data (GET) dan manipulasi data (POST).
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Memasukkan skrip koneksi database untuk mendapatkan akses ke variabel $conn.
require 'db_connect.php';

// Mengatur header HTTP untuk memastikan bahwa output dari file ini
// selalu dikenali sebagai konten berformat JSON oleh browser.
header('Content-Type: application/json');

// Menyiapkan array respons default. Ini akan dikirim jika tidak ada
// parameter 'action' yang valid yang diterima oleh skrip.
$response = ['status' => 'error', 'message' => 'Invalid Request Action'];

// ==============================================================================
// Fungsi Pembantu (Helper Function)
// ==============================================================================

/**
 * Menangani proses unggahan file gambar.
 * Termasuk validasi, pembuatan nama unik, dan pemindahan file.
 * @param array $file Array file dari superglobal $_FILES.
 * @return array Mengembalikan array berisi 'path' jika sukses, atau 'error' jika gagal.
 */
function handleFileUpload($file)
{
    // Direktori tempat file akan disimpan, relatif terhadap lokasi api.php.
    $uploadDir = 'uploads/';
    // Path yang akan disimpan di database, relatif terhadap root direktori proyek.
    $dbPathPrefix = 'administrator/uploads/';

    // Validasi 1: Periksa apakah direktori unggahan ada dan dapat ditulis oleh server.
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        return ['error' => 'Server Configuration Error: The directory \'administrator/uploads/\' does not exist or is not writable. Please check folder permissions (e.g., chmod 775).'];
    }
    // Validasi 2: Periksa kode error dari unggahan PHP itu sendiri.
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $phpUploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk. Check server permissions.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];
        $errorMessage = $phpUploadErrors[$file['error']] ?? 'An unknown upload error occurred.';
        return ['error' => $errorMessage];
    }

    // Membuat nama file yang unik untuk mencegah tumpang tindih file dengan nama yang sama.
    $fileName = uniqid() . '-' . basename($file['name']);
    $targetPathOnServer = $uploadDir . $fileName; // Path fisik di server untuk memindahkan file.
    $pathForDatabase = $dbPathPrefix . $fileName; // Path yang akan disimpan di database.

    $fileType = strtolower(pathinfo($targetPathOnServer, PATHINFO_EXTENSION));
    $check = getimagesize($file['tmp_name']);

    // Validasi lebih lanjut: tipe file, ukuran, dan apakah itu gambar asli.
    if ($check === false) return ['error' => 'File is not a valid image.'];
    if ($file['size'] > 5000000) return ['error' => 'File is too large (Max 5MB).'];
    if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) return ['error' => 'Only JPG, JPEG, PNG & GIF files are allowed.'];

    // Memindahkan file dari lokasi sementara ke direktori tujuan akhir.
    if (move_uploaded_file($file['tmp_name'], $targetPathOnServer)) {
        return ['path' => $pathForDatabase]; // Sukses, kembalikan path untuk database.
    } else {
        return ['error' => 'Server error: Could not move the uploaded file. This is likely a permissions issue.'];
    }
}

// ==============================================================================
// Logika Penanganan Permintaan GET (Membaca Data)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action) {
        case 'get_programs':
            $result = $conn->query("SELECT id, title, short_description, image_url FROM programs ORDER BY id DESC");
            $programs = [];
            while ($row = $result->fetch_assoc()) {
                $programs[] = $row;
            }
            $response = ['status' => 'success', 'data' => $programs];
            break;

        case 'get_program_detail':
            if (isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt = $conn->prepare("SELECT * FROM programs WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $program = $result->fetch_assoc();
                if ($program) {
                    $response = ['status' => 'success', 'data' => $program];
                } else {
                    $response['message'] = 'Program not found.';
                }
                $stmt->close();
            } else {
                $response['message'] = 'Program ID is required.';
            }
            break;

        // *** AKSI BARU: Mengambil semua data yang dibutuhkan oleh halaman utama (index.html) ***
        case 'get_homepage_data':
            $homepage_data = [];

            // 1. Mengambil semua pengaturan dari tabel `site_settings`
            $settings_result = $conn->query("SELECT setting_name, setting_value FROM site_settings");
            $settings = [];
            while ($row = $settings_result->fetch_assoc()) {
                $settings[$row['setting_name']] = $row['setting_value'];
            }
            $homepage_data['settings'] = $settings;

            // 2. Mengambil 4 program terbaru yang berstatus 'published' untuk carousel
            $programs_result = $conn->query("SELECT id, title, short_description, image_url FROM programs WHERE status = 'published' ORDER BY id DESC LIMIT 4");
            $programs = [];
            while ($row = $programs_result->fetch_assoc()) {
                $programs[] = $row;
            }
            $homepage_data['programs'] = $programs;

            // 3. Mengambil semua anggota tim, diurutkan berdasarkan `display_order`
            $team_result = $conn->query("SELECT name, position, image_url FROM team_members ORDER BY display_order ASC");
            $team = [];
            while ($row = $team_result->fetch_assoc()) {
                $team[] = $row;
            }
            $homepage_data['team'] = $team;

            // 4. Mengambil semua gambar galeri, diurutkan berdasarkan `display_order`
            $gallery_result = $conn->query("SELECT image_url, alt_text FROM gallery_images ORDER BY display_order ASC");
            $gallery = [];
            while ($row = $gallery_result->fetch_assoc()) {
                $gallery[] = $row;
            }
            $homepage_data['gallery'] = $gallery;

            $response = ['status' => 'success', 'data' => $homepage_data];
            break;

             // *** AKSI BARU: MENGAMBIL SEMUA GAMBAR GALERI ***
        case 'get_gallery_images':
            try {
                $result = $conn->query("SELECT * FROM gallery_images ORDER BY display_order ASC, id DESC");
                $images = [];
                while ($row = $result->fetch_assoc()) {
                    $images[] = $row;
                }
                $response = ['status' => 'success', 'data' => $images];
            } catch (Exception $e) {
                $response['message'] = 'A server error occurred: ' . $e->getMessage();
            }
            break;

              // *** AKSI BARU: MENGAMBIL SEMUA ANGGOTA TIM ***
        case 'get_team_members':
            try {
                $result = $conn->query("SELECT * FROM team_members ORDER BY display_order ASC, id DESC");
                $members = [];
                while ($row = $result->fetch_assoc()) {
                    $members[] = $row;
                }
                $response = ['status' => 'success', 'data' => $members];
            } catch (Exception $e) {
                $response['message'] = 'A server error occurred: ' . $e->getMessage();
            }
            break;

             // *** AKSI BARU: MENGAMBIL SEMUA PENGATURAN SITUS ***
        case 'get_site_settings':
            try {
                $result = $conn->query("SELECT setting_name, setting_value FROM site_settings");
                $settings = [];
                while ($row = $result->fetch_assoc()) {
                    // Membuat format key-value, misal: 'hero_title' => 'Education with Impact'
                    $settings[$row['setting_name']] = $row['setting_value'];
                }
                $response = ['status' => 'success', 'data' => $settings];
            } catch (Exception $e) {
                $response['message'] = 'A server error occurred: ' . $e->getMessage();
            }
            break;

         // *** AKSI BARU: MENGAMBIL PESAN KONTAK ***
        case 'get_contact_messages':
            try {
                $result = $conn->query("SELECT * FROM contact_messages ORDER BY submission_date DESC");
                $messages = [];
                while ($row = $result->fetch_assoc()) {
                    $messages[] = $row;
                }
                $response = ['status' => 'success', 'data' => $messages];
            } catch (Exception $e) {
                $response['message'] = 'Server Error: ' . $e->getMessage();
            }
            break;

        // *** AKSI BARU: MENGAMBIL PELANGGAN BULETIN ***
        case 'get_newsletter_subscribers':
            try {
                $result = $conn->query("SELECT * FROM newsletter_subscribers ORDER BY subscription_date DESC");
                $subscribers = [];
                while ($row = $result->fetch_assoc()) {
                    $subscribers[] = $row;
                }
                $response = ['status' => 'success', 'data' => $subscribers];
            } catch (Exception $e) {
                $response['message'] = 'Server Error: ' . $e->getMessage();
            }
            break;

            
    }
}
// ==============================================================================
// Logika Penanganan Permintaan POST (Membuat, Memperbarui, Menghapus Data)
// ==============================================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Switch case ini akan diperluas untuk menangani CRUD tabel lain (team, gallery, settings)
    switch ($action) {
        // ... (Seluruh case 'add_program', 'update_program', 'delete_program' dari jawaban sebelumnya tetap di sini, tidak berubah) ...
        case 'add_program':
            $title = $_POST['title'];
            $short_desc = $_POST['short_description'];
            $full_content = $_POST['full_content'] ?? '';
            $location = $_POST['location'] ?? 'Remote villages';
            $focus = $_POST['focus'] ?? 'Children & Elderly';
            $key_areas = $_POST['key_areas'] ?? 'Infrastructure, Aid, Support';
            $imageUrl = 'https://images.unsplash.com/photo-1495446815901-a7297e633e8d?q=80&w=1470&auto=format=fit=crop';
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $uploadResult = handleFileUpload($_FILES['image']);
                if (isset($uploadResult['path'])) {
                    $imageUrl = $uploadResult['path'];
                } else {
                    $response['message'] = $uploadResult['error'];
                    echo json_encode($response);
                    exit;
                }
            }
            $stmt = $conn->prepare("INSERT INTO programs (title, short_description, full_content, image_url, location, focus, key_areas) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $title, $short_desc, $full_content, $imageUrl, $location, $focus, $key_areas);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Program added successfully.'];
            } else {
                $response['message'] = 'Failed to add program: ' . $stmt->error;
            }
            $stmt->close();
            break;
        case 'update_program':
            $id = $_POST['id'];
            $title = $_POST['title'];
            $short_desc = $_POST['short_description'];
            $full_content = $_POST['full_content'] ?? '';
            $location = $_POST['location'] ?? 'Remote villages';
            $focus = $_POST['focus'] ?? 'Children & Elderly';
            $key_areas = $_POST['key_areas'] ?? 'Infrastructure, Aid, Support';
            $imageUrl = $_POST['existing_image_url'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $uploadResult = handleFileUpload($_FILES['image']);
                if (isset($uploadResult['path'])) {
                    if ($imageUrl && file_exists('../' . $imageUrl) && strpos($imageUrl, 'unsplash') === false) {
                        unlink('../' . $imageUrl);
                    }
                    $imageUrl = $uploadResult['path'];
                } else {
                    $response['message'] = $uploadResult['error'];
                    echo json_encode($response);
                    exit;
                }
            }
            $stmt = $conn->prepare("UPDATE programs SET title = ?, short_description = ?, full_content = ?, image_url = ?, location = ?, focus = ?, key_areas = ? WHERE id = ?");
            $stmt->bind_param("sssssssi", $title, $short_desc, $full_content, $imageUrl, $location, $focus, $key_areas, $id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Program updated successfully.'];
            } else {
                $response['message'] = 'Failed to update program: ' . $stmt->error;
            }
            $stmt->close();
            break;
        case 'delete_program':
            $id = $_POST['id'];
            $stmt_select = $conn->prepare("SELECT image_url FROM programs WHERE id = ?");
            $stmt_select->bind_param("i", $id);
            $stmt_select->execute();
            $result = $stmt_select->get_result();
            $row = $result->fetch_assoc();
            $imageUrl_to_delete = $row ? $row['image_url'] : null;
            $stmt_select->close();
            $stmt_delete = $conn->prepare("DELETE FROM programs WHERE id = ?");
            $stmt_delete->bind_param("i", $id);
            if ($stmt_delete->execute()) {
                if ($imageUrl_to_delete && file_exists('../' . $imageUrl_to_delete) && strpos($imageUrl_to_delete, 'unsplash') === false) {
                    unlink('../' . $imageUrl_to_delete);
                }
                $response = ['status' => 'success', 'message' => 'Program deleted successfully.'];
            } else {
                $response['message'] = 'Failed to delete program: ' . $stmt_delete->error;
            }
            $stmt_delete->close();
            break;
        case 'add_donation':
            // Ambil semua data dari formulir donasi
            $program_name = $_POST['selected_program'] ?? 'Not specified';
            $amount = $_POST['selected_amount'] ?? 0;
            $currency = $_POST['selected_currency'] ?? 'EUR';
            $frequency = $_POST['selected_frequency'] ?? 'one-time';
            $salutation = $_POST['salutation'] ?? null;
            $academic_degree = $_POST['academicDegree'] ?? null;
            $first_name = $_POST['firstName'] ?? '';
            $last_name = $_POST['lastName'] ?? '';
            $address = $_POST['address'] ?? null;
            $house_number = $_POST['houseNumber'] ?? null;
            $postal_code = $_POST['postalCode'] ?? null;
            $city = $_POST['city'] ?? null;
            $country = $_POST['country'] ?? null;
            $telephone = $_POST['telephone'] ?? null;
            $email = $_POST['emailDonation'] ?? '';
            $message = $_POST['messageDonation'] ?? null;
            $wants_annual_receipt = isset($_POST['annualReceipt']) ? 1 : 0;
            $wants_digital_postcard = isset($_POST['digitalPostcard']) ? 1 : 0;
            $wants_member_certificate = isset($_POST['memberCertificate']) ? 1 : 0;
            $payment_method = $_POST['payment'] ?? 'Online Bank Transfer';

            // Validasi sederhana
            if (empty($first_name) || empty($last_name) || empty($email)) {
                $response['message'] = 'First Name, Last Name, and Email are required.';
                echo json_encode($response);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO donations (program_name, amount, currency, frequency, salutation, academic_degree, first_name, last_name, address, house_number, postal_code, city, country, telephone, email, message, wants_annual_receipt, wants_digital_postcard, wants_member_certificate, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sdssssssssssssssiiis", $program_name, $amount, $currency, $frequency, $salutation, $academic_degree, $first_name, $last_name, $address, $house_number, $postal_code, $city, $country, $telephone, $email, $message, $wants_annual_receipt, $wants_digital_postcard, $wants_member_certificate, $payment_method);

            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Thank you! Your donation has been recorded.'];
            } else {
                $response['message'] = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
            break;

        case 'get_homepage_data':
            $homepage_data = [];

            // 1. Ambil Pengaturan Situs
            $settings_result = $conn->query("SELECT setting_name, setting_value FROM site_settings");
            $settings = [];
            while ($row = $settings_result->fetch_assoc()) {
                $settings[$row['setting_name']] = $row['setting_value'];
            }
            $homepage_data['settings'] = $settings;

            // 2. Ambil Program 
            // *** PERUBAHAN DI SINI ***
            // Ambil SEMUA program untuk dropdown donasi dan carousel
            $programs_result = $conn->query("SELECT id, title, short_description, image_url FROM programs WHERE status = 'published' ORDER BY id DESC");
            $programs = [];
            while ($row = $programs_result->fetch_assoc()) {
                $programs[] = $row;
            }
            // Kita akan kirim semua program, dan biarkan Vue yang membatasinya untuk carousel jika perlu
            $homepage_data['programs'] = $programs;

            // 3. Ambil Anggota Tim
            $team_result = $conn->query("SELECT name, position, image_url FROM team_members ORDER BY display_order ASC");
            $team = [];
            while ($row = $team_result->fetch_assoc()) {
                $team[] = $row;
            }
            $homepage_data['team'] = $team;

            // 4. Ambil Gambar Galeri
            $gallery_result = $conn->query("SELECT image_url, alt_text FROM gallery_images ORDER BY display_order ASC");
            $gallery = [];
            while ($row = $gallery_result->fetch_assoc()) {
                $gallery[] = $row;
            }
            $homepage_data['gallery'] = $gallery;

            $response = ['status' => 'success', 'data' => $homepage_data];
            break;

        // donasi

        // *** AKSI BARU: MENGAMBIL DATA DONASI DENGAN FILTER DAN PAGINASI ***
       case 'get_donations':
            try {
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
                $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                $offset = ($page - 1) * $limit;

                $baseQuery = " FROM donations";
                $whereClauses = [];
                $filterParams = [];
                $filterTypes = '';

                if (!empty($_GET['search'])) {
                    $searchTerm = '%' . $_GET['search'] . '%';
                    $whereClauses[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
                    $filterParams[] = $searchTerm;
                    $filterParams[] = $searchTerm;
                    $filterParams[] = $searchTerm;
                    $filterTypes .= 'sss';
                }

                if (!empty($_GET['program'])) {
                    $whereClauses[] = "program_name = ?";
                    $filterParams[] = $_GET['program'];
                    $filterTypes .= 's';
                }

                $whereSql = '';
                if (count($whereClauses) > 0) {
                    $whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
                }

                // Query untuk menghitung total data
                $countQuery = "SELECT COUNT(*) as total" . $baseQuery . $whereSql;
                $stmtTotal = $conn->prepare($countQuery);
                if (!$stmtTotal) { throw new Exception("Prepare failed (count): " . $conn->error); }
                if (count($filterParams) > 0) {
                    $stmtTotal->bind_param($filterTypes, ...$filterParams);
                }
                $stmtTotal->execute();
                $totalResult = $stmtTotal->get_result()->fetch_assoc();
                $totalRecords = $totalResult['total'];
                $totalPages = ceil($totalRecords / $limit);
                $stmtTotal->close();

                // Query untuk mengambil data donasi
                $dataQuery = "SELECT *" . $baseQuery . $whereSql . " ORDER BY donation_date DESC LIMIT ? OFFSET ?";
                $dataStmt = $conn->prepare($dataQuery);
                if (!$dataStmt) { throw new Exception("Prepare failed (data): " . $conn->error); }
                
                // PERBAIKAN UTAMA: Cara binding parameter yang lebih aman dan terpisah
                $allParams = $filterParams;
                $allTypes = $filterTypes;
                $allParams[] = $limit;
                $allParams[] = $offset;
                $allTypes .= 'ii';
                
                if(count($allParams) > 0) {
                    $dataStmt->bind_param($allTypes, ...$allParams);
                }

                $dataStmt->execute();
                $result = $dataStmt->get_result();
                $donations = [];
                while ($row = $result->fetch_assoc()) {
                    $donations[] = $row;
                }
                $dataStmt->close();

                $response = [
                    'status' => 'success',
                    'data' => $donations,
                    'pagination' => [
                        'currentPage' => $page,
                        'totalPages' => $totalPages,
                        'totalRecords' => $totalRecords
                    ]
                ];
            } catch (Exception $e) {
                $response['message'] = 'A server error occurred: ' . $e->getMessage();
            }
            break;

            // *** AKSI BARU: MENAMBAHKAN GAMBAR GALERI BARU ***
        case 'add_gallery_image':
            $alt_text = $_POST['alt_text'] ?? '';

            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $uploadResult = handleFileUpload($_FILES['image']);
                if (isset($uploadResult['path'])) {
                    $imageUrl = $uploadResult['path'];
                    
                    $stmt = $conn->prepare("INSERT INTO gallery_images (image_url, alt_text) VALUES (?, ?)");
                    $stmt->bind_param("ss", $imageUrl, $alt_text);

                    if ($stmt->execute()) {
                        $response = ['status' => 'success', 'message' => 'Image uploaded successfully.'];
                    } else {
                        $response['message'] = 'Failed to save image record to database: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $response['message'] = $uploadResult['error'];
                }
            } else {
                $response['message'] = 'No file was uploaded or an upload error occurred.';
            }
            break;

        // *** AKSI BARU: MENGHAPUS GAMBAR GALERI ***
        case 'delete_gallery_image':
            $id = $_POST['id'] ?? 0;
            if ($id > 0) {
                // Ambil URL gambar sebelum dihapus dari database
                $stmt_select = $conn->prepare("SELECT image_url FROM gallery_images WHERE id = ?");
                $stmt_select->bind_param("i", $id);
                $stmt_select->execute();
                $result = $stmt_select->get_result();
                $row = $result->fetch_assoc();
                $imageUrl_to_delete = $row ? $row['image_url'] : null;
                $stmt_select->close();

                // Hapus record dari database
                $stmt_delete = $conn->prepare("DELETE FROM gallery_images WHERE id = ?");
                $stmt_delete->bind_param("i", $id);
                
                if ($stmt_delete->execute()) {
                    // Hapus file gambar fisik dari server
                    if ($imageUrl_to_delete && file_exists('../' . $imageUrl_to_delete)) {
                        unlink('../' . $imageUrl_to_delete);
                    }
                    $response = ['status' => 'success', 'message' => 'Image deleted successfully.'];
                } else {
                    $response['message'] = 'Failed to delete image from database: ' . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $response['message'] = 'Invalid image ID.';
            }
            break;
            // *** AKSI BARU: MENAMBAHKAN ANGGOTA TIM BARU ***
        case 'add_team_member':
            $name = $_POST['name'] ?? '';
            $position = $_POST['position'] ?? '';
            $display_order = $_POST['display_order'] ?? 0;

            if (empty($name) || empty($position)) {
                $response['message'] = 'Name and position are required.';
                break;
            }

            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $uploadResult = handleFileUpload($_FILES['image']);
                if (isset($uploadResult['path'])) {
                    $imageUrl = $uploadResult['path'];
                    
                    $stmt = $conn->prepare("INSERT INTO team_members (name, position, image_url, display_order) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $name, $position, $imageUrl, $display_order);

                    if ($stmt->execute()) {
                        $response = ['status' => 'success', 'message' => 'Team member added successfully.'];
                    } else {
                        $response['message'] = 'Failed to save record to database: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $response['message'] = $uploadResult['error'];
                }
            } else {
                $response['message'] = 'An image is required to add a new team member.';
            }
            break;

        // *** AKSI BARU: MEMPERBARUI ANGGOTA TIM ***
        case 'update_team_member':
            $id = $_POST['id'] ?? 0;
            $name = $_POST['name'] ?? '';
            $position = $_POST['position'] ?? '';
            $display_order = $_POST['display_order'] ?? 0;
            $imageUrl = $_POST['existing_image_url'] ?? '';

            if ($id <= 0 || empty($name) || empty($position)) {
                $response['message'] = 'Valid ID, Name, and Position are required.';
                break;
            }

            // Cek apakah ada file gambar baru yang diunggah
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $uploadResult = handleFileUpload($_FILES['image']);
                if (isset($uploadResult['path'])) {
                    // Hapus gambar lama jika ada dan bukan URL placeholder
                    if ($imageUrl && file_exists('../' . $imageUrl) && strpos($imageUrl, 'unsplash') === false) {
                        unlink('../' . $imageUrl);
                    }
                    $imageUrl = $uploadResult['path'];
                } else {
                    $response['message'] = $uploadResult['error'];
                    break; // Keluar dari switch jika upload gagal
                }
            }
            
            $stmt = $conn->prepare("UPDATE team_members SET name = ?, position = ?, image_url = ?, display_order = ? WHERE id = ?");
            $stmt->bind_param("sssii", $name, $position, $imageUrl, $display_order, $id);
            
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Team member updated successfully.'];
            } else {
                $response['message'] = 'Failed to update team member: ' . $stmt->error;
            }
            $stmt->close();
            break;

        // *** AKSI BARU: MENGHAPUS ANGGOTA TIM ***
        case 'delete_team_member':
            $id = $_POST['id'] ?? 0;
            if ($id > 0) {
                $stmt_select = $conn->prepare("SELECT image_url FROM team_members WHERE id = ?");
                $stmt_select->bind_param("i", $id);
                $stmt_select->execute();
                $result = $stmt_select->get_result();
                $row = $result->fetch_assoc();
                $imageUrl_to_delete = $row ? $row['image_url'] : null;
                $stmt_select->close();

                $stmt_delete = $conn->prepare("DELETE FROM team_members WHERE id = ?");
                $stmt_delete->bind_param("i", $id);
                
                if ($stmt_delete->execute()) {
                    if ($imageUrl_to_delete && file_exists('../' . $imageUrl_to_delete) && strpos($imageUrl_to_delete, 'unsplash') === false) {
                        unlink('../' . $imageUrl_to_delete);
                    }
                    $response = ['status' => 'success', 'message' => 'Team member deleted successfully.'];
                } else {
                    $response['message'] = 'Failed to delete from database: ' . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $response['message'] = 'Invalid team member ID.';
            }
            break;

             // *** AKSI BARU: MEMPERBARUI PENGATURAN SITUS SECARA MASSAL (BATCH UPDATE) ***
        case 'update_site_settings':
            try {
                // Hapus 'action' dari array POST agar tidak ikut di-loop
                unset($_POST['action']); 
                
                $conn->begin_transaction(); // Mulai transaksi untuk memastikan semua atau tidak sama sekali

                $stmt = $conn->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_name = ?");
                if (!$stmt) {
                    throw new Exception("Prepare statement failed: " . $conn->error);
                }

                foreach ($_POST as $setting_name => $setting_value) {
                    $stmt->bind_param("ss", $setting_value, $setting_name);
                    if (!$stmt->execute()) {
                        // Jika satu saja gagal, batalkan semua perubahan
                        throw new Exception("Execute failed for setting '{$setting_name}': " . $stmt->error);
                    }
                }
                
                $stmt->close();
                $conn->commit(); // Terapkan semua perubahan jika berhasil

                $response = ['status' => 'success', 'message' => 'Site settings updated successfully.'];

            } catch (Exception $e) {
                $conn->rollback(); // Batalkan semua perubahan jika terjadi error
                $response['message'] = 'A server error occurred: ' . $e->getMessage();
            }
            break;

           // *** AKSI BARU: MENANGANI SUBMISI FORMULIR KONTAK ***
        case 'submit_contact_form':
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $message = $_POST['message'] ?? '';

            if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($message)) {
                $response['message'] = 'Please fill all fields with valid data.';
                break;
            }

            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $message);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Thank you! Your message has been sent.'];
            } else {
                $response['message'] = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
            break;

        // *** AKSI BARU: MENANGANI SUBMISI FORMULIR BULETIN ***
        case 'subscribe_newsletter':
            $email = $_POST['email'] ?? '';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'Please provide a valid email address.';
                break;
            }

            // Cek apakah email sudah terdaftar
            $stmt_check = $conn->prepare("SELECT id FROM newsletter_subscribers WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            if ($result->num_rows > 0) {
                $response = ['status' => 'success', 'message' => 'This email is already subscribed. Thank you!'];
                break;
            }
            $stmt_check->close();

            // Jika belum, masukkan email baru
            $stmt_insert = $conn->prepare("INSERT INTO newsletter_subscribers (email) VALUES (?)");
            $stmt_insert->bind_param("s", $email);
            if ($stmt_insert->execute()) {
                $response = ['status' => 'success', 'message' => 'Thank you for subscribing!'];
            } else {
                $response['message'] = 'Database error: ' . $stmt_insert->error;
            }
            $stmt_insert->close();
            break;

            
    }
}

// Menutup koneksi database sebelum mengakhiri skrip.
$conn->close();

// Mengirimkan respons akhir ke klien dalam format JSON.
echo json_encode($response);
