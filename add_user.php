<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $profile_pic = 'default.jpg'; // Default profile picture

    // Handle file upload
    if (!empty($_FILES['profile_pic']['name'])) {
        $target_dir = "uploads/";
        $profile_pic = $target_dir . basename($_FILES['profile_pic']['name']);
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $profile_pic);
    }

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, profile_pic) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssss', $username, $email, $password, $role, $profile_pic);
    $stmt->execute();

    header('Location: admin_dashboard.php'); // Redirect back to the admin dashboard
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Add User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Add New Admin/User</h2>
        <a href="admin_dashboard.php" class="btn btn-secondary mb-3">Back to Dashboard</a>
        <form method="POST" action="" enctype="multipart/form-data" class="row g-3">
            <div class="col-md-6">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="col-md-6">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="col-md-6">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="profile_pic" class="form-label">Profile Picture</label>
                <input type="file" class="form-control" id="profile_pic" name="profile_pic">
            </div>
            <div class="col-12">
                <button class="btn btn-primary w-100" type="submit">Add User</button>
            </div>
        </form>
    </div>
</body>
</html>
