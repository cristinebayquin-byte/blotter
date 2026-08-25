<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/log.php';

session_timeout_guard();
require_login();
require_role(['captain', 'secretary']);

$users = db()->query('SELECT id, full_name, username, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fullName = trim((string)($_POST['full_name'] ?? ''));
  $username = trim((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $role = (string)($_POST['role'] ?? '');

  $allowedRoles = ['captain', 'secretary', 'councilor', 'lupon'];

  if ($fullName === '' || $username === '' || $password === '' || $role === '') {
    $notice = 'All fields are required.';
  } elseif (!in_array($role, $allowedRoles, true)) {
    $notice = 'Invalid role.';
  } elseif (strlen($username) < 3 || strlen($username) > 60) {
    $notice = 'Username must be between 3 and 60 characters.';
  } elseif (strlen($password) < 4) {
    $notice = 'Password must be at least 4 characters.';
  } else {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = db()->prepare('INSERT INTO users (full_name, username, password_hash, role) VALUES (:full_name, :username, :password_hash, :role)');
    $stmt->execute([
      ':full_name' => $fullName,
      ':username' => $username,
      ':password_hash' => $hash,
      ':role' => $role,
    ]);
    system_log('create', 'users', (int)db()->lastInsertId());
    $notice = 'User created successfully.';
    $users = db()->query('SELECT id, full_name, username, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <script src="assets/app.js" defer></script>
  <style>
    .password-field { position: relative; }
    .password-field .form-control { padding-right: 3rem; }
    .password-toggle { position: absolute; top: 50%; right: .75rem; z-index: 2; width: 1.5rem; height: 1.5rem; padding: 0; color: #212529; background: transparent; border: 0; transform: translateY(-50%); }
    .password-toggle:focus-visible { outline: 2px solid #0d6efd; outline-offset: 2px; border-radius: .25rem; }
    .password-toggle svg { width: 100%; height: 100%; }
  </style>
</head>
<body>
<div class="d-flex app-shell">
  <aside class="bg-dark text-white p-3 app-sidebar">
    <div class="app-brand">BRGY. SAN JOSE<small>BLOTTER SYSTEM</small></div>
    <nav class="nav flex-column gap-1">
      <a class="nav-link text-white" href="?route=dashboard">Dashboard</a>
      <a class="nav-link text-white" href="?route=blotter_create">Blotter Records</a>
      <a class="nav-link text-white" href="?route=search">Search</a>
      <a class="nav-link text-white" href="?route=reports">Reports</a>
      <a class="nav-link text-white active" href="?route=users">User Management</a>
    </nav>
  </aside>

  <main class="flex-grow-1 p-4 app-main">
    <div class="d-flex align-items-start justify-content-between page-header">
      <div><h1 class="page-heading">User Management</h1><p class="page-subtitle">Manage authorized staff access to the blotter system.</p></div>
      <div class="small text-muted">Logged in: <?php echo htmlspecialchars((string)current_user()['full_name']); ?></div>
    </div>

    <?php if ($notice): ?>
      <div class="alert alert-info"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Add User</h6>
        <form method="post" id="userForm" class="needs-validation" novalidate>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Full Name</label>
              <input class="form-control" name="full_name" required maxlength="120">
              <div class="invalid-feedback">Full name is required.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Username</label>
              <input class="form-control" name="username" required maxlength="60" minlength="3">
              <div class="invalid-feedback">Username is required (min 3 chars).</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Password</label>
              <div class="password-field">
                <input type="password" id="newUserPassword" class="form-control" name="password" required minlength="4">
                <button class="password-toggle" type="button" id="toggleNewUserPassword" aria-label="Show password" aria-pressed="false">
                  <svg id="toggleNewUserPasswordIcon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="2.5" /><path d="M3 3l18 18" /></svg>
                </button>
              </div>
              <div class="invalid-feedback">Password is required (min 4 chars).</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Role</label>
              <select class="form-select" name="role" required>
                <option value="">Select role...</option>
                <option value="captain">Barangay Captain</option>
                <option value="secretary">Barangay Secretary</option>
                <option value="councilor">Barangay Councilor</option>
                <option value="lupon">Lupon Member</option>
              </select>
              <div class="invalid-feedback">Role is required.</div>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Create User</button>
            <a class="btn btn-outline-secondary" href="?route=dashboard">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm mt-4">
      <div class="card-body">
        <h6 class="mb-3">Existing Users</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr><th>ID</th><th>Full Name</th><th>Username</th><th>Role</th><th>Created</th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?php echo (int)$u['id']; ?></td>
                  <td><?php echo htmlspecialchars((string)$u['full_name']); ?></td>
                  <td><?php echo htmlspecialchars((string)$u['username']); ?></td>
                  <td><?php echo htmlspecialchars((string)$u['role']); ?></td>
                  <td><?php echo htmlspecialchars((string)$u['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="mt-3">
      <a href="?route=logout" class="btn btn-outline-dark">Logout</a>
    </div>
  </main>
</div>

<script>
  (function(){
    const form = document.getElementById('userForm');
      form.addEventListener('submit', function(e){
      if(!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      form.classList.add('was-validated');
      });
      const password = document.getElementById('newUserPassword');
      const toggle = document.getElementById('toggleNewUserPassword');
      const icon = document.getElementById('toggleNewUserPasswordIcon');
      toggle.addEventListener('click', function () {
        const isPassword = password.type === 'password';
        password.type = isPassword ? 'text' : 'password';
        toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        toggle.setAttribute('aria-pressed', String(isPassword));
        icon.innerHTML = isPassword
          ? '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="2.5" />'
          : '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="2.5" /><path d="M3 3l18 18" />';
      });
  })();
</script>
</body>
</html>

