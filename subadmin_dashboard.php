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
$permissions = json_decode($_SESSION['permissions'] ?? '[]', true);
$can_approve_research = in_array('approve_research', $permissions);
$can_verify_students = in_array('verify_students', $permissions);
$can_manage_repository = in_array('manage_repository', $permissions);

// Redirect if no permissions
if (!$can_approve_research && !$can_verify_students && !$can_manage_repository) {
    $_SESSION['error'] = "You don't have any permissions. Contact the administrator.";
    header("Location: login.php");
    exit();
}

// Initialize variables
$pending_research = $verified_students = $unverified_students = 0;
$recent_research = $student_stats = [];
$research_stats = ['approved' => 0, 'pending' => 0];
$total_papers = 0;

try {
    // Research Stats
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM research_submission GROUP BY status");
    $stmt->execute();
    $raw_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $research_stats = [
        'approved' => $raw_stats['1'] ?? 0,
        'pending' => $raw_stats['0'] ?? 0
    ];
    
    $total_papers = array_sum($research_stats);

    // Get recent research submissions (only show properly uploaded ones)
    $stmt = $conn->prepare("
        SELECT rs.title, 
               COALESCE(CONCAT(s.firstname, ' ', s.lastname), 'Unknown Author') AS author, 
               rs.submission_date, 
               rs.status,
               rs.file_path
        FROM research_submission rs 
        LEFT JOIN students s ON rs.student_id = s.student_id 
        WHERE rs.file_path IS NOT NULL AND rs.file_path != ''
        ORDER BY rs.submission_date DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_research = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Student verification stats
    if ($can_verify_students) {
        $stmt = $conn->prepare("SELECT verified, COUNT(*) as count FROM students GROUP BY verified");
        $stmt->execute();
        $verification_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($verification_stats as $stat) {
            if ($stat['verified'] == 1) {
                $verified_students = $stat['count'];
            } else {
                $unverified_students = $stat['count'];
            }
        }
    }

    // Get student stats by grade level
    if ($can_verify_students) {
        $stmt = $conn->prepare("SELECT grade_level, COUNT(*) as count FROM students WHERE grade_level IS NOT NULL GROUP BY grade_level ORDER BY grade_level");
        $stmt->execute();
        $student_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "Error fetching analytics: " . $e->getMessage();
}

// Get sub-admin name
$subadmin_name = $_SESSION['subadmin_name'] ?? 'Sub-Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sub-Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-slow': 'pulse 3s infinite',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .glass-effect {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dark .glass-effect {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .dark .gradient-bg {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .sidebar {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .dark .sidebar {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        }

        .menu-item {
            position: relative;
            overflow: hidden;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.1), transparent);
            transition: left 0.5s;
        }

        .menu-item:hover::before {
            left: 100%;
        }

        .stat-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(147, 51, 234, 0.1) 100%);
        }

        .dark .stat-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(147, 51, 234, 0.2) 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen transition-all duration-300">
    <?php if (isset($error_message)): ?>
        <div class="fixed top-4 right-4 z-50 bg-red-500 text-white p-4 rounded-xl shadow-2xl max-w-sm">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="min-h-screen flex">
        <!-- Enhanced Sidebar -->
        <aside class="w-72 sidebar shadow-2xl min-h-screen p-8 transition-all duration-300 relative">
            <!-- Logo/Brand -->
            <div class="mb-10">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-user-tie text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Sub-Admin</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Control Panel</p>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="space-y-3">
                <!-- Dashboard -->
                <a href="subadmin_dashboard.php" class="menu-item flex items-center p-4 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-800 transition-all duration-200 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-blue-300 bg-blue-50 dark:bg-gray-700 text-blue-800 dark:text-blue-300">
                    <i class="fas fa-tachometer-alt mr-4 text-lg"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                
                <!-- Research Approvals -->
                <?php if ($can_approve_research): ?>
                <a href="research_approvals.php" class="menu-item flex items-center p-4 rounded-xl text-gray-700 hover:bg-green-50 hover:text-green-800 transition-all duration-200 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-green-300 cursor-pointer">
                    <i class="fas fa-file-alt mr-4 text-lg"></i>
                    <span class="font-medium">Research Approvals</span>
                    <?php if ($research_stats['pending'] > 0): ?>
                    <div class="ml-auto">
                        <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $research_stats['pending']; ?></span>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                
                <!-- Manage Students -->
                <?php if ($can_verify_students): ?>
                <a href="manage_students.php" class="menu-item flex items-center p-4 rounded-xl text-gray-700 hover:bg-blue-50 hover:text-blue-800 transition-all duration-200 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-blue-300 cursor-pointer">
                    <i class="fas fa-users mr-4 text-lg"></i>
                    <span class="font-medium">Manage Students</span>
                    <?php if ($unverified_students > 0): ?>
                    <div class="ml-auto">
                        <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $unverified_students; ?></span>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            
                <!-- Repository Management -->
                <?php if ($can_manage_repository): ?>
                <a href="manage_repository.php" class="menu-item flex items-center p-4 rounded-xl text-gray-700 hover:bg-purple-50 hover:text-purple-800 transition-all duration-200 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-purple-300 cursor-pointer">
                    <i class="fas fa-archive mr-4 text-lg"></i>
                    <span class="font-medium">Repository</span>
                </a>
                <?php endif; ?>
            
                <!-- Settings -->
                <a href="settings.php" class="menu-item flex items-center p-4 rounded-xl text-gray-700 hover:bg-gray-50 hover:text-gray-800 transition-all duration-200 dark:text-gray-200 dark:hover:bg-gray-700 cursor-pointer">
                    <i class="fas fa-cog mr-4 text-lg"></i>
                    <span class="font-medium">Settings</span>
                </a>
                
                <div class="border-t border-gray-200 dark:border-gray-600 my-6"></div>
            
                <!-- Logout -->
                <a href="logout.php" class="menu-item flex items-center p-4 rounded-xl text-red-600 hover:bg-red-50 hover:text-red-800 transition-all duration-200 dark:text-red-400 dark:hover:bg-gray-700 cursor-pointer">
                    <i class="fas fa-sign-out-alt mr-4 text-lg"></i>
                    <span class="font-medium">Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 overflow-y-auto">
            <!-- Enhanced Header -->
            <header class="glass-effect rounded-3xl shadow-2xl p-8 mb-8 animate-fade-in">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-user-tie text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-4xl font-bold text-white mb-2">
                                Welcome Back, <?php echo htmlspecialchars($subadmin_name); ?>
                            </h1>
                            <p class="text-blue-100 text-lg">
                                Sub-Administrator Dashboard
                            </p>
                        </div>
                    </div>
                    
                    <!-- Dark Mode Toggle -->
                    <div class="mt-6 lg:mt-0 flex items-center space-x-4">
                        <div class="glass-effect rounded-xl p-1 flex">
                            <button onclick="setLightMode()" class="px-4 py-2 rounded-lg transition-all duration-200 light-btn">
                                <i class="fas fa-sun mr-2"></i>Light
                            </button>
                            <button onclick="setDarkMode()" class="px-4 py-2 rounded-lg transition-all duration-200 dark-btn">
                                <i class="fas fa-moon mr-2"></i>Dark
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Permissions Overview -->
            <div class="glass-effect rounded-3xl shadow-xl p-8 mb-8 animate-slide-up">
                <h3 class="text-2xl font-bold text-white mb-6 flex items-center">
                    <i class="fas fa-shield-alt mr-3 text-blue-300"></i> Your Permissions
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php if ($can_approve_research): ?>
                    <div class="flex items-center p-4 rounded-xl bg-green-500/20 border border-green-400/30">
                        <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-check text-white"></i>
                        </div>
                        <div>
                            <p class="text-white font-medium">Approve Research</p>
                            <p class="text-green-200 text-sm">Full Access</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($can_verify_students): ?>
                    <div class="flex items-center p-4 rounded-xl bg-blue-500/20 border border-blue-400/30">
                        <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-check text-white"></i>
                        </div>
                        <div>
                            <p class="text-white font-medium">Verify Students</p>
                            <p class="text-blue-200 text-sm">Full Access</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($can_manage_repository): ?>
                    <div class="flex items-center p-4 rounded-xl bg-purple-500/20 border border-purple-400/30">
                        <div class="w-10 h-10 bg-purple-500 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-check text-white"></i>
                        </div>
                        <div>
                            <p class="text-white font-medium">Manage Repository</p>
                            <p class="text-purple-200 text-sm">Full Access</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Research Approvals Card -->
                <?php if ($can_approve_research): ?>
                <div class="group cursor-pointer" onclick="window.location.href='research_approvals.php'">
                    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl p-8 card-hover border border-gray-200 dark:border-gray-700 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-green-400 to-green-600 rounded-full -translate-y-16 translate-x-16 opacity-10"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-6">
                                <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-file-alt text-white text-2xl"></i>
                                </div>
                                <?php if ($research_stats['pending'] > 0): ?>
                                <div class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-3 py-1 rounded-full text-sm font-medium">
                                    <?php echo $research_stats['pending']; ?> Pending
                                </div>
                                <?php else: ?>
                                <div class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-3 py-1 rounded-full text-sm font-medium">
                                    No Pending
                                </div>
                                <?php endif; ?>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Research Approvals</h4>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">Review and approve pending research submissions</p>
                            <div class="flex items-center text-green-600 dark:text-green-400 font-medium">
                                <span>Review Now</span>
                                <i class="fas fa-arrow-right ml-2 group-hover:translate-x-2 transition-transform duration-200"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Manage Students Card -->
                <?php if ($can_verify_students): ?>
                <div class="group cursor-pointer" onclick="window.location.href='manage_students.php'">
                    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl p-8 card-hover border border-gray-200 dark:border-gray-700 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full -translate-y-16 translate-x-16 opacity-10"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-6">
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-users text-white text-2xl"></i>
                                </div>
                                <?php if ($unverified_students > 0): ?>
                                <div class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full text-sm font-medium">
                                    <?php echo $unverified_students; ?> Unverified
                                </div>
                                <?php else: ?>
                                <div class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-3 py-1 rounded-full text-sm font-medium">
                                    All Verified
                                </div>
                                <?php endif; ?>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Manage Students</h4>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">Verify student accounts and manage registrations</p>
                            <div class="flex items-center text-blue-600 dark:text-blue-400 font-medium">
                                <span>Manage Now</span>
                                <i class="fas fa-arrow-right ml-2 group-hover:translate-x-2 transition-transform duration-200"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Enhanced Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Papers -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 card-hover border border-gray-200 dark:border-gray-700 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 to-purple-600/5 dark:from-purple-400/10 dark:to-purple-500/10"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="stat-icon p-3 rounded-2xl">
                                <i class="fas fa-file-alt text-purple-600 dark:text-purple-400 text-2xl"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $total_papers; ?></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Total Papers</p>
                            </div>
                        </div>
                        <div class="flex items-center text-sm text-purple-600 dark:text-purple-400">
                            <i class="fas fa-file mr-1"></i>
                            <span>Research submissions</span>
                        </div>
                    </div>
                </div>

                <!-- Approved Research -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 card-hover border border-gray-200 dark:border-gray-700 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 to-green-600/5 dark:from-green-400/10 dark:to-green-500/10"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="stat-icon p-3 rounded-2xl">
                                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-2xl"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $research_stats['approved']; ?></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Approved</p>
                            </div>
                        </div>
                        <div class="flex items-center text-sm text-green-600 dark:text-green-400">
                            <i class="fas fa-check mr-1"></i>
                            <span>Successfully reviewed</span>
                        </div>
                    </div>
                </div>

                <!-- Pending Research -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 card-hover border border-gray-200 dark:border-gray-700 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-orange-500/5 to-orange-600/5 dark:from-orange-400/10 dark:to-orange-500/10"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="stat-icon p-3 rounded-2xl">
                                <i class="fas fa-clock text-orange-600 dark:text-orange-400 text-2xl"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $research_stats['pending']; ?></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Pending</p>
                            </div>
                        </div>
                        <div class="flex items-center text-sm text-orange-600 dark:text-orange-400">
                            <?php if ($research_stats['pending'] > 0): ?>
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span>Needs attention</span>
                            <?php else: ?>
                            <i class="fas fa-check mr-1"></i>
                            <span>All caught up</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Verified Students -->
                <?php if ($can_verify_students): ?>
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 card-hover border border-gray-200 dark:border-gray-700 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-blue-600/5 dark:from-blue-400/10 dark:to-blue-500/10"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="stat-icon p-3 rounded-2xl">
                                <i class="fas fa-user-check text-blue-600 dark:text-blue-400 text-2xl"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $verified_students; ?></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Verified</p>
                            </div>
                        </div>
                        <div class="flex items-center text-sm text-blue-600 dark:text-blue-400">
                            <i class="fas fa-users mr-1"></i>
                            <span>Active students</span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 card-hover border border-gray-200 dark:border-gray-700 relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-br from-gray-500/5 to-gray-600/5 dark:from-gray-400/10 dark:to-gray-500/10"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="stat-icon p-3 rounded-2xl">
                                <i class="fas fa-lock text-gray-600 dark:text-gray-400 text-2xl"></i>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-bold text-gray-900 dark:text-white">--</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">No Access</p>
                            </div>
                        </div>
                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                            <i class="fas fa-ban mr-1"></i>
                            <span>Permission required</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Charts and Recent Activity -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                <!-- Research Status Chart -->
                <div class="xl:col-span-1 bg-white dark:bg-gray-800 rounded-3xl shadow-xl p-8 border border-gray-200 dark:border-gray-700 card-hover">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                        <i class="fas fa-chart-pie mr-3 text-blue-600"></i>
                        Research Status
                    </h2>
                    <?php if ($total_papers > 0): ?>
                    <div class="flex justify-center">
                        <canvas id="researchStatusChart" style="max-width: 280px; max-height: 280px;"></canvas>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-chart-pie text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500 dark:text-gray-400">No research data available</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Research Submissions -->
                <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-3xl shadow-xl p-8 border border-gray-200 dark:border-gray-700 card-hover">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                            <i class="fas fa-clock mr-3 text-blue-600"></i>
                            Recent Submissions
                        </h2>
                        <?php if ($can_approve_research): ?>
                        <a href="research_approvals.php" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                            View All
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if (!empty($recent_research)): ?>
                            <?php foreach ($recent_research as $research): ?>
                            <div class="flex items-start p-4 rounded-xl border border-gray-100 dark:border-gray-700 hover:border-blue-200 dark:hover:border-blue-600 transition-all duration-200 group cursor-pointer">
                                <div class="w-12 h-12 bg-gradient-to-br from-<?php echo $research['status'] == '1' ? 'green' : 'orange'; ?>-500 to-<?php echo $research['status'] == '1' ? 'green' : 'orange'; ?>-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                                    <i class="fas fa-<?php echo $research['status'] == '1' ? 'check' : 'clock'; ?> text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                        <?php echo htmlspecialchars($research['title']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">by <?php echo htmlspecialchars($research['author']); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500">Submitted <?php echo date('M j, Y', strtotime($research['submission_date'])); ?></p>
                                </div>
                                <span class="bg-<?php echo $research['status'] == '1' ? 'green' : 'orange'; ?>-100 dark:bg-<?php echo $research['status'] == '1' ? 'green' : 'orange'; ?>-900 text-<?php echo $research['status'] == '1' ? 'green' : 'orange'; ?>-800 dark:text-<?php echo $research['status'] == '1' ? 'green' : 'orange'; ?>-200 px-3 py-1 rounded-full text-xs font-medium">
                                    <?php echo $research['status'] == '1' ? 'Approved' : 'Pending'; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-file-alt text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500 dark:text-gray-400">No recent research submissions</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Overview -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl p-8 border border-gray-200 dark:border-gray-700 card-hover mt-8">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                    <i class="fas fa-activity mr-3 text-blue-600"></i>
                    System Activity
                </h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Weekly Activity -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <i class="fas fa-chart-line text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2"><?php echo $research_stats['pending'] + $research_stats['approved']; ?></h3>
                        <p class="text-gray-600 dark:text-gray-400">Total Submissions</p>
                    </div>
                    
                    <!-- Approval Rate -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-teal-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <i class="fas fa-percentage text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                            <?php 
                            $approval_rate = $total_papers > 0 ? round(($research_stats['approved'] / $total_papers) * 100) : 0;
                            echo $approval_rate . '%';
                            ?>
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">Approval Rate</p>
                    </div>
                    
                    <!-- Student Count -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                            <i class="fas fa-users text-white text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2"><?php echo $verified_students + $unverified_students; ?></h3>
                        <p class="text-gray-600 dark:text-gray-400">Total Students</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Dark mode functionality
        let isDarkMode = false;

        function setLightMode() {
            isDarkMode = false;
            document.documentElement.classList.remove('dark');
            updateModeButtons();
            saveThemePreference();
        }

        function setDarkMode() {
            isDarkMode = true;
            document.documentElement.classList.add('dark');
            updateModeButtons();
            saveThemePreference();
        }

        function updateModeButtons() {
            const lightBtn = document.querySelector('.light-btn');
            const darkBtn = document.querySelector('.dark-btn');
            
            if (isDarkMode) {
                lightBtn.classList.remove('bg-white', 'text-gray-900', 'shadow-lg');
                lightBtn.classList.add('text-white/70', 'hover:text-white');
                darkBtn.classList.add('bg-white', 'text-gray-900', 'shadow-lg');
                darkBtn.classList.remove('text-white/70', 'hover:text-white');
            } else {
                darkBtn.classList.remove('bg-white', 'text-gray-900', 'shadow-lg');
                darkBtn.classList.add('text-white/70', 'hover:text-white');
                lightBtn.classList.add('bg-white', 'text-gray-900', 'shadow-lg');
                lightBtn.classList.remove('text-white/70', 'hover:text-white');
            }
        }

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial mode button styles
            updateModeButtons();
            
            // Research Status Chart (only if there's data)
            const chartCanvas = document.getElementById('researchStatusChart');
            if (chartCanvas && <?php echo $total_papers; ?> > 0) {
                const ctx = chartCanvas.getContext('2d');
                
                const gradient1 = ctx.createLinearGradient(0, 0, 0, 400);
                gradient1.addColorStop(0, 'rgba(16, 185, 129, 0.8)');
                gradient1.addColorStop(1, 'rgba(16, 185, 129, 0.4)');
                
                const gradient2 = ctx.createLinearGradient(0, 0, 0, 400);
                gradient2.addColorStop(0, 'rgba(251, 146, 60, 0.8)');
                gradient2.addColorStop(1, 'rgba(251, 146, 60, 0.4)');

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Approved', 'Pending'],
                        datasets: [{
                            data: [<?php echo $research_stats['approved']; ?>, <?php echo $research_stats['pending']; ?>],
                            backgroundColor: [gradient1, gradient2],
                            borderColor: ['rgba(16, 185, 129, 1)', 'rgba(251, 146, 60, 1)'],
                            borderWidth: 3,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: isDarkMode ? '#ffffff' : '#374151',
                                    font: {
                                        size: 14,
                                        weight: '500'
                                    },
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                cornerRadius: 12,
                                padding: 12,
                                displayColors: true,
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        animation: {
                            animateRotate: true,
                            duration: 1000
                        }
                    }
                });
            }

            // Add smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Add loading states for cards
            const cards = document.querySelectorAll('.card-hover');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('animate-fade-in');
            });

            // Add interactive hover effects
            const menuItems = document.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(8px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
        });

        // Add notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-xl shadow-2xl max-w-sm transform translate-x-full transition-transform duration-300 ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                type === 'warning' ? 'bg-yellow-500 text-white' :
                'bg-blue-500 text-white'
            }`;
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-3"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white/80 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(full)';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }, 5000);
        }

        // Theme persistence
        function saveThemePreference() {
            localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
        }

        // Load theme preference
        function loadThemePreference() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                setDarkMode();
            } else {
                setLightMode();
            }
        }

        // Initialize theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadThemePreference();
            
            // Success notification on load
            setTimeout(() => {
                showNotification('Dashboard loaded successfully!', 'success');
            }, 1000);
        });
    </script>
</body>
</html>