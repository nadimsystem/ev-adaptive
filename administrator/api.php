<?php
// File: administrator/api.php
session_start(); // Mulai sesi di awal untuk semua action

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';
header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Invalid Request Action'];


// FUNGSI UNTUK EKSPOR EXCEL (HTML TABLE)
function exportDonationsToExcel($conn, $filters) {
    // Header untuk file Excel (.xls)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=donations_report_' . date('Y-m-d') . '.xls');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Membangun query
    $baseQuery = "SELECT * FROM donations";
    $whereClauses = [];
    $params = [];
    $types = '';

    if (!empty($filters['search'])) {
        $searchTerm = '%' . $filters['search'] . '%';
        $whereClauses[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
        $types .= 'sss';
    }
    if (!empty($filters['program'])) {
        $whereClauses[] = "program_name = ?";
        $params[] = $filters['program'];
        $types .= 's';
    }
    if (count($whereClauses) > 0) {
        $baseQuery .= ' WHERE ' . implode(' AND ', $whereClauses);
    }
    $baseQuery .= " ORDER BY donation_date DESC";

    $stmt = $conn->prepare($baseQuery);
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Output HTML Table
    echo '<table border="1">';
    echo '<thead><tr>
            <th>ID</th>
            <th>Date</th>
            <th>Program</th>
            <th>Amount</th>
            <th>Currency</th>
            <th>Frequency</th>
            <th>Salutation</th>
            <th>Title</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th>House No</th>
            <th>Postal Code</th>
            <th>City</th>
            <th>Country</th>
            <th>Message</th>
            <th>Payment Method</th>
            <th>Proof of Payment</th>
            <th>Annual Receipt</th>
            <th>Digital Postcard</th>
            <th>Member Cert</th>
          </tr></thead>';
    echo '<tbody>';
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['donation_date'] . '</td>';
        echo '<td>' . htmlspecialchars($row['program_name']) . '</td>';
        echo '<td>' . $row['amount'] . '</td>';
        echo '<td>' . $row['currency'] . '</td>';
        echo '<td>' . $row['frequency'] . '</td>';
        echo '<td>' . htmlspecialchars($row['salutation'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['academic_degree'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['first_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['last_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
        echo '<td>' . htmlspecialchars($row['telephone'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['address'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['house_number'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['postal_code'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['city'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['country'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['message'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['payment_method'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['proof_of_payment'] ?? 'None') . '</td>';
        echo '<td>' . ($row['wants_annual_receipt'] ? 'Yes' : 'No') . '</td>';
        echo '<td>' . ($row['wants_digital_postcard'] ? 'Yes' : 'No') . '</td>';
        echo '<td>' . ($row['wants_member_certificate'] ? 'Yes' : 'No') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    $stmt->close();
    exit();
}
// ... (Fungsi handleFileUpload tidak berubah) ...
function handleFileUpload($file, $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif']) {
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
    
    // Validasi tipe file
    if (!in_array($fileType, $allowedExtensions)) {
        return ['error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)];
    }
    
    // Validasi gambar hanya jika ekstensi adalah gambar
    if (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        $check = getimagesize($file['tmp_name']);
        if ($check === false) return ['error' => 'File is not a valid image.'];
    }

    if ($file['size'] > 5000000) return ['error' => 'File is too large (Max 5MB).'];

    // Memindahkan file dari lokasi sementara ke direktori tujuan akhir.
    if (move_uploaded_file($file['tmp_name'], $targetPathOnServer)) {
        return ['path' => $pathForDatabase]; // Sukses, kembalikan path untuk database.
    } else {
        return ['error' => 'Server error: Could not move the uploaded file. This is likely a permissions issue.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];

    // Pengecualian: get_homepage_data tidak memerlukan login
    if ($action !== 'get_homepage_data' && !isset($_SESSION['admin_id'])) {
        $response['message'] = 'Authentication required.';
        echo json_encode($response);
        exit;
    }

    switch ($action) {
        // ... (semua case GET dari jawaban sebelumnya tetap di sini, tidak berubah) ...
        
        // *** AKSI BARU: MENGAMBIL SEMUA DATA UNTUK DASHBOARD ***
        case 'get_dashboard_data':
            try {
                $data = [];
                // 1. Statistik Donasi (Bulan Ini)
                $stmt = $conn->prepare("SELECT SUM(amount) as total_donations, COUNT(DISTINCT email) as new_donors FROM donations WHERE MONTH(donation_date) = MONTH(CURRENT_DATE()) AND YEAR(donation_date) = YEAR(CURRENT_DATE())");
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $data['donations_this_month'] = $result['total_donations'] ?? 0;
                $data['new_donors_this_month'] = $result['new_donors'] ?? 0;
                $stmt->close();

                // 2. Pesan Belum Dibaca
                $result = $conn->query("SELECT COUNT(id) as unread_messages FROM contact_messages WHERE status = 'unread'");
                $data['unread_messages'] = $result->fetch_assoc()['unread_messages'] ?? 0;
                
                // 3. Statistik Total
                $data['total_programs'] = $conn->query("SELECT COUNT(id) FROM programs")->fetch_row()[0] ?? 0;
                $data['total_gallery_images'] = $conn->query("SELECT COUNT(id) FROM gallery_images")->fetch_row()[0] ?? 0;
                $data['total_admins'] = $conn->query("SELECT COUNT(id) FROM admins")->fetch_row()[0] ?? 0;
                
                // 4. Data Grafik Pertumbuhan Donasi (6 Bulan Terakhir)
                $chart_data = [];
                for ($i = 5; $i >= 0; $i--) {
                    $month = date('Y-m', strtotime("-$i months"));
                    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM donations WHERE DATE_FORMAT(donation_date, '%Y-%m') = ?");
                    $stmt->bind_param("s", $month);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    
                    $chart_data['labels'][] = date('F', strtotime($month . '-01'));
                    $chart_data['data'][] = floatval($result['total'] ?? 0);
                }
                $data['donation_chart'] = $chart_data;
                $stmt->close();

                // 5. Aktivitas Terbaru (5 entri terakhir)
                $query = "(SELECT id, CONCAT('New Donation from ', first_name) as description, donation_date as activity_date, 'donation' as type FROM donations ORDER BY donation_date DESC LIMIT 3)
                          UNION ALL
                          (SELECT id, CONCAT('New Message from ', name) as description, submission_date as activity_date, 'message' as type FROM contact_messages ORDER BY submission_date DESC LIMIT 2)
                          ORDER BY activity_date DESC LIMIT 5";
                $result = $conn->query($query);
                $activities = [];
                while ($row = $result->fetch_assoc()) {
                    $activities[] = $row;
                }
                $data['recent_activities'] = $activities;
                
                $response = ['status' => 'success', 'data' => $data];

            } catch (Exception $e) {
                $response['message'] = 'Server Error: ' . $e->getMessage();
            }
            break;
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

        case 'get_admins':
            $result = $conn->query("SELECT id, name, email, role, last_login FROM admins ORDER BY name ASC");
            $admins = [];
            while ($row = $result->fetch_assoc()) {
                $admins[] = $row;
            }
            $response = ['status' => 'success', 'data' => $admins];
            break;

            case 'get_donations':
            try {
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
                $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                $offset = ($page - 1) * $limit;

                $baseQuery = " FROM donations";
                $whereClauses = [];
                $params = [];
                $types = '';

                if (!empty($_GET['search'])) {
                    $searchTerm = '%' . $_GET['search'] . '%';
                    $whereClauses[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
                    $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
                    $types .= 'sss';
                }
                if (!empty($_GET['program'])) {
                    $whereClauses[] = "program_name = ?";
                    $params[] = $_GET['program'];
                    $types .= 's';
                }
                $whereSql = count($whereClauses) > 0 ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

                // Query Hitung Total
                $countQuery = "SELECT COUNT(*) as total" . $baseQuery . $whereSql;
                $stmtTotal = $conn->prepare($countQuery);
                if (count($params) > 0) { $stmtTotal->bind_param($types, ...$params); }
                $stmtTotal->execute();
                $totalRecords = $stmtTotal->get_result()->fetch_assoc()['total'];
                $totalPages = ceil($totalRecords / $limit);
                $stmtTotal->close();

                // Query Ambil Data
                $dataQuery = "SELECT *" . $baseQuery . $whereSql . " ORDER BY donation_date DESC LIMIT ? OFFSET ?";
                $dataStmt = $conn->prepare($dataQuery);
                $allParams = $params;
                $allTypes = $types . 'ii';
                $allParams[] = $limit;
                $allParams[] = $offset;
                if(count($allParams) > 0) { $dataStmt->bind_param($allTypes, ...$allParams); }
                $dataStmt->execute();
                $result = $dataStmt->get_result();
                $donations = [];
                while ($row = $result->fetch_assoc()) { $donations[] = $row; }
                $dataStmt->close();

                $response = [
                    'status' => 'success', 'data' => $donations,
                    'pagination' => ['currentPage' => $page, 'totalPages' => $totalPages, 'totalRecords' => $totalRecords]
                ];
            } catch (Exception $e) {
                $response['message'] = 'Server Error: ' . $e->getMessage();
            }
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
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
            $proof_of_payment = null;

            // Handle Proof of Payment Upload
            if (isset($_FILES['proofOfPayment']) && $_FILES['proofOfPayment']['error'] == 0) {
                $uploadResult = handleFileUpload($_FILES['proofOfPayment'], ['jpg', 'jpeg', 'png', 'gif', 'pdf']);
                if (isset($uploadResult['path'])) {
                    $proof_of_payment = $uploadResult['path'];
                }
            }

            // Validasi sederhana
            if (empty($first_name) || empty($last_name) || empty($email)) {
                $response['message'] = 'First Name, Last Name, and Email are required.';
                echo json_encode($response);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO donations (program_name, amount, currency, frequency, salutation, academic_degree, first_name, last_name, address, house_number, postal_code, city, country, telephone, email, message, wants_annual_receipt, wants_digital_postcard, wants_member_certificate, payment_method, proof_of_payment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sdssssssssssssssiiiss", $program_name, $amount, $currency, $frequency, $salutation, $academic_degree, $first_name, $last_name, $address, $house_number, $postal_code, $city, $country, $telephone, $email, $message, $wants_annual_receipt, $wants_digital_postcard, $wants_member_certificate, $payment_method, $proof_of_payment);


            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Thank you! Your donation has been recorded.'];
            } else {
                $response['message'] = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
            break;

        case 'export_donations_csv': // Keep the action name for compatibility or rename if you want
            $filters = [
                'search' => $_GET['search'] ?? '',
                'program' => $_GET['program'] ?? ''
            ];
            exportDonationsToExcel($conn, $filters);
            break;

        case 'get_homepage_data':
            $homepage_data = [
                'settings' => [],
                'programs' => [],
                'team' => [],
                'gallery' => []
            ];

            // 1. AMBIL SETTINGS (Teks & Gambar Dinamis)
            // Pastikan tabel site_settings sudah dibuat di database
            $settings_query = "SELECT setting_name, setting_value FROM site_settings";
            $settings_result = $conn->query($settings_query);
            
            if ($settings_result) {
                while ($row = $settings_result->fetch_assoc()) {
                    // Konversi jadi format: settings.hero_title = 'Isi Judul'
                    $homepage_data['settings'][$row['setting_name']] = $row['setting_value'];
                }
            }

            // 2. AMBIL PROGRAMS (Hanya yang published, max 6 terbaru)
            $programs_result = $conn->query("SELECT id, title, short_description, image_url FROM programs WHERE status = 'published' ORDER BY id DESC LIMIT 6");
            if ($programs_result) {
                while ($row = $programs_result->fetch_assoc()) {
                    $homepage_data['programs'][] = $row;
                }
            }

            // 3. AMBIL TEAM (Urutkan sesuai display_order)
            // Pastikan tabel team_members ada kolom display_order atau hapus ORDER BY-nya
            $team_query = "SELECT name, position, image_url FROM team_members ORDER BY id ASC"; 
            // Cek apakah kolom display_order ada, jika error ganti ORDER BY id DESC
            $team_result = $conn->query($team_query);
            if ($team_result) {
                while ($row = $team_result->fetch_assoc()) {
                    $homepage_data['team'][] = $row;
                }
            }

            // 4. AMBIL GALLERY (Max 12 gambar terbaru)
            $gallery_result = $conn->query("SELECT image_url, alt_text FROM gallery_images ORDER BY id DESC LIMIT 12");
            if ($gallery_result) {
                while ($row = $gallery_result->fetch_assoc()) {
                    $homepage_data['gallery'][] = $row;
                }
            }

            $response = ['status' => 'success', 'data' => $homepage_data];
            break;

        // donasi

        

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
        case 'login':
            $email = $_POST['email'] ?? '';
            $password_from_form = $_POST['password'] ?? ''; // Ganti nama variabel agar lebih jelas

            if (empty($email) || empty($password_from_form)) {
                $response['message'] = 'Email and password are required.';
                break;
            }

            $stmt = $conn->prepare("SELECT id, name, password FROM admins WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();

            // *** PERUBAHAN UTAMA DI SINI ***
            // Kita sekarang membandingkan password dari form langsung dengan password dari database.
            // Fungsi password_verify() dinonaktifkan.
            if ($admin && $password_from_form === $admin['password']) {
                // Login sukses, simpan data ke session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];

                // Update last_login timestamp
                $conn->query("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = " . $admin['id']);

                $response = ['status' => 'success', 'message' => 'Login successful (without hash).'];
            } else {
                $response['message'] = 'Invalid email or password.';
            }
            $stmt->close();
            break;

        // Action yang memerlukan login
        default:
            if (!isset($_SESSION['admin_id'])) {
                $response['message'] = 'Authentication required. Please log in again.';
                break;
            }

            // Lanjutkan ke switch case untuk action admin lainnya
            switch ($action) {
                // ... (semua case POST admin dari jawaban sebelumnya: programs, donations, gallery, team, settings) ...

                case 'add_admin':
                    $name = $_POST['name'] ?? '';
                    $email = $_POST['email'] ?? '';
                    $password = $_POST['password'] ?? ''; // Password plain text
                    $role = $_POST['role'] ?? 'Administrator';

                    if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
                        $response['message'] = 'Valid name, email, and password are required.';
                        break;
                    }
                    
                    // *** PERUBAHAN UTAMA DI SINI ***
                    // Kita tidak lagi menggunakan password_hash()
                    $stmt = $conn->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $password, $role); // Simpan password sebagai plain text
                    
                    if ($stmt->execute()) {
                        $response = ['status' => 'success', 'message' => 'Admin added successfully (without hash).'];
                    } else {
                        $response['message'] = 'Failed to add admin. Email might already exist.';
                    }
                    $stmt->close();
                    break;

                case 'delete_admin':
                    $id = $_POST['id'] ?? 0;
                    if ($id == $_SESSION['admin_id']) {
                        $response['message'] = 'You cannot delete your own account.';
                        break;
                    }
                    if ($id > 0) {
                        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            $response = ['status' => 'success', 'message' => 'Admin deleted successfully.'];
                        } else {
                            $response['message'] = 'Failed to delete admin.';
                        }
                        $stmt->close();
                    } else {
                        $response['message'] = 'Invalid ID.';
                    }
                    break;

                default:
                    // Jika action tidak ditemukan setelah login
                    $response['message'] = "Unknown or invalid action for authenticated user.";
                    break;
            }
            break;
    }
}

$conn->close();
echo json_encode($response);
