<?php
session_start();
require_once __DIR__ . '/functions/connectdb.php';

// AuthZ: require logged-in admin
if (!isset($_SESSION['USER_ID'])) { header('Location: login.php'); exit; }
$role = $_SESSION['USR_ROLE'] ?? '';
if ($role !== 'admin') { header('Location: index.php'); exit; }

$errors = [];
$success = '';
$isEdit = false;

// Handle create user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Branch: delete action first
  if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $targetId = isset($_POST['user_id']) && ctype_digit($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $currentUserId = (int)($_SESSION['USER_ID'] ?? 0);
    if ($targetId <= 0) {
      $errors[] = 'Invalid user id.';
    } else if ($targetId === $currentUserId) {
      $errors[] = 'You cannot delete your own account.';
    } else {
      // Prevent deleting last admin
      $isTargetAdmin = false;
      $resT = mysqli_query($con, 'SELECT role FROM users WHERE id=' . $targetId . ' LIMIT 1');
      if ($resT && ($rowT = mysqli_fetch_assoc($resT))) { $isTargetAdmin = ($rowT['role'] === 'admin'); }
      if ($isTargetAdmin) {
        $resCnt = mysqli_query($con, "SELECT COUNT(*) AS c FROM users WHERE role='admin'");
        $cnt = ($resCnt && ($r = mysqli_fetch_assoc($resCnt))) ? (int)$r['c'] : 1;
        if ($cnt <= 1) {
          $errors[] = 'Cannot delete the last admin.';
        }
      }
      if (!$errors) {
        if (mysqli_query($con, 'DELETE FROM users WHERE id=' . $targetId . ' LIMIT 1')) {
          $success = 'User deleted.';
        } else {
          $errors[] = 'Failed to delete user: ' . mysqli_error($con);
        }
      }
    }
  } else {
    // Create / Update
  $email = trim($_POST['email'] ?? '');
  $password = trim($_POST['password'] ?? '');
  $password2 = trim($_POST['password_confirm'] ?? '');
  $newRole = trim($_POST['role'] ?? 'user');
  $editId = isset($_POST['user_id']) && ctype_digit($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
  $isEdit = $editId > 0;

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required.';
  }
  if (!in_array($newRole, ['user','admin'], true)) {
    $errors[] = 'Invalid role.';
  }

  if ($isEdit) {
    // For edit: password is optional, but if provided must match confirm
    if ($password !== '' && $password !== $password2) {
      $errors[] = 'Passwords do not match.';
    }
  } else {
    // For create: password required and must match confirm
    if ($password === '') { $errors[] = 'Password is required.'; }
    if ($password !== $password2) { $errors[] = 'Passwords do not match.'; }
  }

  if (!$errors) {
    $emailEsc = mysqli_real_escape_string($con, $email);
    $roleEsc = mysqli_real_escape_string($con, $newRole);

    if ($isEdit) {
      // Check duplicate email for other users
      $dupeSql = "SELECT id FROM users WHERE email='$emailEsc' AND id <> $editId LIMIT 1";
      $dupeRes = mysqli_query($con, $dupeSql);
      if ($dupeRes && mysqli_fetch_assoc($dupeRes)) {
        $errors[] = 'A user with this email already exists.';
      } else {
        $setParts = ["email='$emailEsc'"];
        // Prevent editing own role
        $currentUserId = (int)($_SESSION['USER_ID'] ?? 0);
        if ($editId !== $currentUserId) {
          $setParts[] = "role='$roleEsc'";
        }
        if ($password !== '') {
          $hash = hash('sha512', $password);
          $setParts[] = "pass='$hash'";
        }
        $updSql = 'UPDATE users SET ' . implode(',', $setParts) . ' WHERE id=' . $editId . ' LIMIT 1';
        if (mysqli_query($con, $updSql)) {
          $success = 'User updated successfully.';
        } else {
          $errors[] = 'Failed to update user: ' . mysqli_error($con);
      }
      }
      }
    } else {
      // Create
      $dupeSql = "SELECT id FROM users WHERE email='$emailEsc' LIMIT 1";
      $dupeRes = mysqli_query($con, $dupeSql);
      if ($dupeRes && mysqli_fetch_assoc($dupeRes)) {
        $errors[] = 'A user with this email already exists.';
      } else {
        $hash = hash('sha512', $password);
        $insSql = "INSERT INTO users (email, pass, role) VALUES ('$emailEsc', '$hash', '$roleEsc')";
        if (mysqli_query($con, $insSql)) {
          $success = 'User created successfully.';
        } else {
          $errors[] = 'Failed to create user: ' . mysqli_error($con);
        }
      }
    }
  }
}

// Load users list
$users = [];
$res = mysqli_query($con, 'SELECT id, email, role FROM users ORDER BY id DESC');
if ($res) { while ($row = mysqli_fetch_assoc($res)) { $users[] = $row; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="assets/favicon.png" type="image/x-icon">
  <title>Admin - User Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
  <style>
    html, body { margin: 0; padding: 0; }
    header .navbar { top: 0; left: 0; right: 0; }
    .top-offset { margin-top: 90px; }
    header .navbar { z-index: 1050; }
  </style>
</head>
<body>
<header>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark position-absolute w-100 p-0">
    <div class="container-fluid">
      <a class="navbar-brand" style="font-weight: bold" href="index.php">
        <img style="width: 80px;" src="assets/logo.png" alt="Fleetara Logo"/>
        Fleetara
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto" style="font-weight: bold">
          <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
          <li class="nav-item"><a class="nav-link" href="trailers.php">Trailers</a></li>
          <li class="nav-item"><a class="nav-link" href="rentals.php">Rentals</a></li>
          <li class="nav-item"><a class="nav-link" href="assets.php">Assets</a></li>
          <li class="nav-item"><a class="nav-link" href="fuel.php">Fuel</a></li>
          <li class="nav-item"><a class="nav-link" href="dvirs.php">DVIRS</a></li>
          <li class="nav-item"><a class="nav-link active" href="admin.php">Admin</a></li>
          <li class="nav-item"><a class="nav-link text-danger" href="index.php?Logout=1">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
</header>

<div class="bg-light border-bottom top-offset">
  <div class="container py-2 d-flex align-items-center justify-content-between">
    <div class="fw-bold">Admin / User Management</div>
    <div class="text-muted small">Signed in as: <?php echo htmlspecialchars($_SESSION['USR_LOGIN'] ?? ''); ?> (<?php echo htmlspecialchars($role); ?>)</div>
  </div>
</div>

<main class="container my-4">
  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger mb-3">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-md-5">
      <div class="card">
        <div class="card-header fw-bold" id="formTitle">Add New User</div>
        <div class="card-body">
          <form method="post" autocomplete="off" id="userForm">
            <input type="hidden" name="user_id" id="user_id" value="">
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <div class="input-group">
                <input type="password" class="form-control" id="password" name="password">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePw()"><i class="bi bi-eye"></i></button>
              </div>
              <div class="form-text" id="pwHelp" style="display:none;">Leave blank to keep the current password.</div>
            </div>
            <div class="mb-3">
              <label for="password_confirm" class="form-label">Confirm Password</label>
              <input type="password" class="form-control" id="password_confirm" name="password_confirm">
            </div>
            <div class="mb-3">
              <label for="role" class="form-label">Role</label>
              <select id="role" name="role" class="form-select">
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select>
              <div class="form-text" id="roleHelp" style="display:none;">You cannot change your own role.</div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary" id="submitBtn">Create User</button>
              <button type="button" class="btn btn-outline-secondary" id="cancelEditBtn" style="display:none;">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card">
        <div class="card-header fw-bold">Existing Users</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:80px;">ID</th>
                  <th>Email</th>
                  <th style="width:120px;">Role</th>
                  <th style="width:80px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$users): ?>
                  <tr><td colspan="4" class="text-center text-muted">No users found</td></tr>
                <?php else: foreach ($users as $u): ?>
                  <tr>
                    <td><?php echo (int)$u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><span class="badge bg-<?php echo $u['role']==='admin' ? 'danger' : 'secondary'; ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
                    <td class="d-flex gap-1">
                      <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                        onclick="startEditUser(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($u['role'], ENT_QUOTES); ?>')">
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <form method="post" onsubmit="return confirmDelete(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars($u['email'], ENT_QUOTES); ?>');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
function togglePw(){
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
function confirmDelete(id, email){
  const currentUserId = <?php echo (int)($_SESSION['USER_ID'] ?? 0); ?>;
  if (id === currentUserId) { alert('You cannot delete your own account.'); return false; }
  return confirm('Delete user ' + email + '? This cannot be undone.');
}
function startEditUser(id, email, role){
  document.getElementById('formTitle').textContent = 'Edit User';
  document.getElementById('user_id').value = id;
  document.getElementById('email').value = email;
  document.getElementById('role').value = role;
  document.getElementById('password').value = '';
  document.getElementById('password_confirm').value = '';
  document.getElementById('pwHelp').style.display = '';
  document.getElementById('submitBtn').textContent = 'Update User';
  document.getElementById('cancelEditBtn').style.display = '';
  document.getElementById('email').focus();
  // UI guard: if editing self, disable role select
  const currentUserId = <?php echo (int)($_SESSION['USER_ID'] ?? 0); ?>;
  const roleSel = document.getElementById('role');
  const roleHelp = document.getElementById('roleHelp');
  if (id === currentUserId) { roleSel.setAttribute('disabled','disabled'); roleHelp.style.display = ''; }
  else { roleSel.removeAttribute('disabled'); roleHelp.style.display = 'none'; }
}
function resetForm(){
  document.getElementById('formTitle').textContent = 'Add New User';
  document.getElementById('user_id').value = '';
  document.getElementById('email').value = '';
  document.getElementById('role').value = 'user';
  document.getElementById('password').value = '';
  document.getElementById('password_confirm').value = '';
  document.getElementById('pwHelp').style.display = 'none';
  document.getElementById('submitBtn').textContent = 'Create User';
  document.getElementById('cancelEditBtn').style.display = 'none';
  document.getElementById('role').removeAttribute('disabled');
  document.getElementById('roleHelp').style.display = 'none';
}
document.getElementById('cancelEditBtn')?.addEventListener('click', resetForm);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
