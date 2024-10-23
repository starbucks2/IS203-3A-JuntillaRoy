<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header('Location: admin_dashboard.php'); // Redirect if user not found
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>View Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
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
        <h2 class="text-center"> Profile</h2>
        <a href="admin_dashboard.php" class="btn btn-secondary mb-3">Back to Dashboard</a>
        <div class="row">
            <div class="col-md-8">
                <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong>Role:</strong> <?= htmlspecialchars($user['role']) ?></p>
                <p><strong>Profile Picture:</strong></p>
                <img src="<?= $user['profile_pic'] ?>" alt="Profile Picture" class="img-thumbnail" style="max-width: 200px;">
            </div>
            <button class="btn btn-sm btn-flat btn-success" id ="printButton"onclick="window.print()">Print</button>
        </div>
    </div>
</body>
</html>
