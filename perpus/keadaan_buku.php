<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';
require_login();

$success = '';
$error = '';

// Handle update kondisi buku
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_condition') {
        $book_id = clean_input($_POST['book_id']);
        $condition_status = clean_input($_POST['condition_status']);
        $notes = clean_input($_POST['notes']);
        
        $stmt = $conn->prepare("UPDATE books SET condition_status = ? WHERE book_id = ?");
        $stmt->bind_param("si", $condition_status, $book_id);
        
        if ($stmt->execute()) {
            $success = "Kondisi buku berhasil diperbarui!";
            log_activity($_SESSION['user_id'], 'update_book_condition', "Memperbarui kondisi buku ID: $book_id - $notes");
        } else {
            $error = "Gagal memperbarui kondisi buku!";
        }
        $stmt->close();
    } elseif ($_POST['action'] == 'update_stock') {
        $book_id = clean_input($_POST['book_id']);
        $total_stock = clean_input($_POST['total_stock']);
        
        // Get current borrowed count
        $borrowed_query = $conn->prepare("SELECT COUNT(*) as count FROM borrowings WHERE book_id = ? AND status = 'Dipinjam'");
        $borrowed_query->bind_param("i", $book_id);
        $borrowed_query->execute();
        $borrowed_count = $borrowed_query->get_result()->fetch_assoc()['count'];
        
        $available = $total_stock - $borrowed_count;
        
        if ($available < 0) {
            $error = "Stok tidak boleh kurang dari jumlah buku yang sedang dipinjam ($borrowed_count)!";
        } else {
            $stmt = $conn->prepare("UPDATE books SET total_stock = ?, available_stock = ? WHERE book_id = ?");
            $stmt->bind_param("iii", $total_stock, $available, $book_id);
            
            if ($stmt->execute()) {
                $success = "Stok buku berhasil diperbarui!";
                log_activity($_SESSION['user_id'], 'update_book_stock', "Memperbarui stok buku ID: $book_id");
            } else {
                $error = "Gagal memperbarui stok buku!";
            }
            $stmt->close();
        }
        $borrowed_query->close();
    }
}

// Get statistics
$total_books = $conn->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'];
$good_condition = $conn->query("SELECT COUNT(*) as count FROM books WHERE condition_status = 'Baik'")->fetch_assoc()['count'];
$light_damage = $conn->query("SELECT COUNT(*) as count FROM books WHERE condition_status = 'Rusak Ringan'")->fetch_assoc()['count'];
$heavy_damage = $conn->query("SELECT COUNT(*) as count FROM books WHERE condition_status = 'Rusak Berat'")->fetch_assoc()['count'];

// Get books by category
$category_query = $conn->query("
    SELECT category, COUNT(*) as count, SUM(total_stock) as total_stock, SUM(available_stock) as available_stock 
    FROM books 
    GROUP BY category 
    ORDER BY count DESC
");

// Get all books
$books_query = $conn->query("SELECT * FROM books ORDER BY title");

log_activity($_SESSION['user_id'], 'access_page', 'Mengakses halaman keadaan buku');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keadaan Buku | Perpustakaan </title>
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
                <a href="input_buku.php">Input Buku</a>
                <a href="keadaan_buku.php" class="active">Keadaan Buku</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-content">
            <?php if ($success): ?>
                <div class="alert alert-success">&#9989; <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">&#9888; <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>&#128218; Total Buku</h3>
                    <div class="number"><?php echo $total_books; ?></div>
                    <p>Judul buku</p>
                </div>

                <div class="stat-card">
                    <h3>&#9989; Kondisi Baik</h3>
                    <div class="number" style="color: green;"><?php echo $good_condition; ?></div>
                    <p>Buku dalam kondisi baik</p>
                </div>

                <div class="stat-card">
                    <h3>&#9888; Rusak Ringan</h3>
                    <div class="number" style="color: orange;"><?php echo $light_damage; ?></div>
                    <p>Perlu perhatian</p>
                </div>

                <div class="stat-card">
                    <h3>&#10060; Rusak Berat</h3>
                    <div class="number" style="color: red;"><?php echo $heavy_damage; ?></div>
                    <p>Perlu perbaikan</p>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128202; Buku Per Kategori</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jumlah Judul</th>
                                <th>Total Stok</th>
                                <th>Tersedia</th>
                                <th>Dipinjam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($category = $category_query->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $category['category']; ?></strong></td>
                                <td><?php echo $category['count']; ?></td>
                                <td><?php echo $category['total_stock']; ?></td>
                                <td style="color: green;"><?php echo $category['available_stock']; ?></td>
                                <td style="color: orange;"><?php echo $category['total_stock'] - $category['available_stock']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128214; Detail Keadaan Setiap Buku</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Pengarang</th>
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
                                <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo $book['category']; ?></td>
                                <td>
                                    <form method="POST" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                        <input type="hidden" name="action" value="update_stock">
                                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                        <input type="number" name="total_stock" value="<?php echo $book['total_stock']; ?>" min="0" style="width: 60px; padding: 0.3rem; border: 1px solid var(--pink-light); border-radius: 5px;">
                                        <button type="submit" class="btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem;">Update</button>
                                    </form>
                                </td>
                                <td style="color: <?php echo $book['available_stock'] > 0 ? 'green' : 'red'; ?>;">
                                    <strong><?php echo $book['available_stock']; ?></strong>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                        <input type="hidden" name="action" value="update_condition">
                                        <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                        <select name="condition_status" style="padding: 0.3rem; border: 1px solid var(--pink-light); border-radius: 5px; background: white;">
                                            <option value="Baik" <?php echo $book['condition_status'] == 'Baik' ? 'selected' : ''; ?>>Baik</option>
                                            <option value="Rusak Ringan" <?php echo $book['condition_status'] == 'Rusak Ringan' ? 'selected' : ''; ?>>Rusak Ringan</option>
                                            <option value="Rusak Berat" <?php echo $book['condition_status'] == 'Rusak Berat' ? 'selected' : ''; ?>>Rusak Berat</option>
                                        </select>
                                        <input type="text" name="notes" placeholder="Catatan" style="width: 100px; padding: 0.3rem; border: 1px solid var(--pink-light); border-radius: 5px;">
                                        <button type="submit" class="btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem;">Update</button>
                                    </form>
                                </td>
                                <td><?php echo htmlspecialchars($book['location']); ?></td>
                                <td>
                                    <?php 
                                    $borrowed = $book['total_stock'] - $book['available_stock'];
                                    if ($borrowed > 0): ?>
                                        <span style="color: orange;">&#128260; <?php echo $borrowed; ?> dipinjam</span>
                                    <?php else: ?>
                                        <span style="color: green;">&#9989; Lengkap</span>
                                    <?php endif; ?>
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