<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$users = $conn->query("SELECT * FROM users");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $id = $_POST['id'];
    $conn->query("DELETE FROM users WHERE id=$id");
    header('Location: admin_dashboard.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    @media print{
  #printButton{
    display: none;
   
  }
}
  </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Admin Dashboard</h2>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Profile Picture</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $users->fetch_assoc()) { ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= $row['username'] ?></td>
                            <td><?= $row['email'] ?></td>
                            <td><?= $row['role'] ?></td>
                            <td><img src="<?= $row['profile_pic'] ?>" width="50" class="img-fluid"></td>
                            <td>
                                
                                <a href="edit_profile.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="view_profile.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">View</a> <!-- View Profile Button -->
                                <button class="btn btn-sm btn-flat btn-success" id ="printButton"onclick="window.print()">Print</button>
                                <form method="POST" action="">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="delete" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <a href="add_user.php" class="btn btn-success">Add New User</a>
        <br><br><a href="logout.php" class="btn btn-secondary mb-3">Logout</a>

    </div>
</body>
</html>
