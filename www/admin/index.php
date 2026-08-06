<?php
// admin/index.php - Ohati Administrator Dashboard Overview
require_once __DIR__ . '/../db.php';
session_start();
require_once __DIR__ . '/auth_guard.php';

// Fetch statistics
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_vendors = $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pending_kyc = $pdo->query("SELECT COUNT(*) FROM users WHERE kyc_status = 'pending_verification'")->fetchColumn();

// Fetch recent bookings with vendor details
$recent_bookings = $pdo->query("
    SELECT b.*, v.name as vendor_name 
    FROM bookings b 
    LEFT JOIN vendors v ON b.vendor_id = v.id 
    ORDER BY b.id DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ohati Admin - Dashboard Overview</title>
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
                <h1 class="admin-page-title">Dashboard Overview</h1>
            </div>
            <div style="font-size:0.8rem; font-weight:600; color:var(--gray-600); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-circle-user" style="font-size:1.2rem; color:var(--accent);"></i>
                <span>System Administrator</span>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="admin-content">
            
            <!-- Statistics Grid -->
            <div class="admin-stat-grid">
                <div class="admin-stat-card">
                    <div class="admin-stat-label">Total Users</div>
                    <div class="admin-stat-value"><?= number_format($total_users) ?></div>
                    <div class="admin-stat-change" style="color:var(--teal);"><i class="fa-solid fa-arrow-trend-up"></i> Active platform users</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-label">Registered Vendors</div>
                    <div class="admin-stat-value"><?= number_format($total_vendors) ?></div>
                    <div class="admin-stat-change" style="color:var(--accent);"><i class="fa-solid fa-briefcase"></i> Service providers</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-label">Total Bookings</div>
                    <div class="admin-stat-value"><?= number_format($total_bookings) ?></div>
                    <div class="admin-stat-change" style="color:var(--teal);"><i class="fa-solid fa-chart-simple"></i> Enquiries & reservations</div>
                </div>
                <div class="admin-stat-card" style="<?= $pending_kyc > 0 ? 'border-color:var(--rose); background:rgba(244,63,94,0.02);' : '' ?>">
                    <div class="admin-stat-label">Pending KYC</div>
                    <div class="admin-stat-value" style="<?= $pending_kyc > 0 ? 'color:var(--rose);' : '' ?>"><?= number_format($pending_kyc) ?></div>
                    <div class="admin-stat-change" style="color:<?= $pending_kyc > 0 ? 'var(--rose)' : 'var(--gray-500)' ?>;">
                        <i class="fa-solid fa-shield-halved"></i> Requires review
                    </div>
                </div>
            </div>

            <!-- Recent Bookings Table -->
            <div class="admin-table-wrap">
                <div style="padding:18px 20px; border-bottom:1px solid #E4E7ED; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-family:'Fraunces',serif; font-size:1.1rem; color:var(--primary); margin:0;">Recent Bookings & Inquiries</h3>
                    <a href="bookings.php" class="btn btn-outline btn-sm" style="font-size:0.75rem;">View All Bookings</a>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Vendor Service</th>
                            <th>Event Type</th>
                            <th>Event Date</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_bookings)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:30px; color:var(--gray-400);">No bookings recorded in database yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_bookings as $b): ?>
                                <tr>
                                    <td style="font-weight:700;">#<?= $b['id'] ?></td>
                                    <td><?= htmlspecialchars($b['user_name']) ?></td>
                                    <td>
                                        <div style="font-weight:600; color:var(--primary);"><?= htmlspecialchars($b['vendor_name'] ?: 'Unknown Vendor') ?></div>
                                        <div style="font-size:0.7rem; color:var(--gray-500);"><?= htmlspecialchars($b['package_name'] ?: 'Base Package') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($b['event_type'] ?: 'Celebration') ?></td>
                                    <td><?= htmlspecialchars($b['event_date']) ?></td>
                                    <td style="font-weight:700; color:var(--primary);">GH₵ <?= number_format($b['price'], 2) ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = 'status-pending';
                                        $s = strtolower($b['status']);
                                        if ($s === 'confirmed' || $s === 'completed') $statusClass = 'status-confirmed';
                                        elseif ($s === 'cancelled' || $s === 'rejected') $statusClass = 'status-cancelled';
                                        ?>
                                        <span class="booking-status <?= $statusClass ?>" style="padding:4px 8px; font-size:0.7rem; border-radius:20px; font-weight:600; text-transform:capitalize;">
                                            <?= htmlspecialchars($b['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline btn-sm" style="padding:4px 8px; font-size:0.75rem; font-weight:700;" onclick='viewBookingDetails(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Full Details"><i class="fa-solid fa-eye"></i> Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

    <script>
        function toggleSidebar(open) {
            const sidebar = document.querySelector('.admin-sidebar');
            if (sidebar) {
                if (open) sidebar.classList.add('open');
                else sidebar.classList.remove('open');
            }
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
                    <div><strong>Customer Name:</strong> ${b.user_name || 'N/A'}</div>
                    <div><strong>Vendor Name:</strong> ${b.vendor_name || 'N/A'}</div>
                    <div><strong>Event Date:</strong> 📅 ${b.event_date || 'N/A'}</div>
                    <div><strong>Agreed Price:</strong> <span style="font-weight:800; color:var(--primary);">GH₵ ${parseFloat(b.price || 0).toFixed(2)}</span></div>
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
        <div style="background:#fff; border-radius:16px; width:90%; max-width:540px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.2); max-height:85vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E5E7EB; padding-bottom:12px;">
                <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--primary); font-family:'Fraunces', serif;">
                    <i class="fa-solid fa-calendar-days" style="color:var(--accent);"></i> Booking Details Summary
                </h3>
                <button onclick="closeBookingDetailsModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--gray-500);">&times;</button>
            </div>
            <div id="bookingDetailsContent"></div>
        </div>
    </div>
</body>
</html>
