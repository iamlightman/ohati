<?php
// admin/bookings.php - Ohati Admin Bookings Management
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Handle AJAX actions (update status, update payment status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $bid = intval($input['booking_id'] ?? 0);
    $action = $input['action'] ?? '';
    
    if ($bid > 0) {
        if ($action === 'confirm') {
            $pdo->prepare("UPDATE bookings SET status = 'Confirmed' WHERE id = ?")->execute([$bid]);
            echo json_encode(['success' => true]);
            exit;
        } elseif ($action === 'cancel') {
            $pdo->prepare("UPDATE bookings SET status = 'Cancelled' WHERE id = ?")->execute([$bid]);
            echo json_encode(['success' => true]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Fetch bookings with search, status, and date range filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql_base = "
    FROM bookings b 
    LEFT JOIN vendors v ON b.vendor_id = v.id 
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql_base .= " AND (b.user_name LIKE ? OR b.user_phone LIKE ? OR v.name LIKE ? OR b.package_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== '') {
    $sql_base .= " AND b.status = ?";
    $params[] = $status;
}

if ($date_from !== '') {
    $sql_base .= " AND b.event_date >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $sql_base .= " AND b.event_date <= ?";
    $params[] = $date_to;
}

// Count total
$count_query = "SELECT COUNT(*) " . $sql_base;
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));

$sql = "SELECT b.*, v.name as vendor_name " . $sql_base . " ORDER BY b.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Bookings Management</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Mobile responsive overrides for admin panel */
        @media(max-width: 900px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                display: flex !important;
                box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .admin-main {
                margin-left: 0 !important;
            }
            .admin-stat-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media(max-width: 600px) {
            .admin-stat-grid {
                grid-template-columns: 1fr !important;
            }
            .admin-topbar {
                padding: 12px 16px !important;
            }
            .admin-content {
                padding: 16px !important;
            }
        }
        .admin-sidebar-logo img {
            height: 36px;
            width: auto;
            object-fit: contain;
            border-radius: 0;
        }
        .admin-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        .pagination-buttons {
            display: flex;
            gap: 6px;
        }
        .pagination-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #E4E7ED;
            background: #fff;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pagination-btn:hover:not(.disabled) {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .pagination-btn.active {
            background: var(--accent);
            color: var(--primary-dark);
            border-color: var(--accent);
            cursor: default;
        }
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            color: var(--gray-400);
        }
        .admin-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--primary);
            cursor: pointer;
            padding: 8px;
        }
        @media(max-width: 900px) {
            .admin-menu-toggle {
                display: block;
            }
        }
        .admin-sidebar-close {
            display: none;
            background: none;
            border: none;
            color: rgba(255,255,255,0.6);
            font-size: 1.25rem;
            cursor: pointer;
            margin-left: auto;
            padding: 4px;
        }
        @media(max-width: 900px) {
            .admin-sidebar-close {
                display: block;
            }
        }
    </style>
</head>
<body class="admin-layout">

    <!-- Admin Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Admin Main Body -->
    <main class="admin-main">
        <!-- Top Bar -->
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
                <h1 class="admin-page-title">Bookings & Inquiries</h1>
            </div>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-circle-user" style="font-size:1.2rem; color:var(--accent);"></i>
                <span>System Administrator</span>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="admin-content">

            <!-- Filter Controls -->
            <div class="card mb-20" style="background:#fff; border:1px solid #E4E7ED; border-radius:16px; padding:16px;">
                <form method="GET" action="bookings.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                    <div style="flex:2; min-width:200px;">
                        <input type="text" name="search" class="form-input" placeholder="Search by customer, vendor, package..." value="<?= htmlspecialchars($search) ?>" style="margin:0; padding:10px 14px;">
                    </div>
                    <div style="flex:1; min-width:140px;">
                        <select name="status" class="form-select" style="margin:0; padding:10px 14px; width:100%;">
                            <option value="">All Statuses</option>
                            <option value="Inquiry" <?= ($status === 'Inquiry') ? 'selected' : '' ?>>Inquiry</option>
                            <option value="Confirmed" <?= ($status === 'Confirmed') ? 'selected' : '' ?>>Confirmed</option>
                            <option value="Completed" <?= ($status === 'Completed') ? 'selected' : '' ?>>Completed</option>
                            <option value="Cancelled" <?= ($status === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div style="flex:1; min-width:140px;">
                        <input type="date" name="date_from" class="form-input" title="From Event Date" value="<?= htmlspecialchars($date_from) ?>" style="margin:0; padding:10px 14px; width:100%;">
                    </div>
                    <div style="flex:1; min-width:140px;">
                        <input type="date" name="date_to" class="form-input" title="To Event Date" value="<?= htmlspecialchars($date_to) ?>" style="margin:0; padding:10px 14px; width:100%;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:10px 20px; font-weight:700;"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                    <a href="bookings.php" class="btn btn-outline" style="padding:10px 20px; font-weight:700;">Reset</a>
                </form>
            </div>

            <!-- Bookings Table -->
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Info</th>
                            <th>Vendor Service</th>
                            <th>Event Details</th>
                            <th>Total Price</th>
                            <th>Booking Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:40px; color:var(--gray-400);">No bookings found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr id="row-<?= $b['id'] ?>">
                                    <td style="font-weight:700;">#<?= $b['id'] ?></td>
                                    <td>
                                        <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($b['user_name']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--gray-500);"><?= htmlspecialchars($b['user_phone']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($b['vendor_name'] ?: 'Unknown Vendor') ?></div>
                                        <div style="font-size:0.72rem; color:var(--gray-500);"><?= htmlspecialchars($b['package_name'] ?: 'Custom Package') ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= htmlspecialchars($b['event_type']) ?></div>
                                        <div style="font-size:0.72rem; color:var(--gray-500);"><i class="fa-solid fa-calendar-day" style="font-size:0.7rem; color:var(--gray-400);"></i> <?= htmlspecialchars($b['event_date']) ?></div>
                                    </td>
                                    <td style="font-weight:800; color:var(--primary);">
                                        GH₵ <?= number_format($b['price'], 2) ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = 'status-pending';
                                        $s = strtolower($b['status']);
                                        if ($s === 'confirmed' || $s === 'completed') $statusClass = 'status-confirmed';
                                        elseif ($s === 'cancelled' || $s === 'rejected') $statusClass = 'status-cancelled';
                                        ?>
                                        <span id="status-badge-<?= $b['id'] ?>" class="booking-status <?= $statusClass ?>" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; text-transform:capitalize;">
                                            <?= htmlspecialchars($b['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <button class="btn btn-outline btn-sm" style="padding:6px 10px; font-size:0.75rem; font-weight:700;" onclick='viewBookingDetails(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Booking Details"><i class="fa-solid fa-eye"></i> Details</button>
                                            <?php if ($s !== 'confirmed' && $s !== 'completed' && $s !== 'cancelled'): ?>
                                                <button id="btn-confirm-<?= $b['id'] ?>" class="btn btn-primary btn-sm" style="padding:6px; font-size:0.75rem;" onclick="updateStatus(<?= $b['id'] ?>, 'confirm')"><i class="fa-solid fa-check"></i> Confirm</button>
                                            <?php endif; ?>
                                            <?php if ($s !== 'cancelled'): ?>
                                                <button id="btn-cancel-<?= $b['id'] ?>" class="btn btn-outline btn-sm" style="padding:6px; font-size:0.75rem; color:var(--rose); border-color:rgba(244,63,94,0.2);" onclick="updateStatus(<?= $b['id'] ?>, 'cancel')"><i class="fa-solid fa-ban"></i> Cancel</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination UI -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div>
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_items) ?> of <?= $total_items ?> bookings
                    </div>
                    <div class="pagination-buttons">
                        <!-- Prev button -->
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="pagination-btn <?= $page == 1 ? 'disabled' : '' ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                        
                        <!-- Page numbers -->
                        <?php 
                        $start_range = max(1, $page - 2);
                        $end_range = min($total_pages, $page + 2);
                        if ($start_range > 1) {
                            echo '<a href="?page=1&search='.urlencode($search).'&status='.urlencode($status).'" class="pagination-btn">1</a>';
                            if ($start_range > 2) echo '<span style="padding:6px;">...</span>';
                        }
                        for ($i = $start_range; $i <= $end_range; $i++) {
                            $active_cls = $i == $page ? 'active' : '';
                            echo '<a href="?page='.$i.'&search='.urlencode($search).'&status='.urlencode($status).'" class="pagination-btn '.$active_cls.'">'.$i.'</a>';
                        }
                        if ($end_range < $total_pages) {
                            if ($end_range < $total_pages - 1) echo '<span style="padding:6px;">...</span>';
                            echo '<a href="?page='.$total_pages.'&search='.urlencode($search).'&status='.urlencode($status).'" class="pagination-btn">'.$total_pages.'</a>';
                        }
                        ?>

                        <!-- Next button -->
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="pagination-btn <?= $page == $total_pages ? 'disabled' : '' ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Scripts -->
    <script>
        function toggleSidebar(open) {
            const sidebar = document.querySelector('.admin-sidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
        }

        function updateStatus(bookingId, action) {
            let confirmMsg = `Are you sure you want to perform this action on Booking #${bookingId}?`;
            if (action === 'cancel') confirmMsg = `Are you sure you want to cancel Booking #${bookingId}?`;
            
            if (!confirm(confirmMsg)) return;

            fetch('bookings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: bookingId, action: action })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (action === 'confirm') {
                        const statusBadge = document.getElementById('status-badge-' + bookingId);
                        if (statusBadge) {
                            statusBadge.className = 'booking-status status-confirmed';
                            statusBadge.textContent = 'Confirmed';
                        }
                        const confirmBtn = document.getElementById('btn-confirm-' + bookingId);
                        if (confirmBtn) confirmBtn.remove();
                    } else if (action === 'cancel') {
                        const statusBadge = document.getElementById('status-badge-' + bookingId);
                        if (statusBadge) {
                            statusBadge.className = 'booking-status status-cancelled';
                            statusBadge.textContent = 'Cancelled';
                        }
                        const confirmBtn = document.getElementById('btn-confirm-' + bookingId);
                        if (confirmBtn) confirmBtn.remove();
                        const cancelBtn = document.getElementById('btn-cancel-' + bookingId);
                        if (cancelBtn) cancelBtn.remove();
                    }
                } else {
                    alert('Action failed: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Communication error.');
            });
        }

        function viewBookingDetails(b) {
            const content = document.getElementById('bookingDetailsContent');
            if (!content) return;

            content.innerHTML = `
                <div style="text-align:center; margin-bottom:16px;">
                    <div style="font-size:1.6rem; color:var(--primary);"><i class="fa-solid fa-calendar-check"></i></div>
                    <h4 style="margin:4px 0 0 0; font-size:1.1rem; font-weight:800; color:var(--primary);">Booking #${b.id}</h4>
                    <div style="font-size:0.75rem; color:var(--gray-600); margin-top:2px;">Event: <strong>${b.event_type || 'Event'}</strong> | Package: ${b.package_name || 'Standard'}</div>
                    <span class="booking-status ${b.status === 'confirmed' || b.status === 'completed' ? 'status-confirmed' : (b.status === 'cancelled' ? 'status-cancelled' : 'status-pending')}" style="font-size:0.65rem; padding:4px 10px; border-radius:20px; text-transform:uppercase; font-weight:700; display:inline-block; margin-top:6px;">${b.status}</span>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.8rem; background:var(--gray-50); padding:14px; border-radius:12px; border:1px solid #E5E7EB; margin-bottom:16px;">
                    <div><strong>Customer Name:</strong> ${b.customer_name || 'N/A'}</div>
                    <div><strong>Customer Phone:</strong> ${b.customer_phone || 'N/A'}</div>
                    <div><strong>Customer Email:</strong> ${b.customer_email || 'N/A'}</div>
                    <div><strong>Vendor Name:</strong> ${b.vendor_name || 'N/A'}</div>
                    <div><strong>Vendor Phone:</strong> ${b.vendor_phone || 'N/A'}</div>
                    <div><strong>Vendor Email:</strong> ${b.vendor_email || 'N/A'}</div>
                    <div><strong>Event Date:</strong> 📅 ${b.event_date || 'N/A'}</div>
                    <div><strong>Proposed Price:</strong> <span style="font-weight:800; color:var(--primary);">GH₵ ${parseFloat(b.price || 0).toFixed(2)}</span></div>
                    <div><strong>Created At:</strong> ${b.created_at || 'N/A'}</div>
                    <div><strong>Payment Status:</strong> ${b.payment_status || 'Pending'}</div>
                    <div style="grid-column: span 2;"><strong>Special Notes:</strong> ${b.notes || 'No special requests submitted.'}</div>
                </div>

                <button class="btn btn-outline btn-full" onclick="closeBookingDetailsModal()" style="font-weight:700;">Close</button>
            `;

            document.getElementById('bookingDetailsModal').style.display = 'flex';
        }

        function closeBookingDetailsModal() {
            document.getElementById('bookingDetailsModal').style.display = 'none';
        }
    </script>

    <!-- Booking Details Modal -->
    <div id="bookingDetailsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; width:90%; max-width:560px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E5E7EB; padding-bottom:12px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">
                    <i class="fa-solid fa-calendar-days" style="color:var(--accent);"></i> Booking Order Full Specification
                </h3>
                <button onclick="closeBookingDetailsModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--gray-500);">&times;</button>
            </div>
            <div id="bookingDetailsContent"></div>
        </div>
    </div>
</body>
</html>
