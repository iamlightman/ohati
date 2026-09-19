<?php
// admin/homepage_vendors.php — Production Admin Homepage Vendors Management Console
session_start();
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../db.php';

// Helper to get a setting
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        global $pdo;
        try {
            if (!$pdo) return $default;
            $stmt = $pdo->prepare("SELECT val_value FROM system_settings WHERE key_name = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return ($val !== false) ? $val : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

// Helper to save a setting
if (!function_exists('setSetting')) {
    function setSetting($key, $value) {
        global $pdo;
        try {
            if (!$pdo) return false;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE key_name = ?");
            $stmt->execute([$key]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->prepare("UPDATE system_settings SET val_value = ? WHERE key_name = ?")->execute([$value, $key]);
            } else {
                $pdo->prepare("INSERT INTO system_settings (key_name, val_value) VALUES (?, ?)")->execute([$key, $value]);
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Handle AJAX Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
    $action = $input['action'] ?? $_POST['action'] ?? '';

    if ($action === 'search_vendors') {
        $q = trim($input['query'] ?? '');
        $params = [];
        $sql = "SELECT v.id, v.name, v.category, v.location, v.rating, v.reviews_count, v.logo, v.cover_photo, v.is_active, v.verified, v.premium 
                FROM vendors v 
                WHERE 1=1";
        if ($q !== '') {
            $sql .= " AND (v.name LIKE ? OR v.category LIKE ? OR v.location LIKE ? OR CAST(v.id AS CHAR) = ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = $q;
        }
        $sql .= " ORDER BY v.is_active DESC, v.rating DESC, v.name ASC LIMIT 25";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$row) {
                $row['city'] = !empty($row['location']) ? explode(',', $row['location'])[0] : 'Ghana';
            }
            echo json_encode(['success' => true, 'vendors' => $results]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Search error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_settings' || $action === 'get_automatic_defaults') {
        try {
            $handpicked_raw = getSetting('homepage_handpicked_ids', '[]');
            $featured_raw = getSetting('homepage_featured_ids', '[]');
            $rec_raw = getSetting('homepage_recommended_ids', '[]');
            $rec_mode = getSetting('homepage_recommended_mode', 'automatic');

            $force_defaults = ($action === 'get_automatic_defaults');
            $handpicked_ids = $force_defaults ? [] : (json_decode($handpicked_raw, true) ?: []);
            $featured_ids = $force_defaults ? [] : (json_decode($featured_raw, true) ?: []);
            $rec_ids = $force_defaults ? [] : (json_decode($rec_raw, true) ?: []);

            // Helper to hydrate vendor objects in exact requested order
            $hydrate = function($ids, $allowNulls = false) use ($pdo) {
                if (empty($ids)) return [];
                $clean_ids = [];
                foreach ($ids as $id) {
                    $intId = intval($id);
                    if ($intId > 0) $clean_ids[] = $intId;
                }
                if (empty($clean_ids)) {
                    return $allowNulls ? array_fill(0, count($ids), null) : [];
                }
                $in = implode(',', array_fill(0, count($clean_ids), '?'));
                $stmt = $pdo->prepare("SELECT id, name, category, location, rating, reviews_count, logo, cover_photo, is_active, verified, premium FROM vendors WHERE id IN ($in)");
                $stmt->execute($clean_ids);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $map = [];
                foreach ($rows as $r) {
                    $r['city'] = !empty($r['location']) ? explode(',', $r['location'])[0] : 'Ghana';
                    $map[$r['id']] = $r;
                }
                $ordered = [];
                foreach ($ids as $id) {
                    $intId = intval($id);
                    if ($intId > 0 && isset($map[$intId])) {
                        $ordered[] = $map[$intId];
                    } elseif ($allowNulls) {
                        $ordered[] = null;
                    }
                }
                return $ordered;
            };

            // 1. Handpicked: If not explicitly saved, load live homepage defaults so admin can see and manage them
            $has_hp = false;
            foreach ($handpicked_ids as $val) { if (intval($val) > 0) { $has_hp = true; break; } }
            if ($has_hp) {
                $handpicked = $hydrate($handpicked_ids, true);
            } else {
                $hp_stmt = $pdo->query("SELECT id, name, category, location, rating, reviews_count, logo, cover_photo, is_active, verified, premium FROM vendors WHERE is_active = 1 AND (featured = 1 OR rating >= 4.0) ORDER BY featured DESC, rating DESC, reviews_count DESC, id DESC LIMIT 4");
                $hp_rows = $hp_stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($hp_rows) < 4) {
                    $ex_ids = array_column($hp_rows, 'id');
                    $ph = !empty($ex_ids) ? " AND id NOT IN (" . implode(',', array_fill(0, count($ex_ids), '?')) . ")" : "";
                    $fill_stmt = $pdo->prepare("SELECT id, name, category, location, rating, reviews_count, logo, cover_photo, is_active, verified, premium FROM vendors WHERE is_active = 1 $ph ORDER BY rating DESC, reviews_count DESC, id DESC LIMIT " . (4 - count($hp_rows)));
                    $fill_stmt->execute($ex_ids);
                    $hp_rows = array_merge($hp_rows, $fill_stmt->fetchAll(PDO::FETCH_ASSOC));
                }
                foreach ($hp_rows as &$hr) {
                    $hr['city'] = !empty($hr['location']) ? explode(',', $hr['location'])[0] : 'Ghana';
                }
                $handpicked = array_pad($hp_rows, 4, null);
            }

            // 2. Featured (Premium Selection): If not explicitly saved, load live active premium vendors
            $has_feat = false;
            foreach ($featured_ids as $val) { if (intval($val) > 0) { $has_feat = true; break; } }
            if ($has_feat) {
                $featured = $hydrate($featured_ids, false);
            } else {
                $feat_stmt = $pdo->query("SELECT id, name, category, location, rating, reviews_count, logo, cover_photo, is_active, verified, premium FROM vendors WHERE is_active = 1 AND premium = 1 ORDER BY featured DESC, premium DESC, verified DESC, rating DESC, reviews_count DESC, completed_jobs DESC LIMIT 12");
                $feat_rows = $feat_stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($feat_rows as &$fr) {
                    $fr['city'] = !empty($fr['location']) ? explode(',', $fr['location'])[0] : 'Ghana';
                }
                $featured = $feat_rows;
            }

            // 3. Recommended: If not explicitly saved, load live popularity algorithm vendors
            $has_rec = false;
            foreach ($rec_ids as $val) { if (intval($val) > 0) { $has_rec = true; break; } }
            if ($has_rec) {
                $recommended = $hydrate($rec_ids, false);
            } else {
                $rec_stmt = $pdo->query("SELECT id, name, category, location, rating, reviews_count, logo, cover_photo, is_active, verified, premium FROM vendors WHERE is_active = 1 AND (verification_status = 'verified' OR verification_status IS NULL OR verification_status = '') ORDER BY views_count DESC, rating DESC, id DESC LIMIT 6");
                $rec_rows = $rec_stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rec_rows as &$rr) {
                    $rr['city'] = !empty($rr['location']) ? explode(',', $rr['location'])[0] : 'Ghana';
                }
                $recommended = $rec_rows;
            }

            echo json_encode([
                'success' => true,
                'handpicked' => $handpicked,
                'featured' => $featured,
                'recommended' => $recommended,
                'recommended_mode' => in_array($rec_mode, ['automatic', 'curated'], true) ? $rec_mode : 'automatic',
                'is_default' => !$has_hp && !$has_feat && !$has_rec,
                'handpicked_is_custom' => (bool)$has_hp,
                'featured_is_custom' => (bool)$has_feat
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'save_settings') {
        try {
            $handpicked_in = $input['handpicked'] ?? [];
            $featured_in = $input['featured'] ?? [];
            $rec_in = $input['recommended'] ?? [];
            $rec_mode_in = $input['recommended_mode'] ?? 'automatic';

            // Clean Handpicked: Exactly 4 slots max, positive integers or 0/null, no duplicates among positive IDs
            $clean_handpicked = [];
            $seen_handpicked = [];
            for ($i = 0; $i < 4; $i++) {
                $val = isset($handpicked_in[$i]) ? intval($handpicked_in[$i]) : 0;
                if ($val > 0 && !isset($seen_handpicked[$val])) {
                    $clean_handpicked[] = $val;
                    $seen_handpicked[$val] = true;
                } else {
                    $clean_handpicked[] = null;
                }
            }
            // Trim trailing nulls
            while (count($clean_handpicked) > 0 && end($clean_handpicked) === null) {
                array_pop($clean_handpicked);
            }

            // Clean Featured: array of positive integers, deduplicated
            $clean_featured = [];
            $seen_featured = [];
            foreach ($featured_in as $fid) {
                $val = intval($fid);
                if ($val > 0 && !isset($seen_featured[$val])) {
                    $clean_featured[] = $val;
                    $seen_featured[$val] = true;
                }
            }

            // Clean Recommended: array of positive integers, deduplicated
            $clean_rec = [];
            $seen_rec = [];
            foreach ($rec_in as $rid) {
                $val = intval($rid);
                if ($val > 0 && !isset($seen_rec[$val])) {
                    $clean_rec[] = $val;
                    $seen_rec[$val] = true;
                }
            }

            $clean_mode = ($rec_mode_in === 'curated') ? 'curated' : 'automatic';

            setSetting('homepage_handpicked_ids', json_encode($clean_handpicked));
            setSetting('homepage_featured_ids', json_encode($clean_featured));
            setSetting('homepage_recommended_ids', json_encode($clean_rec));
            setSetting('homepage_recommended_mode', $clean_mode);

            echo json_encode([
                'success' => true,
                'message' => 'Homepage vendor settings successfully saved.',
                'saved' => [
                    'handpicked' => $clean_handpicked,
                    'featured' => $clean_featured,
                    'recommended' => $clean_rec,
                    'recommended_mode' => $clean_mode
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()]);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$page_title = "Homepage Vendors";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Vendors Management — Ohati Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-main { background: #F8FAFC; min-height: 100vh; }
        .admin-content { padding: 24px 32px; max-width: 1400px; margin: 0 auto; }
        .section-panel { background: #ffffff; border: 1px solid #E2E8F0; border-radius: 14px; padding: 24px; margin-bottom: 28px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); }
        .section-panel-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; border-bottom: 1px solid #F1F5F9; padding-bottom: 16px; }
        .section-panel-title { font-size: 1.2rem; font-weight: 800; color: #0F172A; margin: 0; display: flex; align-items: center; gap: 10px; }
        .section-panel-desc { font-size: 0.85rem; color: #64748B; margin: 4px 0 0 0; }
        
        /* 4 Fixed Handpicked Slots */
        .slots-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        @media (max-width: 1100px) { .slots-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .slots-grid { grid-template-columns: 1fr; } }
        
        .slot-card { background: #FFFFFF; border: 2px dashed #CBD5E1; border-radius: 12px; padding: 16px; min-height: 270px; display: flex; flex-direction: column; position: relative; transition: all 0.2s; }
        .slot-card.assigned { border-style: solid; border-color: #E2E8F0; box-shadow: 0 2px 5px rgba(0,0,0,0.04); }
        .slot-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .slot-badge { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; background: #EEF2F6; color: #475569; padding: 4px 10px; border-radius: 20px; }
        .slot-badge.active-badge { background: #DEF7EC; color: #03543F; }
        .slot-badge.inactive-badge { background: #FDE8E8; color: #9B1C1C; }

        .vendor-thumb-box { width: 100%; height: 110px; border-radius: 8px; background: #F1F5F9; overflow: hidden; position: relative; margin-bottom: 12px; }
        .vendor-thumb-img { width: 100%; height: 110px; object-fit: cover; }
        .vendor-logo-overlay { position: absolute; bottom: 8px; left: 8px; width: 36px; height: 36px; border-radius: 50%; border: 2px solid #fff; object-fit: cover; background: #fff; }

        .slot-vendor-name { font-size: 0.95rem; font-weight: 700; color: #0F172A; margin: 0 0 2px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .slot-vendor-cat { font-size: 0.75rem; color: #64748B; margin-bottom: 6px; }
        .slot-vendor-meta { font-size: 0.75rem; color: #94A3B8; display: flex; align-items: center; gap: 8px; }

        .slot-actions { margin-top: auto; padding-top: 14px; display: flex; flex-wrap: wrap; gap: 6px; border-top: 1px solid #F1F5F9; }
        .slot-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; text-align: center; color: #94A3B8; padding: 20px 10px; }

        /* List Items for Featured & Recommended */
        .vendor-list-container { display: flex; flex-direction: column; gap: 10px; }
        .vendor-list-row { display: flex; align-items: center; gap: 14px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 10px 16px; transition: all 0.2s; }
        .vendor-list-row:hover { border-color: #CBD5E1; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
        .row-order-idx { font-weight: 800; font-size: 0.9rem; color: #64748B; width: 28px; text-align: center; }
        .row-avatar { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; background: #F1F5F9; flex-shrink: 0; }
        .row-info { flex: 1; min-width: 0; }
        .row-name { font-weight: 700; font-size: 0.9rem; color: #0F172A; margin: 0 0 2px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .row-meta { font-size: 0.75rem; color: #64748B; display: flex; align-items: center; gap: 10px; }
        .row-actions { display: flex; align-items: center; gap: 6px; }

        /* Mode Switcher */
        .mode-toggle-wrap { display: flex; background: #F1F5F9; border-radius: 30px; padding: 4px; border: 1px solid #E2E8F0; max-width: 320px; margin-bottom: 18px; }
        .mode-btn { flex: 1; border: none; background: transparent; padding: 8px 16px; font-size: 0.82rem; font-weight: 700; border-radius: 24px; color: #64748B; cursor: pointer; transition: all 0.2s; }
        .mode-btn.active { background: #E05A47; color: #FFFFFF; box-shadow: 0 2px 6px rgba(224,90,71,0.25); }

        /* Buttons */
        .btn-action { background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; border-radius: 6px; padding: 6px 10px; font-size: 0.75rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.15s; }
        .btn-action:hover:not(:disabled) { background: #E2E8F0; color: #0F172A; }
        .btn-action:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-danger-sm { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        .btn-danger-sm:hover:not(:disabled) { background: #FCA5A5; color: #7F1D1D; }
        .btn-primary-sm { background: #E05A47; color: #fff; border: none; }
        .btn-primary-sm:hover:not(:disabled) { background: #C84634; }
        .btn-primary-lg { background: #E05A47; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(224,90,71,0.2); }
        .btn-primary-lg:hover { background: #C84634; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15,23,42,0.6); z-index: 10000; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(2px); }
        .modal-card { background: #fff; width: 100%; max-width: 620px; border-radius: 16px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); max-height: 85vh; display: flex; flex-direction: column; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .modal-title { font-size: 1.15rem; font-weight: 800; color: #0F172A; margin: 0; }
        .modal-close { background: none; border: none; font-size: 1.2rem; color: #64748B; cursor: pointer; }
        .search-results-box { overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 8px; margin-top: 14px; max-height: 380px; padding-right: 4px; }
        .search-result-item { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border: 1px solid #E2E8F0; border-radius: 8px; cursor: pointer; transition: all 0.15s; }
        .search-result-item:hover { background: #F8FAFC; border-color: #CBD5E1; }

        /* Toast Alert */
        .admin-toast { position: fixed; bottom: 24px; right: 24px; padding: 14px 22px; border-radius: 10px; font-size: 0.88rem; font-weight: 700; color: #fff; z-index: 10001; display: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2); }
        .admin-toast.success { background: #059669; }
        .admin-toast.error { background: #DC2626; }
    </style>
</head>
<body class="admin-layout">

    <!-- Admin Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Admin Main -->
    <main class="admin-main">
        <!-- Topbar -->
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-menu-toggle" onclick="toggleSidebar(true)"><i class="fa-solid fa-bars"></i></button>
                <h1 class="admin-page-title">Homepage Vendors</h1>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="btn-primary-lg" onclick="saveAllSettings()" id="top-save-btn">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <div class="admin-content">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 800; color: #0F172A; margin: 0;">Manage Homepage Showcases</h2>
                    <p style="color: #64748B; font-size: 0.88rem; margin: 4px 0 0 0;">Curate and order vendors appearing across the 3 core homepage carousels with instant fallback preservation.</p>
                </div>
            </div>

            <!-- SECTION 1: HANDPICKED FOR YOU -->
            <div class="section-panel" id="panel-handpicked">
                <div class="section-panel-header">
                    <div>
                        <h3 class="section-panel-title">
                            <i class="fa-solid fa-star" style="color:#D4AF37;"></i>
                            1. Handpicked For You (4 Fixed Positions)
                        </h3>
                        <p class="section-panel-desc">Assign exactly up to 4 vendor positions for the hero showcase card deck. Leave empty for automatic top-rated selection.</p>
                    </div>
                    <button class="btn-action" onclick="resetHandpickedToAuto()">
                        <i class="fa-solid fa-rotate-left"></i> Reset to Automatic
                    </button>
                </div>

                <div class="slots-grid" id="handpicked-slots-container">
                    <!-- Loaded dynamically -->
                </div>
            </div>

            <!-- SECTION 2: FEATURED VENDORS (PREMIUM SELECTION) -->
            <div class="section-panel" id="panel-featured">
                <div class="section-panel-header">
                    <div>
                        <h3 class="section-panel-title">
                            <i class="fa-solid fa-crown" style="color:#E05A47;"></i>
                            2. Featured Vendors (Premium Selection)
                        </h3>
                        <p class="section-panel-desc">Curate the horizontal carousel of featured vendors. If no vendors are manually pinned here, the system automatically displays all active Premium vendors.</p>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="btn-action btn-primary-sm" onclick="openSearchModal('featured')">
                            <i class="fa-solid fa-plus"></i> Add Vendor
                        </button>
                        <button class="btn-action" onclick="resetFeaturedToDefault()">
                            <i class="fa-solid fa-rotate-left"></i> Reset to Default
                        </button>
                    </div>
                </div>

                <div id="featured-empty-notice" style="display:none; padding:16px; background:#F1F5F9; border-radius:8px; font-size:0.85rem; color:#64748B; text-align:center;">
                    <i class="fa-solid fa-circle-info"></i> Currently in automatic mode: displaying all active vendors with Premium status enabled.
                </div>
                <div class="vendor-list-container" id="featured-list-container">
                    <!-- Loaded dynamically -->
                </div>
            </div>

            <!-- SECTION 3: RECOMMENDED FOR YOU -->
            <div class="section-panel" id="panel-recommended">
                <div class="section-panel-header">
                    <div>
                        <h3 class="section-panel-title">
                            <i class="fa-solid fa-thumbs-up" style="color:#2563EB;"></i>
                            3. Recommended for You
                        </h3>
                        <p class="section-panel-desc">Choose between the automatic popularity algorithm (views count & ratings) or manually pin curated vendors in exact order.</p>
                    </div>
                    <div id="rec-curated-actions" style="display:none; gap:8px;">
                        <button class="btn-action btn-primary-sm" onclick="openSearchModal('recommended')">
                            <i class="fa-solid fa-plus"></i> Add Vendor
                        </button>
                        <button class="btn-action" onclick="clearSection('recommended')">
                            <i class="fa-solid fa-trash-can"></i> Clear List
                        </button>
                    </div>
                </div>

                <!-- Mode Toggle -->
                <div class="mode-toggle-wrap">
                    <button class="mode-btn active" id="btn-mode-auto" onclick="setRecommendedMode('automatic')">
                        <i class="fa-solid fa-chart-line"></i> Automatic (Popularity)
                    </button>
                    <button class="mode-btn" id="btn-mode-curated" onclick="setRecommendedMode('curated')">
                        <i class="fa-solid fa-hand-pointer"></i> Curated (Manual)
                    </button>
                </div>

                <div id="rec-auto-notice" style="padding:16px; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:8px; font-size:0.85rem; color:#1E40AF; margin-bottom:16px;">
                    <i class="fa-solid fa-robot"></i> <strong>Automatic Algorithm Active:</strong> The customer homepage displays vendors with the highest views count and star ratings (top 6).
                </div>

                <div id="rec-curated-wrap" style="display:none;">
                    <div id="rec-curated-empty" style="display:none; padding:16px; background:#FEF3C7; border:1px solid #FDE68A; border-radius:8px; font-size:0.85rem; color:#92400E; text-align:center; margin-bottom:12px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Curated list is currently empty. If kept empty, the system automatically falls back to the popularity algorithm.
                    </div>
                    <div class="vendor-list-container" id="recommended-list-container">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
            </div>

            <!-- Bottom Save Bar -->
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; padding-bottom:40px;">
                <button class="btn-primary-lg" onclick="saveAllSettings()" id="bottom-save-btn">
                    <i class="fa-solid fa-floppy-disk"></i> Save All Changes
                </button>
            </div>
        </div>
    </main>

    <!-- SEARCH & SELECT MODAL -->
    <div class="modal-overlay" id="search-modal" onclick="closeSearchModal()">
        <div class="modal-card" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h4 class="modal-title" id="modal-target-title">Select Vendor</h4>
                <button class="modal-close" onclick="closeSearchModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div style="position:relative;">
                <input type="text" id="modal-search-input" class="form-input" placeholder="Search by vendor name, category, city, or ID..." oninput="debounceSearch()" style="width:100%; padding:10px 14px; border:1px solid #CBD5E1; border-radius:8px;">
            </div>
            <div class="search-results-box" id="modal-results-box">
                <div style="text-align:center; padding:30px; color:#94A3B8;">Type to search active vendors...</div>
            </div>
        </div>
    </div>

    <!-- TOAST NOTIFICATION -->
    <div class="admin-toast" id="admin-toast"></div>

    <script>
        // State Store
        let state = {
            handpicked: [null, null, null, null],
            featured: [],
            recommended: [],
            recommended_mode: 'automatic',
            handpicked_is_custom: false,
            featured_is_custom: false,
            searchTarget: null, // { section: 'handpicked', slot: 0 } or { section: 'featured' }
            debounceTimer: null
        };

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('admin-toast');
            toast.textContent = msg;
            toast.className = `admin-toast ${type}`;
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 3500);
        }

        function formatAdminMediaUrl(url, fallback) {
            if (!url || typeof url !== 'string' || !url.trim()) return fallback;
            url = url.trim();
            if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:') || url.startsWith('../') || url.startsWith('/')) {
                return url;
            }
            return '../' + url;
        }

        // Load settings from backend
        async function loadSettings() {
            try {
                const res = await fetch('homepage_vendors.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_settings' })
                });
                const data = await res.json();
                if (data.success) {
                    state.handpicked = [null, null, null, null];
                    if (Array.isArray(data.handpicked)) {
                        for (let i = 0; i < 4; i++) {
                            state.handpicked[i] = data.handpicked[i] || null;
                        }
                    }
                    state.featured = Array.isArray(data.featured) ? data.featured : [];
                    state.recommended = Array.isArray(data.recommended) ? data.recommended : [];
                    state.recommended_mode = data.recommended_mode || 'automatic';
                    state.handpicked_is_custom = !!data.handpicked_is_custom;
                    state.featured_is_custom = !!data.featured_is_custom;

                    renderAll();
                } else {
                    showToast(data.error || 'Failed to load settings', 'error');
                }
            } catch (err) {
                showToast('Network error loading settings', 'error');
            }
        }

        function renderAll() {
            renderHandpicked();
            renderFeatured();
            renderRecommended();
        }

        // 1. Render Handpicked
        function renderHandpicked() {
            const container = document.getElementById('handpicked-slots-container');
            container.innerHTML = state.handpicked.map((v, idx) => {
                const slotNum = idx + 1;
                if (!v) {
                    return `
                        <div class="slot-card">
                            <div class="slot-header">
                                <span class="slot-badge">Position ${slotNum}</span>
                                <span class="slot-badge">Empty</span>
                            </div>
                            <div class="slot-empty-state">
                                <i class="fa-solid fa-plus-circle" style="font-size:2rem; margin-bottom:10px; color:#CBD5E1;"></i>
                                <div style="font-weight:700; font-size:0.85rem; color:#64748B;">Slot ${slotNum} Unassigned</div>
                                <div style="font-size:0.75rem; color:#94A3B8; margin-top:2px;">Click below to pick a vendor</div>
                            </div>
                            <div class="slot-actions">
                                <button class="btn-action btn-primary-sm" style="flex:1;" onclick="openSearchModal('handpicked', ${idx})">
                                    <i class="fa-solid fa-plus"></i> Assign Vendor
                                </button>
                            </div>
                        </div>
                    `;
                }

                const isActive = parseInt(v.is_active) === 1;
                const coverUrl = formatAdminMediaUrl(v.cover_photo || v.logo, '../img/default-cover.jpg');
                const logoUrl = formatAdminMediaUrl(v.logo || v.cover_photo, '../img/default-avatar.png');
                const statusBadge = isActive 
                    ? '<span class="slot-badge active-badge">Active</span>' 
                    : '<span class="slot-badge inactive-badge">Inactive</span>';

                return `
                    <div class="slot-card assigned">
                        <div class="slot-header">
                            <span class="slot-badge">Position ${slotNum}</span>
                            ${statusBadge}
                        </div>
                        <div class="vendor-thumb-box">
                            <img src="${coverUrl}" onerror="this.onerror=null; this.src='../img/default-cover.jpg';" class="vendor-thumb-img" alt="">
                            <img src="${logoUrl}" onerror="this.onerror=null; this.src='../img/default-avatar.png';" class="vendor-logo-overlay" alt="">
                        </div>
                        <h4 class="slot-vendor-name" title="${escapeHtml(v.name)}">${escapeHtml(v.name)}</h4>
                        <div class="slot-vendor-cat">${escapeHtml(v.category || 'Vendor')}</div>
                        <div class="slot-vendor-meta">
                            <span><i class="fa-solid fa-star" style="color:#F59E0B;"></i> ${parseFloat(v.rating || 0).toFixed(1)}</span>
                            <span>•</span>
                            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(v.city || v.location || 'Ghana')}</span>
                        </div>
                        <div class="slot-actions">
                            <button class="btn-action" onclick="moveHandpicked(${idx}, -1)" ${idx === 0 ? 'disabled' : ''} title="Move Left">
                                <i class="fa-solid fa-arrow-left"></i>
                            </button>
                            <button class="btn-action" onclick="moveHandpicked(${idx}, 1)" ${idx === 3 ? 'disabled' : ''} title="Move Right">
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                            <button class="btn-action" style="flex:1;" onclick="openSearchModal('handpicked', ${idx})">
                                Replace
                            </button>
                            <button class="btn-action btn-danger-sm" onclick="removeHandpicked(${idx})" title="Remove">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function moveHandpicked(idx, dir) {
            const newIdx = idx + dir;
            if (newIdx < 0 || newIdx >= 4) return;
            const temp = state.handpicked[idx];
            state.handpicked[idx] = state.handpicked[newIdx];
            state.handpicked[newIdx] = temp;
            state.handpicked_is_custom = true;
            renderHandpicked();
        }

        function removeHandpicked(idx) {
            state.handpicked[idx] = null;
            state.handpicked_is_custom = true;
            renderHandpicked();
        }

        async function resetHandpickedToAuto() {
            if (confirm('Reset all 4 handpicked positions to live automatic defaults?')) {
                try {
                    const res = await fetch('homepage_vendors.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'get_automatic_defaults' })
                    });
                    const data = await res.json();
                    if (data.success && data.handpicked) {
                        state.handpicked = data.handpicked;
                        state.handpicked_is_custom = false;
                        renderHandpicked();
                        showToast('Handpicked slots reset to current top-rated vendors');
                    }
                } catch(e) {
                    state.handpicked = [null, null, null, null];
                    state.handpicked_is_custom = false;
                    renderHandpicked();
                }
            }
        }

        async function resetFeaturedToDefault() {
            if (confirm('Reset featured list to live active Premium vendors?')) {
                try {
                    const res = await fetch('homepage_vendors.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'get_automatic_defaults' })
                    });
                    const data = await res.json();
                    if (data.success && data.featured) {
                        state.featured = data.featured;
                        state.featured_is_custom = false;
                        renderFeatured();
                        showToast('Featured list reset to active Premium vendors');
                    }
                } catch(e) {
                    state.featured = [];
                    state.featured_is_custom = false;
                    renderFeatured();
                }
            }
        }

        // 2. Render Featured
        function renderFeatured() {
            const container = document.getElementById('featured-list-container');
            const notice = document.getElementById('featured-empty-notice');

            if (state.featured.length === 0) {
                notice.style.display = 'block';
                container.innerHTML = '';
                return;
            }
            notice.style.display = 'none';

            container.innerHTML = state.featured.map((v, idx) => {
                const logoUrl = formatAdminMediaUrl(v.logo || v.cover_photo, '../img/default-avatar.png');
                const isActive = parseInt(v.is_active) === 1;
                return `
                    <div class="vendor-list-row">
                        <span class="row-order-idx">${idx + 1}</span>
                        <img src="${logoUrl}" onerror="this.onerror=null; this.src='../img/default-avatar.png';" class="row-avatar" alt="">
                        <div class="row-info">
                            <div class="row-name">${escapeHtml(v.name)} ${!isActive ? '<span class="slot-badge inactive-badge">Inactive</span>' : ''}</div>
                            <div class="row-meta">
                                <span>${escapeHtml(v.category)}</span>
                                <span>•</span>
                                <span><i class="fa-solid fa-star" style="color:#F59E0B;"></i> ${parseFloat(v.rating || 0).toFixed(1)}</span>
                                <span>•</span>
                                <span><i class="fa-solid fa-location-dot"></i> ${escapeHtml(v.city || v.location || 'Ghana')}</span>
                            </div>
                        </div>
                        <div class="row-actions">
                            <button class="btn-action" onclick="moveList('featured', ${idx}, -1)" ${idx === 0 ? 'disabled' : ''} title="Move Up">
                                <i class="fa-solid fa-arrow-up"></i>
                            </button>
                            <button class="btn-action" onclick="moveList('featured', ${idx}, 1)" ${idx === state.featured.length - 1 ? 'disabled' : ''} title="Move Down">
                                <i class="fa-solid fa-arrow-down"></i>
                            </button>
                            <button class="btn-action btn-danger-sm" onclick="removeList('featured', ${idx})" title="Remove">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // 3. Render Recommended
        function renderRecommended() {
            const btnAuto = document.getElementById('btn-mode-auto');
            const btnCurated = document.getElementById('btn-mode-curated');
            const autoNotice = document.getElementById('rec-auto-notice');
            const curatedWrap = document.getElementById('rec-curated-wrap');
            const curatedActions = document.getElementById('rec-curated-actions');
            const curatedEmpty = document.getElementById('rec-curated-empty');
            const container = document.getElementById('recommended-list-container');

            if (state.recommended_mode === 'automatic') {
                btnAuto.classList.add('active');
                btnCurated.classList.remove('active');
                autoNotice.style.display = 'block';
                curatedWrap.style.display = 'none';
                curatedActions.style.display = 'none';
            } else {
                btnAuto.classList.remove('active');
                btnCurated.classList.add('active');
                autoNotice.style.display = 'none';
                curatedWrap.style.display = 'block';
                curatedActions.style.display = 'flex';

                if (state.recommended.length === 0) {
                    curatedEmpty.style.display = 'block';
                    container.innerHTML = '';
                } else {
                    curatedEmpty.style.display = 'none';
                    container.innerHTML = state.recommended.map((v, idx) => {
                        const logoUrl = formatAdminMediaUrl(v.logo || v.cover_photo, '../img/default-avatar.png');
                        const isActive = parseInt(v.is_active) === 1;
                        return `
                            <div class="vendor-list-row">
                                <span class="row-order-idx">${idx + 1}</span>
                                <img src="${logoUrl}" onerror="this.onerror=null; this.src='../img/default-avatar.png';" class="row-avatar" alt="">
                                <div class="row-info">
                                    <div class="row-name">${escapeHtml(v.name)} ${!isActive ? '<span class="slot-badge inactive-badge">Inactive</span>' : ''}</div>
                                    <div class="row-meta">
                                        <span>${escapeHtml(v.category)}</span>
                                        <span>•</span>
                                        <span><i class="fa-solid fa-star" style="color:#F59E0B;"></i> ${parseFloat(v.rating || 0).toFixed(1)}</span>
                                        <span>•</span>
                                        <span><i class="fa-solid fa-location-dot"></i> ${escapeHtml(v.city || v.location || 'Ghana')}</span>
                                    </div>
                                </div>
                                <div class="row-actions">
                                    <button class="btn-action" onclick="moveList('recommended', ${idx}, -1)" ${idx === 0 ? 'disabled' : ''} title="Move Up">
                                        <i class="fa-solid fa-arrow-up"></i>
                                    </button>
                                    <button class="btn-action" onclick="moveList('recommended', ${idx}, 1)" ${idx === state.recommended.length - 1 ? 'disabled' : ''} title="Move Down">
                                        <i class="fa-solid fa-arrow-down"></i>
                                    </button>
                                    <button class="btn-action btn-danger-sm" onclick="removeList('recommended', ${idx})" title="Remove">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
        }

        function setRecommendedMode(mode) {
            state.recommended_mode = mode;
            renderRecommended();
        }

        function moveList(section, idx, dir) {
            const list = state[section];
            const newIdx = idx + dir;
            if (newIdx < 0 || newIdx >= list.length) return;
            const temp = list[idx];
            list[idx] = list[newIdx];
            list[newIdx] = temp;
            if (section === 'featured') {
                state.featured_is_custom = true;
                renderFeatured();
            }
            if (section === 'recommended') renderRecommended();
        }

        function removeList(section, idx) {
            state[section].splice(idx, 1);
            if (section === 'featured') {
                state.featured_is_custom = true;
                renderFeatured();
            }
            if (section === 'recommended') renderRecommended();
        }

        function clearSection(section) {
            if (confirm(`Clear all vendors from ${section}?`)) {
                state[section] = [];
                if (section === 'featured') {
                    state.featured_is_custom = true;
                    renderFeatured();
                }
                if (section === 'recommended') renderRecommended();
            }
        }

        // Modal Search
        function openSearchModal(section, slot = 0) {
            state.searchTarget = { section, slot };
            const titleEl = document.getElementById('modal-target-title');
            if (section === 'handpicked') {
                titleEl.textContent = `Select Vendor for Handpicked Position ${slot + 1}`;
            } else if (section === 'featured') {
                titleEl.textContent = 'Add Vendor to Featured Selection';
            } else {
                titleEl.textContent = 'Add Vendor to Recommended Selection';
            }

            document.getElementById('modal-search-input').value = '';
            document.getElementById('search-modal').style.display = 'flex';
            document.getElementById('modal-search-input').focus();
            fetchVendors('');
        }

        function closeSearchModal() {
            document.getElementById('search-modal').style.display = 'none';
            state.searchTarget = null;
        }

        function debounceSearch() {
            clearTimeout(state.debounceTimer);
            state.debounceTimer = setTimeout(() => {
                const q = document.getElementById('modal-search-input').value.trim();
                fetchVendors(q);
            }, 250);
        }

        async function fetchVendors(q) {
            const box = document.getElementById('modal-results-box');
            box.innerHTML = '<div style="text-align:center; padding:20px; color:#94A3B8;"><i class="fa-solid fa-spinner fa-spin"></i> Searching vendors...</div>';
            try {
                const res = await fetch('homepage_vendors.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'search_vendors', query: q })
                });
                const data = await res.json();
                if (data.success && data.vendors) {
                    if (data.vendors.length === 0) {
                        box.innerHTML = '<div style="text-align:center; padding:20px; color:#94A3B8;">No active vendors found matching your query.</div>';
                        return;
                    }

                    window._modalSearchResults = data.vendors;
                    box.innerHTML = data.vendors.map((v, idx) => {
                        const logoUrl = formatAdminMediaUrl(v.logo || v.cover_photo, '../img/default-avatar.png');
                        return `
                            <div class="search-result-item" onclick="selectVendorByIndex(${idx})">
                                <img src="${logoUrl}" onerror="this.onerror=null; this.src='../img/default-avatar.png';" style="width:40px; height:40px; border-radius:6px; object-fit:cover;">
                                <div style="flex:1; min-width:0;">
                                    <div style="font-weight:700; font-size:0.88rem; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(v.name)}</div>
                                    <div style="font-size:0.75rem; color:#64748B;">
                                        ${escapeHtml(v.category)} • <i class="fa-solid fa-star" style="color:#F59E0B;"></i> ${parseFloat(v.rating || 0).toFixed(1)} • ${escapeHtml(v.city || v.location || 'Ghana')}
                                    </div>
                                </div>
                                <button class="btn-action btn-primary-sm" type="button">Select</button>
                            </div>
                        `;
                    }).join('');
                } else {
                    box.innerHTML = `<div style="text-align:center; padding:20px; color:#EF4444;">${escapeHtml(data.error || 'Error searching vendors.')}</div>`;
                }
            } catch (e) {
                box.innerHTML = '<div style="text-align:center; padding:20px; color:#EF4444;">Network error.</div>';
            }
        }

        window.selectVendorByIndex = function(idx) {
            if (!window._modalSearchResults || !window._modalSearchResults[idx]) return;
            selectVendor(window._modalSearchResults[idx]);
        };

        function selectVendor(v) {
            if (!state.searchTarget) return;
            const { section, slot } = state.searchTarget;
            if (section === 'handpicked') {
                // Check if vendor already assigned in another handpicked slot
                const existingIdx = state.handpicked.findIndex(x => x && parseInt(x.id) === parseInt(v.id));
                if (existingIdx !== -1 && existingIdx !== slot) {
                    alert(`This vendor is already assigned to Position ${existingIdx + 1}. Each position must have a distinct vendor.`);
                    return;
                }
                state.handpicked[slot] = v;
                state.handpicked_is_custom = true;
                renderHandpicked();
            } else if (section === 'featured') {
                // Check if already in featured list
                if (state.featured.some(x => parseInt(x.id) === parseInt(v.id))) {
                    alert('This vendor is already in the Featured list.');
                    return;
                }
                state.featured.push(v);
                state.featured_is_custom = true;
                renderFeatured();
            } else if (section === 'recommended') {
                // Check if already in recommended list
                if (state.recommended.some(x => parseInt(x.id) === parseInt(v.id))) {
                    alert('This vendor is already in the Recommended list.');
                    return;
                }
                state.recommended.push(v);
                renderRecommended();
            }

            closeSearchModal();
        }

        // Save All Settings
        async function saveAllSettings() {
            const btn1 = document.getElementById('top-save-btn');
            const btn2 = document.getElementById('bottom-save-btn');
            btn1.disabled = true;
            btn2.disabled = true;
            btn1.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            btn2.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            const payload = {
                action: 'save_settings',
                handpicked: state.handpicked_is_custom
                    ? state.handpicked.map(v => v ? parseInt(v.id) : null)
                    : [],
                featured: state.featured_is_custom
                    ? state.featured.map(v => parseInt(v.id))
                    : [],
                recommended: state.recommended.map(v => parseInt(v.id)),
                recommended_mode: state.recommended_mode
            };

            try {
                const res = await fetch('homepage_vendors.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Homepage vendor configuration saved successfully!', 'success');
                } else {
                    showToast(data.error || 'Failed to save configuration', 'error');
                }
            } catch (err) {
                showToast('Network error while saving', 'error');
            } finally {
                btn1.disabled = false;
                btn2.disabled = false;
                btn1.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';
                btn2.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save All Changes';
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
            });
        }

        // Initial load
        document.addEventListener('DOMContentLoaded', loadSettings);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSearchModal();
        });
    </script>
</body>
</html>
