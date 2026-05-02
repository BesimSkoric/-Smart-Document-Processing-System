<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

include '../config.php';
include '../includes/parser.php';
include '../includes/validator.php';

function jsonResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Only POST method is allowed.', [], 405);
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'File upload failed. Field name must be document.', [], 400);
}

$allowedExtensions = ['pdf', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'webp'];
$maxSize = 10 * 1024 * 1024;

$originalName = basename($_FILES['document']['name']);
$tmpName = $_FILES['document']['tmp_name'];
$fileSize = (int)$_FILES['document']['size'];

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions, true)) {
    jsonResponse(false, 'Unsupported file type. Allowed: PDF, CSV, TXT, JPG, JPEG, PNG, WEBP.', [], 400);
}

if ($fileSize <= 0) {
    jsonResponse(false, 'Uploaded file is empty.', [], 400);
}

if ($fileSize > $maxSize) {
    jsonResponse(false, 'File is too large. Max size is 10MB.', [], 400);
}

if (!is_uploaded_file($tmpName)) {
    jsonResponse(false, 'Invalid uploaded file.', [], 400);
}

$uploadDir = '../uploads';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$storedName = uniqid('doc_', true) . '.' . $extension;
$filePathForMove = $uploadDir . '/' . $storedName;
$filePathForDb = 'uploads/' . $storedName;

if (!move_uploaded_file($tmpName, $filePathForMove)) {
    jsonResponse(false, 'Could not save uploaded file.', [], 500);
}

$data = parseDocumentFile($filePathForMove, $extension);

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
    jsonResponse(false, 'Database prepare failed: ' . $conn->error, [], 500);
}

$stmt->bind_param(
    "ssssssssssdddsss",
    $originalName,
    $storedName,
    $filePathForDb,
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
    jsonResponse(false, 'Database insert failed: ' . $stmt->error, [], 500);
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
    ? 'Document uploaded through API and validated automatically.'
    : 'Document uploaded through API and marked as Needs Review.';

$logStmt = $conn->prepare("
    INSERT INTO document_logs (document_id, action, message)
    VALUES (?, 'api_upload', ?)
");

if ($logStmt) {
    $logStmt->bind_param("is", $documentId, $logMessage);
    $logStmt->execute();
    $logStmt->close();
}

jsonResponse(true, 'Document uploaded successfully.', [
    'document_id' => $documentId,
    'status' => $status,
    'file' => [
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'file_type' => $extension,
        'file_path' => $filePathForDb
    ],
    'extracted_data' => [
        'document_type' => $data['document_type'],
        'supplier_name' => $data['supplier_name'],
        'document_number' => $data['document_number'],
        'issue_date' => $data['issue_date'],
        'due_date' => $data['due_date'],
        'currency' => $data['currency'],
        'subtotal' => $data['subtotal'],
        'tax' => $data['tax'],
        'total' => $data['total'],
        'line_items' => $data['line_items']
    ],
    'validation_issues' => $issues,
    'review_url' => '../review.php?id=' . $documentId
], 201);
