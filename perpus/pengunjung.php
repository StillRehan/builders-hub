<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';
require_login();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $name = clean_input($_POST['name']);
        $id_number = clean_input($_POST['id_number']);
        $phone = clean_input($_POST['phone']);
        $email = clean_input($_POST['email']);
        $address = clean_input($_POST['address']);
        $member_type = clean_input($_POST['member_type']);
        $visit_date = clean_input($_POST['visit_date']);
        $visit_time = clean_input($_POST['visit_time']);
        $purpose = clean_input($_POST['purpose']);
        
        $stmt = $conn->prepare("INSERT INTO visitors (name, id_number, phone, email, address, member_type, visit_date, visit_time, purpose) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $name, $id_number, $phone, $email, $address, $member_type, $visit_date, $visit_time, $purpose);
        
        if ($stmt->execute()) {
            $success = "Data pengunjung berhasil ditambahkan!";
            log_activity($_SESSION['user_id'], 'add_visitor', "Menambah pengunjung: $name");
        } else {
            $error = "Gagal menambahkan data pengunjung!";
        }
        $stmt->close();
    } elseif ($_POST['action'] == 'delete') {
        $visitor_id = clean_input($_POST['visitor_id']);
        
        $stmt = $conn->prepare("DELETE FROM visitors WHERE visitor_id = ?");
        $stmt->bind_param("i", $visitor_id);
        
        if ($stmt->execute()) {
            $success = "Data pengunjung berhasil dihapus!";
            log_activity($_SESSION['user_id'], 'delete_visitor', "Menghapus pengunjung ID: $visitor_id");
        } else {
            $error = "Gagal menghapus data pengunjung!";
        }
        $stmt->close();
    }
}

// Get visitors data
$visitors_query = $conn->query("SELECT * FROM visitors ORDER BY visit_date DESC, visit_time DESC");

log_activity($_SESSION['user_id'], 'access_page', 'Mengakses halaman pengunjung');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengunjung | Perpustakaan </title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="bg-decoration">
        <div class="bg-circle bg-circle-1"></div>
        <div class="bg-circle bg-circle-2"></div>
    </div>

    <header>
        <div class="header-content">
            <a href="dashboard.php" class="logo">
                &#128218; Perpustakaan 
            </a>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="sistem_perpustakaan.php">Sistem Perpustakaan</a>
                <a href="pengunjung.php" class="active">Pengunjung</a>
                <a href="input_buku.php">Input Buku</a>
                <a href="keadaan_buku.php">Keadaan Buku</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-content">
            <div class="page-section">
                <h2>&#128101; Data Pengunjung Perpustakaan</h2>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">&#9989; <?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">&#9888; <?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" class="login-form" style="max-width: 100%; margin: 2rem 0;">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nama Lengkap *</label>
                            <input type="text" name="name" class="form-input" placeholder="Nama lengkap" required>
                        </div>

                        <div class="form-group">
                            <label>No. Identitas (KTP/NIM) *</label>
                            <input type="text" name="id_number" class="form-input" placeholder="Nomor identitas" required>
                        </div>

                        <div class="form-group">
                            <label>No. Telepon</label>
                            <input type="text" name="phone" class="form-input" placeholder="08xxxxxxxxxx">
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-input" placeholder="email@example.com">
                        </div>

                        <div class="form-group">
                            <label>Alamat</label>
                            <input type="text" name="address" class="form-input" placeholder="Alamat lengkap">
                        </div>

                        <div class="form-group">
                            <label>Jenis Anggota *</label>
                            <select name="member_type" class="form-input" required>
                                <option value="Mahasiswa">Mahasiswa</option>
                                <option value="Dosen">Dosen</option>
                                <option value="Umum">Umum</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Tanggal Kunjungan *</label>
                            <input type="date" name="visit_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Waktu Kunjungan *</label>
                            <input type="time" name="visit_time" class="form-input" value="<?php echo date('H:i'); ?>" required>
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Tujuan Kunjungan</label>
                            <input type="text" name="purpose" class="form-input" placeholder="Membaca, Meminjam Buku, dll">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="max-width: 300px;">Tambah Pengunjung</button>
                </form>
            </div>

            <div class="page-section">
                <h2>&#128203; Daftar Pengunjung</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>No. Identitas</th>
                                <th>Telepon</th>
                                <th>Jenis</th>
                                <th>Tgl Kunjungan</th>
                                <th>Waktu</th>
                                <th>Tujuan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($visitor = $visitors_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $visitor['visitor_id']; ?></td>
                                <td><?php echo htmlspecialchars($visitor['name']); ?></td>
                                <td><?php echo htmlspecialchars($visitor['id_number']); ?></td>
                                <td><?php echo htmlspecialchars($visitor['phone']); ?></td>
                                <td><?php echo $visitor['member_type']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($visitor['visit_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($visitor['visit_time'])); ?></td>
                                <td><?php echo htmlspecialchars($visitor['purpose']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="visitor_id" value="<?php echo $visitor['visitor_id']; ?>">
                                        <button type="submit" class="btn-danger" onclick="return confirm('Hapus data pengunjung ini?')">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>