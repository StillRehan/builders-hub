<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';
require_login();

$user_name = $_SESSION['full_name'] ?? $_SESSION['username'];

// Get statistics
$total_books_query = $conn->query("SELECT COUNT(*) as total FROM books");
$total_books = $total_books_query->fetch_assoc()['total'];

$available_books_query = $conn->query("SELECT SUM(available_stock) as total FROM books");
$available_books = $available_books_query->fetch_assoc()['total'] ?? 0;

$total_visitors_query = $conn->query("SELECT COUNT(*) as total FROM visitors WHERE DATE(visit_date) = CURDATE()");
$total_visitors = $total_visitors_query->fetch_assoc()['total'];

$borrowed_books_query = $conn->query("SELECT COUNT(*) as total FROM borrowings WHERE status = 'Dipinjam'");
$borrowed_books = $borrowed_books_query->fetch_assoc()['total'];

// Get unpaid fines statistics
$unpaid_fines_query = $conn->query("
    SELECT 
        COUNT(*) as count,
        SUM(CASE 
            WHEN status = 'Dipinjam' AND CURDATE() > due_date 
            THEN DATEDIFF(CURDATE(), due_date) * 1000
            ELSE fine_amount
        END) as total_amount
    FROM borrowings 
    WHERE fine_paid != 'Lunas' AND (
        (status = 'Dipinjam' AND CURDATE() > due_date) OR 
        (status = 'Dikembalikan' AND fine_amount > 0)
    )
");
$unpaid_fines = $unpaid_fines_query->fetch_assoc();

log_activity($_SESSION['user_id'], 'access_page', 'Mengakses dashboard');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Perpustakaan </title>
    <link rel="stylesheet" href="style.css">
    <style>
        .report-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        .report-btn {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-decoration: none;
            color: var(--maroon);
            box-shadow: 0 5px 20px rgba(133, 14, 53, 0.1);
            transition: all 0.3s ease;
            text-align: center;
            border: 2px solid var(--pink-light);
        }
        .report-btn:hover {
            transform: translateY(-5px);
            border-color: var(--pink-medium);
            box-shadow: 0 8px 25px rgba(133, 14, 53, 0.2);
        }
        .report-btn h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--pink-medium);
        }
        .report-btn p {
            font-size: 0.9rem;
            color: var(--maroon);
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
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="sistem_perpustakaan.php">Sistem Perpustakaan</a>
                <a href="pengunjung.php">Pengunjung</a>
                <a href="input_buku.php">Input Buku</a>
                <a href="keadaan_buku.php">Keadaan Buku</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-content">
            <div class="welcome-section">
                <h1>Selamat Datang, <?php echo htmlspecialchars($user_name); ?>! &#128075;</h1>
                <p>Sistem Informasi Perpustakaan - Dashboard Utama</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>&#128218; Total Buku</h3>
                    <div class="number"><?php echo $total_books; ?></div>
                    <p>Judul buku terdaftar</p>
                </div>

                <div class="stat-card">
                    <h3>&#9989; Buku Tersedia</h3>
                    <div class="number"><?php echo $available_books; ?></div>
                    <p>Siap dipinjam</p>
                </div>

                <div class="stat-card">
                    <h3>&#128101; Pengunjung Hari Ini</h3>
                    <div class="number"><?php echo $total_visitors; ?></div>
                    <p>Kunjungan hari ini</p>
                </div>

                <div class="stat-card">
                    <h3>&#128214; Buku Dipinjam</h3>
                    <div class="number"><?php echo $borrowed_books; ?></div>
                    <p>Sedang dipinjam</p>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128202; Laporan Aktivitas Transaksi Perpustakaan</h2>
                <p style="margin-bottom: 1rem; color: var(--maroon);">Pilih periode laporan yang ingin Anda lihat:</p>
                
                <div class="report-buttons">
                    <a href="laporan_harian.php?date=<?php echo date('Y-m-d'); ?>" class="report-btn">
                        <h3>&#128198; Harian</h3>
                        <p>Laporan transaksi harian</p>
                    </a>
                    <a href="laporan_mingguan.php?date=<?php echo date('Y-m-d'); ?>" class="report-btn">
                        <h3>&#128197; Mingguan</h3>
                        <p>Laporan transaksi mingguan</p>
                    </a>
                    <a href="laporan_bulanan.php?date=<?php echo date('Y-m-d'); ?>" class="report-btn">
                        <h3>&#128196; Bulanan</h3>
                        <p>Laporan transaksi bulanan</p>
                    </a>
                    <a href="laporan_tahunan.php?date=<?php echo date('Y-m-d'); ?>" class="report-btn">
                        <h3>&#128193; Tahunan</h3>
                        <p>Laporan transaksi tahunan</p>
                    </a>
                </div>
            </div>

            <?php if ($unpaid_fines['count'] > 0): ?>
            <div class="page-section" style="background: #fff3cd; border: 2px solid #ffc107;">
                <h2 style="color: #856404;">&#9888; Peringatan Denda</h2>
                <p style="font-size: 1.1rem; color: #856404;">
                    Terdapat <strong><?php echo $unpaid_fines['count']; ?> peminjaman</strong> dengan denda yang belum dibayar
                    <br>
                    Total Denda: <strong style="font-size: 1.3rem;">Rp <?php echo number_format($unpaid_fines['total_amount'], 0, ',', '.'); ?></strong>
                </p>
                <a href="sistem_perpustakaan.php" class="btn-primary" style="display: inline-block; max-width: 250px; margin-top: 1rem;">
                    Lihat Detail Peminjaman
                </a>
            </div>
            <?php endif; ?>

            <div class="page-section">
                <h2>Aktivitas Terbaru</h2>
                <?php
                $activity_query = $conn->query("
                    SELECT a.*, u.full_name 
                    FROM activity_log a 
                    JOIN users u ON a.user_id = u.user_id 
                    ORDER BY a.created_at DESC 
                    LIMIT 10
                ");
                ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>User</th>
                                <th>Aktivitas</th>
                                <th>Deskripsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($activity = $activity_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($activity['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($activity['activity_type']); ?></td>
                                <td><?php echo htmlspecialchars($activity['description']); ?></td>
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