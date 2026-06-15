<?php
// playground.php - HTML/CSS/JS Live Editor (Logged-in users only)
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Restrict access to logged-in users only
requireLogin();

// If you want to allow guests to view but not save, remove requireLogin().
// But the requirement says: "only accessible to logged in users".

$pageTitle = 'Live HTML/CSS/JS Playground';
include 'includes/header.php';
?>
<style>
    /* Additional styles for the playground */
    .playground-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-top: 20px;
    }
    .editors-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    .editor-panel {
        flex: 1;
        min-width: 250px;
        background: rgba(10, 25, 47, 0.6);
        border-radius: 10px;
        border: 1px solid rgba(100, 255, 218, 0.2);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .editor-header {
        background: rgba(100, 255, 218, 0.1);
        padding: 10px 15px;
        border-bottom: 1px solid rgba(100, 255, 218, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .editor-header h3 {
        color: #64ffda;
        font-size: 1rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .editor-header button {
        background: none;
        border: none;
        color: #64ffda;
        cursor: pointer;
        font-size: 0.9rem;
        transition: 0.2s;
    }
    .editor-header button:hover {
        color: #ffffff;
    }
    textarea.code-editor {
        width: 100%;
        min-height: 250px;
        background: #0a192f;
        color: #e6f1ff;
        border: none;
        padding: 15px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        line-height: 1.5;
        resize: vertical;
        tab-size: 4;
        outline: none;
    }
    .preview-panel {
        background: rgba(10, 25, 47, 0.6);
        border-radius: 10px;
        border: 1px solid rgba(100, 255, 218, 0.2);
        overflow: hidden;
    }
    .preview-header {
        background: rgba(100, 255, 218, 0.1);
        padding: 10px 15px;
        border-bottom: 1px solid rgba(100, 255, 218, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .preview-header h3 {
        color: #64ffda;
        font-size: 1rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .preview-header .btn-group {
        display: flex;
        gap: 10px;
    }
    .btn-small {
        background: rgba(100, 255, 218, 0.2);
        border: 1px solid #64ffda;
        padding: 5px 12px;
        border-radius: 5px;
        color: #64ffda;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.8rem;
    }
    .btn-small:hover {
        background: #64ffda;
        color: #0a192f;
    }
    iframe {
        width: 100%;
        height: 400px;
        border: none;
        background: white;
    }
    .action-buttons {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 10px;
    }
    @media (max-width: 992px) {
        .editors-row {
            flex-direction: column;
        }
        textarea.code-editor {
            min-height: 200px;
        }
        iframe {
            height: 300px;
        }
    }
</style>

<div class="playground-container">
    <div class="editors-row">
        <!-- HTML Editor -->
        <div class="editor-panel">
            <div class="editor-header">
                <h3><i class="fab fa-html5"></i> HTML</h3>
                <button onclick="resetEditor('html')" title="Reset"><i class="fas fa-undo-alt"></i></button>
            </div>
            <textarea id="htmlEditor" class="code-editor" placeholder="<div>Your HTML here</div>"></textarea>
        </div>
        <!-- CSS Editor -->
        <div class="editor-panel">
            <div class="editor-header">
                <h3><i class="fab fa-css3-alt"></i> CSS</h3>
                <button onclick="resetEditor('css')" title="Reset"><i class="fas fa-undo-alt"></i></button>
            </div>
            <textarea id="cssEditor" class="code-editor" placeholder="body { background: #f0f0f0; }"></textarea>
        </div>
        <!-- JavaScript Editor -->
        <div class="editor-panel">
            <div class="editor-header">
                <h3><i class="fab fa-js"></i> JavaScript</h3>
                <button onclick="resetEditor('js')" title="Reset"><i class="fas fa-undo-alt"></i></button>
            </div>
            <textarea id="jsEditor" class="code-editor" placeholder="console.log('Hello');"></textarea>
        </div>
    </div>

    <div class="preview-panel">
        <div class="preview-header">
            <h3><i class="fas fa-eye"></i> Live Preview</h3>
            <div class="btn-group">
                <button class="btn-small" onclick="updatePreview()"><i class="fas fa-play"></i> Run</button>
                <button class="btn-small" onclick="downloadCode()"><i class="fas fa-download"></i> Download HTML</button>
                <button class="btn-small" onclick="copyCode()"><i class="fas fa-copy"></i> Copy HTML</button>
            </div>
        </div>
        <iframe id="previewFrame" title="Live Preview" sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-modals"></iframe>
    </div>

    <div class="action-buttons">
        <button class="btn" onclick="loadExample()"><i class="fas fa-file-alt"></i> Load Example</button>
        <button class="btn secondary" onclick="clearAll()"><i class="fas fa-trash"></i> Clear All</button>
    </div>
</div>

<script>
    // Default example
    const defaultHTML = `<div class="container">
    <h1>Hello, Developer!</h1>
    <p>This is a live HTML/CSS/JS editor.</p>
    <button id="clickBtn">Click me</button>
    <div id="output"></div>
</div>`;

    const defaultCSS = `body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    margin: 0;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}
.container {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    text-align: center;
    max-width: 500px;
}
h1 {
    color: #333;
}
button {
    background: #667eea;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 10px;
}
button:hover {
    background: #5a67d8;
}
#output {
    margin-top: 15px;
    font-weight: bold;
}`;

    const defaultJS = `document.getElementById('clickBtn').addEventListener('click', function() {
    document.getElementById('output').innerHTML = 'Button clicked at ' + new Date().toLocaleTimeString();
});
console.log('JavaScript loaded');`;

    // Load defaults or localStorage saved content
    function loadSaved() {
        const savedHTML = localStorage.getItem('playground_html');
        const savedCSS = localStorage.getItem('playground_css');
        const savedJS = localStorage.getItem('playground_js');
        document.getElementById('htmlEditor').value = savedHTML || defaultHTML;
        document.getElementById('cssEditor').value = savedCSS || defaultCSS;
        document.getElementById('jsEditor').value = savedJS || defaultJS;
        updatePreview();
    }

    function updatePreview() {
        const html = document.getElementById('htmlEditor').value;
        const css = document.getElementById('cssEditor').value;
        const js = document.getElementById('jsEditor').value;

        // Save to localStorage
        localStorage.setItem('playground_html', html);
        localStorage.setItem('playground_css', css);
        localStorage.setItem('playground_js', js);

        const combined = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>${css}</style>
            </head>
            <body>
                ${html}
                <script>${js}<\/script>
            </body>
            </html>
        `;
        const iframe = document.getElementById('previewFrame');
        iframe.srcdoc = combined;
    }

    function resetEditor(type) {
        if (type === 'html') document.getElementById('htmlEditor').value = defaultHTML;
        else if (type === 'css') document.getElementById('cssEditor').value = defaultCSS;
        else if (type === 'js') document.getElementById('jsEditor').value = defaultJS;
        updatePreview();
    }

    function clearAll() {
        document.getElementById('htmlEditor').value = '';
        document.getElementById('cssEditor').value = '';
        document.getElementById('jsEditor').value = '';
        updatePreview();
    }

    function downloadCode() {
        const html = document.getElementById('htmlEditor').value;
        const css = document.getElementById('cssEditor').value;
        const js = document.getElementById('jsEditor').value;
        const full = `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Playground Export</title>
    <style>${css}</style>
</head>
<body>
    ${html}
    <script>${js}<\/script>
</body>
</html>`;
        const blob = new Blob([full], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'playground_export.html';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function copyCode() {
        const html = document.getElementById('htmlEditor').value;
        const css = document.getElementById('cssEditor').value;
        const js = document.getElementById('jsEditor').value;
        const full = `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Playground Export</title>
    <style>${css}</style>
</head>
<body>
    ${html}
    <script>${js}<\/script>
</body>
</html>`;
        navigator.clipboard.writeText(full).then(() => {
            alert('HTML code copied to clipboard!');
        }).catch(() => {
            alert('Failed to copy');
        });
    }

    function loadExample() {
        // You can load another example if desired, or just reset to defaults
        resetEditor('html');
        resetEditor('css');
        resetEditor('js');
    }

    // Auto-update on input (debounced)
    let timeout;
    function autoUpdate() {
        clearTimeout(timeout);
        timeout = setTimeout(() => updatePreview(), 500);
    }

    document.getElementById('htmlEditor').addEventListener('input', autoUpdate);
    document.getElementById('cssEditor').addEventListener('input', autoUpdate);
    document.getElementById('jsEditor').addEventListener('input', autoUpdate);

    // Initial load
    loadSaved();
</script>

<?php include 'includes/footer.php'; ?>