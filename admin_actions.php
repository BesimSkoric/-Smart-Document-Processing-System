<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
header('Location: admin.php');
exit;
}

$documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
$action = $_POST['action'] ?? '';

$allowedActions = [
'approve' => 'uploaded',
'reject' => 'rejected'
];

if ($documentId <= 0 || !isset($allowedActions[$action])) {
header('Location: admin.php?error=' . urlencode('Invalid admin action.'));
exit;
}

$stmt = $conn->prepare("
SELECT id, TRIM(LOWER(status)) AS clean_status
FROM documents
WHERE id = ?
LIMIT 1
");
$stmt->bind_param("i", $documentId);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();
$stmt->close();

if (!$document) {
header('Location: admin.php?error=' . urlencode('Document not found.'));
exit;
}

if ($document['clean_status'] !== 'validated') {
header('Location: admin.php?error=' . urlencode('Only validated documents can be accepted or rejected.'));
exit;
}

$newStatus = $allowedActions[$action];

$stmt = $conn->prepare("
UPDATE documents
SET status = ?
WHERE id = ?
");
$stmt->bind_param("si", $newStatus, $documentId);
$stmt->execute();
$stmt->close();

$message = $newStatus === 'uploaded'
? 'Document marked as uploaded.'
: 'Document rejected.';

header('Location: admin.php?success=' . urlencode($message));
exit;