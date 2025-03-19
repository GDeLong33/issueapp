<?php
session_start();
require 'database/database.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Connect to the database
$conn = Database::connect();

// Fetch list of people for dropdown selection
$people_query = "SELECT id, fname, lname FROM iss_persons ORDER BY lname ASC";
$people_result = $conn->query($people_query);

// Handle new issue submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $short_description = $_POST['short_description'];
    $long_description = $_POST['long_description'];
    $open_date = $_POST['open_date'];
    $close_date = !empty($_POST['close_date']) ? $_POST['close_date'] : NULL;
    $priority = $_POST['priority'];
    $organization = $_POST['organization'];
    $project = $_POST['project'];
    $per_id = $_POST['per_id']; // Selected person

    try {
        // Insert the new issue
        $stmt = $conn->prepare("INSERT INTO iss_issues (short_description, long_description, open_date, close_date, priority, org, project, per_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$short_description, $long_description, $open_date, $close_date, $priority, $organization, $project, $per_id]);

        // Redirect back to the issues list
        header("Location: issues_list.php");
        exit();
    } catch (PDOException $e) {
        echo "Error inserting issue: " . $e->getMessage();
    }
}

// Fetch issues with responsible person's name
$sql = "SELECT iss_issues.*, iss_persons.fname, iss_persons.lname 
        FROM iss_issues 
        LEFT JOIN iss_persons ON iss_issues.per_id = iss_persons.id 
        ORDER BY iss_issues.project, iss_issues.priority DESC, iss_issues.open_date ASC";
$result = $conn->query($sql);

Database::disconnect();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issues List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar bg-body-tertiary fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Department Issues</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="offcanvas offcanvas-end" id="offcanvasNavbar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="issues_list.php">Issues</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">People</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-5 pt-4">
    <div class="d-flex justify-content-end mt-3">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addIssueModal">+ Add Issue</button>
    </div>
    
    <table class="table table-striped mt-3">
        <thead>
            <tr>
                <th>ID</th>
                <th>Issue</th>
                <th>Short Description</th>
                <th>Priority</th>
                <th>Open Date</th>
                <th>Close Date</th>
                <th>Person Responsible</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->rowCount() > 0): ?>
                <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['project']); ?></td>
                        <td><?php echo htmlspecialchars($row['short_description']); ?></td>
                        <td><?php echo htmlspecialchars($row['priority']); ?></td>
                        <td><?php echo htmlspecialchars($row['open_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['close_date'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                                if (!empty($row['fname']) && !empty($row['lname'])) {
                                    echo htmlspecialchars($row['fname'] . " " . $row['lname']);
                                } else {
                                    echo "Unknown";
                                }
                            ?>
                        </td>
                        <td>
                            <!-- View Button -->
                            <a href="issue.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                            
                            <!-- Update Button -->
                            <a href="issues_list.php?edit_id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addIssueModal">Update</a>
                            
                            <!-- Delete Button -->
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $row['id']; ?>)">Delete</button>
                            <form id="deleteForm<?php echo $row['id']; ?>" method="POST" action="issues_list.php" class="d-inline" style="display: none;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8">No issues found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Issue Modal -->
<div class="modal fade" id="addIssueModal" tabindex="-1" aria-labelledby="addIssueModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIssueModalLabel">Add New Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="per_id" class="form-label">Person Responsible</label>
                        <select class="form-select" name="per_id" required>
                            <option value="">Select a Person</option>
                            <?php while ($row = $people_result->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $row['id']; ?>">
                                    <?php echo htmlspecialchars($row['fname'] . " " . $row['lname']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="short_description" class="form-label">Short Description</label>
                        <input type="text" class="form-control" name="short_description" required>
                    </div>
                    <div class="mb-3">
                        <label for="long_description" class="form-label">Long Description</label>
                        <textarea class="form-control" name="long_description" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="open_date" class="form-label">Open Date</label>
                        <input type="date" class="form-control" name="open_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="close_date" class="form-label">Close Date (Optional)</label>
                        <input type="date" class="form-control" name="close_date">
                    </div>
                    <div class="mb-3">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" name="priority" required>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="organization" class="form-label">Organization</label>
                        <input type="text" class="form-control" name="organization" required>
                    </div>
                    <div class="mb-3">
                        <label for="project" class="form-label">Issue</label>
                        <input type="text" class="form-control" name="project" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Issue</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
