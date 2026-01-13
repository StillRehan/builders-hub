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
        $isbn = clean_input($_POST['isbn']);
        $title = clean_input($_POST['title']);
        $author = clean_input($_POST['author']);
        $publisher = clean_input($_POST['publisher']);
        $year_published = clean_input($_POST['year_published']);
        $category = clean_input($_POST['category']);
        $total_stock = clean_input($_POST['total_stock']);
        $condition_status = clean_input($_POST['condition_status']);
        $location = clean_input($_POST['location']);
        
        // Check if ISBN already exists
        $check_stmt = $conn->prepare("SELECT book_id FROM books WHERE isbn = ?");
        $check_stmt->bind_param("s", $isbn);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "ISBN sudah terdaftar!";
        } else {
            $stmt = $conn->prepare("INSERT INTO books (isbn, title, author, publisher, year_published, category, total_stock, available_stock, condition_status, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssississ", $isbn, $title, $author, $publisher, $year_published, $category, $total_stock, $total_stock, $condition_status, $location);
            
            if ($stmt->execute()) {
                $success = "Buku berhasil ditambahkan!";
                log_activity($_SESSION['user_id'], 'add_book', "Menambah buku: $title");
            } else {
                $error = "Gagal menambahkan buku!";
            }
            $stmt->close();
        }
        $check_stmt->close();
    } elseif ($_POST['action'] == 'delete') {
        $book_id = clean_input($_POST['book_id']);
        
        // Check if book is currently borrowed
        $check_borrow = $conn->prepare("SELECT COUNT(*) as count FROM borrowings WHERE book_id = ? AND status = 'Dipinjam'");
        $check_borrow->bind_param("i", $book_id);
        $check_borrow->execute();
        $borrow_count = $check_borrow->get_result()->fetch_assoc()['count'];
        
        if ($borrow_count > 0) {
            $error = "Buku sedang dipinjam, tidak dapat dihapus!";
        } else {
            $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
            $stmt->bind_param("i", $book_id);
            
            if ($stmt->execute()) {
                $success = "Buku berhasil dihapus!";
                log_activity($_SESSION['user_id'], 'delete_book', "Menghapus buku ID: $book_id");
            } else {
                $error = "Gagal menghapus buku!";
            }
            $stmt->close();
        }
        $check_borrow->close();
    }
}

// Get books data
$books_query = $conn->query("SELECT * FROM books ORDER BY created_at DESC");

log_activity($_SESSION['user_id'], 'access_page', 'Mengakses halaman input buku');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Buku | Perpustakaan </title>
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
                <a href="pengunjung.php">Pengunjung</a>
                <a href="input_buku.php" class="active">Input Buku</a>
                <a href="keadaan_buku.php">Keadaan Buku</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-content">
            <div class="page-section">
                <h2>&#128214; Input Data Buku Baru</h2>
                
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
                            <label>ISBN *</label>
                            <input type="text" name="isbn" class="form-input" placeholder="978-xxx-xxxx-xx-x" required>
                        </div>

                        <div class="form-group">
                            <label>Judul Buku *</label>
                            <input type="text" name="title" class="form-input" placeholder="Judul buku" required>
                        </div>

                        <div class="form-group">
                            <label>Pengarang *</label>
                            <input type="text" name="author" class="form-input" placeholder="Nama pengarang" required>
                        </div>

                        <div class="form-group">
                            <label>Penerbit</label>
                            <input type="text" name="publisher" class="form-input" placeholder="Nama penerbit">
                        </div>

                        <div class="form-group">
                            <label>Tahun Terbit</label>
                            <input type="number" name="year_published" class="form-input" placeholder="2024" min="1900" max="2099">
                        </div>

                        <div class="form-group">
                            <label>Kategori *</label>
                            <select name="category" class="form-input" required>
                                <option value="">-- Pilih Kategori --</option>
                                <option value="Fiksi">Fiksi</option>
                                <option value="Non-Fiksi">Non-Fiksi</option>
                                <option value="Teknologi">Teknologi</option>
                                <option value="Sejarah">Sejarah</option>
                                <option value="Sains">Sains</option>
                                <option value="Agama">Agama</option>
                                <option value="Pendidikan">Pendidikan</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Jumlah Stok *</label>
                            <input type="number" name="total_stock" class="form-input" placeholder="0" min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Kondisi Buku *</label>
                            <select name="condition_status" class="form-input" required>
                                <option value="Baik">Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Lokasi Rak *</label>
                            <input type="text" name="location" class="form-input" placeholder="Rak A1" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="max-width: 300px;">Tambah Buku</button>
                </form>
            </div>

            <div class="page-section">
                <h2>&#128218; Daftar Buku</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ISBN</th>
                                <th>Judul</th>
                                <th>Pengarang</th>
                                <th>Penerbit</th>
                                <th>Tahun</th>
                                <th>Kategori</th>
                                <th>Stok Total</th>
                                <th>Tersedia</th>
                                <th>Kondisi</th>
                                <th>Lokasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($book = $books_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo htmlspecialchars($book['publisher']); ?></td>
                                <td><?php echo $book['year_published']; ?></td>
                                <td><?php echo $book['category']; ?></td>
                                <td><?php echo $book['total_stock']; ?></td>
                                <td><?php echo $book['available_stock']; ?></td>
                                <td>
                                    <span style="color: <?php echo $book['condition_status'] == 'Baik' ? 'green' : ($book['condition_status'] == 'Rusak Ringan' ? 'orange' : 'red'); ?>;">
                                        <?php echo $book['condition_status']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($book['location']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                        <button type="submit" class="btn-danger" onclick="return confirm('Hapus buku ini?')">Hapus</button>
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