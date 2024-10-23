<?php
session_start();
include 'db.php';

$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];

    // Handle profile picture upload
    $profile_pic = $user['profile_pic'];
    if (!empty($_FILES['profile_pic']['name'])) {
        $target_dir = "uploads/";
        $profile_pic = $target_dir . basename($_FILES['profile_pic']['name']);
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $profile_pic);
    }

    $id = $user['id'];
    $conn->query("UPDATE users SET username='$username', email='$email', profile_pic='$profile_pic' WHERE id=$id");

    $_SESSION['user']['username'] = $username;
    $_SESSION['user']['email'] = $email;
    $_SESSION['user']['profile_pic'] = $profile_pic;

    header('Location: user_dashboard.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Edit Profile</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" value="<?= $user['username'] ?>" required>
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= $user['email'] ?>" required>
            </div>
            <div class="mb-3">
                <label>Profile Picture</label>
                <input type="file" name="profile_pic" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</body>
</html>
