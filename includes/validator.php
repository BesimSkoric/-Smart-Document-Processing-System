<?php

function validateDocumentData($conn, $data, $ignoreDocumentId = null) {
    $issues = [];

    if (empty($data['document_type']) || $data['document_type'] === 'unknown') {
        $issues[] = 'Document type could not be detected.';
    }

    if (empty($data['supplier_name'])) {
        $issues[] = 'Supplier/company name is missing.';
    }

    if (empty($data['document_number'])) {
        $issues[] = 'Document number is missing.';
    }

    if (empty($data['issue_date'])) {
        $issues[] = 'Issue date is missing or invalid.';
    }

    if (!empty($data['due_date']) && !empty($data['issue_date'])) {
        if (strtotime($data['due_date']) < strtotime($data['issue_date'])) {
            $issues[] = 'Due date is before issue date.';
        }
    }

    if (empty($data['currency'])) {
        $issues[] = 'Currency is missing.';
    }

    $itemsSum = 0;

    if (!empty($data['line_items']) && is_array($data['line_items'])) {
        foreach ($data['line_items'] as $index => $item) {
            $description = trim($item['description'] ?? '');
            $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 0;
            $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
            $lineTotal = isset($item['line_total']) ? (float)$item['line_total'] : 0;

            if ($description === '') {
                $issues[] = 'Line item ' . ($index + 1) . ' description is missing.';
            }

            if ($quantity <= 0) {
                $issues[] = 'Line item ' . ($index + 1) . ' quantity is invalid.';
            }

            if ($unitPrice <= 0) {
                $issues[] = 'Line item ' . ($index + 1) . ' unit price is invalid.';
            }

            $expectedLineTotal = $quantity * $unitPrice;

            if ($quantity > 0 && $unitPrice > 0 && abs($expectedLineTotal - $lineTotal) > 0.01) {
                $issues[] = 'Line item ' . ($index + 1) . ' total is incorrect.';
            }

            $itemsSum += $lineTotal;
        }
    }

    $subtotal = isset($data['subtotal']) ? (float)$data['subtotal'] : 0;
    $tax = isset($data['tax']) ? (float)$data['tax'] : 0;
    $total = isset($data['total']) ? (float)$data['total'] : 0;

    /*
    Ako subtotal nije pronađen, ali imamo line items,
    koristimo sumu stavki kao fallback.
    */
    if ($subtotal <= 0 && $itemsSum > 0) {
        $subtotal = $itemsSum;
    }

    /*
    Ako subtotal fali, ali imamo total i nema tax,
    koristi total kao subtotal.
    Ovo rješava TXT dokumente tipa:
    Invoice TXT-1
    Total: 406 EUR
    */
    if ($subtotal <= 0 && $total > 0 && $tax <= 0) {
        $subtotal = $total;
    }

    /*
    Line items nisu obavezni ako imamo validan subtotal ili total.
    Za kratke TXT/PDF dokumente često imamo samo total.
    */
    if (empty($data['line_items']) && $subtotal <= 0 && $total <= 0) {
        $issues[] = 'No line items detected.';
    }

    if ($subtotal <= 0) {
        $issues[] = 'Subtotal is missing or invalid.';
    }

    if ($total <= 0) {
        $issues[] = 'Total is missing or invalid.';
    }

    /*
    Provjeri line items sumu samo ako line items postoje.
    Ako ih nema, ne ruši dokument zbog toga.
    */
    if (!empty($data['line_items']) && $subtotal > 0 && abs($itemsSum - $subtotal) > 0.01) {
        $issues[] = 'Subtotal does not match sum of line items.';
    }

    $expectedTotal = $subtotal + $tax;

    if ($subtotal > 0 && $total > 0 && abs($expectedTotal - $total) > 0.01) {
        $issues[] = 'Total does not match subtotal + tax.';
    }

    /*
    Duplicate provjera koristi document_number + supplier_name.
    Tako dvije različite firme mogu imati isti broj dokumenta bez greške.
    */
    if (!empty($data['document_number']) && !empty($data['supplier_name'])) {
        if ($ignoreDocumentId) {
            $stmt = $conn->prepare("SELECT id FROM documents WHERE document_number = ? AND supplier_name = ? AND id != ? LIMIT 1");
            $stmt->bind_param("ssi", $data['document_number'], $data['supplier_name'], $ignoreDocumentId);
        } else {
            $stmt = $conn->prepare("SELECT id FROM documents WHERE document_number = ? AND supplier_name = ? LIMIT 1");
            $stmt->bind_param("ss", $data['document_number'], $data['supplier_name']);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $issues[] = 'Duplicate document detected for this supplier.';
        }

        $stmt->close();
    } elseif (!empty($data['document_number'])) {
        if ($ignoreDocumentId) {
            $stmt = $conn->prepare("SELECT id FROM documents WHERE document_number = ? AND id != ? LIMIT 1");
            $stmt->bind_param("si", $data['document_number'], $ignoreDocumentId);
        } else {
            $stmt = $conn->prepare("SELECT id FROM documents WHERE document_number = ? LIMIT 1");
            $stmt->bind_param("s", $data['document_number']);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $issues[] = 'Duplicate document number detected.';
        }

        $stmt->close();
    }

    return $issues;
}
