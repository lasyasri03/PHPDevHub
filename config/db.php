<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USERNAME');
$dbPass = getenv('DB_PASSWORD');
$dbName = getenv('DB_NAME');
$dbPort = (int) getenv('DB_PORT');
try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    $conn->set_charset('utf8mb4');

    $conn->query(
        "CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            budget DECIMAL(10,2) NULL,
            deadline DATE NULL,
            developers_needed INT NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            developer_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $projectColumns = [];
    $projectColumnResult = $conn->query("SHOW COLUMNS FROM projects");
    while ($column = $projectColumnResult->fetch_assoc()) {
        $projectColumns[] = $column['Field'];
    }

    if (!in_array('deadline', $projectColumns, true)) {
        $conn->query("ALTER TABLE projects ADD COLUMN deadline DATE NULL AFTER budget");
    }

    if (!in_array('developers_needed', $projectColumns, true)) {
        $conn->query("ALTER TABLE projects ADD COLUMN developers_needed INT NOT NULL DEFAULT 1 AFTER deadline");
    }

    $statusColumnResult = $conn->query("SHOW COLUMNS FROM projects LIKE 'status'");
    $statusColumn = $statusColumnResult->fetch_assoc();
    if ($statusColumn && stripos($statusColumn['Type'], 'varchar') === false) {
        $conn->query("ALTER TABLE projects MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }

    $conn->query("UPDATE projects SET status = 'approved' WHERE status = 'open'");

    $developerColumns = [];
    $developerColumnResult = $conn->query("SHOW COLUMNS FROM developers");
    while ($column = $developerColumnResult->fetch_assoc()) {
        $developerColumns[] = $column['Field'];
    }

    if (!in_array('github', $developerColumns, true)) {
        $conn->query("ALTER TABLE developers ADD COLUMN github VARCHAR(255) NULL AFTER portfolio");
    }

    if (!in_array('hourly_rate', $developerColumns, true)) {
        $conn->query("ALTER TABLE developers ADD COLUMN hourly_rate DECIMAL(10,2) NULL AFTER php_proficiency");
    }

    if (!in_array('is_verified', $developerColumns, true)) {
        $conn->query("ALTER TABLE developers ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER availability");
    }

    $userColumns = [];
    $userColumnResult = $conn->query("SHOW COLUMNS FROM users");
    while ($column = $userColumnResult->fetch_assoc()) {
        $userColumns[] = $column['Field'];
    }

    if (!in_array('account_status', $userColumns, true)) {
        $conn->query("ALTER TABLE users ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER role");
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS platform_earnings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            client_id INT NOT NULL,
            developer_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            stripe_session_id VARCHAR(255) NULL,
            payment_status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            transaction_id VARCHAR(255) NULL,
            razorpay_order_id VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            paid_at TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS disputes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            client_id INT NOT NULL,
            developer_id INT NOT NULL,
            reason VARCHAR(255) NULL,
            description TEXT NULL,
            complaint TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            admin_note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS dispute_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dispute_id INT NOT NULL,
            user_id INT NOT NULL,
            response TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (dispute_id) REFERENCES disputes(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS user_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role VARCHAR(20) NOT NULL,
            action VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            target_user VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS developer_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            developer_id INT NOT NULL,
            client_id INT NOT NULL,
            project_id INT NOT NULL,
            rating DECIMAL(3,2) NOT NULL DEFAULT 0,
            review TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $disputeColumns = [];
    $disputeColumnResult = $conn->query("SHOW COLUMNS FROM disputes");
    while ($column = $disputeColumnResult->fetch_assoc()) {
        $disputeColumns[] = $column['Field'];
    }

    if (!in_array('reason', $disputeColumns, true)) {
        $conn->query("ALTER TABLE disputes ADD COLUMN reason VARCHAR(255) NULL AFTER developer_id");
    }

    if (!in_array('description', $disputeColumns, true)) {
        $conn->query("ALTER TABLE disputes ADD COLUMN description TEXT NULL AFTER reason");
    }

    if (!in_array('admin_note', $disputeColumns, true)) {
        $conn->query("ALTER TABLE disputes ADD COLUMN admin_note TEXT NULL AFTER status");
    }

    $paymentColumns = [];
    $paymentColumnResult = $conn->query("SHOW COLUMNS FROM payments");
    while ($column = $paymentColumnResult->fetch_assoc()) {
        $paymentColumns[] = $column['Field'];
    }

    if (!in_array('transaction_id', $paymentColumns, true)) {
        $conn->query("ALTER TABLE payments ADD COLUMN transaction_id VARCHAR(255) NULL AFTER payment_status");
    }

    if (!in_array('stripe_session_id', $paymentColumns, true)) {
        $conn->query("ALTER TABLE payments ADD COLUMN stripe_session_id VARCHAR(255) NULL AFTER amount");
    }

    if (!in_array('razorpay_order_id', $paymentColumns, true)) {
        $conn->query("ALTER TABLE payments ADD COLUMN razorpay_order_id VARCHAR(255) NULL AFTER transaction_id");
    }

    if (!in_array('paid_at', $paymentColumns, true)) {
        $conn->query("ALTER TABLE payments ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
    }

    $paymentStatusResult = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_status'");
    $paymentStatusColumn = $paymentStatusResult->fetch_assoc();
    if ($paymentStatusColumn && stripos((string) $paymentStatusColumn['Type'], 'varchar(50)') === false) {
        $conn->query("ALTER TABLE payments MODIFY COLUMN payment_status VARCHAR(50) NOT NULL DEFAULT 'Pending'");
    }

    if (in_array('complaint', $disputeColumns, true)) {
        $conn->query("UPDATE disputes SET description = COALESCE(description, complaint), reason = COALESCE(reason, 'General dispute') WHERE complaint IS NOT NULL");
    }

    $conn->query("UPDATE disputes SET status = 'under_review' WHERE status = 'in_review'");
} catch (mysqli_sql_exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
