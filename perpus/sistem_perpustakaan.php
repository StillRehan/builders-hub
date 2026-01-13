<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';
require_login();

$user_name = $_SESSION['full_name'] ?? $_SESSION['username'];

// Handle peminjaman buku
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'borrow') {
        $visitor_id = clean_input($_POST['visitor_id']);
        $book_id = clean_input($_POST['book_id']);
        $borrow_date = clean_input($_POST['borrow_date']);
        $due_date = clean_input($_POST['due_date']);
        
        // Check stock
        $check_stock = $conn->prepare("SELECT available_stock FROM books WHERE book_id = ?");
        $check_stock->bind_param("i", $book_id);
        $check_stock->execute();
        $stock = $check_stock->get_result()->fetch_assoc();
        
        if ($stock['available_stock'] > 0) {
            $stmt = $conn->prepare("INSERT INTO borrowings (visitor_id, book_id, borrow_date, due_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $visitor_id, $book_id, $borrow_date, $due_date);
            
            if ($stmt->execute()) {
                // Update stock
                $update_stock = $conn->prepare("UPDATE books SET available_stock = available_stock - 1 WHERE book_id = ?");
                $update_stock->bind_param("i", $book_id);
                $update_stock->execute();
                
                $success = "Peminjaman berhasil dicatat!";
                log_activity($_SESSION['user_id'], 'borrow_book', "Mencatat peminjaman buku ID: $book_id");
            } else {
                $error = "Gagal mencatat peminjaman!";
            }
        } else {
            $error = "Stok buku tidak tersedia!";
        }
    } elseif ($_POST['action'] == 'return') {
        $borrow_id = clean_input($_POST['borrow_id']);
        $return_date = date('Y-m-d');
        
        // Get borrowing details
        $get_borrow = $conn->prepare("SELECT book_id, due_date FROM borrowings WHERE borrow_id = ?");
        $get_borrow->bind_param("i", $borrow_id);
        $get_borrow->execute();
        $borrow_data = $get_borrow->get_result()->fetch_assoc();
        
        // Calculate fine
        $due_date = new DateTime($borrow_data['due_date']);
        $return_date_obj = new DateTime($return_date);
        $interval = $due_date->diff($return_date_obj);
        $days_late = $interval->days;
        
        $fine_amount = 0;
        if ($return_date_obj > $due_date) {
            $fine_amount = $days_late * 1000; // Denda Rp 1000 per hari
        }
        
        $stmt = $conn->prepare("UPDATE borrowings SET status = 'Dikembalikan', return_date = ?, fine_amount = ? WHERE borrow_id = ?");
        $stmt->bind_param("sii", $return_date, $fine_amount, $borrow_id);
        
        if ($stmt->execute()) {
            // Update stock
            $update_stock = $conn->prepare("UPDATE books SET available_stock = available_stock + 1 WHERE book_id = ?");
            $update_stock->bind_param("i", $borrow_data['book_id']);
            $update_stock->execute();
            
            if ($fine_amount > 0) {
                $success = "Buku berhasil dikembalikan! Denda keterlambatan: Rp " . number_format($fine_amount, 0, ',', '.');
            } else {
                $success = "Buku berhasil dikembalikan tepat waktu!";
            }
            log_activity($_SESSION['user_id'], 'return_book', "Mencatat pengembalian buku ID: {$borrow_data['book_id']}");
        }
    } elseif ($_POST['action'] == 'pay_fine') {
        $borrow_id = clean_input($_POST['borrow_id']);
        
        $stmt = $conn->prepare("UPDATE borrowings SET fine_paid = 'Lunas' WHERE borrow_id = ?");
        $stmt->bind_param("i", $borrow_id);
        
        if ($stmt->execute()) {
            $success = "Pembayaran denda berhasil dicatat!";
            log_activity($_SESSION['user_id'], 'pay_fine', "Pembayaran denda untuk peminjaman ID: $borrow_id");
        }
    }
}

// Get borrowings data with fine calculation
$borrowings_query = $conn->query("
    SELECT b.*, v.name as visitor_name, bk.title as book_title, bk.author,
    CASE 
        WHEN b.status = 'Dipinjam' AND CURDATE() > b.due_date 
        THEN DATEDIFF(CURDATE(), b.due_date) * 1000
        ELSE b.fine_amount
    END as calculated_fine,
    CASE 
        WHEN b.status = 'Dipinjam' AND CURDATE() > b.due_date 
        THEN DATEDIFF(CURDATE(), b.due_date)
        ELSE 0
    END as days_late
    FROM borrowings b
    JOIN visitors v ON b.visitor_id = v.visitor_id
    JOIN books bk ON b.book_id = bk.book_id
    ORDER BY b.borrow_date DESC
");

// Get visitors for dropdown
$visitors_query = $conn->query("SELECT visitor_id, name, id_number FROM visitors ORDER BY name");

// Get available books for dropdown
$books_query = $conn->query("SELECT book_id, title, author, available_stock FROM books WHERE available_stock > 0 ORDER BY title");

log_activity($_SESSION['user_id'], 'access_page', 'Mengakses halaman sistem perpustakaan');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Perpustakaan | Perpustakaan </title>
    <link rel="stylesheet" href="style.css">
    <style>
        .fine-warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: bold;
        }
        .fine-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: bold;
        }
        .fine-paid {
            background-color: #d4edda;
            color: #155724;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
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
                <a href="sistem_perpustakaan.php" class="active">Sistem Perpustakaan</a>
                <a href="pengunjung.php">Pengunjung</a>
                <a href="input_buku.php">Input Buku</a>
                <a href="keadaan_buku.php">Keadaan Buku</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-content">
            <div class="page-section">
                <h2>&#128214; Sistem Peminjaman & Pengembalian Buku</h2>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">&#9989; <?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">&#9888; <?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" class="login-form" style="max-width: 800px; margin: 2rem 0;">
                    <input type="hidden" name="action" value="borrow">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Pilih Pengunjung</label>
                            <select name="visitor_id" class="form-input" required>
                                <option value="">-- Pilih Pengunjung --</option>
                                <?php while ($visitor = $visitors_query->fetch_assoc()): ?>
                                <option value="<?php echo $visitor['visitor_id']; ?>">
                                    <?php echo htmlspecialchars($visitor['name'] . ' - ' . $visitor['id_number']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Pilih Buku</label>
                            <select name="book_id" class="form-input" required>
                                <option value="">-- Pilih Buku --</option>
                                <?php while ($book = $books_query->fetch_assoc()): ?>
                                <option value="<?php echo $book['book_id']; ?>">
                                    <?php echo htmlspecialchars($book['title'] . ' - ' . $book['author'] . ' (Stok: ' . $book['available_stock'] . ')'); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Tanggal Pinjam</label>
                            <input type="date" name="borrow_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Tanggal Kembali</label>
                            <input type="date" name="due_date" class="form-input" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="max-width: 300px;">Catat Peminjaman</button>
                </form>
            </div>

            <div class="page-section">
                <h2>&#128203; Daftar Peminjaman</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pengunjung</th>
                                <th>Judul Buku</th>
                                <th>Pengarang</th>
                                <th>Tgl Pinjam</th>
                                <th>Tgl Kembali</th>
                                <th>Status</th>
                                <th>Denda</th>
                                <th>Status Bayar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($borrow = $borrowings_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $borrow['borrow_id']; ?></td>
                                <td><?php echo htmlspecialchars($borrow['visitor_name']); ?></td>
                                <td><?php echo htmlspecialchars($borrow['book_title']); ?></td>
                                <td><?php echo htmlspecialchars($borrow['author']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($borrow['borrow_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($borrow['due_date'])); ?></td>
                                <td>
                                    <span style="color: <?php echo $borrow['status'] == 'Dipinjam' ? 'orange' : 'green'; ?>; font-weight: bold;">
                                        <?php echo $borrow['status']; ?>
                                    </span>
                                    <?php if ($borrow['days_late'] > 0): ?>
                                        <br><small style="color: red;">Terlambat <?php echo $borrow['days_late']; ?> hari</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($borrow['calculated_fine'] > 0): ?>
                                        <span class="<?php echo $borrow['status'] == 'Dipinjam' ? 'fine-danger' : 'fine-warning'; ?>">
                                            Rp <?php echo number_format($borrow['calculated_fine'], 0, ',', '.'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: green;">Rp 0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($borrow['calculated_fine'] > 0): ?>
                                        <?php if ($borrow['fine_paid'] == 'Lunas'): ?>
                                            <span class="fine-paid">✓ Lunas</span>
                                        <?php else: ?>
                                            <span style="color: red; font-weight: bold;">Belum Bayar</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: gray;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($borrow['status'] == 'Dipinjam'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="return">
                                        <input type="hidden" name="borrow_id" value="<?php echo $borrow['borrow_id']; ?>">
                                        <button type="submit" class="btn-success" onclick="return confirm('Kembalikan buku ini?')">Kembalikan</button>
                                    </form>
                                    <?php elseif ($borrow['calculated_fine'] > 0 && $borrow['fine_paid'] != 'Lunas'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="pay_fine">
                                        <input type="hidden" name="borrow_id" value="<?php echo $borrow['borrow_id']; ?>">
                                        <button type="submit" class="btn-secondary" onclick="return confirm('Konfirmasi pembayaran denda Rp <?php echo number_format($borrow['calculated_fine'], 0, ',', '.'); ?>?')">Bayar Denda</button>
                                    </form>
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