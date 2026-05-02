<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'config.php';
include 'includes/parser.php';
include 'includes/validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    header('Location: index.php?error=' . urlencode('File upload failed.'));
    exit;
}

$allowedExtensions = ['pdf', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'webp'];
$maxSize = 10 * 1024 * 1024;

$originalName = basename($_FILES['document']['name']);
$tmpName = $_FILES['document']['tmp_name'];
$fileSize = (int)$_FILES['document']['size'];

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions, true)) {
    header('Location: index.php?error=' . urlencode('Unsupported file type. Allowed: PDF, CSV, TXT, JPG, JPEG, PNG, WEBP.'));
    exit;
}

if ($fileSize <= 0) {
    header('Location: index.php?error=' . urlencode('Uploaded file is empty.'));
    exit;
}

if ($fileSize > $maxSize) {
    header('Location: index.php?error=' . urlencode('File is too large. Max size is 10MB.'));
    exit;
}

if (!is_uploaded_file($tmpName)) {
    header('Location: index.php?error=' . urlencode('Invalid uploaded file.'));
    exit;
}

if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
}

$storedName = uniqid('doc_', true) . '.' . $extension;
$filePath = 'uploads/' . $storedName;

if (!move_uploaded_file($tmpName, $filePath)) {
    header('Location: index.php?error=' . urlencode('Could not save uploaded file.'));
    exit;
}

$data = parseDocumentFile($filePath, $extension);

$data['document_type'] = $data['document_type'] ?? 'unknown';
$data['supplier_name'] = $data['supplier_name'] ?? null;
$data['document_number'] = $data['document_number'] ?? null;
$data['issue_date'] = $data['issue_date'] ?? null;
$data['due_date'] = $data['due_date'] ?? null;
$data['currency'] = !empty($data['currency']) ? $data['currency'] : 'BAM';
$data['subtotal'] = isset($data['subtotal']) ? (float)$data['subtotal'] : 0;
$data['tax'] = isset($data['tax']) ? (float)$data['tax'] : 0;
$data['total'] = isset($data['total']) ? (float)$data['total'] : 0;
$data['raw_text'] = $data['raw_text'] ?? '';
$data['line_items'] = $data['line_items'] ?? [];

if (!is_array($data['line_items'])) {
    $data['line_items'] = [];
}

if (empty($data['due_date']) && !empty($data['issue_date'])) {
    $data['due_date'] = date('Y-m-d', strtotime($data['issue_date'] . ' +30 days'));
}

$issues = validateDocumentData($conn, $data);

$status = empty($issues) ? 'Validated' : 'Needs Review';
$issuesJson = json_encode($issues, JSON_UNESCAPED_UNICODE);

$stmt = $conn->prepare("
    INSERT INTO documents (
        original_name,
        stored_name,
        file_path,
        file_type,
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
        raw_text
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    header('Location: index.php?error=' . urlencode('Database prepare failed: ' . $conn->error));
    exit;
}

$stmt->bind_param(
    "ssssssssssdddsss",
    $originalName,
    $storedName,
    $filePath,
    $extension,
    $data['document_type'],
    $data['supplier_name'],
    $data['document_number'],
    $data['issue_date'],
    $data['due_date'],
    $data['currency'],
    $data['subtotal'],
    $data['tax'],
    $data['total'],
    $status,
    $issuesJson,
    $data['raw_text']
);

if (!$stmt->execute()) {
    header('Location: index.php?error=' . urlencode('Database insert failed: ' . $stmt->error));
    exit;
}

$documentId = $stmt->insert_id;
$stmt->close();

if (!empty($data['line_items'])) {
    $itemStmt = $conn->prepare("
        INSERT INTO document_line_items (
            document_id,
            description,
            quantity,
            unit_price,
            line_total
        ) VALUES (?, ?, ?, ?, ?)
    ");

    if ($itemStmt) {
        foreach ($data['line_items'] as $item) {
            $description = trim((string)($item['description'] ?? ''));
            $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 1;
            $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
            $lineTotal = isset($item['line_total']) ? (float)$item['line_total'] : 0;

            if ($quantity <= 0) {
                $quantity = 1;
            }

            if ($lineTotal <= 0 && $unitPrice > 0) {
                $lineTotal = $quantity * $unitPrice;
            }

            if ($description === '' && $lineTotal <= 0) {
                continue;
            }

            $itemStmt->bind_param(
                "isddd",
                $documentId,
                $description,
                $quantity,
                $unitPrice,
                $lineTotal
            );

            $itemStmt->execute();
        }

        $itemStmt->close();
    }
}

$logMessage = empty($issues)
    ? 'Document uploaded and validated automatically.'
    : 'Document uploaded and marked as Needs Review.';

$logStmt = $conn->prepare("
    INSERT INTO document_logs (document_id, action, message)
    VALUES (?, 'upload', ?)
");

if ($logStmt) {
    $logStmt->bind_param("is", $documentId, $logMessage);
    $logStmt->execute();
    $logStmt->close();
}

header('Location: review.php?id=' . $documentId);
exit;