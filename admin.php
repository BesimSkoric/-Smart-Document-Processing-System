<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'config.php';

/* ===============================
   TOTALS BY CURRENCY - ALL DOCUMENTS
================================ */
$currencyTotals = [];

$currencyStmt = $conn->prepare("
    SELECT 
        currency,
        SUM(total) AS total_sum,
        COUNT(*) AS total_docs
    FROM documents
    GROUP BY currency
    ORDER BY currency ASC
");

if ($currencyStmt) {
    $currencyStmt->execute();
    $currencyResult = $currencyStmt->get_result();

    while ($row = $currencyResult->fetch_assoc()) {
        $currencyTotals[] = $row;
    }

    $currencyStmt->close();
}

/* ===============================
   STATUS COUNTS - ALL DOCUMENTS
================================ */
$statusCounts = [
    'validated' => 0,
    'accepted' => 0,
    'needs review' => 0,
    'rejected' => 0,
    'uploaded' => 0
];

$statusStmt = $conn->prepare("
    SELECT LOWER(status) AS status_key, COUNT(*) AS total
    FROM documents
    GROUP BY LOWER(status)
");

if ($statusStmt) {
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();

    while ($row = $statusResult->fetch_assoc()) {
        $key = strtolower(trim($row['status_key'] ?? ''));

        if ($key !== '') {
            $statusCounts[$key] = (int)$row['total'];
        }
    }

    $statusStmt->close();
}

/* ===============================
   VALIDATED DOCUMENTS FOR ADMIN ACTION
================================ */
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
total,
status,
created_at
FROM documents
WHERE LOWER(status) = 'validated'
ORDER BY created_at DESC
");

$stmt->execute();
$result = $stmt->get_result();

$documents = [];

while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}

$stmt->close();

$validatedCount = count($documents);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Review</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="style/pocetna.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="style/review.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="style/admin.css?v=<?php echo time(); ?>">
</head>

<body class="admin-page">

<main class="admin-main">

<section class="admin-hero">
    <div>
        <span>Document control</span>
        <h1>Admin Review</h1>
        <p>View validated documents and mark them as accepted or rejected.</p>
    </div>

    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
        <a href="dashboard.php" class="admin-back-btn">← Back to client view</a>

        <div class="admin-count-box">
            <span>Pending</span>
            <strong><?php echo $validatedCount; ?></strong>
        </div>
    </div>
</section>

<div class="review-summary admin-summary">
    <div>
        <span>Validated</span>
        <strong><?php echo (int)($statusCounts['validated'] ?? 0); ?></strong>
    </div>

    <div>
        <span>Accepted</span>
        <strong><?php echo (int)($statusCounts['accepted'] ?? 0); ?></strong>
    </div>

    <div>
        <span>Needs Review</span>
        <strong><?php echo (int)($statusCounts['needs review'] ?? 0); ?></strong>
    </div>

    <div>
        <span>Rejected</span>
        <strong><?php echo (int)($statusCounts['rejected'] ?? 0); ?></strong>
    </div>
</div>

<?php if (!empty($currencyTotals)): ?>
<div class="review-summary admin-summary">
    <?php foreach ($currencyTotals as $ct): ?>
        <div>
            <span><?php echo htmlspecialchars($ct['currency'] ?: 'N/A'); ?> Total</span>
            <strong>
                <?php echo number_format((float)$ct['total_sum'], 2); ?>
            </strong>
            <small><?php echo (int)$ct['total_docs']; ?> documents</small>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
<div class="success-box">
<?php echo htmlspecialchars($_GET['success']); ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="issues-box">
<h2>Error</h2>
<ul>
<li><?php echo htmlspecialchars($_GET['error']); ?></li>
</ul>
</div>
<?php endif; ?>

<section class="review-card admin-card">
<div class="review-card-head admin-card-head">
<div>
<h2>Validated Documents</h2>
<p>Only validated documents are shown here for final admin decision.</p>
</div>
</div>

<?php if (empty($documents)): ?>

<div class="admin-empty">
No validated documents waiting for admin decision.
</div>

<?php else: ?>

<div class="items-table-wrapper">
<table class="items-table admin-table">
<thead>
<tr>
<th>Document</th>
<th>Supplier</th>
<th>Number</th>
<th>Issue Date</th>
<th>Total</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>

<tbody>
<?php foreach ($documents as $document): ?>
<tr>

<td>
<strong><?php echo htmlspecialchars($document['original_name'] ?? '-'); ?></strong>
<span class="admin-muted">
<?php echo htmlspecialchars($document['document_type'] ?? 'unknown'); ?>
</span>
</td>

<td><?php echo htmlspecialchars($document['supplier_name'] ?? '-'); ?></td>
<td><?php echo htmlspecialchars($document['document_number'] ?? '-'); ?></td>
<td><?php echo htmlspecialchars($document['issue_date'] ?? '-'); ?></td>

<td>
<?php echo number_format((float)($document['total'] ?? 0), 2); ?>
<?php echo htmlspecialchars($document['currency'] ?? ''); ?>
</td>

<td>
<span class="admin-status admin-status-validated">
<?php echo htmlspecialchars($document['status']); ?>
</span>
</td>

<td>
<div class="admin-actions">

<form action="admin_actions.php" method="POST">
<input type="hidden" name="document_id" value="<?php echo (int)$document['id']; ?>">
<input type="hidden" name="action" value="approve">
<button type="submit" class="admin-accept-btn">Accept</button>
</form>

<form action="admin_actions.php" method="POST">
<input type="hidden" name="document_id" value="<?php echo (int)$document['id']; ?>">
<input type="hidden" name="action" value="reject">
<button type="submit" class="admin-reject-btn">Reject</button>
</form>

<a class="admin-check-btn" href="review.php?id=<?php echo (int)$document['id']; ?>">
Check again
</a>

</div>
</td>

</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>

</section>

</main>

</body>
</html>