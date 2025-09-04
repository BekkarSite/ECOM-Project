<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

// CSRF token for admin actions
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

// Flash helpers
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
function flash_set($ok, $msg) { $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $msg; }

// Load roles for validation / UI
$roles = [];
if ($res = $conn->query("SELECT name FROM roles ORDER BY name ASC")) {
    while ($r = $res->fetch_assoc()) { $roles[] = $r['name']; }
}
if (empty($roles)) { $roles = ['admin','customer']; }

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        flash_set(false, 'Invalid CSRF token.');
        header('Location: manage_users.php');
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_registration') {
        $value = isset($_POST['registration_paused']) ? '1' : '0';
        if ($stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'registration_paused'")) {
            $stmt->bind_param('s', $value);
            $stmt->execute();
            $stmt->close();
        }
        flash_set(true, 'Registration setting updated.');
        header('Location: manage_users.php');
        exit();
    }

    if ($action === 'create_user') {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'customer');
        if (!in_array($role, $roles, true)) { $role = 'customer'; }

        // Validate
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set(false, 'Please provide a valid email.');
        } elseif (strlen($password) < 8) {
            flash_set(false, 'Password must be at least 8 characters.');
        } else {
            // Uniqueness
            if ($chk = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1')) {
                $chk->bind_param('s', $email);
                $chk->execute();
                $exists = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($exists) {
                    flash_set(false, 'Email already exists.');
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    if ($ins = $conn->prepare('INSERT INTO users (email, password, role, is_banned) VALUES (?, ?, ?, 0)')) {
                        $ins->bind_param('sss', $email, $hash, $role);
                        if ($ins->execute()) {
                            flash_set(true, 'User created successfully.');
                        } else {
                            flash_set(false, 'Failed to create user.');
                        }
                        $ins->close();
                    }
                }
            }
        }
        header('Location: manage_users.php');
        exit();
    }

    if ($action === 'toggle_ban') {
        $id = (int)($_POST['id'] ?? 0);
        $mode = $_POST['mode'] ?? '';
        if ($id <= 0 || !in_array($mode, ['ban','unban'], true)) {
            flash_set(false, 'Invalid request.');
            header('Location: manage_users.php');
            exit();
        }
        // Disallow banning admins and self
        if ($stmt = $conn->prepare('SELECT role FROM users WHERE id = ?')) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                flash_set(false, 'User not found.');
            } elseif ($row['role'] === 'admin') {
                flash_set(false, 'Cannot change ban status for admin accounts.');
            } elseif ($id === (int)$_SESSION['admin_id']) {
                flash_set(false, 'You cannot change your own status.');
            } else {
                $is_banned = $mode === 'ban' ? 1 : 0;
                if ($upd = $conn->prepare('UPDATE users SET is_banned = ? WHERE id = ?')) {
                    $upd->bind_param('ii', $is_banned, $id);
                    if ($upd->execute()) flash_set(true, 'User status updated.'); else flash_set(false, 'Failed to update status.');
                    $upd->close();
                }
            }
        }
        header('Location: manage_users.php');
        exit();
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { flash_set(false, 'Invalid user id.'); header('Location: manage_users.php'); exit(); }
        // Disallow deleting admins and self
        if ($stmt = $conn->prepare('SELECT role FROM users WHERE id = ?')) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                flash_set(false, 'User not found.');
            } elseif ($row['role'] === 'admin') {
                flash_set(false, 'Cannot delete admin accounts.');
            } elseif ($id === (int)$_SESSION['admin_id']) {
                flash_set(false, 'You cannot delete your own account.');
            } else {
                if ($del = $conn->prepare('DELETE FROM users WHERE id = ?')) {
                    $del->bind_param('i', $id);
                    if ($del->execute()) flash_set(true, 'User deleted.'); else flash_set(false, 'Failed to delete user.');
                    $del->close();
                }
            }
        }
        header('Location: manage_users.php');
        exit();
    }
}

// Fetch current registration status
$registrationPaused = false;
if ($res = $conn->query("SELECT value FROM settings WHERE name = 'registration_paused' LIMIT 1")) {
    if ($row = $res->fetch_assoc()) $registrationPaused = $row['value'] === '1';
}

// Filters & pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest'; // newest|oldest|email_asc|email_desc
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
$types  = '';

if ($search !== '') {
    if (ctype_digit($search)) {
        $where[] = 'id = ?'; $types .= 'i'; $params[] = (int)$search;
    } else {
        $where[] = 'email LIKE ?'; $types .= 's'; $params[] = '%' . $search . '%';
    }
}
if ($roleFilter !== '' && in_array($roleFilter, $roles, true)) {
    $where[] = 'role = ?'; $types .= 's'; $params[] = $roleFilter;
}
if ($statusFilter === 'active') { $where[] = 'is_banned = 0'; }
if ($statusFilter === 'banned') { $where[] = 'is_banned = 1'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderSql = 'ORDER BY created_at DESC';
if ($sort === 'oldest') $orderSql = 'ORDER BY created_at ASC';
if ($sort === 'email_asc') $orderSql = 'ORDER BY email ASC';
if ($sort === 'email_desc') $orderSql = 'ORDER BY email DESC';

// Count total
$total = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users $whereSql")) {
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $total = (int)$row['cnt']; }
    $stmt->close();
}
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// Fetch users page
$users = [];
$sql = "SELECT id, email, role, is_banned, created_at FROM users $whereSql $orderSql LIMIT ? OFFSET ?";
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
        $bindTypes = $types . 'ii'; $bindParams = $params; $bindParams[] = $limit; $bindParams[] = $offset; $stmt->bind_param($bindTypes, ...$bindParams);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $users[] = $row; }
    $stmt->close();
}

function buildQuery(array $q): string {
    return http_build_query(array_filter($q, function($k){ return $k !== 'page'; }, ARRAY_FILTER_USE_KEY));
}
$baseQuery = buildQuery([
    'search' => $search !== '' ? $search : null,
    'role' => $roleFilter !== '' ? $roleFilter : null,
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'sort' => $sort !== 'newest' ? $sort : null,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">Manage Users</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-admin-primary" data-bs-toggle="modal" data-bs-target="#createUserModal"><i class="bi bi-person-plus me-1"></i> New User</button>
                <a class="btn btn-outline-secondary" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <?php if ($flash_success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>

        <form method="get" class="card card-body mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="search">Search</label>
                    <input id="search" name="search" class="form-control" placeholder="Email or ID" value="<?= htmlspecialchars($search); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="role">Role</label>
                    <select id="role" name="role" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= htmlspecialchars($r); ?>" <?= $roleFilter===$r?'selected':''; ?>><?= htmlspecialchars(ucfirst($r)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" <?= $statusFilter==='active'?'selected':''; ?>>Active</option>
                        <option value="banned" <?= $statusFilter==='banned'?'selected':''; ?>>Banned</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="sort">Sort</label>
                    <select id="sort" name="sort" class="form-select">
                        <option value="newest" <?= $sort==='newest'?'selected':''; ?>>Newest</option>
                        <option value="oldest" <?= $sort==='oldest'?'selected':''; ?>>Oldest</option>
                        <option value="email_asc" <?= $sort==='email_asc'?'selected':''; ?>>Email A-Z</option>
                        <option value="email_desc" <?= $sort==='email_desc'?'selected':''; ?>>Email Z-A</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-admin-primary flex-fill"><i class="bi bi-funnel me-1"></i> Filter</button>
                    <a href="manage_users.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="card mb-3">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-person-lock text-muted"></i>
                    <strong>Pause User Registration</strong>
                </div>
                <form method="post" class="m-0">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="toggle_registration">
                    <div class="form-check form-switch d-inline-flex align-items-center gap-2">
                        <input class="form-check-input" type="checkbox" id="regPaused" name="registration_paused" value="1" <?= $registrationPaused?'checked':''; ?>>
                        <label class="form-check-label" for="regPaused"><?= $registrationPaused ? 'Registration paused' : 'Registration open'; ?></label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary ms-2">Save</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= (int)$u['id']; ?></td>
                                        <td><?= htmlspecialchars($u['email']); ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($u['role'])); ?></span></td>
                                        <td>
                                            <?php if ((int)$u['is_banned'] === 1): ?>
                                                <span class="badge bg-danger">Banned</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($u['created_at']))); ?></td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <a href="edit_user.php?id=<?= (int)$u['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <form method="post" class="px-3 py-1">
                                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                                            <input type="hidden" name="action" value="toggle_ban">
                                                            <input type="hidden" name="id" value="<?= (int)$u['id']; ?>">
                                                            <input type="hidden" name="mode" value="<?= (int)$u['is_banned'] ? 'unban' : 'ban'; ?>">
                                                            <button type="submit" class="dropdown-item"><?= (int)$u['is_banned'] ? 'Unban' : 'Ban'; ?></button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="post" onsubmit="return confirm('Delete this user? This cannot be undone.');" class="px-3 py-1">
                                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                                            <input type="hidden" name="action" value="delete_user">
                                                            <input type="hidden" name="id" value="<?= (int)$u['id']; ?>">
                                                            <button type="submit" class="dropdown-item text-danger">Delete</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3">No users found.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="mt-3" aria-label="Users pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page<=1?'disabled':''; ?>">
                        <a class="page-link" href="manage_users.php?<?= $baseQuery ?>&page=<?= max(1, $page-1); ?>">&laquo;</a>
                    </li>
                    <?php $start=max(1,$page-2);$end=min($total_pages,$page+2);
                    if ($start>1){echo '<li class="page-item"><a class="page-link" href="manage_users.php?'.$baseQuery.'&page=1">1</a></li>'; if($start>2)echo '<li class="page-item disabled"><span class="page-link">…</span></li>';}
                    for($i=$start;$i<=$end;$i++){echo '<li class="page-item '.($i===$page?'active':'').'"><a class="page-link" href="manage_users.php?'.$baseQuery.'&page='.$i.'">'.$i.'</a></li>';}
                    if ($end<$total_pages){if($end<$total_pages-1)echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; echo '<li class="page-item"><a class="page-link" href="manage_users.php?'.$baseQuery.'&page='.$total_pages.'">'.$total_pages.'</a></li>'; }?>
                    <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>">
                        <a class="page-link" href="manage_users.php?<?= $baseQuery ?>&page=<?= min($total_pages, $page+1); ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </main>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createUserLabel">Create New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="create_user">
        <div class="modal-body">
            <div class="mb-3">
                <label for="new_email" class="form-label">Email</label>
                <input type="email" class="form-control" id="new_email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">Password</label>
                <input type="password" class="form-control" id="new_password" name="password" minlength="8" required>
                <div class="form-text">Minimum 8 characters.</div>
            </div>
            <div class="mb-3">
                <label for="new_role" class="form-label">Role</label>
                <select id="new_role" name="role" class="form-select">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= htmlspecialchars($r); ?>"><?= htmlspecialchars(ucfirst($r)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-admin-primary">Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
