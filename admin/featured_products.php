<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/helpers/settings.php';

// CSRF token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

// Flash
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle POST to save featured product IDs with order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $_SESSION['flash_error'] = 'Invalid CSRF token.';
        header('Location: featured_products.php');
        exit();
    }

    // Parse posted order (from drag-drop list)
    $postedOrder = trim((string)($_POST['featured_order'] ?? ''));
    $orderIds = [];
    if ($postedOrder !== '') {
        $tmp = explode(',', $postedOrder);
        foreach ($tmp as $id) {
            $id = trim($id);
            if ($id !== '' && ctype_digit($id)) { $orderIds[] = (int)$id; }
        }
        // Remove duplicates, keep first occurrence
        $seen = [];
        $ord = [];
        foreach ($orderIds as $id) { if (!isset($seen[$id])) { $ord[] = $id; $seen[$id] = true; } }
        $orderIds = $ord;
    }

    // Visible IDs on this page (for checkbox merge)
    $visible = isset($_POST['visible_ids']) && is_array($_POST['visible_ids']) ? $_POST['visible_ids'] : [];
    $visibleIds = [];
    foreach ($visible as $id) { if (ctype_digit((string)$id)) { $visibleIds[] = (int)$id; } }

    // Checked IDs submitted from this page
    $checked = isset($_POST['featured_ids']) && is_array($_POST['featured_ids']) ? $_POST['featured_ids'] : [];
    $checkedIds = [];
    foreach ($checked as $id) { if (ctype_digit((string)$id)) { $checkedIds[] = (int)$id; } }
    $checkedLookup = array_fill_keys($checkedIds, true);

    // Start from the posted order if available; otherwise from existing setting
    if (!empty($orderIds)) {
        $newSel = $orderIds;
        // Add any newly checked IDs (e.g., from current page) that aren't already in order
        $pos = array_fill_keys($newSel, true);
        foreach ($checkedIds as $cid) { if (!isset($pos[$cid])) { $newSel[] = $cid; $pos[$cid] = true; } }
        // Remove any visible IDs that were unchecked (ensure they are not kept)
        if (!empty($visibleIds)) {
            $vset = array_fill_keys($visibleIds, true);
            $tmp = [];
            foreach ($newSel as $cid) {
                if (isset($vset[$cid]) && !isset($checkedLookup[$cid])) { continue; }
                $tmp[] = $cid;
            }
            $newSel = $tmp;
        }
    } else {
        // Fallback to previous selection and merge with visible page changes (previous behavior)
        $currentJson = (string)get_setting($conn, 'featured_product_ids', '[]');
        $currentSel = json_decode($currentJson, true);
        if (!is_array($currentSel)) { $currentSel = []; }
        $currentSel = array_values(array_filter($currentSel, fn($v)=>is_int($v) || ctype_digit((string)$v)));
        $currentSel = array_map('intval', $currentSel);

        $newSel = [];
        $visibleLookup = array_fill_keys($visibleIds, true);
        foreach ($currentSel as $cid) {
            if (isset($visibleLookup[$cid])) {
                if (isset($checkedLookup[$cid])) { $newSel[] = $cid; }
            } else {
                $newSel[] = $cid; // keep non-visible
            }
        }
        // Append newly checked
        $newLookup = array_fill_keys($newSel, true);
        foreach ($checkedIds as $cid) { if (!isset($newLookup[$cid])) { $newSel[] = $cid; $newLookup[$cid] = true; } }
    }

    $json = json_encode(array_values($newSel));
    if ($json === false) { $json = '[]'; }
    if (set_setting($conn, 'featured_product_ids', $json)) {
        $_SESSION['flash_success'] = 'Featured products updated.';
    } else {
        $_SESSION['flash_error'] = 'Failed to save featured products.';
    }
    header('Location: featured_products.php');
    exit();
}

// Current featured selection (rename to avoid collision with sidebar's $current)
$featuredSelJson = (string)get_setting($conn, 'featured_product_ids', '[]');
$featuredSel = json_decode($featuredSelJson, true);
if (!is_array($featuredSel)) { $featuredSel = []; }
$featuredSel = array_values(array_filter($featuredSel, fn($v) => is_int($v) || ctype_digit((string)$v)));
$featuredSel = array_map('intval', $featuredSel);

// Fetch details for currently featured (preserve order)
$currentProducts = [];
if (!empty($featuredSel)) {
    $idsLimited = $featuredSel; // allow all here; home may slice to 8
    $placeholders = implode(',', array_fill(0, count($idsLimited), '?'));
    $types = str_repeat('i', count($idsLimited));
    $orderField = implode(',', $idsLimited);
    $sql = "SELECT id, name, price, image FROM products WHERE id IN ($placeholders) ORDER BY FIELD(id, $orderField)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$idsLimited);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) { $currentProducts[] = $row; }
        $stmt->close();
    }
}

// Simple search + pagination for products list
$search = trim((string)($_GET['search'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where = '';
$types = '';
$params = [];
if ($search !== '') {
    if (ctype_digit($search)) {
        $where = 'WHERE p.id = ?';
        $types = 'i';
        $params[] = (int)$search;
    } else {
        $where = 'WHERE p.name LIKE ?';
        $types = 's';
        $params[] = '%' . $search . '%';
    }
}

// Total count
$total = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS c FROM products p $where")) {
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $total = (int)$row['c']; }
    $stmt->close();
}
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// Fetch list
$products = [];
$sql = "SELECT p.id, p.name, p.price, p.image FROM products p $where ORDER BY p.id DESC LIMIT ? OFFSET ?";
if ($stmt = $conn->prepare($sql)) {
    if ($types) {
        $bindTypes = $types . 'ii';
        $bindParams = $params;
        $bindParams[] = $limit;
        $bindParams[] = $offset;
        $stmt->bind_param($bindTypes, ...$bindParams);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $products[] = $row; }
    $stmt->close();
}

function is_checked(array $sel, int $id): bool { return in_array($id, $sel, true); }
function e($s){return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Featured Products</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css" />
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css" />
    <style>
      .drag-list { list-style:none; margin:0; padding:0; }
      .drag-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border:1px solid #e9ecef; border-radius:8px; background:#fff; }
      .drag-item + .drag-item { margin-top:8px; }
      .drag-handle { cursor:move; color:#999; }
      .drag-ghost { opacity:.5; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 mb-0">Featured Products</h1>
                <div class="text-muted-600">Select and reorder products featured on the home page.</div>
            </div>
            <div class="d-flex gap-2">
                <a href="manage_products.php" class="btn btn-outline-secondary"><i class="bi bi-box me-1"></i> Manage Products</a>
            </div>
        </div>

        <?php if ($flash_success): ?><div class="alert alert-success"><?= e($flash_success); ?></div><?php endif; ?>
        <?php if ($flash_error): ?><div class="alert alert-danger"><?= e($flash_error); ?></div><?php endif; ?>

        <form method="get" class="card card-body mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="search" class="form-label">Search products</label>
                    <input id="search" name="search" class="form-control" placeholder="Product name or ID" value="<?= e($search); ?>" />
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-admin-primary"><i class="bi bi-search me-1"></i> Search</button>
                    <a href="featured_products.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        <form method="post" class="card" id="featuredForm">
            <input type="hidden" name="csrf" value="<?= e($csrf); ?>" />
            <input type="hidden" name="featured_order" id="featured_order" value="" />

            <div class="card-body p-0">
                <?php if (!empty($products)): ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50px;" class="text-center">
                                        <input type="checkbox" id="checkAll" />
                                    </th>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Product</th>
                                    <th class="text-end">Price</th>
                                    <th>Featured</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): $pid=(int)$p['id']; ?>
                                    <tr data-product-row="<?= $pid; ?>">
                                        <input type="hidden" name="visible_ids[]" value="<?= $pid; ?>" />
                                        <td class="text-center">
                                            <input class="form-check-input fp-check" type="checkbox" name="featured_ids[]" value="<?= $pid; ?>" <?= is_checked($featuredSel, $pid) ? 'checked' : '' ?> />
                                        </td>
                                        <td><?= $pid; ?></td>
                                        <td>
                                            <?php if (!empty($p['image'])): ?>
                                                <img src="../assets/images/<?= e($p['image']); ?>" alt="<?= e($p['name']); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:4px;" />
                                            <?php else: ?>
                                                <div style="width:48px;height:48px;background:#eee;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;">—</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($p['name']); ?></td>
                                        <td class="text-end"><?= e(number_format((float)$p['price'], 2)); ?> PKR</td>
                                        <td>
                                            <?php if (is_checked($featuredSel, $pid)): ?>
                                                <span class="badge bg-success">Yes</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3">No products found.</div>
                <?php endif; ?>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="text-muted small">Page <?= (int)$page; ?> of <?= (int)$total_pages; ?> (<?= (int)$total; ?> items)</div>
                <div class="d-flex gap-2">
                    <a href="featured_products.php?page=<?= max(1,$page-1); ?>&search=<?= urlencode($search); ?>" class="btn btn-outline-secondary<?= $page<=1?' disabled':''; ?>">&laquo; Prev</a>
                    <a href="featured_products.php?page=<?= min($total_pages,$page+1); ?>&search=<?= urlencode($search); ?>" class="btn btn-outline-secondary<?= $page>=$total_pages?' disabled':''; ?>">Next &raquo;</a>
                    <button type="submit" class="btn btn-admin-primary"><i class="bi bi-save me-1"></i> Save Featured</button>
                </div>
            </div>

            <div class="card-body border-top">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h5 class="mb-0">Currently Featured (drag to reorder)</h5>
                    <button class="btn btn-sm btn-outline-danger" type="button" id="clearFeatured">Clear All</button>
                </div>
                <?php if (!empty($currentProducts)): ?>
                    <ul class="drag-list" id="featuredList">
                        <?php foreach ($currentProducts as $cp): $cid=(int)$cp['id']; ?>
                            <li class="drag-item" draggable="true" data-id="<?= $cid; ?>">
                                <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                                <?php if (!empty($cp['image'])): ?>
                                    <img src="../assets/images/<?= e($cp['image']); ?>" alt="<?= e($cp['name']); ?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;">
                                <?php else: ?>
                                    <div style="width:36px;height:36px;background:#eee;border-radius:4px;"></div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">#<?= $cid; ?> &middot; <?= e($cp['name']); ?></div>
                                    <div class="text-muted small"><?= e(number_format((float)$cp['price'],2)); ?> PKR</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary remove-featured" data-id="<?= $cid; ?>"><i class="bi bi-x"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-muted">No featured products yet. Check products above and save.</div>
                <?php endif; ?>
            </div>
        </form>
    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>

<script>
(function(){
  var form = document.getElementById('featuredForm');
  var list = document.getElementById('featuredList');
  var orderInput = document.getElementById('featured_order');
  var checkAll = document.getElementById('checkAll');

  function buildOrder(){
    if (!list) { orderInput.value = ''; return; }
    var ids = Array.prototype.map.call(list.querySelectorAll('[data-id]'), function(el){ return el.getAttribute('data-id'); });
    orderInput.value = ids.join(',');
  }

  // Drag and drop ordering
  if (list) {
    var dragEl = null;

    list.addEventListener('dragstart', function(e){
      var li = e.target.closest('.drag-item');
      if (!li) return;
      dragEl = li;
      li.classList.add('drag-ghost');
      e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragend', function(e){
      var li = e.target.closest('.drag-item');
      if (li) li.classList.remove('drag-ghost');
      dragEl = null;
      buildOrder();
    });
    list.addEventListener('dragover', function(e){
      if (!dragEl) return;
      e.preventDefault();
      var target = e.target.closest('.drag-item');
      if (!target || target === dragEl) return;
      var rect = target.getBoundingClientRect();
      var next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
      list.insertBefore(dragEl, next ? target.nextSibling : target);
    });

    // Remove button
    list.addEventListener('click', function(e){
      var btn = e.target.closest('.remove-featured');
      if (!btn) return;
      var id = btn.getAttribute('data-id');
      var li = list.querySelector('[data-id="' + id + '"]');
      if (li) li.remove();
      // Also uncheck checkbox in the table if visible
      var cb = document.querySelector('.fp-check[value="' + id + '"]');
      if (cb) cb.checked = false;
      buildOrder();
    });
  }

  // Check/uncheck all in table
  if (checkAll) {
    checkAll.addEventListener('change', function(){
      document.querySelectorAll('.fp-check').forEach(function(cb){ cb.checked = checkAll.checked; });
    });
  }

  // When a checkbox is unchecked, remove it from current list so order doesn't retain it unintentionally
  document.querySelectorAll('.fp-check').forEach(function(cb){
    cb.addEventListener('change', function(){
      if (!list) return;
      var id = cb.value;
      if (!cb.checked) {
        var li = list.querySelector('[data-id="' + id + '"]');
        if (li) li.remove();
        buildOrder();
      }
    });
  });

  // Clear all featured list
  var clearBtn = document.getElementById('clearFeatured');
  if (clearBtn && list) {
    clearBtn.addEventListener('click', function(){
      list.innerHTML = '';
      // Uncheck any visible checkboxes
      document.querySelectorAll('.fp-check').forEach(function(cb){ cb.checked = false; });
      buildOrder();
    });
  }

  // On submit, capture current order
  if (form) {
    form.addEventListener('submit', function(){ buildOrder(); });
  }
})();
</script>
</body>
</html>
