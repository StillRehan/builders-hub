<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';
require_login();

$custom_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$date_condition = "DATE(b.borrow_date) = '$custom_date'";
$activity_date_condition = "DATE(a.created_at) = '$custom_date'";
$visitor_date_condition = "DATE(v.visit_date) = '$custom_date'";
$period_label = "Laporan Harian - " . date('d F Y', strtotime($custom_date));

// Get borrowing statistics
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN status = 'Dipinjam' THEN 1 ELSE 0 END) as active_borrows,
        SUM(CASE WHEN status = 'Dikembalikan' THEN 1 ELSE 0 END) as returned_books,
        SUM(CASE 
            WHEN status = 'Dipinjam' AND CURDATE() > due_date 
            THEN DATEDIFF(CURDATE(), due_date) * 1000
            ELSE fine_amount
        END) as total_fines,
        SUM(CASE WHEN fine_paid = 'Lunas' THEN fine_amount ELSE 0 END) as paid_fines
    FROM borrowings b
    WHERE $date_condition
")->fetch_assoc();

// Get visitor statistics
$visitor_stats = $conn->query("
    SELECT 
        COUNT(*) as total_visitors,
        SUM(CASE WHEN member_type = 'Mahasiswa' THEN 1 ELSE 0 END) as mahasiswa,
        SUM(CASE WHEN member_type = 'Dosen' THEN 1 ELSE 0 END) as dosen,
        SUM(CASE WHEN member_type = 'Umum' THEN 1 ELSE 0 END) as umum
    FROM visitors v
    WHERE $visitor_date_condition
")->fetch_assoc();

// Get category distribution
$category_query = $conn->query("
    SELECT 
        bk.category,
        COUNT(*) as count
    FROM borrowings b
    JOIN books bk ON b.book_id = bk.book_id
    WHERE $date_condition
    GROUP BY bk.category
    ORDER BY count DESC
");

// Get top borrowers
$top_borrowers_query = $conn->query("
    SELECT 
        v.name,
        v.member_type,
        COUNT(*) as borrow_count
    FROM borrowings b
    JOIN visitors v ON b.visitor_id = v.visitor_id
    WHERE $date_condition
    GROUP BY v.visitor_id
    ORDER BY borrow_count DESC
    LIMIT 5
");

// Get detailed transactions
$transactions_query = $conn->query("
    SELECT 
        b.borrow_id,
        b.borrow_date,
        b.due_date,
        b.return_date,
        b.status,
        b.fine_amount,
        b.fine_paid,
        v.name as visitor_name,
        v.id_number,
        v.member_type,
        bk.title as book_title,
        bk.author,
        bk.category,
        CASE 
            WHEN b.status = 'Dipinjam' AND CURDATE() > b.due_date 
            THEN DATEDIFF(CURDATE(), b.due_date) * 1000
            ELSE b.fine_amount
        END as calculated_fine
    FROM borrowings b
    JOIN visitors v ON b.visitor_id = v.visitor_id
    JOIN books bk ON b.book_id = bk.book_id
    WHERE $date_condition
    ORDER BY b.borrow_date DESC, b.borrow_id DESC
");

log_activity($_SESSION['user_id'], 'view_report', "Melihat laporan harian: $custom_date");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Harian | Perpustakaan</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .report-header {
            background: linear-gradient(135deg, var(--pink-medium), var(--maroon));
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .date-selector {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 20px rgba(133, 14, 53, 0.1);
        }
        .nav-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .nav-btn {
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--pink-light);
            border-radius: 10px;
            background: white;
            color: var(--maroon);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .nav-btn:hover {
            background: var(--pink-medium);
            color: white;
            border-color: var(--pink-medium);
        }
        .nav-btn.active {
            background: var(--pink-medium);
            color: white;
            border-color: var(--pink-medium);
        }
        @media print {
            header, .date-selector, .nav-buttons { display: none; }
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
            <a href="dashboard.php" class="logo">&#128218; Perpustakaan</a>
            <nav>
                <a href="dashboard.php">Dashboard</a>
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
            <div class="nav-buttons">
                <a href="laporan_harian.php?date=<?php echo date('Y-m-d'); ?>" class="nav-btn active">&#128198; Harian</a>
                <a href="laporan_mingguan.php?date=<?php echo date('Y-m-d'); ?>" class="nav-btn">&#128197; Mingguan</a>
                <a href="laporan_bulanan.php?date=<?php echo date('Y-m-d'); ?>" class="nav-btn">&#128196; Bulanan</a>
                <a href="laporan_tahunan.php?date=<?php echo date('Y-m-d'); ?>" class="nav-btn">&#128193; Tahunan</a>
            </div>

            <div class="report-header">
                <h1><?php echo $period_label; ?></h1>
                <p>Sistem Informasi Perpustakaan</p>
            </div>

            <div class="date-selector">
                <label style="font-weight: 600;">Pilih Tanggal:</label>
                <input type="date" id="custom_date" class="form-input" value="<?php echo $custom_date; ?>" style="max-width: 200px;">
                <button onclick="filterByDate()" class="btn-secondary">Tampilkan</button>
                <button onclick="window.print()" class="btn-primary" style="max-width: 200px;">&#128424; Cetak Laporan</button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>&#128202; Total Transaksi</h3>
                    <div class="number"><?php echo $stats_query['total_transactions']; ?></div>
                    <p>Peminjaman</p>
                </div>
                <div class="stat-card">
                    <h3>&#128214; Sedang Dipinjam</h3>
                    <div class="number" style="color: orange;"><?php echo $stats_query['active_borrows']; ?></div>
                    <p>Buku aktif</p>
                </div>
                <div class="stat-card">
                    <h3>&#9989; Dikembalikan</h3>
                    <div class="number" style="color: green;"><?php echo $stats_query['returned_books']; ?></div>
                    <p>Buku kembali</p>
                </div>
                <div class="stat-card">
                    <h3>&#128101; Pengunjung</h3>
                    <div class="number"><?php echo $visitor_stats['total_visitors']; ?></div>
                    <p>Total kunjungan</p>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128181; Informasi Denda</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div style="background: #ffe5e5; padding: 1.5rem; border-radius: 10px; border-left: 4px solid red;">
                        <h3 style="color: red; margin-bottom: 0.5rem;">Total Denda</h3>
                        <div style="font-size: 2rem; font-weight: 700; color: red;">
                            Rp <?php echo number_format($stats_query['total_fines'], 0, ',', '.'); ?>
                        </div>
                    </div>
                    <div style="background: #e5ffe5; padding: 1.5rem; border-radius: 10px; border-left: 4px solid green;">
                        <h3 style="color: green; margin-bottom: 0.5rem;">Sudah Dibayar</h3>
                        <div style="font-size: 2rem; font-weight: 700; color: green;">
                            Rp <?php echo number_format($stats_query['paid_fines'], 0, ',', '.'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128101; Statistik Pengunjung</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: 10px;">
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--maroon);"><?php echo $visitor_stats['mahasiswa']; ?></div>
                        <div>Mahasiswa</div>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: 10px;">
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--maroon);"><?php echo $visitor_stats['dosen']; ?></div>
                        <div>Dosen</div>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: 10px;">
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--maroon);"><?php echo $visitor_stats['umum']; ?></div>
                        <div>Umum</div>
                    </div>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128218; Distribusi Kategori Buku</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Jumlah Peminjaman</th>
                                <th>Persentase</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = $stats_query['total_transactions'];
                            if ($category_query->num_rows > 0):
                                while ($cat = $category_query->fetch_assoc()): 
                                    $percentage = $total > 0 ? ($cat['count'] / $total * 100) : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo $cat['category']; ?></strong></td>
                                <td><?php echo $cat['count']; ?></td>
                                <td><?php echo number_format($percentage, 1); ?>%</td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="3" style="text-align: center; padding: 2rem;">Tidak ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="page-section">
                <h2>&#127941; Top 5 Peminjam Hari Ini</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Peringkat</th>
                                <th>Nama</th>
                                <th>Jenis Anggota</th>
                                <th>Jumlah Peminjaman</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($top_borrowers_query->num_rows > 0):
                                $rank = 1;
                                while ($borrower = $top_borrowers_query->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><strong><?php echo $rank++; ?></strong></td>
                                <td><?php echo htmlspecialchars($borrower['name']); ?></td>
                                <td><?php echo $borrower['member_type']; ?></td>
                                <td><strong><?php echo $borrower['borrow_count']; ?></strong> buku</td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4" style="text-align: center; padding: 2rem;">Tidak ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128203; Detail Transaksi</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Waktu</th>
                                <th>Peminjam</th>
                                <th>Jenis</th>
                                <th>Buku</th>
                                <th>Kategori</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                                <th>Denda</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transactions_query->num_rows > 0): ?>
                                <?php while ($trans = $transactions_query->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $trans['borrow_id']; ?></td>
                                    <td><?php echo date('H:i', strtotime($trans['borrow_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($trans['visitor_name']); ?></strong><br>
                                        <small><?php echo $trans['id_number']; ?></small>
                                    </td>
                                    <td><?php echo $trans['member_type']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($trans['book_title']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($trans['author']); ?></small>
                                    </td>
                                    <td><?php echo $trans['category']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($trans['due_date'])); ?></td>
                                    <td>
                                        <span style="color: <?php echo $trans['status'] == 'Dipinjam' ? 'orange' : 'green'; ?>; font-weight: bold;">
                                            <?php echo $trans['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($trans['calculated_fine'] > 0): ?>
                                            <span style="color: red; font-weight: bold;">
                                                Rp <?php echo number_format($trans['calculated_fine'], 0, ',', '.'); ?>
                                            </span>
                                            <?php if ($trans['fine_paid'] == 'Lunas'): ?>
                                                <br><small style="color: green;">✓ Lunas</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: green;">Rp 0</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="9" style="text-align: center; padding: 2rem;">Tidak ada transaksi pada tanggal ini</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="page-section" style="text-align: center; background: linear-gradient(135deg, var(--pink-medium), var(--maroon)); color: white;">
                <p style="margin: 0;">Laporan dibuat pada: <?php echo date('d F Y, H:i'); ?> WIB</p>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Sistem Informasi Perpustakaan </p>
            </div>
        </div>
    </div>

    <script>
        function filterByDate() {
            const date = document.getElementById('custom_date').value;
            window.location.href = `?date=${date}`;
        }
    </script>
</body>
</html>