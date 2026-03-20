<?php
$requestId = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;
header('Location: /chat/project_chat.php?request_id=' . $requestId);
exit;
