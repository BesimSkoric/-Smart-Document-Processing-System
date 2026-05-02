<?php
include 'config.php';

$totalDocuments = 0;
$acceptedCount = 0;
$reviewCount = 0;
$validatedCount = 0;
$rejectedCount = 0;

// STATISTIKA
$result = $conn->query("
SELECT
COUNT(*) AS total,
SUM(LOWER(status) IN ('uploaded', 'accepted')) AS accepted,
SUM(LOWER(status) IN ('review', 'needs review')) AS needs_review,
SUM(LOWER(status) = 'validated') AS validated,
SUM(LOWER(status) = 'rejected') AS rejected
FROM documents
");

if ($result && $row = $result->fetch_assoc()) {
    $totalDocuments = (int)$row['total'];
    $acceptedCount = (int)$row['accepted'];
    $reviewCount = (int)$row['needs_review'];
    $validatedCount = (int)$row['validated'];
    $rejectedCount = (int)$row['rejected'];
}

// FILTER
$statusFilter = isset($_GET['status']) ? strtolower($_GET['status']) : 'all';

$where = "";

if ($statusFilter === 'accepted') {
    $where = "WHERE LOWER(status) IN ('uploaded', 'accepted')";
} elseif ($statusFilter === 'review') {
    $where = "WHERE LOWER(status) IN ('review', 'needs review')";
} elseif ($statusFilter === 'validated') {
    $where = "WHERE LOWER(status) = 'validated'";
} elseif ($statusFilter === 'rejected') {
    $where = "WHERE LOWER(status) = 'rejected'";
}

// PAGINACIJA
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

$countResult = $conn->query("
SELECT COUNT(*) AS total
FROM documents
$where
");

$totalFilteredDocuments = 0;

if ($countResult && $countRow = $countResult->fetch_assoc()) {
    $totalFilteredDocuments = (int)$countRow['total'];
}

$totalPages = (int)ceil($totalFilteredDocuments / $perPage);

if ($totalPages < 1) {
    $totalPages = 1;
}

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// DOKUMENTI
$documents = $conn->query("
SELECT *
FROM documents
$where
ORDER BY created_at DESC
LIMIT $perPage OFFSET $offset
");

function pageUrl($pageNumber, $statusFilter) {
    return '?status=' . urlencode($statusFilter) . '&page=' . (int)$pageNumber;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DocFlow - Dashboard</title>
<link rel="stylesheet" href="style/pocetna.css?v=<?= time(); ?>">

<style>
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 22px;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    padding: 9px 13px;
    border-radius: 10px;
    border: 1px solid #334155;
    background: #202b37;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 14px;
}

.pagination a:hover {
    background: #334155;
    color: #fff;
}

.pagination .active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.pagination .disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
</style>
</head>
<body>

<div class="layout">

<?php include 'includes/header.php'; ?>

<main class="content">

<?php if (isset($_GET['success'])): ?>
<div class="flash success">Document saved successfully.</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="flash error"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="topline">
<div>
<p class="label">Dashboard</p>
<h1>Processed documents</h1>
<p class="subtitle">
View uploaded documents, validation statuses and detected issues.
</p>
</div>

<a href="index.php" class="plain-link">Upload new</a>
</div>

<!-- FILTER -->
<div class="filters">
    <a href="?status=all&page=1" class="<?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
    <a href="?status=accepted&page=1" class="<?= $statusFilter === 'accepted' ? 'active' : '' ?>">Accepted</a>
    <a href="?status=review&page=1" class="<?= $statusFilter === 'review' ? 'active' : '' ?>">Needs Review</a>
    <a href="?status=validated&page=1" class="<?= $statusFilter === 'validated' ? 'active' : '' ?>">Validated</a>
    <a href="?status=rejected&page=1" class="<?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
</div>

<section class="stats-row">
<div class="stat-card">
<span>Total</span>
<strong><?= $totalDocuments ?></strong>
</div>

<div class="stat-card">
<span>Accepted</span>
<strong><?= $acceptedCount ?></strong>
</div>

<div class="stat-card">
<span>Needs Review</span>
<strong><?= $reviewCount ?></strong>
</div>

<div class="stat-card">
<span>Validated</span>
<strong><?= $validatedCount ?></strong>
</div>

<div class="stat-card">
<span>Rejected</span>
<strong><?= $rejectedCount ?></strong>
</div>
</section>

<section class="card">
<div class="card-head">
<h2>Documents</h2>
<p>
Showing <?= $totalFilteredDocuments > 0 ? ($offset + 1) : 0 ?>
-
<?= min($offset + $perPage, $totalFilteredDocuments) ?>
of <?= $totalFilteredDocuments ?> documents.
</p>
</div>

<?php if ($documents && $documents->num_rows > 0): ?>

<div class="table-wrapper">
<table class="documents-table">
<thead>
<tr>
<th>File</th>
<th>Type</th>
<th>Supplier</th>
<th>Number</th>
<th>Total</th>
<th>Status</th>
<th>Issues</th>
<th></th>
</tr>
</thead>
<tbody>
<?php while ($doc = $documents->fetch_assoc()): ?>
<?php
$statusRaw = strtolower(trim($doc['status'] ?? ''));
$statusLabel = $doc['status'] ?? '';
$statusClass = str_replace(' ', '-', $statusRaw);

if ($statusRaw === 'uploaded' || $statusRaw === 'accepted') {
    $statusLabel = 'Accepted';
    $statusClass = 'accepted';
}

if ($statusRaw === 'review') {
    $statusLabel = 'Needs Review';
    $statusClass = 'needs-review';
}

$issues = json_decode($doc['issues'] ?? '[]', true);
$issueCount = is_array($issues) ? count($issues) : 0;

$isLockedView = in_array($statusRaw, ['uploaded', 'accepted', 'rejected']);
?>
<tr>
<td><?= htmlspecialchars($doc['original_name'] ?? '') ?></td>
<td><?= htmlspecialchars($doc['document_type'] ?? 'unknown') ?></td>
<td><?= htmlspecialchars($doc['supplier_name'] ?? '-') ?></td>
<td><?= htmlspecialchars($doc['document_number'] ?? '-') ?></td>
<td>
<?= number_format((float)($doc['total'] ?? 0), 2) ?>
<?= htmlspecialchars($doc['currency'] ?? '') ?>
</td>
<td>
<span class="status-badge <?= htmlspecialchars($statusClass) ?>">
<?= htmlspecialchars($statusLabel) ?>
</span>
</td>
<td>
<?php if ($issueCount > 0): ?>
<span class="issue-count"><?= $issueCount ?> issue(s)</span>
<?php else: ?>
<span class="no-issues">No issues</span>
<?php endif; ?>
</td>
<td>
<?php if ($isLockedView): ?>
<a class="table-link" href="view.php?id=<?= (int)$doc['id'] ?>">View</a>
<?php else: ?>
<a class="table-link" href="review.php?id=<?= (int)$doc['id'] ?>">Review</a>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">

<?php if ($page > 1): ?>
<a href="<?= pageUrl($page - 1, $statusFilter) ?>">Previous</a>
<?php else: ?>
<span class="disabled">Previous</span>
<?php endif; ?>

<?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <?php if ($i == $page): ?>
        <span class="active"><?= $i ?></span>
    <?php else: ?>
        <a href="<?= pageUrl($i, $statusFilter) ?>"><?= $i ?></a>
    <?php endif; ?>
<?php endfor; ?>

<?php if ($page < $totalPages): ?>
<a href="<?= pageUrl($page + 1, $statusFilter) ?>">Next</a>
<?php else: ?>
<span class="disabled">Next</span>
<?php endif; ?>

</div>
<?php endif; ?>

<?php else: ?>

<div class="empty-state">
<h3>No documents found.</h3>
<p>Try changing the filter or upload a new document.</p>
<a href="index.php" class="primary-small-btn">Upload document</a>
</div>

<?php endif; ?>

</section>

</main>

</div>

</body>
</html>