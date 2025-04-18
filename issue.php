<?php
session_start();
require '../database/database.php';

if (!isset($_GET['id'])) {
    die("Issue ID not provided.");
}

// ensure logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$issue_id = intval($_GET['id']);
$user_id  = $_SESSION['user_id'];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// fetch admin status
$user_stmt = $pdo->prepare("SELECT admin FROM iss_persons WHERE id = ?");
$user_stmt->execute([$user_id]);
$user      = $user_stmt->fetch(PDO::FETCH_ASSOC);
$is_admin  = ($user && $user['admin'] === 'T');

// —————————————————————————————————————————
// handle new comment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['comment'])) {
    $short = trim($_POST['short_comment']);
    $long  = trim($_POST['long_comment']);
    $date  = date("Y-m-d");
    if ($short && $long) {
        $ins = $pdo->prepare("
          INSERT INTO iss_comments
            (per_id, iss_id, short_comment, long_comment, posted_date)
          VALUES (?, ?, ?, ?, ?)
        ");
        $ins->execute([$user_id, $issue_id, $short, $long, $date]);
    }
    header("Location: issue.php?id={$issue_id}");
    exit();
}

// handle delete comment (admin only)
if (isset($_GET['delete_comment']) && $is_admin) {
    $cid = intval($_GET['delete_comment']);
    $del = $pdo->prepare("DELETE FROM iss_comments WHERE id = ?");
    $del->execute([$cid]);
    header("Location: issue.php?id={$issue_id}");
    exit();
}

// handle update comment (admin or owner)
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'update_comment') {
    $cid    = intval($_POST['comment_id']);
    $short  = trim($_POST['short_comment']);
    $long   = trim($_POST['long_comment']);
    // fetch owner
    $own = $pdo->prepare("SELECT per_id FROM iss_comments WHERE id = ?");
    $own->execute([$cid]);
    $owner = $own->fetchColumn();
    if ($is_admin || $owner == $user_id) {
        $upd = $pdo->prepare("
          UPDATE iss_comments
          SET short_comment = ?, long_comment = ?
          WHERE id = ?
        ");
        $upd->execute([$short, $long, $cid]);
    }
    header("Location: issue.php?id={$issue_id}");
    exit();
}

// prepare edit-comment modal
$editCommentMode = false;
$editCommentData = [];
if (isset($_GET['edit_comment'])) {
    $ecid = intval($_GET['edit_comment']);
    $fetch = $pdo->prepare("
      SELECT id, per_id, short_comment, long_comment
      FROM iss_comments WHERE id = ?
    ");
    $fetch->execute([$ecid]);
    $data = $fetch->fetch(PDO::FETCH_ASSOC);
    if ($data && ($is_admin || $data['per_id'] == $user_id)) {
        $editCommentMode = true;
        $editCommentData = $data;
    }
}

// fetch issue
$iss = $pdo->prepare("SELECT * FROM iss_issues WHERE id = ?");
$iss->execute([$issue_id]);
$issue = $iss->fetch(PDO::FETCH_ASSOC);
if (!$issue) {
    die("Issue not found.");
}

// fetch comments (including per_id)
$cstmt = $pdo->prepare("
  SELECT c.id, c.per_id, c.short_comment, c.long_comment, c.posted_date,
         p.fname, p.lname
    FROM iss_comments c
    JOIN iss_persons p ON c.per_id = p.id
   WHERE c.iss_id = ?
   ORDER BY c.posted_date DESC
");
$cstmt->execute([$issue_id]);
$comments = $cstmt->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Issue Details</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
</head>
<body class="bg-light">

  <!-- NAVBAR / OFFCANVAS -->
  <nav class="navbar bg-body-tertiary fixed-top">
    <div class="container-fluid">
      <a class="navbar-brand" href="issues_list.php">Department Issues</a>
      <button class="navbar-toggler" type="button"
              data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="offcanvas offcanvas-end" id="offcanvasNavbar">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title">Menu</h5>
          <button type="button" class="btn-close"
                  data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" href="issues_list.php">Issues</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="people.php">People</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="logout.php">Logout</a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <div class="container mt-5 pt-4">
    <h2 class="mb-4">Issue Details</h2>

    <div class="card shadow p-4 mb-4">
      <h4><?= htmlspecialchars($issue['short_description']) ?></h4>
      <p><strong>Description:</strong>
         <?= nl2br(htmlspecialchars($issue['long_description'])) ?></p>
      <p><strong>Priority:</strong> <?= htmlspecialchars($issue['priority']) ?></p>
      <p><strong>Organization:</strong> <?= htmlspecialchars($issue['org']) ?></p>
      <p><strong>Project:</strong> <?= htmlspecialchars($issue['project']) ?></p>
      <p><strong>Open Date:</strong> <?= htmlspecialchars($issue['open_date']) ?></p>
      <p><strong>Close Date:</strong> <?= htmlspecialchars($issue['close_date']) ?></p>
    </div>

    <h3 class="mt-4">Comments</h3>
    <div class="list-group mb-4">
      <?php foreach ($comments as $c): ?>
        <div class="list-group-item">
          <p><strong>Topic:</strong>
             <?= nl2br(htmlspecialchars($c['short_comment'])) ?></p>
          <p><strong>Comment:</strong>
             <?= nl2br(htmlspecialchars($c['long_comment'])) ?></p>
          <small class="text-muted">
            By <?= htmlspecialchars($c['fname'].' '.$c['lname']) ?> 
            on <?= htmlspecialchars($c['posted_date']) ?>
          </small>

          <?php if ($is_admin || $c['per_id'] == $user_id): ?>
            <a href="issue.php?id=<?= $issue_id ?>&edit_comment=<?= $c['id'] ?>"
               class="btn btn-warning btn-sm me-1">Edit</a>
          <?php endif; ?>

          <?php if ($is_admin): ?>
            <a href="issue.php?id=<?= $issue_id ?>&delete_comment=<?= $c['id'] ?>"
               class="btn btn-danger btn-sm float-end"
               onclick="return confirm('Delete this comment?')"
            >Delete</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <h3>Add a Comment</h3>
    <form method="POST" class="card p-4 shadow mb-5">
      <div class="mb-3">
        <label class="form-label">Topic</label>
        <textarea name="short_comment" class="form-control" rows="2" required></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Comment</label>
        <textarea name="long_comment" class="form-control" rows="4" required></textarea>
      </div>
      <button type="submit" name="comment" class="btn btn-primary">
        Submit Comment
      </button>
    </form>
  </div>

  <!-- EDIT COMMENT MODAL -->
  <?php if ($editCommentMode): ?>
  <div class="modal fade" id="editCommentModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Comment #<?= $editCommentData['id'] ?></h5>
          <button type="button" class="btn-close"
                  data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form method="POST" action="issue.php?id=<?= $issue_id ?>">
            <input type="hidden" name="action" value="update_comment">
            <input type="hidden" name="comment_id"
                   value="<?= $editCommentData['id'] ?>">
            <div class="mb-3">
              <label class="form-label">Topic</label>
              <textarea name="short_comment" class="form-control" rows="2" required><?= htmlspecialchars($editCommentData['short_comment']) ?></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Comment</label>
              <textarea name="long_comment" class="form-control" rows="4" required><?= htmlspecialchars($editCommentData['long_comment']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <?php if ($editCommentMode): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      new bootstrap.Modal(
        document.getElementById('editCommentModal')
      ).show();
    });
  </script>
  <?php endif; ?>

</body>
</html>
