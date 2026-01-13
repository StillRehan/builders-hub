<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';
require_login();

$custom_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$year = date('Y', strtotime($custom_date));
$date_condition = "YEAR(b.borrow_date) = '$year'";
$visitor_date_condition = "YEAR(v.visit_date) = '$year'";
$period_label = "Laporan Tahunan - Tahun " . $year;

// Statistics
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

$visitor_stats = $conn->query("
    SELECT 
        COUNT(*) as total_visitors,
        SUM(CASE WHEN member_type = 'Mahasiswa' THEN 1 ELSE 0 END) as mahasiswa,
        SUM(CASE WHEN member_type = 'Dosen' THEN 1 ELSE 0 END) as dosen,
        SUM(CASE WHEN member_type = 'Umum' THEN 1 ELSE 0 END) as umum
    FROM visitors v
    WHERE $visitor_date_condition
")->fetch_assoc();

// Monthly breakdown for yearly report
$monthly_stats = $conn->query("
    SELECT 
        MONTH(b.borrow_date) as month_num,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Dipinjam' THEN 1 ELSE 0 END) as borrowed,
        SUM(CASE WHEN status = 'Dikembalikan' THEN 1 ELSE 0 END) as returned
    FROM borrowings b
    WHERE $date_condition
    GROUP BY MONTH(b.borrow_date)
    ORDER BY month_num
");

$category_query = $conn->query("
    SELECT bk.category, COUNT(*) as count
    FROM borrowings b
    JOIN books bk ON b.book_id = bk.book_id
    WHERE $date_condition
    GROUP BY bk.category
    ORDER BY count DESC
");

$top_borrowers_query = $conn->query("
    SELECT v.name, v.member_type, COUNT(*) as borrow_count
    FROM borrowings b
    JOIN visitors v ON b.visitor_id = v.visitor_id
    WHERE $date_condition
    GROUP BY v.visitor_id
    ORDER BY borrow_count DESC
    LIMIT 15
");

$top_books_query = $conn->query("
    SELECT bk.title, bk.author, bk.category, COUNT(*) as borrow_count
    FROM borrowings b
    JOIN books bk ON b.book_id = bk.book_id
    WHERE $date_condition
    GROUP BY b.book_id
    ORDER BY borrow_count DESC
    LIMIT 15
");

// Books added this year
$new_books = $conn->query("
    SELECT COUNT(*) as count
    FROM books
    WHERE YEAR(created_at) = '$year'
")->fetch_assoc();

log_activity($_SESSION['user_id'], 'view_report', "Melihat laporan tahunan: $year");

$months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Tahunan | Perpustakaan</title>
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
        .highlight-box {
            background: var(--cream);
            padding: 2rem;
            border-radius: 15px;
            border-left: 5px solid var(--pink-medium);
            margin: 2rem 0;
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
                <a href="laporan_harian.php?date=<?php echo date('Y-m-d'); ?>" class="nav-btn">&#128198; Harian</a>
                <a href="laporan_mingguan.php?date=<?php echo date('Y-m-d'); ?>" class="nav-btn">&#128197; Mingguan</a>
                <a href="laporan_bulanan.php?date=<?php echo date('Y-m-d'); ?>" class="nav-btn">&#128196; Bulanan</a>
                <a href="laporan_tahunan.php?date=<?php echo date('Y-m-d'); ?>" class="nav-btn active">&#128193; Tahunan</a>
            </div>

            <div class="report-header">
                <h1><?php echo $period_label; ?></h1>
                <p>Sistem Informasi Perpustakaan</p>
            </div>

            <div class="date-selector">
                <label style="font-weight: 600;">Pilih Tahun:</label>
                <select id="custom_year" class="form-input" style="max-width: 150px;">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <button onclick="filterByYear()" class="btn-secondary">Tampilkan</button>
                <button onclick="window.print()" class="btn-primary" style="max-width: 200px;">&#128424; Cetak Laporan</button>
            </div>

            <div class="highlight-box">
                <h2 style="color: var(--maroon); margin-bottom: 1rem;">&#127881; Ringkasan Tahun <?php echo $year; ?></h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div>
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--pink-medium);">
                            <?php echo number_format($stats_query['total_transactions']); ?>
                        </div>
                        <div style="font-weight: 600;">Total Transaksi Peminjaman</div>
                    </div>
                    <div>
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--pink-medium);">
                            <?php echo number_format($visitor_stats['total_visitors']); ?>
                        </div>
                        <div style="font-weight: 600;">Total Kunjungan</div>
                    </div>
                    <div>
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--pink-medium);">
                            <?php echo number_format($new_books['count']); ?>
                        </div>
                        <div style="font-weight: 600;">Buku Ditambahkan</div>
                    </div>
                    <div>
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--pink-medium);">
                            Rp <?php echo number_format($stats_query['paid_fines'], 0, ',', '.'); ?>
                        </div>
                        <div style="font-weight: 600;">Pendapatan Denda</div>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>&#128202; Total Transaksi</h3>
                    <div class="number"><?php echo $stats_query['total_transactions']; ?></div>
                    <p>Peminjaman tahun ini</p>
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
                    <p>Total tahun ini</p>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128197; Breakdown Bulanan</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Bulan</th>
                                <th>Total Transaksi</th>
                                <th>Dipinjam</th>
                                <th>Dikembalikan</th>
                                <th>Rata-rata/Hari</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($monthly_stats->num_rows > 0): ?>
                                <?php while ($month_data = $monthly_stats->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo $months[$month_data['month_num']]; ?></strong></td>
                                    <td><?php echo $month_data['total']; ?></td>
                                    <td style="color: orange;"><?php echo $month_data['borrowed']; ?></td>
                                    <td style="color: green;"><?php echo $month_data['returned']; ?></td>
                                    <td><?php echo number_format($month_data['total'] / 30, 1); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align: center; padding: 2rem;">Tidak ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128181; Informasi Denda Tahunan</h2>
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
                    <div style="background: #fff3cd; padding: 1.5rem; border-radius: 10px; border-left: 4px solid orange;">
                        <h3 style="color: #856404; margin-bottom: 0.5rem;">Belum Dibayar</h3>
                        <div style="font-size: 2rem; font-weight: 700; color: #856404;">
                            Rp <?php echo number_format($stats_query['total_fines'] - $stats_query['paid_fines'], 0, ',', '.'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-section">
                <h2>&#128101; Statistik Pengunjung</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div style="text-align: center; padding: 1.5rem; background: var(--cream); border-radius: 10px;">
                        <div style="font-size: 3rem; font-weight: 700; color: var(--maroon);"><?php echo $visitor_stats['mahasiswa']; ?></div>
                        <div style="font-weight: 600;">Mahasiswa</div>
                        <div style="font-size: 0.85rem; opacity: 0.7;">
                            <?php echo $visitor_stats['total_visitors'] > 0 ? number_format(($visitor_stats['mahasiswa'] / $visitor_stats['total_visitors']) * 100, 1) : 0; ?>%
                        </div>
                    </div>
                    <div style="text-align: center; padding: 1.5rem; background: var(--cream); border-radius: 10px;">
                        <div style="font-size: 3rem; font-weight: 700; color: var(--maroon);"><?php echo $visitor_stats['dosen']; ?></div>
                        <div style="font-weight: 600;">Dosen</div>
                        <div style="font-size: 0.85rem; opacity: 0.7;">
                            <?php echo $visitor_stats['total_visitors'] > 0 ? number_format(($visitor_stats['dosen'] / $visitor_stats['total_visitors']) * 100, 1) : 0; ?>%
                        </div>
                    </div>
                    <div style="text-align: center; padding: 1.5rem; background: var(--cream); border-radius: 10px;">
                        <div style="font-size: 3rem; font-weight: 700; color: var(--maroon);"><?php echo $visitor_stats['umum']; ?></div>
                        <div style="font-weight: 600;">Umum</div>
                        <div style="font-size: 0.85rem; opacity: 0.7;">
                            <?php echo $visitor_stats['total_visitors'] > 0 ? number_format(($visitor_stats['umum'] / $visitor_stats['total_visitors']) * 100, 1) : 0; ?>%
                        </div>
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
                                <td><?php echo number_format($cat['count']); ?></td>
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
                <h2>&#127941; Top 15 Peminjam Tahun <?php echo $year; ?></h2>
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
                                <td><strong style="font-size: 1.2rem; color: var(--pink-medium);"><?php echo $rank++; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($borrower['name']); ?></strong></td>
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
                <h2>&#11088; Top 15 Buku Terpopuler Tahun <?php echo $year; ?></h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Peringkat</th>
                                <th>Judul Buku</th>
                                <th>Pengarang</th>
                                <th>Kategori</th>
                                <th>Jumlah Peminjaman</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($top_books_query->num_rows > 0):
                                $rank = 1;
                                while ($book = $top_books_query->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><strong style="font-size: 1.2rem; color: var(--pink-medium);"><?php echo $rank++; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo $book['category']; ?></td>
                                <td><strong><?php echo $book['borrow_count']; ?></strong> kali</td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align: center; padding: 2rem;">Tidak ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="page-section" style="text-align: center; background: linear-gradient(135deg, var(--pink-medium), var(--maroon)); color: white;">
                <p style="margin: 0;">Laporan dibuat pada: <?php echo date('d F Y, H:i'); ?> WIB</p>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Sistem Informasi Perpustakaan</p>
            </div>
        </div>
    </div>

    <script>
        function filterByYear() {
            const year = document.getElementById('custom_year').value;
            window.location.href = `?date=${year}-01-01`;
        }
    </script>
</body>
</html>