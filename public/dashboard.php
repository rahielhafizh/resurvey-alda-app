<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$userName = $_SESSION['user_name'] ?? 'User';
$userInitials = $_SESSION['user_initials'] ?? 'U';

function svgIcon(string $name, string $class = 'icon'): string
{
    $path = __DIR__ . '/../assets/icons/' . $name . '.svg';

    if (!file_exists($path)) {
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24"></svg>';
    }

    $svg = file_get_contents($path);

    if (preg_match('/\bclass="/', $svg)) {
        return preg_replace('/\bclass="/', 'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' ', $svg, 1);
    }

    return preg_replace('/<svg\b/', '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"', $svg, 1);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Resurvey Alda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div class="page-background">
        <nav class="navbar">
            <div class="navbar-left">
            </div>

            <h1 class="navbar-title">Resurvey Alda</h1>

            <div class="navbar-right">
                <button class="nav-icon-btn"
                    onclick="if(confirm('Yakin ingin keluar?')) window.location.href='logout.php'">
                    <?php echo svgIcon('logout-icon', 'icon task-icon'); ?>
                </button>
            </div>
        </nav>

        <div class="dashboard-content">
            <div class="greeting-section">
                <p class="greeting-text">Selamat Datang,</p>
                <h2 class="greeting-name"><?php echo htmlspecialchars($userName); ?></h2>
            </div>

            <div class="task-buttons">
                <a href="tugas-baru.php" class="task-button">
                    <?php echo svgIcon('tugas-baru-icon', 'icon task-icon'); ?>
                    <span class="task-label">Tugas Baru</span>
                    <span class="task-badge">0</span>
                    <svg class="icon task-chevron" viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>

                <a href="tugas-proses.php" class="task-button">
                    <?php echo svgIcon('tugas-proses-icon', 'icon task-icon'); ?>
                    <span class="task-label">Tugas Proses</span>
                    <span class="task-badge">0</span>
                    <svg class="icon task-chevron" viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>

                <a href="tugas-sedang-berjalan.php" class="task-button">
                    <?php echo svgIcon('tugas-berjalan-icon', 'icon task-icon'); ?>
                    <span class="task-label">Tugas Sedang Berjalan</span>
                    <span class="task-badge">0</span>
                    <svg class="icon task-chevron" viewBox="0 0 24 24">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>

            <div class="secondary-buttons">
                <a href="upload.php" class="secondary-button">
                    <?php echo svgIcon('upload-icon', 'icon task-icon'); ?>
                    <span class="secondary-label">Upload</span>
                </a>

                <a href="selesai.php" class="secondary-button">
                    <?php echo svgIcon('selesai-icon', 'icon task-icon'); ?>
                    <span class="secondary-label">Selesai</span>
                </a>
            </div>
        </div>
    </div>
</body>

</html>