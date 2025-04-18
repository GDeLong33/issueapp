<?php
session_start();
require '../database/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1) CONNECT & MAKE close_date NULLABLE
$conn = Database::connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->exec("ALTER TABLE iss_issues MODIFY close_date DATE NULL");

// 2) WHO’S LOGGED IN?
$user_stmt = $conn->prepare("SELECT * FROM iss_persons WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user    = $user_stmt->fetch(PDO::FETCH_ASSOC);
$isAdmin = $user && strtoupper($user['admin']) === 'T';

// 3) DELETE (admins OR owners)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' 
    && ($_POST['action'] ?? '') === 'delete'
) {
    // lookup owner
    $ownerStmt = $conn->prepare("SELECT per_id FROM iss_issues WHERE id = ?");
    $ownerStmt->execute([$_POST['id']]);
    $ownerId = $ownerStmt->fetchColumn();

    if ($isAdmin || $ownerId == $_SESSION['user_id']) {
        $del = $conn->prepare("DELETE FROM iss_issues WHERE id = ?");
        $del->execute([$_POST['id']]);
    }
    header("Location: issues_list.php");
    exit();
}

// 4) ADD (any logged‑in user)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' 
    && ($_POST['action'] ?? '') === 'add'
) {
    $ins = $conn->prepare("
      INSERT INTO iss_issues 
        (short_description,long_description,open_date,close_date,priority,org,project,per_id)
      VALUES (?,?,?,?,?,?,?,?)
    ");
    $ins->bindValue(1, $_POST['short_description'], PDO::PARAM_STR);
    $ins->bindValue(2, $_POST['long_description'],  PDO::PARAM_STR);
    $ins->bindValue(3, $_POST['open_date'],         PDO::PARAM_STR);

    if (empty($_POST['close_date'])) {
        $ins->bindValue(4, null, PDO::PARAM_NULL);
    } else {
        $ins->bindValue(4, $_POST['close_date'], PDO::PARAM_STR);
    }

    $ins->bindValue(5, $_POST['priority'],   PDO::PARAM_STR);
    $ins->bindValue(6, $_POST['organization'], PDO::PARAM_STR);
    $ins->bindValue(7, $_POST['project'],      PDO::PARAM_STR);
    // assign current user as owner
    $ins->bindValue(8, $_SESSION['user_id'],   PDO::PARAM_INT);

    $ins->execute();
    header("Location: issues_list.php");
    exit();
}

// 5) UPDATE (admins OR owners)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' 
    && ($_POST['action'] ?? '') === 'update'
) {
    $id = $_POST['id'];
    // lookup owner
    $ownerStmt = $conn->prepare("SELECT per_id FROM iss_issues WHERE id = ?");
    $ownerStmt->execute([$id]);
    $ownerId = $ownerStmt->fetchColumn();

    if ($isAdmin || $ownerId == $_SESSION['user_id']) {
        $upd = $conn->prepare("
          UPDATE iss_issues SET
            short_description=?, long_description=?, open_date=?, close_date=?,
            priority=?, org=?, project=?
          WHERE id=?
        ");
        $upd->bindValue(1, $_POST['short_description'], PDO::PARAM_STR);
        $upd->bindValue(2, $_POST['long_description'],  PDO::PARAM_STR);
        $upd->bindValue(3, $_POST['open_date'],         PDO::PARAM_STR);

        if (empty($_POST['close_date'])) {
            $upd->bindValue(4, null, PDO::PARAM_NULL);
        } else {
            $upd->bindValue(4, $_POST['close_date'], PDO::PARAM_STR);
        }

        $upd->bindValue(5, $_POST['priority'],  PDO::PARAM_STR);
        $upd->bindValue(6, $_POST['organization'], PDO::PARAM_STR);
        $upd->bindValue(7, $_POST['project'],     PDO::PARAM_STR);
        $upd->bindValue(8, $id,                  PDO::PARAM_INT);

        $upd->execute();
    }
    header("Location: issues_list.php");
    exit();
}

// 6) EDIT MODE? (admins OR owners)
$editMode = false;
$editData = [];
if (isset($_GET['edit_id'])) {
    $eid = $_GET['edit_id'];
    $ownerStmt = $conn->prepare("SELECT per_id FROM iss_issues WHERE id = ?");
    $ownerStmt->execute([$eid]);
    $ownerId = $ownerStmt->fetchColumn();

    if ($isAdmin || $ownerId == $_SESSION['user_id']) {
        $editMode = true;
        $e = $conn->prepare("SELECT * FROM iss_issues WHERE id = ?");
        $e->execute([$eid]);
        $editData = $e->fetch(PDO::FETCH_ASSOC);
    }
}

// 7) FETCH FOR DISPLAY
$issues = $conn
  ->query("
    SELECT i.*, p.fname, p.lname
      FROM iss_issues i
      LEFT JOIN iss_persons p ON i.per_id = p.id
      ORDER BY i.project, i.priority DESC, i.open_date
  ")->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Issues List</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
</head>
<body>

<nav class="navbar bg-body-tertiary fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Department Issues</a>
    <button class="navbar-toggler" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="offcanvas offcanvas-end" id="offcanvasNavbar">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menu</h5>
        <button class="btn-close" data-bs-dismiss="offcanvas"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav">
          <li class="nav-item"><a class="nav-link active" href="issues_list.php">Issues</a></li>
          <li class="nav-item"><a class="nav-link" href="people.php">People</a></li>
          <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="container mt-5 pt-4">
  <div class="d-flex justify-content-end mt-3">
    <button 
      class="btn btn-success" 
      data-bs-toggle="modal" 
      data-bs-target="#addIssueModal" 
      id="addBtn"
    >+ Add Issue</button>
  </div>

  <table class="table table-striped mt-3">
    <thead>
      <tr>
        <th>ID</th><th>Issue</th><th>Short Desc</th><th>Priority</th>
        <th>Open Date</th><th>Close Date</th><th>Responsible</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($issues as $row): ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['project']) ?></td>
          <td><?= htmlspecialchars($row['short_description']) ?></td>
          <td><?= htmlspecialchars($row['priority']) ?></td>
          <td><?= htmlspecialchars($row['open_date']) ?></td>
          <td><?= $row['close_date'] ?? 'N/A' ?></td>
          <td>
            <?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?>
          </td>
          <td>
            <a href="issue.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">View</a>

            <?php if ($isAdmin || $row['per_id'] == $_SESSION['user_id']): ?>
              <a href="issues_list.php?edit_id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Update</a>
              <form method="POST" action="issues_list.php" class="d-inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id"     value="<?= $row['id'] ?>">
                <button 
                  type="submit" 
                  class="btn btn-danger btn-sm" 
                  onclick="return confirm('Delete this issue?')"
                >Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Add / Update Modal -->
<div class="modal fade" id="addIssueModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= $editMode ? 'Update Issue' : 'Add New Issue' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="issues_list.php" id="issueForm">
          <input type="hidden" name="action" value="<?= $editMode ? 'update' : 'add' ?>">
          <?php if ($editMode): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id']) ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label">Short Description</label>
            <input type="text" class="form-control" name="short_description" required
                   value="<?= $editMode ? htmlspecialchars($editData['short_description']) : '' ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Long Description</label>
            <textarea class="form-control" name="long_description" required><?= $editMode ? htmlspecialchars($editData['long_description']) : '' ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Open Date</label>
            <input type="date" class="form-control" name="open_date" required
                   value="<?= $editMode ? htmlspecialchars($editData['open_date']) : '' ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Close Date</label>
            <input type="date" class="form-control" name="close_date"
                   value="<?= $editMode && $editData['close_date'] ? htmlspecialchars($editData['close_date']) : '' ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Priority</label>
            <select class="form-select" name="priority" required>
              <?php foreach(['High','Medium','Low'] as $level):
                $sel = ($editMode && $editData['priority'] === $level) ? 'selected' : '';
              ?>
                <option value="<?= $level ?>" <?= $sel ?>><?= $level ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Organization</label>
            <input type="text" class="form-control" name="organization" required
                   value="<?= $editMode ? htmlspecialchars($editData['org']) : '' ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Issue</label>
            <input type="text" class="form-control" name="project" required
                   value="<?= $editMode ? htmlspecialchars($editData['project']) : '' ?>">
          </div>

          <button type="submit" class="btn btn-primary"><?= $editMode ? 'Update Issue' : 'Add Issue' ?></button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // reset form when opening "Add Issue"
  document.getElementById('addBtn').addEventListener('click', function() {
    document.getElementById('issueForm').reset();
    document.querySelector('input[name="action"]').value = 'add';
    document.querySelector('input[name="id"]')?.remove();
  });

  // auto‑show modal if editing
  <?php if ($editMode): ?>
  document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('addIssueModal')).show();
  });
  <?php endif; ?>
</script>

</body>
</html>
