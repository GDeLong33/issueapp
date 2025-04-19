<?php
session_start();
require '../database/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch current user and check admin
$stmt = $pdo->prepare("SELECT * FROM iss_persons WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
$isAdmin = $currentUser && strtoupper($currentUser['admin']) === 'T';

// —————————————————————————————————————————
// HANDLE DELETE
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'delete_user'
    && $isAdmin
) {
    $del = $pdo->prepare("DELETE FROM iss_persons WHERE id = ?");
    $del->execute([$_POST['id']]);
    header("Location: people.php");
    exit();
}

// —————————————————————————————————————————
// HANDLE INLINE UPDATE
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'update_user'
    && $isAdmin
) {
    $upd = $pdo->prepare("UPDATE iss_persons SET fname = ?, lname = ? WHERE id = ?");
    $upd->execute([
        $_POST['fname'],
        $_POST['lname'],
        $_POST['id']
    ]);
    header("Location: people.php");
    exit();
}

// —————————————————————————————————————————
// FETCH FOR EDIT MODAL
$editMode = false;
$editData = [];
if (isset($_GET['edit_id']) && $isAdmin) {
    $editMode = true;
    $e = $pdo->prepare("SELECT id, fname, lname FROM iss_persons WHERE id = ?");
    $e->execute([$_GET['edit_id']]);
    $editData = $e->fetch(PDO::FETCH_ASSOC);
}

// —————————————————————————————————————————
// FETCH ALL USERS
$q = $pdo->prepare("SELECT id, fname, lname FROM iss_persons ORDER BY lname ASC");
$q->execute();
$people = $q->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>All Users</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
</head>
<body>

<nav class="navbar bg-body-tertiary fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="issues_list.php">Department Issues</a>
    <button 
      class="navbar-toggler" 
      type="button" 
      data-bs-toggle="offcanvas" 
      data-bs-target="#offcanvasNavbar"
    >
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="offcanvas offcanvas-end" id="offcanvasNavbar">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menu</h5>
        <button class="btn-close" data-bs-dismiss="offcanvas"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav">
          <li class="nav-item"><a class="nav-link" href="issues_list.php">Issues</a></li>
          <li class="nav-item"><a class="nav-link active" href="people.php">People</a></li>
          <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="container mt-5 pt-4">
  <h2 class="mb-4">All Users</h2>

  <?php if ($isAdmin): ?>
    <a href="Newlogin.php" class="btn btn-success mb-3">+ New User</a>
  <?php endif; ?>

  <table class="table table-striped table-bordered">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>First Name</th>
        <th>Last Name</th>
        <?php if ($isAdmin): ?>
          <th>Actions</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($people as $person): ?>
        <tr>
          <td><?= htmlspecialchars($person['id']) ?></td>
          <td><?= htmlspecialchars($person['fname']) ?></td>
          <td><?= htmlspecialchars($person['lname']) ?></td>
          <?php if ($isAdmin): ?>
            <td>
              <a 
                href="people.php?edit_id=<?= $person['id'] ?>" 
                class="btn btn-primary btn-sm"
              >Edit</a>

              <form 
                method="POST" 
                action="people.php" 
                class="d-inline"
                onsubmit="return confirm('Delete this user?')"
              >
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="id"     value="<?= $person['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- EDIT USER MODAL -->
<?php if ($editMode): ?>
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit User #<?= $editData['id'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="people.php">
          <input type="hidden" name="action" value="update_user">
          <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id']) ?>">

          <div class="mb-3">
            <label class="form-label">First Name</label>
            <input 
              type="text" 
              class="form-control" 
              name="fname" 
              required 
              value="<?= htmlspecialchars($editData['fname']) ?>"
            >
          </div>
          <div class="mb-3">
            <label class="form-label">Last Name</label>
            <input 
              type="text" 
              class="form-control" 
              name="lname" 
              required 
              value="<?= htmlspecialchars($editData['lname']) ?>"
            >
          </div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($editMode): ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
  });
</script>
<?php endif; ?>

</body>
</html>
