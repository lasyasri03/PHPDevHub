<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_ui.php';

if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ' . appUrl('login.php'));
    exit;
}

$payments = $conn->query(
    "SELECT pay.id,
            p.title AS project_title,
            c.name AS client_name,
            d.name AS developer_name,
            pay.amount,
            pay.payment_status,
            pay.transaction_id,
            pay.created_at,
            pay.paid_at
     FROM payments pay
     INNER JOIN projects p ON p.id = pay.project_id
     INNER JOIN users c ON c.id = pay.client_id
     INNER JOIN users d ON d.id = pay.developer_id
     ORDER BY COALESCE(pay.paid_at, pay.created_at) DESC, pay.id DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(appUrl('css/style.css')); ?>">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f7fb; color: #1f2937; }
        a { text-decoration: none; }
        .admin-layout { min-height: 100vh; display: flex; }
        .sidebar { width: 260px; background: #111827; color: #fff; padding: 24px 18px; }
        .brand { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .brand-subtitle { color: #9ca3af; font-size: 14px; margin-bottom: 26px; }
        .nav-link { display: block; color: #d1d5db; padding: 12px 14px; border-radius: 10px; margin-bottom: 8px; }
        .nav-link:hover, .nav-link.active { background: #2563eb; color: #fff; }
        .sidebar-section { margin-bottom: 20px; }
        .sidebar-heading { color: #6b7280; font-size: 11px; font-weight: 700; letter-spacing: .12em; margin: 0 0 10px 12px; }
        .content { flex: 1; padding: 28px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .page-title { margin: 0; font-size: 30px; }
        .topbar-text { margin: 6px 0 0; color: #6b7280; }
        .logout-btn { background: #dc2626; color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 600; }
        .panel { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, .08); }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; }
        tbody tr:hover { background: #f8fbff; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: capitalize; background: #e5e7eb; color: #374151; }
        .paid { background: #dcfce7; color: #166534; }
        .pending { background: #fef3c7; color: #92400e; }
        .cancelled { background: #fee2e2; color: #991b1b; }
        .empty-state { color: #6b7280; margin: 0; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php renderAdminSidebar('payments'); ?>
        <main class="content">
            <div class="topbar">
                <div>
                    <h1 class="page-title">Payments</h1>
                    <p class="topbar-text">Monitor project payments processed through the platform.</p>
                </div>
                <a class="logout-btn" href="<?php echo htmlspecialchars(appUrl('admin/logout.php')); ?>">Logout</a>
            </div>
            <section class="panel">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Developer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Transaction ID</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($payments->num_rows > 0): ?>
                                <?php while ($payment = $payments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo (int) $payment['id']; ?></td>
                                        <td><?php echo htmlspecialchars($payment['project_title']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['developer_name']); ?></td>
                                        <td>$<?php echo number_format((float) $payment['amount'], 2); ?></td>
                                        <td><span class="badge <?php echo htmlspecialchars(strtolower($payment['payment_status'])); ?>"><?php echo htmlspecialchars($payment['payment_status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($payment['transaction_id'] ?: 'Pending'); ?></td>
                                        <td><?php echo htmlspecialchars($payment['paid_at'] ?: $payment['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8"><p class="empty-state">No payments recorded yet.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
