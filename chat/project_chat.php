<?php
include '../includes/db.php';
include '../includes/auth.php';
require_once '../includes/role_ui.php';
requireLogin();

function renderChatMessages(array $messages, int $currentUserId): void
{
    if (empty($messages)) {
        echo '<p class="text-muted mb-0">No messages yet. Start the conversation below.</p>';
        return;
    }

    foreach ($messages as $message) {
        $messageClass = ((int) $message['sender_id'] === $currentUserId) ? 'client' : 'developer';
        ?>
        <div class="chat-row <?php echo $messageClass; ?>">
            <div class="chat-bubble">
                <span class="chat-meta">
                    <?php echo htmlspecialchars($message['sender_name']); ?> |
                    <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($message['created_at']))); ?>
                </span>
                <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
            </div>
        </div>
        <?php
    }
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$requestId = (int) ($_GET['request_id'] ?? 0);
$role = getUserRole();
$dashboardUrl = $role === 'developer' ? appUrl('developer/dashboard.php') : appUrl('client/dashboard.php');

if ($requestId <= 0) {
    header('Location: ' . $dashboardUrl);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT hr.*, c.name AS client_name, d.name AS developer_name, p.title AS project_title
     FROM hire_requests hr
     JOIN users c ON hr.client_id = c.id
     JOIN users d ON hr.developer_id = d.id
     LEFT JOIN projects p ON p.id = hr.project_id
     WHERE hr.id = ?'
);
$stmt->execute([$requestId]);
$hireRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hireRequest) {
    header('Location: ' . $dashboardUrl);
    exit;
}

if ($userId !== (int) $hireRequest['client_id'] && $userId !== (int) $hireRequest['developer_id']) {
    header('Location: ' . $dashboardUrl);
    exit;
}

$messages = [];
$errorMessage = $_GET['error'] ?? '';

try {
    $messageStmt = $pdo->prepare(
        'SELECT m.*, u.name AS sender_name
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.hire_request_id = ?
         ORDER BY m.created_at ASC, m.id ASC'
    );
    $messageStmt->execute([$requestId]);
    $messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = 'Unable to load messages right now.';
}

if (isset($_GET['partial']) && $_GET['partial'] === 'messages') {
    renderChatMessages($messages, $userId);
    exit;
}

$activePage = $role === 'developer' ? 'messages' : 'contracts';
renderRolePageStart($role === 'developer' ? 'developer' : 'client', $activePage, 'Project Chat', 'Continue the conversation from your active contract or accepted hire request.');
?>
<style>
    .project-chat-window { min-height: 420px; max-height: 520px; overflow-y: auto; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 16px; padding: 1.25rem; }
    .chat-row { display: flex; margin-bottom: 1rem; }
    .chat-row.client { justify-content: flex-end; }
    .chat-row.developer { justify-content: flex-start; }
    .chat-bubble { max-width: 75%; padding: 0.9rem 1rem; border-radius: 16px; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08); }
    .chat-row.client .chat-bubble { background: #0d6efd; color: #fff; border-bottom-right-radius: 4px; }
    .chat-row.developer .chat-bubble { background: #fff; color: #212529; border: 1px solid #dee2e6; border-bottom-left-radius: 4px; }
    .chat-meta { display: block; margin-bottom: 0.35rem; font-size: 0.8rem; opacity: 0.85; }
</style>

<?php if ($hireRequest['status'] !== 'accepted'): ?>
    <div class="alert alert-warning">This chat becomes available after the hire request is accepted.</div>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<section class="panel-card">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
        <div>
            <h2 class="section-title mb-1"><?php echo htmlspecialchars($hireRequest['project_title'] ?: 'Direct Hire Conversation'); ?></h2>
            <p class="meta-text mb-0">Client: <?php echo htmlspecialchars($hireRequest['client_name']); ?> | Developer: <?php echo htmlspecialchars($hireRequest['developer_name']); ?></p>
        </div>
        <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="project-chat-window mb-3" id="chat-box">
        <?php renderChatMessages($messages, $userId); ?>
    </div>

    <?php if ($hireRequest['status'] === 'accepted'): ?>
        <form method="post" action="<?php echo htmlspecialchars(appUrl('send_message.php')); ?>">
            <input type="hidden" name="hire_request_id" value="<?php echo (int) $hireRequest['id']; ?>">
            <div class="mb-3">
                <label for="message" class="form-label">Message</label>
                <textarea id="message" name="message" class="form-control" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    <?php endif; ?>
</section>

<?php if ($hireRequest['status'] === 'accepted'): ?>
<script>
    (function () {
        const messageContainer = document.getElementById('chat-box');
        const messageField = document.getElementById('message');
        const endpoint = '<?php echo htmlspecialchars(appUrl('chat/project_chat.php')); ?>?request_id=<?php echo (int) $hireRequest['id']; ?>&partial=messages';

        function stickToBottom(force) {
            if (!messageContainer) return;
            const isNearBottom = messageContainer.scrollTop + messageContainer.clientHeight >= messageContainer.scrollHeight - 80;
            if (force || isNearBottom) {
                messageContainer.scrollTop = messageContainer.scrollHeight;
            }
        }

        async function refreshMessages() {
            if (!messageContainer) return;
            if (messageField && document.activeElement === messageField) return;
            const shouldStick = messageContainer.scrollTop + messageContainer.clientHeight >= messageContainer.scrollHeight - 80;
            try {
                const response = await fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) return;
                messageContainer.innerHTML = await response.text();
                stickToBottom(shouldStick);
            } catch (error) {}
        }

        stickToBottom(true);
        setInterval(refreshMessages, 5000);
    })();
</script>
<?php endif; ?>
<?php renderRolePageEnd(); ?>
