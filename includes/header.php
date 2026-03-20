<?php
require_once __DIR__ . '/auth.php';
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' | PHPDevHub' : 'PHPDevHub';
$usePageShell = $usePageShell ?? true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('css/style.css')); ?>">
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php if ($usePageShell): ?>
<main class="main-shell">
    <div class="container">
<?php endif; ?>
