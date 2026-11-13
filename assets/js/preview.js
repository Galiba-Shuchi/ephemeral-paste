
const editor = document.getElementById("content");
const preview = document.getElementById("preview");

// Escape HTML to prevent HTML injection
function escapeHTML(text) {
    return text.replace(/[&<>"']/g, character => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[character]);
}

// Render Markdown safely, including nested formatting
function renderMarkdown(source) {
    const tokens = [];
    let text = escapeHTML(source);

    // Protect inline code from other Markdown formatting
    text = text.replace(/`([^`\n]+)`/g, (_, code) => {
        tokens.push("<code>" + code + "</code>");
        return `@@TOKEN${tokens.length - 1}@@`;
    });

    // Bold
    text = text.replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");

    // Italic
    text = text.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, "$1<em>$2</em>");
    text = text.replace(/_([^_\n]+)_/g, "<em>$1</em>");

    // Strikethrough
    text = text.replace(/~~(.+?)~~/g, "<del>$1</del>");

    // Restore inline code
    text = text.replace(/@@TOKEN(\d+)@@/g, (_, index) => {
        return tokens[Number(index)];
    });

    return text.replace(/\n/g, "<br>");
}

// Update live preview
function updatePreview() {
    preview.innerHTML = renderMarkdown(editor.value);
}

// Apply or remove formatting
function applyFormatting(symbol) {
    const start = editor.selectionStart;
    const end = editor.selectionEnd;

    if (start === end) {
        editor.focus();
        return;
    }

    const selectedText = editor.value.substring(start, end);

    const before = editor.value.substring(0, start);
    const after = editor.value.substring(end);

    const hasFormatting =
        selectedText.startsWith(symbol) &&
        selectedText.endsWith(symbol) &&
        selectedText.length >= symbol.length * 2;

    let replacement;
    let selectionStart;
    let selectionEnd;

    if (hasFormatting) {
        replacement = selectedText.slice(
            symbol.length,
            -symbol.length
        );

        selectionStart = start;
        selectionEnd = start + replacement.length;

    } else {
        replacement = symbol + selectedText + symbol;

        selectionStart = start + symbol.length;
        selectionEnd = selectionStart + selectedText.length;
    }

    editor.setRangeText(replacement, start, end, "end");

    editor.focus();
    editor.setSelectionRange(selectionStart, selectionEnd);

    updatePreview();
}

// Toolbar buttons
document.querySelectorAll(".format-btn").forEach(button => {

    button.addEventListener("mousedown", event => {
        event.preventDefault();
    });

    button.addEventListener("click", () => {
        applyFormatting(button.dataset.format);
    });

});

// Update preview while typing
editor.addEventListener("input", updatePreview);

// Initial preview
updatePreview();
