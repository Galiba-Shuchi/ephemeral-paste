
<?php

require_once __DIR__ . '/config/db.php';

// ==========================================
// 1. INITIAL VARIABLES
// ==========================================

$token = $_GET['token'] ?? '';

$expired = false;
$error = false;
$paste = null;
$expiredReason = '';


// ==========================================
// 2. TOKEN VALIDATION
// ==========================================

if (!preg_match('/^[a-f0-9]{32}$/', $token)) {

    http_response_code(400);
    $error = true;

} else {

    try {

        $pdo->beginTransaction();

        // Lock the paste row to prevent concurrent view-count errors.
        $checkSql = "
            SELECT
                expires_at,
                remaining_views,
                CASE
                    WHEN expires_at <= (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
                    THEN 1
                    ELSE 0
                END AS is_expired
            FROM pastes
            WHERE token = :token
            FOR UPDATE
        ";

        $checkStmt = $pdo->prepare($checkSql);

        $checkStmt->execute([
            ':token' => $token
        ]);

        $existingPaste = $checkStmt->fetch(PDO::FETCH_ASSOC);


        if (!$existingPaste) {

            $expired = true;
            $expiredReason = 'not_found';

        } elseif ((int)$existingPaste['is_expired'] === 1) {

            // Time expiration reached.
            $expired = true;
            $expiredReason = 'time';

            $deleteStmt = $pdo->prepare("
                DELETE FROM pastes
                WHERE token = :token
            ");

            $deleteStmt->execute([
                ':token' => $token
            ]);

        } elseif ((int)$existingPaste['remaining_views'] <= 0) {

            // Maximum views reached.
            $expired = true;
            $expiredReason = 'views';

            $deleteStmt = $pdo->prepare("
                DELETE FROM pastes
                WHERE token = :token
            ");

            $deleteStmt->execute([
                ':token' => $token
            ]);

        } else {

            // Atomically decrease the remaining view count.
            $updateSql = "
                UPDATE pastes
                SET remaining_views = remaining_views - 1
                WHERE token = :token
                  AND expires_at > (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
                  AND remaining_views > 0
                RETURNING content, expires_at, remaining_views
            ";

            $updateStmt = $pdo->prepare($updateSql);

            $updateStmt->execute([
                ':token' => $token
            ]);

            $paste = $updateStmt->fetch(PDO::FETCH_ASSOC);

            if (!$paste) {

                $expired = true;
                $expiredReason = 'unavailable';

            }

        }


        // Remove other expired pastes using the same UTC clock.
        $pdo->exec("
            DELETE FROM pastes
            WHERE expires_at <= (CURRENT_TIMESTAMP AT TIME ZONE 'UTC')
        ");

        $pdo->commit();

        if ($expired) {
            http_response_code(410);
        }

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Paste viewing error: ' . $e->getMessage());

        $error = true;
        http_response_code(500);

    }

}


// ==========================================
// 3. HTML ESCAPING
// ==========================================

function escapeHTML($text) {

    return htmlspecialchars(
        $text,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

}


// ==========================================
// 4. MARKDOWN PARSER
// ==========================================

function markdownToHTML($text) {

    $length = strlen($text);

    $output = '';
    $buffer = '';

    $markers = [
        '*' => 'strong',
        '_' => 'em',
        '~' => 'del',
        '`' => 'code'
    ];

    $flushBuffer = function () use (&$buffer, &$output) {

        if ($buffer !== '') {

            $output .= nl2br(
                escapeHTML($buffer),
                false
            );

            $buffer = '';

        }

    };

    for ($i = 0; $i < $length; $i++) {

        $char = $text[$i];

        // Handle escaped formatting characters.
        if (
            $char === '\\' &&
            isset($text[$i + 1]) &&
            isset($markers[$text[$i + 1]])
        ) {

            $buffer .= $text[$i + 1];
            $i++;

            continue;

        }

        if (!isset($markers[$char])) {

            $buffer .= $char;
            continue;

        }

        $closing = strpos($text, $char, $i + 1);

        if ($closing === false || $closing === $i + 1) {

            $buffer .= $char;
            continue;

        }

        $inner = substr(
            $text,
            $i + 1,
            $closing - $i - 1
        );

        $flushBuffer();

        $tag = $markers[$char];

        if ($tag === 'code') {

            $output .= '<code>' .
                escapeHTML($inner) .
                '</code>';

        } else {

            $output .= '<' . $tag . '>' .
                markdownToHTML($inner) .
                '</' . $tag . '>';

        }

        $i = $closing;

    }

    $flushBuffer();

    return $output;

}


// ==========================================
// 5. EXPIRATION DATE FOR LOCAL DISPLAY
// ==========================================

$expiresAtIso = '';

if (!$error && !$expired && $paste) {

    // Database timestamp is stored as UTC without timezone.
    $expiresAt = new DateTimeImmutable(
        $paste['expires_at'],
        new DateTimeZone('UTC')
    );

    $expiresAtIso = $expiresAt->format('Y-m-d\TH:i:s\Z');

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?php if ($expired): ?>
            Paste Expired
        <?php elseif ($error): ?>
            Error
        <?php else: ?>
            Shared Paste
        <?php endif; ?>
    </title>

    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<?php if ($expired): ?>

    <!-- ==================================
         EXPIRED PAGE
    =================================== -->

    <main class="container expired-container">

        <div class="expired-icon">
            ⏳
        </div>

        <h1>This Paste Has Expired!</h1>

        <?php if ($expiredReason === 'time'): ?>

            <p>
                The expiration time for this paste has ended.
            </p>

        <?php elseif ($expiredReason === 'views'): ?>

            <p>
                This paste has reached its maximum view limit.
            </p>

        <?php elseif ($expiredReason === 'not_found'): ?>

            <p>
                This paste does not exist or is no longer available.
            </p>

        <?php else: ?>

            <p>
                This temporary paste is no longer available.
            </p>

        <?php endif; ?>

        <p>
            You can create a new paste and share it with others.
        </p>

        <a href="index.php" class="new-paste-btn">
            Create a New Paste
        </a>

        <p class="expired-footer">
            Thank you for using Ephemeral Paste.
        </p>

    </main>


<?php elseif ($error): ?>

    <!-- ==================================
         ERROR PAGE
    =================================== -->

    <main class="container expired-container">

        <div class="expired-icon">
            ⚠️
        </div>

        <h1>Something Went Wrong!</h1>

        <p>
            We couldn't open this paste.
            Please try again later.
        </p>

        <a href="index.php" class="new-paste-btn">
            Go to Home
        </a>

    </main>


<?php else: ?>

    <!-- ==================================
         SHARED PASTE PAGE
    =================================== -->

    <main class="container">

        <header class="page-header">

            <h1>Shared Paste</h1>

            <p>Your temporary note</p>

        </header>


        <div class="preview shared-content">

            <?= markdownToHTML($paste['content']) ?>

        </div>


        <p class="paste-info">

            Remaining views:
            <?= (int)$paste['remaining_views'] ?>

        </p>


        <p class="paste-info">

            Expires at:
            <strong id="expirationDisplay">Loading...</strong>

        </p>


        <p class="back-home">

            <a href="index.php">
                Create a New Paste
            </a>

        </p>

    </main>


    <script>

    // Database stores the timestamp in UTC.
    // Display it in the visitor's local timezone.

    const expirationUTC = <?= json_encode($expiresAtIso) ?>;

    const expirationDate = new Date(expirationUTC);

    const expirationDisplay =
        document.getElementById("expirationDisplay");

    if (!isNaN(expirationDate.getTime())) {

        expirationDisplay.textContent =
            expirationDate.toLocaleString(undefined, {
                year: "numeric",
                month: "long",
                day: "numeric",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit"
            });

    } else {

        expirationDisplay.textContent = "Unavailable";

    }

    </script>

<?php endif; ?>

</body>
</html>
