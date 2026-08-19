<?php
// help.php - Ohati Standalone Help & Support Page
session_start();
require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM faqs ORDER BY category ASC, display_order ASC, id ASC");
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $faqs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - Ohati</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: var(--gray-50);
        }
        .help-wrapper {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            background: #fff;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            padding-bottom: 40px;
        }
    </style>
</head>
<body>
    <div class="help-wrapper">
        <header class="app-header" style="position: sticky; top:0; z-index:100;">
            <button class="chat-back-btn" onclick="window.history.back()" style="border:none; background:none; font-size:1.1rem; cursor:pointer;"><i class="fa-solid fa-chevron-left"></i></button>
            <h2 style="font-family:'Fraunces',serif; font-size:1.15rem; margin-left:12px; color:var(--primary);">Help Center</h2>
        </header>

        <div class="help-hero">
            <h3 style="color:#fff; margin-bottom:4px; font-family:'Fraunces',serif;">How can we help?</h3>
            <p style="font-size:0.75rem; color:rgba(255,255,255,0.85); margin-bottom:12px;">Search Ohati support articles and FAQs</p>
            <div class="help-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input placeholder="Search keywords..." style="border:none; outline:none; background:none; width:100%; font-size:0.8rem;">
            </div>
        </div>

        <div class="p-section">
            <h4 style="font-size:0.9rem; margin-bottom:12px; font-family:'Fraunces',serif; color:var(--primary);">Frequently Asked Questions</h4>
            
            <?php if (empty($faqs)): ?>
                <p style="font-size:0.8rem; color:var(--gray-500); text-align:center;">No FAQs found.</p>
            <?php else: 
                // Group by category
                $grouped = [];
                foreach ($faqs as $f) {
                    $grouped[$f['category']][] = $f;
                }
                foreach ($grouped as $cat => $items):
            ?>
                <h5 class="faq-category-title" style="font-size:0.8rem; margin:20px 0 8px; color:var(--accent); font-weight:700; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid var(--gray-100); padding-bottom:4px;"><?= htmlspecialchars($cat) ?></h5>
                <?php foreach ($items as $item): ?>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)"><?= htmlspecialchars($item['question']) ?> <i class="fa-solid fa-chevron-down"></i></div>
                        <div class="faq-answer"><?= $item['answer'] ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; endif; ?>
        </div>

        <div class="p-section" style="padding-top:0;">
            <div class="card" style="padding:16px; text-align:center; background:var(--gray-50); border:1px solid var(--gray-100);">
                <h4 style="font-size:0.85rem; margin-bottom:6px; color:var(--primary);">Still need assistance?</h4>
                <p style="font-size:0.75rem; color:var(--gray-500); margin-bottom:12px;">Our support desk is online 24/7.</p>
                <a href="https://wa.me/233209001100" target="_blank" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:8px; text-decoration:none; justify-content:center;">
                    <i class="fa-brands fa-whatsapp"></i> Chat Support
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleFaq(el) {
            const item = el.parentElement;
            item.classList.toggle('open');
        }

        // Live Search Filtering
        document.querySelector('.help-search input').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            const items = document.querySelectorAll('.faq-item');
            const categories = document.querySelectorAll('.faq-category-title');
            
            items.forEach(item => {
                const question = item.querySelector('.faq-question').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                if (question.includes(query) || answer.includes(query)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });

            // Hide categories if all items in that category are hidden
            categories.forEach(cat => {
                let next = cat.nextElementSibling;
                let hasVisible = false;
                while (next && next.classList.contains('faq-item')) {
                    if (next.style.display !== 'none') {
                        hasVisible = true;
                    }
                    next = next.nextElementSibling;
                }
                cat.style.display = hasVisible ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
