<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DocFlow - Upload</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style/pocetna.css?v=<?php echo time(); ?>">


</head>

<body>

<div class="layout">

<?php include 'includes/header.php'; ?>

<main class="content">

<div class="topline">
    <div>
        <p class="label">Smart Document Processing</p>
        <h1>Upload document</h1>
        <p class="subtitle">
            Upload your document and let the system extract key information,
            check for possible issues and prepare it for final review.
        </p>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="dashboard.php" class="plain-link">View dashboard</a>
        <button type="button" class="api-open-btn" id="openApiModal">API upload</button>
    </div>
</div>

<?php if (isset($_GET['error'])): ?>
<section class="issues-box">
    <h2>Upload error</h2>
    <ul>
        <li><?php echo htmlspecialchars($_GET['error']); ?></li>
    </ul>
</section>
<?php endif; ?>

<div class="page-grid">

<section class="card upload-card">
    <div class="card-head">
        <h2>Upload document</h2>
        <p>Select a file to start processing.</p>
    </div>

    <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data">
        <label class="upload-area" for="document">
            <input 
                type="file" 
                name="document" 
                id="document" 
                accept=".pdf,.csv,.txt,.jpg,.jpeg,.png,.webp"
                required
            >
            <span>Choose file</span>
            <small>No file selected</small>
        </label>

        <div style="margin-bottom:16px;color:#9ca3af;font-size:13px;">
            PDF, CSV, TXT and image files are supported (up to 10MB).
        </div>

        <button type="submit" class="primary-btn" id="uploadBtn">
            Upload and process
        </button>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <h2>How it works</h2>
        <p>Each document goes through a simple processing flow.</p>
    </div>

    <div class="steps">
        <div class="step">
            <b>Upload</b>
            <span>The file is uploaded and prepared for processing.</span>
        </div>

        <div class="step">
            <b>Automatic checks</b>
            <span>The system extracts data and checks totals, dates and required fields.</span>
        </div>

        <div class="step">
            <b>Review</b>
            <span>If something is missing or incorrect, you can easily adjust it.</span>
        </div>

        <div class="step">
            <b>Final decision</b>
            <span>Once everything is correct, the document is finalized.</span>
        </div>
    </div>
</section>

</div>

<section class="info-row">
    <div>
        <strong>Extracted data</strong>
        <p>Supplier, document number, dates, currency, totals and line items.</p>
    </div>

    <div>
        <strong>Automatic validation</strong>
        <p>The system detects missing fields, incorrect totals and duplicate entries.</p>
    </div>

    <div>
        <strong>Image support</strong>
        <p>Image files can be uploaded and reviewed when needed.</p>
    </div>
</section>

<section class="info-row">
    <div>
        <strong>API integration</strong>
        <p>Documents can also be uploaded directly through the API.</p>
    </div>

    <div>
        <strong>Status tracking</strong>
        <p>Each document moves through clear steps so you always know its state.</p>
    </div>

    <div>
        <strong>Full control</strong>
        <p>You can review and adjust data before confirming the final result.</p>
    </div>
</section>

</main>

</div>

<div class="api-modal-overlay" id="apiModal">
    <div class="api-modal">
        <div class="api-modal-head">
            <div>
                <h2>API upload</h2>
                <p>Use this endpoint when you want to send a document from another system.</p>
            </div>

            <button type="button" class="api-close" id="closeApiModal">Close</button>
        </div>

        <div class="api-modal-body">
            <ul class="api-list">
                <li>Send a <strong>POST</strong> request.</li>
                <li>Use <strong>form-data</strong>.</li>
                <li>The file field must be named <strong>document</strong>.</li>
            </ul>

<pre class="api-box">POST https://projekat.kupidres.com/api/upload_document.php

Field:
document = your file</pre>

            <p style="color:#9ca3af;font-size:13px;margin:14px 0 8px;">
                Example with cURL:
            </p>

<pre class="api-box">curl -X POST \
  -F "document=@invoice.pdf" \
  https://projekat.kupidres.com/api/upload_document.php</pre>

            <p style="color:#9ca3af;font-size:13px;margin:14px 0 8px;">
                The API returns JSON like this:
            </p>

<pre class="api-box">{
  "success": true,
  "message": "Document uploaded successfully.",
  "data": {
    "document_id": 25,
    "status": "Needs Review"
  }
}</pre>
        </div>
    </div>
</div>

<div id="loaderOverlay">
    <div class="loader-spinner"></div>
    <div class="loader-title">Processing document...</div>
    <div class="loader-text">Please wait while the file is uploaded and analyzed.</div>
</div>

<script>
const input = document.getElementById('document');
const small = document.querySelector('.upload-area small');
const uploadForm = document.getElementById('uploadForm');
const uploadBtn = document.getElementById('uploadBtn');
const loaderOverlay = document.getElementById('loaderOverlay');

input.addEventListener('change', function () {
    small.textContent = this.files.length ? this.files[0].name : 'No file selected';
});

uploadForm.addEventListener('submit', function () {
    loaderOverlay.classList.add('active');

    uploadBtn.disabled = true;
    uploadBtn.innerText = 'Processing...';
});

const apiModal = document.getElementById('apiModal');
const openApiModal = document.getElementById('openApiModal');
const closeApiModal = document.getElementById('closeApiModal');

openApiModal.addEventListener('click', function () {
    apiModal.classList.add('active');
});

closeApiModal.addEventListener('click', function () {
    apiModal.classList.remove('active');
});

apiModal.addEventListener('click', function (e) {
    if (e.target === apiModal) {
        apiModal.classList.remove('active');
    }
});
</script>

</body>
</html>