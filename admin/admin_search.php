<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if ($query === '' || mb_strlen($query) < 2) {
    echo json_encode([
        'developers' => [],
        'clients' => [],
        'projects' => [],
    ]);
    exit;
}

$like = '%' . $query . '%';

$developerStmt = $conn->prepare(
    "SELECT u.id, u.name, COALESCE(d.skills, 'Developer') AS skills
     FROM users u
     LEFT JOIN developers d ON d.user_id = u.id
     WHERE u.role = 'developer' AND u.name LIKE ?
     ORDER BY u.name ASC
     LIMIT 5"
);
$developerStmt->bind_param('s', $like);
$developerStmt->execute();
$developerResult = $developerStmt->get_result();
$developers = [];
while ($row = $developerResult->fetch_assoc()) {
    $developers[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'meta' => $row['skills'],
        'url' => appUrl('admin/admin_developer_details.php') . '?developer_id=' . (int) $row['id'],
    ];
}

$clientStmt = $conn->prepare(
    "SELECT id, name, email
     FROM users
     WHERE role = 'client' AND name LIKE ?
     ORDER BY name ASC
     LIMIT 5"
);
$clientStmt->bind_param('s', $like);
$clientStmt->execute();
$clientResult = $clientStmt->get_result();
$clients = [];
while ($row = $clientResult->fetch_assoc()) {
    $clients[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'meta' => $row['email'],
        'url' => appUrl('admin/admin_client_details.php') . '?client_id=' . (int) $row['id'],
    ];
}

$projectStmt = $conn->prepare(
    "SELECT p.id, p.title, u.name AS client_name
     FROM projects p
     INNER JOIN users u ON u.id = p.client_id
     WHERE p.title LIKE ?
     ORDER BY p.created_at DESC
     LIMIT 5"
);
$projectStmt->bind_param('s', $like);
$projectStmt->execute();
$projectResult = $projectStmt->get_result();
$projects = [];
while ($row = $projectResult->fetch_assoc()) {
    $projects[] = [
        'id' => (int) $row['id'],
        'name' => $row['title'],
        'meta' => 'Client: ' . $row['client_name'],
        'url' => appUrl('admin/admin_project_details.php') . '?project_id=' . (int) $row['id'],
    ];
}

echo json_encode([
    'developers' => $developers,
    'clients' => $clients,
    'projects' => $projects,
]);
