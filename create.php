<?php

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$content = trim($_POST['content'] ?? '');
$expiration = (int) ($_POST['expiration'] ?? 10);
$views = (int) ($_POST['views'] ?? 1);

$allowedExpirations = [10, 30, 60, 1440];
$allowedViews = [1, 5, 10, 20];

if ($content === '') {
    die('Please enter some text.');
}

if (!in_array($expiration, $allowedExpirations, true)) {
    die('Invalid expiration time.');
}

if (!in_array($views, $allowedViews, true)) {
    die('Invalid view limit.');
}

$token = bin2hex(random_bytes(16));

$expiresAt = date(
    'Y-m-d H:i:s',
    time() + ($expiration * 60)
);

try {
    // Remove expired pastes
    $pdo->exec(
        "DELETE FROM pastes WHERE expires_at <= CURRENT_TIMESTAMP"
    );

    // Save the new paste
    $sql = "INSERT INTO pastes
            (token, content, expires_at, remaining_views)
            VALUES (:token, :content, :expires_at, :remaining_views)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':token' => $token,
        ':content' => $content,
        ':expires_at' => $expiresAt,
        ':remaining_views' => $views
    ]);

    $shareUrl = 'http://' . $_SERVER['HTTP_HOST']
              . '/view.php?token=' . urlencode($token);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Unable to create paste. Please try again.');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Paste Created</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<main class="container">
    <h1>Paste Created Successfully!</h1>

    <p>Your temporary paste is ready to share.</p>

    <label for="shareLink">Your Shareable Link</label>

    <input
        type="text"
        id="shareLink"
        value="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>"
        readonly
        style="width:100%; padding:12px; margin-bottom:20px;"
    >

    <button type="button" onclick="copyLink()">
        Copy Shareable Link
    </button>

    <p style="margin-top:20px;">
        <a href="index.php">Create another paste</a>
    </p>
</main>

<script>
function copyLink() {
    const link = document.getElementById('shareLink');

    navigator.clipboard.writeText(link.value)
        .then(() => alert('Link copied!'))
        .catch(() => {
            link.select();
            document.execCommand('copy');
            alert('Link copied!');
        });
}
</script>

</body>
</html>