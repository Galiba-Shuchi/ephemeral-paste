
<?php
// create.php

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Get submitted data
$content = trim($_POST['content'] ?? '');
$expiration = $_POST['expiration'] ?? '';
$viewsOption = $_POST['views'] ?? '';

if ($content === '') {
    exit('Please enter some content. <a href="index.php">Go back</a>');
}


// =====================================
// VALIDATE EXPIRATION TIME
// =====================================

if ($expiration === 'custom') {

    $hours = filter_var(
        $_POST['custom_hours'] ?? null,
        FILTER_VALIDATE_INT
    );

    $minutes = filter_var(
        $_POST['custom_minutes'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $hours === false ||
        $minutes === false ||
        $hours < 0 ||
        $hours > 720 ||
        $minutes < 0 ||
        $minutes > 59
    ) {
        exit('Invalid custom expiration time. <a href="index.php">Go back</a>');
    }

    $expirationMinutes = ($hours * 60) + $minutes;

    if ($expirationMinutes < 1 || $expirationMinutes > 43200) {
        exit('Expiration must be between 1 minute and 30 days. <a href="index.php">Go back</a>');
    }

} else {

    $allowedExpirations = [10, 30, 60, 1440];

    $expirationMinutes = filter_var(
        $expiration,
        FILTER_VALIDATE_INT
    );

    if (
        $expirationMinutes === false ||
        !in_array($expirationMinutes, $allowedExpirations, true)
    ) {
        exit('Invalid expiration option. <a href="index.php">Go back</a>');
    }
}


// =====================================
// VALIDATE MAXIMUM VIEWS
// =====================================

if ($viewsOption === 'custom') {

    $views = filter_var(
        $_POST['custom_views'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $views === false ||
        $views < 1 ||
        $views > 1000000
    ) {
        exit('Please enter a valid custom view limit (1–1,000,000). <a href="index.php">Go back</a>');
    }

} else {

    $allowedViews = [1, 5, 10, 20];

    $views = filter_var(
        $viewsOption,
        FILTER_VALIDATE_INT
    );

    if (
        $views === false ||
        !in_array($views, $allowedViews, true)
    ) {
        exit('Invalid view limit. <a href="index.php">Go back</a>');
    }
}


// =====================================
// CREATE PASTE
// =====================================

try {

    // Generate unique token
    $token = bin2hex(random_bytes(16));

    // Remove expired pastes using UTC database time
    $pdo->exec("
        DELETE FROM pastes
        WHERE expires_at <= (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
    ");

    // Insert paste and get its expiration time
    // Both timestamps are stored as UTC in timestamp without time zone columns.
    $sql = "
        INSERT INTO pastes (
            token,
            content,
            created_at,
            expires_at,
            remaining_views
        )
        VALUES (
            :token,
            :content,
            (CURRENT_TIMESTAMP AT TIME ZONE 'UTC'),
            (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
                + make_interval(mins => CAST(:expiration AS integer)),
            :views
        )
        RETURNING expires_at
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':token' => $token,
        ':content' => $content,
        ':expiration' => $expirationMinutes,
        ':views' => $views
    ]);

    $paste = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paste) {
        throw new RuntimeException('Could not retrieve expiration time.');
    }

    // Database timestamp is UTC (without timezone).
    // Convert it to an ISO timestamp with UTC timezone for browser display.
    $expiresAt = new DateTimeImmutable(
        $paste['expires_at'],
        new DateTimeZone('UTC')
    );

    $expiresAtIso = $expiresAt
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d\TH:i:s\Z');


    // =====================================
    // BUILD SHARE URL
    // =====================================

    $scriptDirectory = str_replace(
        '\\',
        '/',
        dirname($_SERVER['SCRIPT_NAME'])
    );

    $scriptDirectory = rtrim($scriptDirectory, '/');

    $protocol = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    ) ? 'https://' : 'http://';

    $shareUrl = $protocol
        . $_SERVER['HTTP_HOST']
        . $scriptDirectory
        . '/view.php?token='
        . urlencode($token);


} catch (Throwable $e) {

    error_log('Paste creation error: ' . $e->getMessage());

    http_response_code(500);

    exit('Something went wrong while creating your paste. Please try again.');
}


// =====================================
// DISPLAY INFORMATION
// =====================================

$safeUrl = htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8');

$safeExpiresAt = htmlspecialchars(
    $expiresAtIso,
    ENT_QUOTES,
    'UTF-8'
);

if ($expirationMinutes < 60) {

    $expirationText = $expirationMinutes . ' minutes';

} elseif ($expirationMinutes % 60 === 0) {

    $expirationText = ($expirationMinutes / 60) . ' hours';

} else {

    $expirationText = intdiv($expirationMinutes, 60)
        . ' hours '
        . ($expirationMinutes % 60)
        . ' minutes';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Paste Created</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<main class="container expired-container">

    <div class="expired-icon">✓</div>

    <h1 style="color: #16a34a;">
        Paste Created Successfully!
    </h1>

    <p>Your temporary paste is ready to share.</p>


    <!-- SHAREABLE LINK -->

    <label for="shareLink">Your Shareable Link</label>

    <input
        type="text"
        id="shareLink"
        value="<?php echo $safeUrl; ?>"
        readonly
        style="width:100%; padding:13px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:15px;"
    >

    <button
        type="button"
        class="submit-btn"
        onclick="copyLink()"
    >
        Copy Shareable Link
    </button>


    <!-- EXPIRATION INFORMATION -->

    <p style="margin-top:20px;">
        Expires after:
        <strong><?php echo htmlspecialchars($expirationText, ENT_QUOTES, 'UTF-8'); ?></strong>
    </p>

    <p>
        Expiration date and time:
        <strong id="expirationDisplay">Loading...</strong>
    </p>

    <p>
        Maximum views:
        <strong><?php echo (int) $views; ?></strong>
    </p>


    <a href="index.php" class="new-paste-btn">
        Create Another Paste
    </a>

</main>


<script>

// Copy shareable link
function copyLink() {

    const link = document.getElementById("shareLink");

    navigator.clipboard.writeText(link.value)
        .then(() => {
            alert("Shareable link copied!");
        })
        .catch(() => {

            link.select();
            document.execCommand("copy");

            alert("Shareable link copied!");
        });
}


// Display expiration in the visitor's local timezone
const expirationUTC = "<?php echo $safeExpiresAt; ?>";

const expirationDate = new Date(expirationUTC);

const expirationDisplay = document.getElementById("expirationDisplay");

if (!isNaN(expirationDate.getTime())) {

    expirationDisplay.textContent = expirationDate.toLocaleString(
        undefined,
        {
            year: "numeric",
            month: "long",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit"
        }
    );

} else {

    expirationDisplay.textContent = "Unavailable";

}

</script>

</body>
</html>
