<?php
// Database connection
$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$port = getenv('DB_PORT');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Ensure required tables exist for both fresh and existing installs
$sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('client', 'developer', 'admin') NOT NULL DEFAULT 'client',
    account_status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS developers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skills TEXT,
    experience INT,
    github_link VARCHAR(255),
    bio TEXT,
    profile_image VARCHAR(255),
    location VARCHAR(255),
    resume VARCHAR(255),
    php_proficiency VARCHAR(50),
    hourly_rate DECIMAL(10,2) NULL,
    availability VARCHAR(20) DEFAULT 'Available for Hire',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS hire_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    developer_id INT,
    project_id INT NULL,
    message TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hire_request_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hire_request_id) REFERENCES hire_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS projects (
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
);

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    developer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS platform_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
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
);

CREATE TABLE IF NOT EXISTS disputes (
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
);

CREATE TABLE IF NOT EXISTS dispute_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispute_id INT NOT NULL,
    user_id INT NOT NULL,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id) REFERENCES disputes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    action VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    target_user VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS developer_ratings (
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
);
";

$pdo->exec($sql);

$developerColumns = [];
$columnStmt = $pdo->query("SHOW COLUMNS FROM developers");
foreach ($columnStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $developerColumns[] = $column['Field'];
}

if (!in_array('resume', $developerColumns, true)) {
    $pdo->exec("ALTER TABLE developers ADD COLUMN resume VARCHAR(255) NULL AFTER location");
}

if (!in_array('php_proficiency', $developerColumns, true)) {
    $pdo->exec("ALTER TABLE developers ADD COLUMN php_proficiency VARCHAR(50) NULL AFTER resume");
}

if (!in_array('github_link', $developerColumns, true)) {
    $afterColumn = in_array('experience', $developerColumns, true) ? 'experience' : 'user_id';
    $pdo->exec("ALTER TABLE developers ADD COLUMN github_link VARCHAR(255) NULL AFTER $afterColumn");
}

if (in_array('github', $developerColumns, true)) {
    $pdo->exec("UPDATE developers SET github_link = COALESCE(github_link, github) WHERE github IS NOT NULL AND github <> ''");
}

if (!in_array('hourly_rate', $developerColumns, true)) {
    $pdo->exec("ALTER TABLE developers ADD COLUMN hourly_rate DECIMAL(10,2) NULL AFTER php_proficiency");
}

if (!in_array('availability', $developerColumns, true)) {
    $pdo->exec("ALTER TABLE developers ADD COLUMN availability VARCHAR(20) NOT NULL DEFAULT 'Available for Hire' AFTER php_proficiency");
}

if (!in_array('is_verified', $developerColumns, true)) {
    $pdo->exec("ALTER TABLE developers ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER availability");
}

$userColumns = [];
$userColumnStmt = $pdo->query("SHOW COLUMNS FROM users");
foreach ($userColumnStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $userColumns[] = $column['Field'];
}

if (!in_array('account_status', $userColumns, true)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER role");
}

$projectColumns = [];
$projectColumnStmt = $pdo->query("SHOW COLUMNS FROM projects");
foreach ($projectColumnStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $projectColumns[] = $column['Field'];
}

if (!in_array('deadline', $projectColumns, true)) {
    $pdo->exec("ALTER TABLE projects ADD COLUMN deadline DATE NULL AFTER budget");
}

if (!in_array('developers_needed', $projectColumns, true)) {
    $pdo->exec("ALTER TABLE projects ADD COLUMN developers_needed INT NOT NULL DEFAULT 1 AFTER deadline");
}

$projectStatusColumnStmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'status'");
$projectStatusColumn = $projectStatusColumnStmt->fetch(PDO::FETCH_ASSOC);
if ($projectStatusColumn && stripos($projectStatusColumn['Type'], 'varchar') === false) {
    $pdo->exec("ALTER TABLE projects MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
}

$pdo->exec("UPDATE projects SET status = 'approved' WHERE status = 'open'");

$hireRequestColumns = [];
$hireRequestColumnStmt = $pdo->query("SHOW COLUMNS FROM hire_requests");
foreach ($hireRequestColumnStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $hireRequestColumns[] = $column['Field'];
}

if (!in_array('project_id', $hireRequestColumns, true)) {
    $pdo->exec("ALTER TABLE hire_requests ADD COLUMN project_id INT NULL AFTER developer_id");
}

$disputeColumns = [];
$disputeColumnStmt = $pdo->query("SHOW COLUMNS FROM disputes");
foreach ($disputeColumnStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $disputeColumns[] = $column['Field'];
}

if (!in_array('reason', $disputeColumns, true)) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN reason VARCHAR(255) NULL AFTER developer_id");
}

if (!in_array('description', $disputeColumns, true)) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN description TEXT NULL AFTER reason");
}

if (!in_array('admin_note', $disputeColumns, true)) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN admin_note TEXT NULL AFTER status");
}

if (in_array('complaint', $disputeColumns, true)) {
    $pdo->exec("UPDATE disputes SET description = COALESCE(description, complaint), reason = COALESCE(reason, 'General dispute') WHERE complaint IS NOT NULL");
}

$pdo->exec("UPDATE disputes SET status = 'under_review' WHERE status = 'in_review'");

$paymentColumns = [];
$paymentColumnStmt = $pdo->query("SHOW COLUMNS FROM payments");
foreach ($paymentColumnStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $paymentColumns[] = $column['Field'];
}

if (!in_array('transaction_id', $paymentColumns, true)) {
    $pdo->exec("ALTER TABLE payments ADD COLUMN transaction_id VARCHAR(255) NULL AFTER payment_status");
}

if (!in_array('stripe_session_id', $paymentColumns, true)) {
    $pdo->exec("ALTER TABLE payments ADD COLUMN stripe_session_id VARCHAR(255) NULL AFTER amount");
}

if (!in_array('razorpay_order_id', $paymentColumns, true)) {
    $pdo->exec("ALTER TABLE payments ADD COLUMN razorpay_order_id VARCHAR(255) NULL AFTER transaction_id");
}

if (!in_array('paid_at', $paymentColumns, true)) {
    $pdo->exec("ALTER TABLE payments ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
}

$paymentStatusColumnStmt = $pdo->query("SHOW COLUMNS FROM payments LIKE 'payment_status'");
$paymentStatusColumn = $paymentStatusColumnStmt->fetch(PDO::FETCH_ASSOC);
if ($paymentStatusColumn && stripos((string) $paymentStatusColumn['Type'], 'varchar(50)') === false) {
    $pdo->exec("ALTER TABLE payments MODIFY COLUMN payment_status VARCHAR(50) NOT NULL DEFAULT 'Pending'");
}
?>
