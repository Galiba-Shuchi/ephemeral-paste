
<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ephemeral Paste</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<main class="container">
    <h1>Ephemeral Paste</h1>
    <p>Write, preview and share temporary notes.</p>

    <form action="create.php" method="POST">
        <div class="editor">
            <label for="content">Your Markdown Text</label>

            <div class="toolbar">
    <button type="button" class="format-btn" data-format="**">B</button>
    <button type="button" class="format-btn" data-format="_">I</button>
    <button type="button" class="format-btn" data-format="~~">S</button>
    <button type="button" class="format-btn" data-format="`">&lt;/&gt;</button>
</div>

            <textarea
                id="content"
                name="content"
                placeholder="Write your text here..."
                required
            ></textarea>
        </div>

        <div class="preview">
            <h2>Live Preview</h2>
            <div id="preview">Your preview will appear here.</div>
        </div>

        <div class="settings">
            <label for="expiration">Expiration Time</label>
            <select id="expiration" name="expiration">
                <option value="10">10 minutes</option>
                <option value="30">30 minutes</option>
                <option value="60">1 hour</option>
                <option value="1440">24 hours</option>
            </select>

            <label for="views">Maximum Views</label>
            <select id="views" name="views">
                <option value="1">1 view</option>
                <option value="5">5 views</option>
                <option value="10">10 views</option>
                <option value="20">20 views</option>
            </select>
        </div>

        <button type="submit">Create Shareable Paste</button>
    </form>
</main>

<script src="assets/js/preview.js"></script>
</body>
</html>
