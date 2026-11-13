<?php

require_once __DIR__ . '/config/db.php';

$token = $_GET['token'] ?? '';

if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    die('Invalid paste link.');
}

try {
    $pdo->beginTransaction();

    // Remove expired pastes
    $pdo->exec(
        "DELETE FROM pastes WHERE expires_at <= CURRENT_TIMESTAMP"
    );

    // Atomically decrease remaining views and get paste content
    $sql = "
        UPDATE pastes
        SET remaining_views = remaining_views - 1
        WHERE token = :token
          AND expires_at > CURRENT_TIMESTAMP
          AND remaining_views > 0
        RETURNING content, expires_at, remaining_views
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token]);

    $paste = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paste) {
        $pdo->commit();
        http_response_code(404);
        die('This paste has expired or reached its view limit.');
    }

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    http_response_code(500);
    die('Unable to open this paste.');
}

function escapeHTML($text) {
    return htmlspecialchars(
        $text,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function formatPaste($text) {
    $text = escapeHTML($text);

    $text = preg_replace(
        '/`([^`]+)`/',
        '<code>$1</code>',
        $text
    );

    $text = preg_replace(
        '/\*([^*]+)\*/',
        '<strong>$1</strong>',
        $text
    );

    $text = preg_replace(
        '/_([^_]+)_/',
        '<em>$1</em>',
        $text
    );

    $text = preg_replace(
        '/~([^~]+)~/',
        '<del>$1</del>',
        $text
    );

    return nl2br($text);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Paste</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<main class="container">

    <h1>Shared Paste</h1>

    <div class="preview">
        <?= formatPaste($paste['content']) ?>
    </div>

    <p style="margin-top:20px;">
        Remaining views: <?= (int) $paste['remaining_views'] ?>
    </p>

    <p>
        Expires at:
        <?= escapeHTML($paste['expires_at']) ?>
    </p>

    <p style="margin-top:20px;">
        <a href="index.php">Create a new paste</a>
    </p>

</main>

</body>
</html>