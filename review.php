<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php?error=' . urlencode('Invalid document ID.'));
    exit;
}

$documentId = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $documentId);
$stmt->execute();
$documentResult = $stmt->get_result();

if (!$documentResult || $documentResult->num_rows === 0) {
    header('Location: dashboard.php?error=' . urlencode('Document not found.'));
    exit;
}

$document = $documentResult->fetch_assoc();
$stmt->close();

$itemStmt = $conn->prepare("SELECT * FROM document_line_items WHERE document_id = ? ORDER BY id ASC");
$itemStmt->bind_param("i", $documentId);
$itemStmt->execute();
$itemsResult = $itemStmt->get_result();

$lineItems = [];

while ($item = $itemsResult->fetch_assoc()) {
    $lineItems[] = $item;
}

$itemStmt->close();

if (empty($lineItems)) {
    $fallbackAmount = 0;

    if (!empty($document['subtotal']) && (float)$document['subtotal'] > 0) {
        $fallbackAmount = (float)$document['subtotal'];
    } elseif (!empty($document['total']) && (float)$document['total'] > 0) {
        $fallbackAmount = (float)$document['total'];
    }

    if ($fallbackAmount > 0) {
        $lineItems[] = [
            'id' => 0,
            'description' => 'Document total',
            'quantity' => 1,
            'unit_price' => $fallbackAmount,
            'line_total' => $fallbackAmount
        ];
    }
}

$issues = json_decode($document['issues'] ?? '[]', true);

if (!is_array($issues)) {
    $issues = [];
}

$statusRaw = strtolower(trim($document['status'] ?? 'review'));
$statusClass = str_replace(' ', '-', $statusRaw);
$statusLabel = $document['status'] ?? 'review';

$isImage = in_array(strtolower($document['file_type'] ?? ''), ['jpg', 'jpeg', 'png', 'webp'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DocFlow - Review</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style/pocetna.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="style/review.css?v=<?php echo time(); ?>">
</head>

<body>

<div class="layout">

<?php include 'includes/header.php'; ?>

<main class="content">

<div class="topline">
    <div>
        <p class="label">Document Review</p>
        <h1>Review extracted data</h1>
        <p class="subtitle">Check extracted fields, fix validation issues and confirm the final document.</p>
    </div>

    <a href="dashboard.php" class="plain-link">Back to dashboard</a>
</div>

<div class="review-summary">
    <div>
        <span>File</span>
        <strong><?= htmlspecialchars($document['original_name'] ?? '') ?></strong>
    </div>

    <div>
        <span>Status</span>
        <strong>
            <span class="status-badge <?= htmlspecialchars($statusClass) ?>">
                <?= htmlspecialchars($statusLabel) ?>
            </span>
        </strong>
    </div>

    <div>
        <span>Uploaded</span>
        <strong><?= htmlspecialchars($document['created_at'] ?? '') ?></strong>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<section class="success-box">
    Data saved. Validation was run again.
</section>
<?php endif; ?>

<?php if (!empty($issues)): ?>
<section class="issues-box">
    <h2>Validation issues</h2>
    <ul>
        <?php foreach ($issues as $issue): ?>
            <li><?= htmlspecialchars($issue) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php else: ?>
<section class="success-box">
    No validation issues detected.
</section>
<?php endif; ?>

<?php if ($isImage): ?>
<section class="review-card raw-card">
    <div class="review-card-head">
        <h2>Uploaded image</h2>
        <p>This image is shown here so the document data can be reviewed and entered manually.</p>
    </div>

    <img
        src="<?= htmlspecialchars($document['file_path'] ?? '') ?>"
        alt="Uploaded document"
        style="width:100%;max-height:650px;object-fit:contain;border-radius:12px;border:1px solid #334155;background:#111827;"
    >
</section>
<?php endif; ?>

<form action="actions/save_review.php" method="POST">

<input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>">

<section class="review-grid">

<div class="review-card">
    <div class="review-card-head">
        <h2>Document details</h2>
        <p>Main extracted fields.</p>
    </div>

    <div class="form-grid">
        <div class="field">
            <label>Document type</label>
            <select name="document_type">
                <option value="unknown" <?= ($document['document_type'] ?? '') === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                <option value="invoice" <?= ($document['document_type'] ?? '') === 'invoice' ? 'selected' : '' ?>>Invoice</option>
                <option value="purchase_order" <?= ($document['document_type'] ?? '') === 'purchase_order' ? 'selected' : '' ?>>Purchase Order</option>
            </select>
        </div>

        <div class="field">
            <label>Supplier / Company</label>
            <input type="text" name="supplier_name" value="<?= htmlspecialchars($document['supplier_name'] ?? '') ?>">
        </div>

        <div class="field">
            <label>Document number</label>
            <input type="text" name="document_number" value="<?= htmlspecialchars($document['document_number'] ?? '') ?>">
        </div>

        <div class="field">
            <label>Currency</label>
            <input type="text" name="currency" value="<?= htmlspecialchars($document['currency'] ?? 'BAM') ?>" placeholder="BAM">
        </div>

        <div class="field">
            <label>Issue date</label>
            <input type="date" name="issue_date" value="<?= htmlspecialchars($document['issue_date'] ?? '') ?>">
        </div>

        <div class="field">
            <label>Due date</label>
            <input type="date" name="due_date" value="<?= htmlspecialchars($document['due_date'] ?? '') ?>">
        </div>
    </div>
</div>

<div class="review-card">
    <div class="review-card-head">
        <h2>Totals</h2>
        <p>Totals update automatically when line items are changed.</p>
    </div>

    <div class="form-grid one-column">
        <div class="field">
            <label>Subtotal</label>
            <input type="number" step="0.01" name="subtotal" value="<?= htmlspecialchars($document['subtotal'] ?? '0') ?>">
        </div>

        <div class="field">
            <label>Tax</label>
            <input type="number" step="0.01" name="tax" value="<?= htmlspecialchars($document['tax'] ?? '0') ?>">
        </div>

        <div class="field">
            <label>Total</label>
            <input type="number" step="0.01" name="total" value="<?= htmlspecialchars($document['total'] ?? '0') ?>">
        </div>
    </div>
</div>

</section>

<section class="review-card line-items-card">
    <div class="review-card-head">
        <h2>Line items</h2>
        <p>Quantity and unit price automatically calculate line total, subtotal and total.</p>
    </div>

    <div class="items-table-wrapper">
        <table class="items-table" id="itemsTable">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit price</th>
                    <th>Line total</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($lineItems as $index => $item): ?>
                <tr>
                    <td>
                        <input type="hidden" name="items[<?= $index ?>][id]" value="<?= (int)($item['id'] ?? 0) ?>">
                        <input type="text" name="items[<?= $index ?>][description]" value="<?= htmlspecialchars($item['description'] ?? '') ?>">
                    </td>

                    <td>
                        <input type="number" step="0.01" name="items[<?= $index ?>][quantity]" value="<?= htmlspecialchars($item['quantity'] ?? '0') ?>">
                    </td>

                    <td>
                        <input type="number" step="0.01" name="items[<?= $index ?>][unit_price]" value="<?= htmlspecialchars($item['unit_price'] ?? '0') ?>">
                    </td>

                    <td>
                        <input type="number" step="0.01" name="items[<?= $index ?>][line_total]" value="<?= htmlspecialchars($item['line_total'] ?? '0') ?>">
                    </td>

                    <td>
                        <button type="button" class="remove-row">Remove</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <button type="button" class="secondary-btn" id="addItemBtn">Add line item</button>
</section>

<section class="review-card raw-card">
    <div class="review-card-head">
        <h2>Raw extracted text</h2>
        <p>Original extracted content from document.</p>
    </div>

    <textarea readonly rows="10"><?= htmlspecialchars($document['raw_text'] ?? '') ?></textarea>
</section>

<div class="review-actions">
    <a href="dashboard.php" class="secondary-link">Cancel</a>
    <button type="submit" class="primary-btn">Save</button>
</div>

</form>

</main>

</div>

<script>
let itemIndex = <?= count($lineItems) ?>;

const subtotalInput = document.querySelector('input[name="subtotal"]');
const taxInput = document.querySelector('input[name="tax"]');
const totalInput = document.querySelector('input[name="total"]');

function toNumber(value) {
    value = String(value || '').replace(',', '.');
    const number = parseFloat(value);
    return isNaN(number) ? 0 : number;
}

function formatMoney(value) {
    return toNumber(value).toFixed(2);
}

function recalculateTotalsFromRows() {
    let subtotal = 0;

    document.querySelectorAll('#itemsTable tbody tr').forEach(function (row) {
        const lineTotalInput = row.querySelector('input[name*="[line_total]"]');
        subtotal += toNumber(lineTotalInput.value);
    });

    const tax = toNumber(taxInput.value);

    subtotalInput.value = formatMoney(subtotal);
    totalInput.value = formatMoney(subtotal + tax);
}

function recalculateTotalFromSubtotal() {
    const subtotal = toNumber(subtotalInput.value);
    const tax = toNumber(taxInput.value);

    totalInput.value = formatMoney(subtotal + tax);
}

function recalculateRow(row) {
    if (!row) return;

    const qtyInput = row.querySelector('input[name*="[quantity]"]');
    const priceInput = row.querySelector('input[name*="[unit_price]"]');
    const lineTotalInput = row.querySelector('input[name*="[line_total]"]');

    const qty = toNumber(qtyInput.value);
    const price = toNumber(priceInput.value);

    lineTotalInput.value = formatMoney(qty * price);

    recalculateTotalsFromRows();
}

document.getElementById('addItemBtn').addEventListener('click', function () {
    const tbody = document.querySelector('#itemsTable tbody');
    const row = document.createElement('tr');

    row.innerHTML = `
        <td>
            <input type="hidden" name="items[${itemIndex}][id]" value="">
            <input type="text" name="items[${itemIndex}][description]" value="">
        </td>
        <td>
            <input type="number" step="0.01" name="items[${itemIndex}][quantity]" value="1">
        </td>
        <td>
            <input type="number" step="0.01" name="items[${itemIndex}][unit_price]" value="0">
        </td>
        <td>
            <input type="number" step="0.01" name="items[${itemIndex}][line_total]" value="0.00">
        </td>
        <td>
            <button type="button" class="remove-row">Remove</button>
        </td>
    `;

    tbody.appendChild(row);
    itemIndex++;
    recalculateTotalsFromRows();
});

document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('remove-row')) return;

    const tbody = document.querySelector('#itemsTable tbody');
    const rows = tbody.querySelectorAll('tr');

    if (rows.length > 1) {
        e.target.closest('tr').remove();
    } else {
        const row = e.target.closest('tr');

        row.querySelector('input[type="hidden"]').value = '';
        row.querySelector('input[type="text"]').value = '';
        row.querySelector('input[name*="[quantity]"]').value = '0';
        row.querySelector('input[name*="[unit_price]"]').value = '0';
        row.querySelector('input[name*="[line_total]"]').value = '0.00';
    }

    recalculateTotalsFromRows();
});

document.addEventListener('input', function (e) {
    if (!e.target.name) return;

    if (
        e.target.name.includes('[quantity]') ||
        e.target.name.includes('[unit_price]')
    ) {
        recalculateRow(e.target.closest('tr'));
        return;
    }

    if (e.target.name.includes('[line_total]')) {
        recalculateTotalsFromRows();
        return;
    }

    if (e.target.name === 'tax') {
        recalculateTotalFromSubtotal();
        return;
    }

    if (e.target.name === 'subtotal') {
        recalculateTotalFromSubtotal();
        return;
    }
});
</script>

</body>
</html>