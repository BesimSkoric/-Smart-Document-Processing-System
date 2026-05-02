<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php?error=' . urlencode('Invalid document ID.'));
    exit;
}

$documentId = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT
        id,
        original_name,
        document_type,
        supplier_name,
        document_number,
        issue_date,
        due_date,
        currency,
        subtotal,
        tax,
        total,
        status,
        issues,
        created_at
    FROM documents
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die('SQL error: ' . $conn->error);
}

$stmt->bind_param("i", $documentId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    header('Location: dashboard.php?error=' . urlencode('Document not found.'));
    exit;
}

$document = $result->fetch_assoc();
$stmt->close();

$lineItems = [];

$itemStmt = $conn->prepare("
    SELECT
        id,
        description,
        quantity,
        unit_price,
        line_total
    FROM document_line_items
    WHERE document_id = ?
    ORDER BY id ASC
");

if ($itemStmt) {
    $itemStmt->bind_param("i", $documentId);
    $itemStmt->execute();
    $itemsResult = $itemStmt->get_result();

    while ($item = $itemsResult->fetch_assoc()) {
        $lineItems[] = $item;
    }

    $itemStmt->close();
}

$issues = json_decode($document['issues'] ?? '[]', true);

if (!is_array($issues)) {
    $issues = [];
}

$statusRaw = strtolower(trim($document['status'] ?? ''));
$statusLabel = $document['status'] ?? '';
$statusClass = str_replace(' ', '-', $statusRaw);

if ($statusRaw === 'uploaded' || $statusRaw === 'accepted') {
    $statusLabel = 'Accepted';
    $statusClass = 'accepted';
}

if ($statusRaw === 'review') {
    $statusLabel = 'Needs Review';
    $statusClass = 'needs-review';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DocFlow - View Document</title>
<link rel="stylesheet" href="style/pocetna.css?v=<?= time(); ?>">
<link rel="stylesheet" href="style/review.css?v=<?= time(); ?>">
</head>

<body>

<div class="layout">

<?php include 'includes/header.php'; ?>

<main class="content">

<div class="topline">
    <div>
        <p class="label">Document View</p>
        <h1>View document</h1>
        <p class="subtitle">This document is locked and can only be viewed.</p>
    </div>

    <a href="dashboard.php" class="plain-link">Back to dashboard</a>
</div>

<div class="review-summary">
    <div>
        <span>File</span>
        <strong><?= htmlspecialchars($document['original_name'] ?? '-') ?></strong>
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
        <span>Created</span>
        <strong><?= htmlspecialchars($document['created_at'] ?? '-') ?></strong>
    </div>
</div>

<section class="info-box">
    This document is locked and cannot be edited.
</section>

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

<section class="review-grid">

<div class="review-card readonly">
    <div class="review-card-head">
        <h2>Document details</h2>
        <p>Main extracted fields.</p>
    </div>

    <div class="form-grid">
        <div class="field">
            <label>Document type</label>
            <input type="text" value="<?= htmlspecialchars($document['document_type'] ?? 'unknown') ?>" readonly>
        </div>

        <div class="field">
            <label>Supplier / Company</label>
            <input type="text" value="<?= htmlspecialchars($document['supplier_name'] ?? '') ?>" readonly>
        </div>

        <div class="field">
            <label>Document number</label>
            <input type="text" value="<?= htmlspecialchars($document['document_number'] ?? '') ?>" readonly>
        </div>

        <div class="field">
            <label>Currency</label>
            <input type="text" value="<?= htmlspecialchars($document['currency'] ?? 'BAM') ?>" readonly>
        </div>

        <div class="field">
            <label>Issue date</label>
            <input type="date" value="<?= htmlspecialchars($document['issue_date'] ?? '') ?>" readonly>
        </div>

        <div class="field">
            <label>Due date</label>
            <input type="date" value="<?= htmlspecialchars($document['due_date'] ?? '') ?>" readonly>
        </div>
    </div>
</div>

<div class="review-card readonly">
    <div class="review-card-head">
        <h2>Totals</h2>
        <p>Document calculations.</p>
    </div>

    <div class="form-grid one-column">
        <div class="field">
            <label>Subtotal</label>
            <input type="number" step="0.01" value="<?= htmlspecialchars($document['subtotal'] ?? '0') ?>" readonly>
        </div>

        <div class="field">
            <label>Tax</label>
            <input type="number" step="0.01" value="<?= htmlspecialchars($document['tax'] ?? '0') ?>" readonly>
        </div>

        <div class="field">
            <label>Total</label>
            <input type="number" step="0.01" value="<?= htmlspecialchars($document['total'] ?? '0') ?>" readonly>
        </div>
    </div>
</div>

</section>

<section class="review-card line-items-card readonly">
    <div class="review-card-head">
        <h2>Line items</h2>
        <p>Products or services from this document.</p>
    </div>

    <div class="items-table-wrapper">
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit price</th>
                    <th>Line total</th>
                </tr>
            </thead>

            <tbody>
            <?php if (!empty($lineItems)): ?>
                <?php foreach ($lineItems as $item): ?>
                <tr>
                    <td>
                        <input type="text" value="<?= htmlspecialchars($item['description'] ?? '') ?>" readonly>
                    </td>
                    <td>
                        <input type="number" step="0.01" value="<?= htmlspecialchars($item['quantity'] ?? '0') ?>" readonly>
                    </td>
                    <td>
                        <input type="number" step="0.01" value="<?= htmlspecialchars($item['unit_price'] ?? '0') ?>" readonly>
                    </td>
                    <td>
                        <input type="number" step="0.01" value="<?= htmlspecialchars($item['line_total'] ?? '0') ?>" readonly>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No line items found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="review-actions">
    <a href="dashboard.php" class="secondary-link">Back</a>
</div>

</main>

</div>

</body>
</html>