<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../config.php';
include '../includes/validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;

if ($documentId <= 0) {
    header('Location: ../dashboard.php?error=' . urlencode('Invalid document ID.'));
    exit;
}

$documentType = $_POST['document_type'] ?? 'unknown';
$supplierName = trim($_POST['supplier_name'] ?? '');
$documentNumber = trim($_POST['document_number'] ?? '');
$currency = strtoupper(trim($_POST['currency'] ?? 'BAM'));

$issueDate = !empty($_POST['issue_date']) ? $_POST['issue_date'] : null;
$dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

if (!$dueDate && $issueDate) {
    $dueDate = date('Y-m-d', strtotime($issueDate . ' +30 days'));
}

/*
|--------------------------------------------------------------------------
| AUTOMATSKI PRERAČUN LINE ITEMA
|--------------------------------------------------------------------------
| Ne vjerujemo totalima iz forme, nego ih računamo ponovo ovdje.
*/

$tax = isset($_POST['tax']) ? (float)$_POST['tax'] : 0;
$subtotal = 0;

if (!empty($_POST['items']) && is_array($_POST['items'])) {
    foreach ($_POST['items'] as &$item) {
        $description = trim($item['description'] ?? '');
        $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 0;
        $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;

        if ($description === '' && $quantity == 0 && $unitPrice == 0) {
            $item['line_total'] = 0;
            continue;
        }

        $lineTotal = $quantity * $unitPrice;

        $item['line_total'] = $lineTotal;
        $subtotal += $lineTotal;
    }

    unset($item);
}

$total = $subtotal + $tax;

/*
|--------------------------------------------------------------------------
| UPDATE DOKUMENTA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE documents
    SET
        document_type = ?,
        supplier_name = ?,
        document_number = ?,
        currency = ?,
        issue_date = ?,
        due_date = ?,
        subtotal = ?,
        tax = ?,
        total = ?
    WHERE id = ?
");

$stmt->bind_param(
    "ssssssdddi",
    $documentType,
    $supplierName,
    $documentNumber,
    $currency,
    $issueDate,
    $dueDate,
    $subtotal,
    $tax,
    $total,
    $documentId
);

$stmt->execute();
$stmt->close();

/*
|--------------------------------------------------------------------------
| SAVE LINE ITEMS
|--------------------------------------------------------------------------
*/

$keptItemIds = [];
$cleanLineItems = [];

if (!empty($_POST['items']) && is_array($_POST['items'])) {
    foreach ($_POST['items'] as $item) {
        $itemId = isset($item['id']) ? (int)$item['id'] : 0;
        $description = trim($item['description'] ?? '');
        $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 0;
        $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
        $lineTotal = isset($item['line_total']) ? (float)$item['line_total'] : 0;

        if ($description === '' && $quantity == 0 && $unitPrice == 0 && $lineTotal == 0) {
            continue;
        }

        if ($itemId > 0) {
            $itemStmt = $conn->prepare("
                UPDATE document_line_items
                SET description = ?, quantity = ?, unit_price = ?, line_total = ?
                WHERE id = ? AND document_id = ?
            ");

            $itemStmt->bind_param(
                "sdddii",
                $description,
                $quantity,
                $unitPrice,
                $lineTotal,
                $itemId,
                $documentId
            );

            $itemStmt->execute();
            $itemStmt->close();

            $keptItemIds[] = $itemId;
        } else {
            $itemStmt = $conn->prepare("
                INSERT INTO document_line_items
                (document_id, description, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?)
            ");

            $itemStmt->bind_param(
                "isddd",
                $documentId,
                $description,
                $quantity,
                $unitPrice,
                $lineTotal
            );

            $itemStmt->execute();
            $newItemId = $conn->insert_id;
            $itemStmt->close();

            $keptItemIds[] = $newItemId;
        }

        $cleanLineItems[] = [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal
        ];
    }
}

/*
|--------------------------------------------------------------------------
| OBRIŠI LINE ITEME KOJI SU UKLONJENI
|--------------------------------------------------------------------------
*/

if (!empty($keptItemIds)) {
    $placeholders = implode(',', array_fill(0, count($keptItemIds), '?'));
    $types = str_repeat('i', count($keptItemIds) + 1);

    $deleteSql = "
        DELETE FROM document_line_items
        WHERE document_id = ?
        AND id NOT IN ($placeholders)
    ";

    $deleteStmt = $conn->prepare($deleteSql);
    $params = array_merge([$documentId], $keptItemIds);
    $deleteStmt->bind_param($types, ...$params);
    $deleteStmt->execute();
    $deleteStmt->close();
} else {
    $deleteStmt = $conn->prepare("
        DELETE FROM document_line_items 
        WHERE document_id = ?
    ");

    $deleteStmt->bind_param("i", $documentId);
    $deleteStmt->execute();
    $deleteStmt->close();
}

/*
|--------------------------------------------------------------------------
| VALIDACIJA
|--------------------------------------------------------------------------
*/

$dataForValidation = [
    'document_type' => $documentType,
    'supplier_name' => $supplierName,
    'document_number' => $documentNumber,
    'currency' => $currency,
    'issue_date' => $issueDate,
    'due_date' => $dueDate,
    'subtotal' => $subtotal,
    'tax' => $tax,
    'total' => $total,
    'line_items' => $cleanLineItems
];

$issues = validateDocumentData($conn, $dataForValidation, $documentId);

$status = empty($issues) ? 'validated' : 'review';
$issuesJson = json_encode($issues, JSON_UNESCAPED_UNICODE);

$stmt = $conn->prepare("
    UPDATE documents
    SET status = ?, issues = ?
    WHERE id = ?
");

$stmt->bind_param("ssi", $status, $issuesJson, $documentId);
$stmt->execute();
$stmt->close();

/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

if ($status === 'validated') {
    header('Location: ../dashboard.php?success=' . urlencode('Document saved and validated successfully.'));
    exit;
}

header('Location: ../review.php?id=' . $documentId . '&saved=1');
exit;
