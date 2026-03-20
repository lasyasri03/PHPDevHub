<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/role_ui.php';
require_once '../includes/marketplace_helpers.php';
requireRole('client');

$search = trim($_GET['search'] ?? '');
$skill = trim($_GET['skill'] ?? '');
$experience = trim($_GET['experience'] ?? '');
$location = trim($_GET['location'] ?? '');

$query = "SELECT u.id, u.name, d.skills, d.experience, d.location, d.availability, d.profile_image
          FROM developers d
          JOIN users u ON d.user_id = u.id
          WHERE u.role = 'developer'";
$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = "(u.name LIKE ? OR d.skills LIKE ? OR d.location LIKE ?)";
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($skill !== '') {
    $conditions[] = "d.skills LIKE ?";
    $params[] = '%' . $skill . '%';
}

if ($experience !== '' && ctype_digit($experience)) {
    $conditions[] = "d.experience >= ?";
    $params[] = (int) $experience;
}

if ($location !== '') {
    $conditions[] = "d.location LIKE ?";
    $params[] = '%' . $location . '%';
}

if ($conditions) {
    $query .= ' AND ' . implode(' AND ', $conditions);
}

$query .= " ORDER BY u.name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$developers = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderRolePageStart('client', 'find-developers', 'Find Developers', 'Search and filter developer profiles to hire the right match for your project.');
?>
<section class="panel-card">
    <h2 class="section-title">Search Developers</h2>
    <form method="get" class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="search">Search</label>
            <input type="text" id="search" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, skill, or location">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="skill">Skills</label>
            <input type="text" id="skill" name="skill" class="form-control" value="<?php echo htmlspecialchars($skill); ?>" placeholder="PHP, Laravel, MySQL">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="experience">Experience</label>
            <select id="experience" name="experience" class="form-select">
                <option value="">Any experience</option>
                <option value="1" <?php echo $experience === '1' ? 'selected' : ''; ?>>1+ years</option>
                <option value="3" <?php echo $experience === '3' ? 'selected' : ''; ?>>3+ years</option>
                <option value="5" <?php echo $experience === '5' ? 'selected' : ''; ?>>5+ years</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="location">Location</label>
            <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($location); ?>" placeholder="Remote or city">
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Search Developers</button>
            <a href="<?php echo htmlspecialchars(appUrl('developers/list.php')); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</section>

<section class="panel-card">
    <h2 class="section-title">Developer Directory</h2>
    <div class="row g-4">
        <?php if ($developers): ?>
            <?php foreach ($developers as $dev): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img
                                    src="<?php echo !empty($dev['profile_image']) ? htmlspecialchars(appUrl('uploads/profile/' . $dev['profile_image'])) : htmlspecialchars(appUrl('assets/images/default-avatar.png')); ?>"
                                    alt="<?php echo htmlspecialchars($dev['name']); ?>"
                                    style="width:72px;height:72px;object-fit:cover;border-radius:50%;border:1px solid #e5e7eb;"
                                >
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($dev['name']); ?></h5>
                                    <div class="meta-text"><?php echo htmlspecialchars($dev['location'] ?: 'Location not set'); ?></div>
                                </div>
                            </div>
                            <p class="mb-2"><strong>Skills:</strong> <?php echo htmlspecialchars($dev['skills'] ?: 'Not provided'); ?></p>
                            <p class="mb-2"><strong>Experience:</strong> <?php echo (int) ($dev['experience'] ?? 0); ?> years</p>
                            <p class="mb-3"><strong>Status:</strong> <?php echo htmlspecialchars($dev['availability'] ?? 'Available for Hire'); ?></p>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="<?php echo htmlspecialchars(appUrl('developers/profile.php')); ?>?id=<?php echo (int) $dev['id']; ?>" class="btn btn-primary btn-sm">View Profile</a>
                                <a href="<?php echo htmlspecialchars(appUrl('developers/profile.php')); ?>?id=<?php echo (int) $dev['id']; ?>#hire-developer" class="btn btn-outline-primary btn-sm">Hire Developer</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <p class="text-muted mb-0">No developers matched your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php renderRolePageEnd(); ?>
