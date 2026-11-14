
"use strict";

// ==========================================
// 1. ELEMENTS
// ==========================================

const editor = document.getElementById("editor");
const contentField = document.getElementById("content");
const preview = document.getElementById("preview");
const form = document.getElementById("pasteForm");
const toolbarButtons = document.querySelectorAll(".format-btn");

let savedRange = null;

const commandMap = {
    bold: "bold",
    italic: "italic",
    strike: "strikeThrough"
};

const allowedPreviewTags = [
    "B", "STRONG",
    "I", "EM",
    "S", "STRIKE", "DEL",
    "CODE", "BR", "DIV", "P"
];

// ==========================================
// 2. SELECTION MANAGEMENT
// ==========================================

function isSelectionInsideEditor(selection) {
    return selection &&
        selection.rangeCount > 0 &&
        selection.anchorNode &&
        selection.focusNode &&
        editor.contains(selection.anchorNode) &&
        editor.contains(selection.focusNode);
}

function saveSelection() {
    const selection = window.getSelection();

    if (isSelectionInsideEditor(selection)) {
        savedRange = selection.getRangeAt(0).cloneRange();
    }
}

function restoreSelection() {
    if (!savedRange) return false;

    if (
        !editor.contains(savedRange.startContainer) ||
        !editor.contains(savedRange.endContainer)
    ) {
        savedRange = null;
        return false;
    }

    const selection = window.getSelection();

    selection.removeAllRanges();
    selection.addRange(savedRange);

    return true;
}

// ==========================================
// 3. FORMAT DETECTION
// ==========================================

function getFormatAncestor(node, tag) {
    let current = node.nodeType === Node.ELEMENT_NODE
        ? node
        : node.parentElement;

    while (current && current !== editor) {
        if (current.tagName === tag.toUpperCase()) {
            return current;
        }

        current = current.parentElement;
    }

    return null;
}

function updateToolbar() {
    const selection = window.getSelection();

    if (!isSelectionInsideEditor(selection)) return;

    toolbarButtons.forEach(button => {
        const format = button.dataset.format;

        if (format === "code") {
            const node = selection.anchorNode;

            button.classList.toggle(
                "active",
                Boolean(getFormatAncestor(node, "code"))
            );

            return;
        }

        const command = commandMap[format];

        if (command) {
            button.classList.toggle(
                "active",
                document.queryCommandState(command)
            );
        }
    });
}

// ==========================================
// 4. CODE FORMATTING HELPERS
// ==========================================

function unwrapCode(code) {
    if (!code || !code.parentNode) return;

    const parent = code.parentNode;

    while (code.firstChild) {
        parent.insertBefore(code.firstChild, code);
    }

    parent.removeChild(code);
}

function getSelectedCodeElements(range) {
    const codes = Array.from(
        editor.querySelectorAll("code")
    );

    return codes.filter(code => {
        try {
            return range.intersectsNode(code);
        } catch (error) {
            return false;
        }
    });
}

function isEntireElementSelected(range, element) {
    const elementRange = document.createRange();

    elementRange.selectNodeContents(element);

    return (
        range.compareBoundaryPoints(
            Range.START_TO_START,
            elementRange
        ) <= 0 &&
        range.compareBoundaryPoints(
            Range.END_TO_END,
            elementRange
        ) >= 0
    );
}

// ==========================================
// 5. INLINE CODE FORMATTING
// ==========================================

function applyCode() {

    if (!restoreSelection()) return;

    const selection = window.getSelection();

    if (!selection || selection.isCollapsed) return;

    const range = selection.getRangeAt(0);

    if (!editor.contains(range.commonAncestorContainer)) return;

    if (!range.toString().trim()) return;

    const selectedCodes = getSelectedCodeElements(range);

    // If selected text is already inside Code,
    // remove the Code formatting.
    if (selectedCodes.length > 0) {

        const startCode = getFormatAncestor(
            range.startContainer,
            "code"
        );

        const endCode = getFormatAncestor(
            range.endContainer,
            "code"
        );

        if (
            startCode &&
            startCode === endCode &&
            isEntireElementSelected(range, startCode)
        ) {

            unwrapCode(startCode);

        } else if (
            startCode &&
            startCode === endCode
        ) {

            // For partial selection, unwrap the
            // containing Code element.
            unwrapCode(startCode);

        } else {

            // Unwrap every fully selected Code element.
            selectedCodes.forEach(code => {

                if (isEntireElementSelected(range, code)) {
                    unwrapCode(code);
                }

            });

        }

        savedRange = null;

        updatePreview();
        updateToolbar();

        return;
    }

    // Apply Code formatting.
    const code = document.createElement("code");

    try {

        range.surroundContents(code);

    } catch (error) {

        const fragment = range.extractContents();

        code.appendChild(fragment);

        range.insertNode(code);

    }

    const newRange = document.createRange();

    newRange.selectNodeContents(code);

    selection.removeAllRanges();

    selection.addRange(newRange);

    savedRange = newRange.cloneRange();

    updatePreview();
    updateToolbar();
}

// ==========================================
// 6. APPLY FORMATTING
// ==========================================

function applyFormat(format) {

    if (format === "code") {
        applyCode();
        return;
    }

    const command = commandMap[format];

    if (!command || !restoreSelection()) return;

    const selection = window.getSelection();

    if (!selection || selection.isCollapsed) return;

    if (!isSelectionInsideEditor(selection)) return;

    editor.focus();

    if (!restoreSelection()) return;

    document.execCommand(command, false, null);

    saveSelection();

    updatePreview();
    updateToolbar();
}

// ==========================================
// 7. TOOLBAR EVENTS
// ==========================================

toolbarButtons.forEach(button => {

    button.addEventListener("mousedown", event => {
        event.preventDefault();
        saveSelection();
    });

    button.addEventListener("click", () => {
        applyFormat(button.dataset.format);
    });

});

// ==========================================
// 8. MARKDOWN CONVERSION
// ==========================================

function convertToMarkdown(node) {

    if (node.nodeType === Node.TEXT_NODE) {
        return node.nodeValue.replace(/\u00a0/g, " ");
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
        return "";
    }

    const tag = node.tagName.toLowerCase();

    if (tag === "br") return "\n";

    const inner = Array.from(node.childNodes)
        .map(convertToMarkdown)
        .join("");

    switch (tag) {

        case "b":
        case "strong":
            return `*${inner}*`;

        case "i":
        case "em":
            return `_${inner}_`;

        case "s":
        case "strike":
        case "del":
            return `~${inner}~`;

        case "code":
            return `\`${inner}\``;

        case "div":
        case "p":
            return inner + "\n";

        default:
            return inner;
    }
}

function getMarkdown() {

    return Array.from(editor.childNodes)
        .map(convertToMarkdown)
        .join("")
        .replace(/\n{3,}/g, "\n\n")
        .replace(/\n+$/, "");
}

// ==========================================
// 9. SAFE PREVIEW
// ==========================================

function createSafePreview(node) {

    if (node.nodeType === Node.TEXT_NODE) {
        return document.createTextNode(node.nodeValue);
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
        return document.createDocumentFragment();
    }

    const tag = node.tagName.toUpperCase();

    if (!allowedPreviewTags.includes(tag)) {

        const fragment = document.createDocumentFragment();

        node.childNodes.forEach(child => {
            fragment.appendChild(createSafePreview(child));
        });

        return fragment;
    }

    const element = document.createElement(tag.toLowerCase());

    node.childNodes.forEach(child => {
        element.appendChild(createSafePreview(child));
    });

    return element;
}

// ==========================================
// 10. LIVE PREVIEW
// ==========================================

function updatePreview() {

    const markdown = getMarkdown();

    contentField.value = markdown;

    preview.replaceChildren();

    if (!editor.textContent.trim()) {

        preview.textContent = "Your preview will appear here.";

        return;
    }

    editor.childNodes.forEach(node => {
        preview.appendChild(createSafePreview(node));
    });
}

// ==========================================
// 11. EDITOR EVENTS
// ==========================================

editor.addEventListener("input", () => {

    updatePreview();

    saveSelection();

    updateToolbar();

});

editor.addEventListener("keyup", () => {

    saveSelection();

    updateToolbar();

});

editor.addEventListener("mouseup", () => {

    saveSelection();

    updateToolbar();

});

editor.addEventListener("focus", saveSelection);

document.addEventListener("selectionchange", () => {

    const selection = window.getSelection();

    if (isSelectionInsideEditor(selection)) {

        saveSelection();

        updateToolbar();

    }

});

// ==========================================
// 12. PASTE PLAIN TEXT
// ==========================================

editor.addEventListener("paste", event => {

    event.preventDefault();

    const text = event.clipboardData.getData("text/plain");

    const selection = window.getSelection();

    if (!selection || !selection.rangeCount) return;

    const range = selection.getRangeAt(0);

    if (!editor.contains(range.commonAncestorContainer)) return;

    range.deleteContents();

    const textNode = document.createTextNode(text);

    range.insertNode(textNode);

    range.setStartAfter(textNode);

    range.collapse(true);

    selection.removeAllRanges();

    selection.addRange(range);

    savedRange = range.cloneRange();

    updatePreview();

    updateToolbar();

});

// ==========================================
// 13. FORM SUBMISSION
// ==========================================

form.addEventListener("submit", event => {

    updatePreview();

    if (!contentField.value.trim()) {

        event.preventDefault();

        alert("Please enter some text.");

        editor.focus();

    }

});

// ==========================================
// 14. INITIALIZE
// ==========================================

updatePreview();
