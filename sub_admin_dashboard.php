<?php
session_start();
include 'db.php';

// Check if sub-admin is logged in
if (!isset($_SESSION['subadmin_id'])) {
    $_SESSION['error'] = "You must be logged in as a sub-admin.";
    header("Location: login.php");
    exit();
}

// Get sub-admin permissions
$permissions            = json_decode($_SESSION['permissions'] ?? '[]', true);
$can_approve_research   = in_array('approve_research', $permissions);
$can_verify_students    = in_array('verify_students', $permissions);
$can_manage_repository  = in_array('manage_repository', $permissions);

// Redirect if no permissions
if (!$can_approve_research && !$can_verify_students && !$can_manage_repository) {
    $_SESSION['error'] = "You don't have any permissions. Contact the administrator.";
    header("Location: login.php");
    exit();
}

// Initialise counters / collections
$pending_research = $verified_students = $unverified_students = 0;
$recent_research  = $student_stats     = [];
$research_stats   = ['approved' => 0, 'pending' => 0];

/* -------------------------------------------------------------------------- */
/*                               DB  QUERIES                                  */
/* -------------------------------------------------------------------------- */
try {
    // Research approval stats (aggregate by status)
    $qry   = "SELECT status, COUNT(*) AS total FROM research_submission GROUP BY status";
    $stmt  = $conn->prepare($qry);
    $stmt->execute();
    $rows  = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $row) {
        if ((int) $row['status'] === 1) {
            $research_stats['approved'] = (int) $row['total'];
        } else {
            $research_stats['pending'] = (int) $row['total'];
        }
    }

    /* Recent submissions (latest 5) – we also exclude rows that do NOT have an
       associated file in the uploads directory to avoid showing non-uploaded
       placeholders.                                                               */
    $recent_qry = "SELECT rs.title,
                         COALESCE(CONCAT(s.firstname,' ',s.lastname), 'Admin Upload') AS author,
                         rs.submission_date,
                         rs.status,
                         rs.file_path
                  FROM   research_submission rs
                  LEFT JOIN students s ON rs.student_id = s.student_id
                  WHERE  rs.file_path IS NOT NULL AND rs.file_path <> ''
                  ORDER BY rs.submission_date DESC
                  LIMIT  5";

    $stmt = $conn->prepare($recent_qry);
    $stmt->execute();
    $recent_research = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Student verification stats
    if ($can_verify_students) {
        $stmt = $conn->prepare("SELECT verified, COUNT(*) AS total FROM students GROUP BY verified");
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $r) {
            if ((int) $r['verified'] === 1) {
                $verified_students = (int) $r['total'];
            } else {
                $unverified_students = (int) $r['total'];
            }
        }

        // Grade level breakdown
        $stmt = $conn->prepare("SELECT grade_level, COUNT(*) AS total FROM students GROUP BY grade_level ORDER BY grade_level");
        $stmt->execute();
        $student_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $error_message = "Error fetching analytics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sub-Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-wajUUArSz9H9VbFMi75lMtSzHhbuKR+agkZBiuay9mVkYVfQ3K4eJXE+N0OvGQ3QIZm7x7/5ozjxk2ZoxjYr1w==" crossorigin="anonymous" referrerpolicy="no-referrer"/>

    <style>
        /* custom animations omitted for brevity */
    </style>
</head>
<body class="gradient-bg min-h-screen transition-all duration-300">
    <div class="min-h-screen flex">
        <!-- Sidebar (trimmed) -->
        <!-- ... existing code ... -->
    </div>

    <!-- Replace the invalid icon class `fas fa-activity` with a valid FontAwesome icon -->
    <!-- Example usage inside a statistics card -->
    <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
        <i class="fas fa-bolt text-white text-2xl"></i>
    </div>

    <!-- Remainder of dashboard markup (omitted for brevity) -->

    <script>
        // JavaScript logic here (omitted)
    </script>
</body>
</html>