<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">User Dashboard</h2>

        <div class="row">
            <div class="col-md-4">
                <img src="<?= $user['profile_pic'] ?>" class="img-fluid rounded mb-3" alt="Profile Picture">
            </div>
            <div class="col-md-8">
                <p><strong>Username:</strong> <?= $user['username'] ?></p>
                <p><strong>Email:</strong> <?= $user['email'] ?></p>
                <a href="edit_profile.php" class="btn btn-primary w-100">Edit Profile</a>
            </div>
        </div>
        <a href="logout.php" class="btn btn-secondary mb-3 w-100">Logout</a>
    </div>
</body>
</html>
