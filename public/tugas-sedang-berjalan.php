<?php
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$pageTitle = 'Tugas Sedang Berjalan';
$emptyStateText = 'Data akan ditampilkan di sini setelah tersedia.';

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
    <title><?php echo htmlspecialchars($pageTitle); ?> - Resurvey Alda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div class="page-background">
        <nav class="navbar">
            <button class="back-button" onclick="window.location.href='dashboard.php'">
                <svg class="icon" viewBox="0 0 24 24" style="color: var(--text-on-dark);">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>

            <h1 class="navbar-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
        </nav>

        <div class="template-content">
            <div class="empty-state">
                <?php echo svgIcon('empty-folder-icon', 'icon-2xl empty-icon'); ?>
                <h3 class="empty-title">Belum ada data</h3>
                <p class="empty-description"><?php echo htmlspecialchars($emptyStateText); ?></p>
            </div>
        </div>

        <button class="fab" id="refresh-page">
            <?php echo svgIcon('refresh-icon', 'icon-lg fab-icon'); ?>
        </button>
    </div>
</body>

</html>