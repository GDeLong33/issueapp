<?php
session_start();
require '../database/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = Database::connect();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// fetch current user
$stmt    = $conn->prepare("SELECT admin FROM iss_persons WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$isAdmin = strtoupper($stmt->fetchColumn() ?? '') === 'T';

// 1) handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='delete') {
    $id = (int)$_POST['id'];
    $own = $conn->prepare("SELECT created_by FROM iss_issues WHERE id = ?");
    $own->execute([$id]);
    $uploader = $own->fetchColumn();
    if ($isAdmin || $uploader == $_SESSION['user_id']) {
        $conn->prepare("DELETE FROM iss_issues WHERE id = ?")
             ->execute([$id]);
    }
    header("Location: issues_list.php?view=" . urlencode($_GET['view'] ?? 'open'));
    exit();
}

// 2) handle add
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='add') {
    $ins = $conn->prepare("
      INSERT INTO iss_issues
        (short_description,long_description,open_date,close_date,priority,org,project,per_id,created_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([
      $_POST['short_description'],
      $_POST['long_description'],
      $_POST['open_date'],
      empty($_POST['close_date']) ? null : $_POST['close_date'],
      $_POST['priority'],
      $_POST['organization'],
      $_POST['project'],
      $_POST['per_id'],
      $_SESSION['user_id'],
    ]);
    header("Location: issues_list.php?view=" . urlencode($_GET['view'] ?? 'open'));
    exit();
}

// 3) handle update
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='update') {
    $id = (int)$_POST['id'];
    $own = $conn->prepare("SELECT created_by FROM iss_issues WHERE id = ?");
    $own->execute([$id]);
    $uploader = $own->fetchColumn();
    if ($isAdmin || $uploader == $_SESSION['user_id']) {
        $upd = $conn->prepare("
          UPDATE iss_issues SET
            short_description=?, long_description=?, open_date=?, close_date=?,
            priority=?, org=?, project=?, per_id=?
          WHERE id=?
        ");
        $upd->execute([
          $_POST['short_description'],
          $_POST['long_description'],
          $_POST['open_date'],
          empty($_POST['close_date']) ? null : $_POST['close_date'],
          $_POST['priority'],
          $_POST['organization'],
          $_POST['project'],
          $_POST['per_id'],
          $id
        ]);
    }
    header("Location: issues_list.php?view=" . urlencode($_GET['view'] ?? 'open'));
    exit();
}

// 4) detect view filter
$view = ($_GET['view'] ?? 'open') === 'all' ? 'all' : 'open';

// 5) fetch issues with filter
if ($view === 'open') {
    $sql = "
      SELECT i.*, p.fname, p.lname
        FROM iss_issues i
        LEFT JOIN iss_persons p ON i.per_id = p.id
       WHERE i.close_date IS NULL
       ORDER BY i.project, i.priority DESC, i.open_date
    ";
} else {
    $sql = "
      SELECT i.*, p.fname, p.lname
        FROM iss_issues i
        LEFT JOIN iss_persons p ON i.per_id = p.id
       ORDER BY i.project, i.priority DESC, i.open_date
    ";
}
$issues = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// 6) fetch people
$people = $conn
  ->query("SELECT id,fname,lname FROM iss_persons ORDER BY lname")
  ->fetchAll(PDO::FETCH_ASSOC);

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
    <a class="navbar-brand" href="issues_list.php">Department Issues</a>
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

  <!-- VIEW TOGGLE -->
  <div class="btn-group mb-3" role="group">
    <a href="issues_list.php?view=open"
       class="btn <?= $view==='open' ? 'btn-primary' : 'btn-outline-primary' ?>">
      Open Issues
    </a>
    <a href="issues_list.php?view=all"
       class="btn <?= $view==='all'  ? 'btn-primary' : 'btn-outline-primary' ?>">
      All Issues
    </a>
  </div>

  <div class="d-flex justify-content-end mb-2">
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#issueModal" id="addBtn">
      + Add Issue
    </button>
  </div>

  <table class="table table-striped">
    <thead>
      <tr>
        <th>ID</th><th>Project</th><th>Short Desc</th><th>Priority</th>
        <th>Open</th><th>Close</th><th>Resp.</th><th>Actions</th>
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
        <td><?= htmlspecialchars($row['fname'].' '.$row['lname']) ?></td>
        <td>
          <a href="issue.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">View</a>
          <?php if($isAdmin || $row['created_by']==$_SESSION['user_id']): ?>
            <a href="?edit_id=<?= $row['id'] ?>&view=<?= $view ?>"
               class="btn btn-warning btn-sm">Edit</a>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id"     value="<?= $row['id'] ?>">
              <button onclick="return confirm('Delete?')" class="btn btn-danger btn-sm">
                Delete
              </button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ADD / EDIT MODAL (same as before) -->
<div class="modal fade" id="issueModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><?= isset($editMode) && $editMode ? 'Edit Issue':'Add Issue' ?></h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <form id="issueForm" method="POST">
        <input type="hidden" name="action" value="<?= isset($editMode)&&$editMode?'update':'add' ?>">
        <?php if(!empty($editData)): ?>
          <input type="hidden" name="id" value="<?= $editData['id'] ?>">
        <?php endif; ?>
        <!-- form fields… same as before … -->
        <div class="mb-3">
          <label>Person Responsible</label>
          <select name="per_id" class="form-select" required>
            <option value="">Select…</option>
            <?php foreach($people as $p): 
              $sel = (!empty($editData) && $editData['per_id']==$p['id'])?'selected':''; ?>
              <option value="<?=$p['id']?>" <?=$sel?>>
                <?=htmlspecialchars($p['fname'].' '.$p['lname'])?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label>Short Description</label>
          <input name="short_description" class="form-control" required
                 value="<?= !empty($editData)?htmlspecialchars($editData['short_description']):'' ?>">
        </div>
        <div class="mb-3">
          <label>Long Description</label>
          <textarea name="long_description" class="form-control" required><?= !empty($editData)?htmlspecialchars($editData['long_description']):'' ?></textarea>
        </div>
        <div class="mb-3">
          <label>Open Date</label>
          <input type="date" name="open_date" class="form-control" required
                 value="<?= !empty($editData)?htmlspecialchars($editData['open_date']):'' ?>">
        </div>
        <div class="mb-3">
          <label>Close Date</label>
          <input type="date" name="close_date" class="form-control"
                 value="<?= (!empty($editData)&&$editData['close_date'])?htmlspecialchars($editData['close_date']):'' ?>">
        </div>
        <div class="mb-3">
          <label>Priority</label>
          <select name="priority" class="form-select" required>
            <?php foreach(['High','Medium','Low'] as $lvl):
              $sel = (!empty($editData) && $editData['priority']==$lvl)?'selected':''; ?>
              <option value="<?=$lvl?>" <?=$sel?>><?=$lvl?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="mb-3">
          <label>Organization</label>
          <input name="organization" class="form-control" required
                 value="<?= !empty($editData)?htmlspecialchars($editData['org']):'' ?>">
        </div>
        <div class="mb-3">
          <label>Project</label>
          <input name="project" class="form-control" required
                 value="<?= !empty($editData)?htmlspecialchars($editData['project']):'' ?>">
        </div>

        <button class="btn btn-primary"><?= isset($editMode)&&$editMode?'Update':'Add' ?> Issue</button>
      </form>
    </div>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('addBtn').onclick = () => {
    document.getElementById('issueForm').reset();
    document.querySelector('input[name="action"]').value = 'add';
    document.querySelector('input[name="id"]')?.remove();
  };
  <?php if(!empty($editMode) && $editMode): ?>
  new bootstrap.Modal(document.getElementById('issueModal')).show();
  <?php endif; ?>
</script>
</body>
</html>
