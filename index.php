
<?php
// index.php
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
    <header class="page-header">
        <h1>Ephemeral Paste</h1>
        <p>Write, format, preview and share temporary notes.</p>
    </header>

    <form id="pasteForm" action="create.php" method="POST">

        <!-- EDITOR -->
        <section class="editor">
            <label>Your Content</label>

            <div class="toolbar">
                <button type="button" class="format-btn" data-format="bold" title="Bold"><b>B</b></button>
                <button type="button" class="format-btn" data-format="italic" title="Italic"><i>I</i></button>
                <button type="button" class="format-btn" data-format="strike" title="Strikethrough"><s>S</s></button>
                <button type="button" class="format-btn" data-format="code" title="Inline Code">&lt;/&gt;</button>
            </div>

            <div
                id="editor"
                class="text-editor"
                contenteditable="true"
                data-placeholder="Write your note here..."
                spellcheck="true"
            ></div>

            <textarea id="content" name="content" hidden></textarea>

            <p class="editor-help">
                Formatting: *bold*, _italic_, ~strike~, `code`
            </p>
        </section>

        <!-- LIVE PREVIEW -->
        <section class="preview-section">
            <h2>Live Preview</h2>
            <div id="preview" class="preview">
                Your formatted content will appear here.
            </div>
        </section>

        <!-- PRESET SETTINGS -->
        <section class="settings">

            <div class="setting-group">
                <label for="expiration">Expiration Time</label>

                <select id="expiration" name="expiration" required>
                    <option value="10">10 Minutes</option>
                    <option value="30">30 Minutes</option>
                    <option value="60">1 Hour</option>
                    <option value="1440">24 Hours</option>
                    <option value="custom">Custom Time</option>
                </select>
            </div>

            <div class="setting-group">
                <label for="views">Maximum Views</label>

                <select id="views" name="views" required>
                    <option value="1">1 View</option>
                    <option value="5" selected>5 Views</option>
                    <option value="10">10 Views</option>
                    <option value="20">20 Views</option>
                    <option value="custom">Custom Views</option>
                </select>
            </div>

        </section>

        <!-- CUSTOM TIME -->
        <section id="customTime" class="custom-time" hidden>
            <h2>Custom Expiration Time</h2>

            <div class="settings">
                <div class="setting-group">
                    <label for="customHours">Hours</label>
                    <input
                        type="number"
                        id="customHours"
                        name="custom_hours"
                        min="0"
                        max="720"
                        value="0"
                        placeholder="e.g. 2"
                    >
                </div>

                <div class="setting-group">
                    <label for="customMinutes">Minutes</label>
                    <input
                        type="number"
                        id="customMinutes"
                        name="custom_minutes"
                        min="0"
                        max="59"
                        value="30"
                        placeholder="e.g. 30"
                    >
                </div>
            </div>

            <p class="editor-help">
                Enter your preferred expiration duration.
                Maximum duration: 30 days.
            </p>
        </section>

        <!-- CUSTOM VIEWS -->
        <section id="customViews" class="custom-time" hidden>
            <h2>Custom Maximum Views</h2>

            <div class="setting-group">
                <label for="customViewCount">Number of Views</label>

                <input
                    type="number"
                    id="customViewCount"
                    name="custom_views"
                    min="1"
                    max="1000000"
                    step="1"
                    value="50"
                    placeholder="e.g. 50"
                >
            </div>

            <p class="editor-help">
                Enter how many times people can open your paste.
                Minimum: 1 view.
            </p>
        </section>

        <!-- SUBMIT -->
        <button type="submit" class="submit-btn">
            Create Shareable Paste
        </button>

    </form>
</main>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("pasteForm");

    // Expiration elements
    const expiration = document.getElementById("expiration");
    const customTime = document.getElementById("customTime");
    const customHours = document.getElementById("customHours");
    const customMinutes = document.getElementById("customMinutes");

    // Views elements
    const views = document.getElementById("views");
    const customViews = document.getElementById("customViews");
    const customViewCount = document.getElementById("customViewCount");


    // Show or hide Custom Time
    function toggleCustomTime() {

        const isCustom = expiration.value === "custom";

        customTime.hidden = !isCustom;

        customHours.required = isCustom;
        customMinutes.required = isCustom;
    }


    // Show or hide Custom Views
    function toggleCustomViews() {

        const isCustom = views.value === "custom";

        customViews.hidden = !isCustom;

        customViewCount.required = isCustom;
    }


    // Expiration selection
    expiration.addEventListener("change", toggleCustomTime);

    // Views selection
    views.addEventListener("change", toggleCustomViews);


    // Initial state
    toggleCustomTime();
    toggleCustomViews();


    // Validate custom settings
    form.addEventListener("submit", function (event) {

        if (expiration.value === "custom") {

            const hours = Number(customHours.value);
            const minutes = Number(customMinutes.value);

            if (
                customHours.value === "" ||
                customMinutes.value === "" ||
                !Number.isInteger(hours) ||
                !Number.isInteger(minutes) ||
                hours < 0 ||
                hours > 720 ||
                minutes < 0 ||
                minutes > 59 ||
                (hours === 0 && minutes === 0)
            ) {
                event.preventDefault();
                alert("Please enter a valid custom expiration time.");
                return;
            }
        }


        if (views.value === "custom") {

            const count = Number(customViewCount.value);

            if (
                customViewCount.value === "" ||
                !Number.isInteger(count) ||
                count < 1 ||
                count > 1000000
            ) {
                event.preventDefault();
                alert("Please enter a valid maximum view count (1–1,000,000).");
                return;
            }
        }

    });

});
</script>

<script src="assets/js/preview.js"></script>

</body>
</html>
