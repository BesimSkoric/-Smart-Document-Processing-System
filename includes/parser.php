<?php

function normalizeAmount($value) {
    $value = trim((string)$value);
    if ($value === '') return 0;

    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    $value = str_replace(['€', '$', 'KM', 'BAM', 'USD', 'EUR', 'GBP', '£'], '', $value);

    if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $value)) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (preg_match('/^\d{1,3}(,\d{3})+\.\d{2}$/', $value)) {
        $value = str_replace(',', '', $value);
    } else {
        $value = str_replace(',', '.', $value);
    }

    $value = preg_replace('/[^0-9\.\-]/', '', $value);

    return is_numeric($value) ? (float)$value : 0;
}

function normalizeDateValue($value) {
    $value = trim((string)$value);
    if ($value === '') return null;

    $formats = [
        'Y-m-d',
        'd.m.Y',
        'd/m/Y',
        'd-m-Y',
        'm/d/Y',
        'm-d-Y',
        'd.m.y',
        'd/m/y',
        'd-m-y',
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function normalizeDueDateValue($dueDate, $issueDate) {
    $dueDate = normalizeDateValue($dueDate);

    if ($dueDate) {
        return $dueDate;
    }

    $issueDate = normalizeDateValue($issueDate);

    if (!$issueDate) {
        return null;
    }

    return date('Y-m-d', strtotime($issueDate . ' +30 days'));
}

function getEmptyParsedData($rawText = '') {
    return [
        'document_type' => 'unknown',
        'supplier_name' => null,
        'document_number' => null,
        'issue_date' => null,
        'due_date' => null,
        'currency' => 'BAM',
        'subtotal' => 0,
        'tax' => 0,
        'total' => 0,
        'raw_text' => $rawText,
        'line_items' => []
    ];
}

function parseDocumentFile($filePath, $extension) {
    $extension = strtolower(trim($extension));

    if ($extension === 'csv') {
        return parseCsvDocument($filePath);
    }

    if ($extension === 'txt') {
        return parseTextDocument((string)file_get_contents($filePath));
    }

    if ($extension === 'pdf') {
        return parsePdfDocument($filePath);
    }

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return parseImageDocument($filePath);
    }

    return getEmptyParsedData('');
}

function normalizeHeaderName($header) {
    $header = strtolower(trim((string)$header));
    $header = str_replace(["\xc2\xa0", ' '], '_', $header);
    $header = preg_replace('/[^a-z0-9_]/', '', $header);

    $map = [
        'desc' => 'description',
        'description' => 'description',
        'item' => 'description',
        'artikl' => 'description',
        'proizvod' => 'description',
        'usluga' => 'description',

        'qty' => 'quantity',
        'quantity' => 'quantity',
        'kolicina' => 'quantity',
        'kol' => 'quantity',

        'price' => 'unit_price',
        'unitprice' => 'unit_price',
        'unit_price' => 'unit_price',
        'cijena' => 'unit_price',
        'jedinicna_cijena' => 'unit_price',

        'line_total' => 'line_total',
        'linetotal' => 'line_total',
        'amount' => 'line_total',
        'total' => 'line_total',
        'ukupno' => 'line_total',

        'document_type' => 'document_type',
        'type' => 'document_type',
        'tip' => 'document_type',

        'supplier' => 'supplier_name',
        'supplier_name' => 'supplier_name',
        'company' => 'supplier_name',
        'vendor' => 'supplier_name',
        'firma' => 'supplier_name',
        'dobavljac' => 'supplier_name',
        'dobavljač' => 'supplier_name',
        'dobavljač' => 'supplier_name',

        'document_number' => 'document_number',
        'invoice_number' => 'document_number',
        'invoice_no' => 'document_number',
        'number' => 'document_number',
        'broj' => 'document_number',
        'po_number' => 'document_number',

        'date' => 'issue_date',
        'issue_date' => 'issue_date',
        'datum' => 'issue_date',

        'due_date' => 'due_date',
        'rok_placanja' => 'due_date',
        'rok_plaćanja' => 'due_date',

        'valuta' => 'currency',
        'currency' => 'currency',

        'tax' => 'tax',
        'vat' => 'tax',
        'pdv' => 'tax',

        'subtotal' => 'subtotal',
        'grand_total' => 'grand_total',
        'document_total' => 'grand_total',
    ];

    return $map[$header] ?? $header;
}

function detectDelimiter($line) {
    $delimiters = [',' => 0, ';' => 0, '|' => 0, "\t" => 0];

    foreach ($delimiters as $delimiter => $count) {
        $delimiters[$delimiter] = substr_count($line, $delimiter);
    }

    arsort($delimiters);
    return array_key_first($delimiters) ?: ',';
}

function cleanText($text) {
    $text = str_replace(["\r\n", "\r"], "\n", (string)$text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function cleanExtractedValue($value) {
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = trim($value, " \t\n\r\0\x0B:-#");
    return $value !== '' ? $value : null;
}

function normalizeCurrency($currency) {
    $currency = strtoupper(trim((string)$currency));
    $currency = str_replace('.', '', $currency);

    $map = [
        'KM' => 'BAM',
        'BAM' => 'BAM',
        'EUR' => 'EUR',
        '€' => 'EUR',
        'USD' => 'USD',
        '$' => 'USD',
        'GBP' => 'GBP',
        '£' => 'GBP',
    ];

    return $map[$currency] ?? null;
}

function detectCurrency($text) {
    if (preg_match('/\b(BAM|KM|EUR|USD|GBP)\b|€|\$|£/i', $text, $m)) {
        return normalizeCurrency($m[0]) ?: 'BAM';
    }

    return 'BAM';
}

function detectDocumentType($text, $given = null) {
    $given = strtolower(trim((string)$given));

    if (in_array($given, ['invoice', 'purchase_order', 'unknown'], true)) {
        return $given;
    }

    $flat = strtolower(preg_replace('/\s+/', ' ', $text));

    $poPatterns = [
        'purchase order',
        'purchase-order',
        'purchaseorder',
        'po number',
        'p.o. number',
        'narudzbenica',
        'narudžbenica',
        'order no',
        'order number',
    ];

    $invoicePatterns = [
        'invoice',
        'tax invoice',
        'faktura',
        'račun',
        'racun',
        'invoice number',
        'invoice no',
        'bill to',
        'amount due',
    ];

    $poScore = 0;
    $invoiceScore = 0;

    foreach ($poPatterns as $pattern) {
        if (str_contains($flat, $pattern)) {
            $poScore += 2;
        }
    }

    foreach ($invoicePatterns as $pattern) {
        if (str_contains($flat, $pattern)) {
            $invoiceScore += 2;
        }
    }

    if (preg_match('/\bPO[\s#:\-]*[A-Z0-9\-\/]+/i', $text)) {
        $poScore += 3;
    }

    if (preg_match('/\bINV[\s#:\-]*[A-Z0-9\-\/]+/i', $text)) {
        $invoiceScore += 3;
    }

    if ($poScore > $invoiceScore) {
        return 'purchase_order';
    }

    if ($invoiceScore > 0) {
        return 'invoice';
    }

    return 'unknown';
}

function extractByPatterns($text, array $patterns) {
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            return cleanExtractedValue($m[1] ?? '');
        }
    }

    return null;
}

function parseCsvDocument($filePath) {
    $content = (string)file_get_contents($filePath);

    if (trim($content) === '') {
        return getEmptyParsedData('');
    }

    $firstLine = strtok($content, "\n");
    $delimiter = detectDelimiter($firstLine);

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return getEmptyParsedData($content);
    }

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        fclose($handle);
        return getEmptyParsedData($content);
    }

    $headers = array_map('normalizeHeaderName', $headers);
    $headers = array_values(array_filter($headers, fn($h) => $h !== ''));

    $rows = [];

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }

        $row = array_slice($row, 0, count($headers));
        $row = array_pad($row, count($headers), '');

        $combined = array_combine($headers, $row);
        if ($combined) {
            $rows[] = $combined;
        }
    }

    fclose($handle);

    if (!$rows) {
        return getEmptyParsedData($content);
    }

    $first = $rows[0];

    $lineItems = [];
    $subtotal = 0;

    foreach ($rows as $row) {
        $description = trim((string)($row['description'] ?? ''));
        $quantity = normalizeAmount($row['quantity'] ?? 1);
        $unitPrice = normalizeAmount($row['unit_price'] ?? 0);
        $lineTotal = normalizeAmount($row['line_total'] ?? 0);

        if ($lineTotal <= 0 && $quantity > 0 && $unitPrice > 0) {
            $lineTotal = $quantity * $unitPrice;
        }

        if ($description !== '' || $lineTotal > 0) {
            $lineItems[] = [
                'description' => $description,
                'quantity' => $quantity > 0 ? $quantity : 1,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal
            ];

            $subtotal += $lineTotal;
        }
    }

    $tax = normalizeAmount($first['tax'] ?? 0);
    $csvSubtotal = normalizeAmount($first['subtotal'] ?? 0);
    $grandTotal = normalizeAmount($first['grand_total'] ?? 0);

    if ($csvSubtotal > 0) {
        $subtotal = $csvSubtotal;
    }

    if ($grandTotal <= 0) {
        $grandTotal = $subtotal + $tax;
    }

    $documentType = detectDocumentType($content, $first['document_type'] ?? null);

    $currency = normalizeCurrency($first['currency'] ?? '');
    if (!$currency) {
        $currency = detectCurrency($content);
    }

    $issueDate = normalizeDateValue($first['issue_date'] ?? null);
    $dueDate = normalizeDueDateValue($first['due_date'] ?? null, $issueDate);

    return [
        'document_type' => $documentType,
        'supplier_name' => cleanExtractedValue($first['supplier_name'] ?? null),
        'document_number' => cleanExtractedValue($first['document_number'] ?? null),
        'issue_date' => $issueDate,
        'due_date' => $dueDate,
        'currency' => $currency ?: 'BAM',
        'subtotal' => round($subtotal, 2),
        'tax' => round($tax, 2),
        'total' => round($grandTotal, 2),
        'raw_text' => $content,
        'line_items' => $lineItems
    ];
}

function parseTextDocument($text) {
    $text = cleanText($text);
    $data = getEmptyParsedData($text);

    if ($text === '') {
        return $data;
    }

    $flat = preg_replace('/\s+/', ' ', $text);

    $data['document_type'] = detectDocumentType($text);
    $data['currency'] = detectCurrency($text) ?: 'BAM';

    $data['document_number'] = extractByPatterns($flat, [
        '/(?:invoice\s*(?:no|number|#)?|faktura\s*(?:br|broj)?|račun\s*(?:br|broj)?|racun\s*(?:br|broj)?|document\s*(?:no|number)|po\s*(?:no|number|#)?|purchase\s*order\s*(?:no|number|#)?|order\s*(?:no|number|#)?)\s*[:#\-]?\s*([A-Z0-9][A-Z0-9\-\/\.]{1,40})/iu',
        '/\b(INV[\-\/]?[0-9A-Z\-\/]+)\b/i',
        '/\b(PO[\-\/]?[0-9A-Z\-\/]+)\b/i',
    ]);

    $data['issue_date'] = normalizeDateValue(extractByPatterns($flat, [
        '/(?:issue\s*date|invoice\s*date|date|datum|datum\s*izdavanja)\s*[:\-]?\s*([0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{1,2}[\/\.\-][0-9]{1,2}[\/\.\-][0-9]{2,4})/iu',
    ]));

    $data['due_date'] = normalizeDateValue(extractByPatterns($flat, [
        '/(?:due\s*date|payment\s*due|rok\s*placanja|rok\s*plaćanja|dospijece|dospijeće)\s*[:\-]?\s*([0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{1,2}[\/\.\-][0-9]{1,2}[\/\.\-][0-9]{2,4})/iu',
    ]));

    if (!$data['due_date'] && $data['issue_date']) {
        $data['due_date'] = date('Y-m-d', strtotime($data['issue_date'] . ' +30 days'));
    }

    $data['supplier_name'] = extractSupplierName($text);

    $data['subtotal'] = normalizeAmount(extractByPatterns($flat, [
        '/(?:subtotal|sub\s*total|net\s*amount|neto|osnovica|iznos\s*bez\s*pdv)\s*[:\-]?\s*(?:BAM|KM|EUR|USD|GBP|€|\$|£)?\s*([0-9\.,]+)/iu',
    ]));

    $data['tax'] = normalizeAmount(extractByPatterns($flat, [
        '/(?:tax|vat|pdv)(?:\s*\d{1,2}%|\s*\([^)]+\))?\s*[:\-]?\s*(?:BAM|KM|EUR|USD|GBP|€|\$|£)?\s*([0-9\.,]+)/iu',
    ]));

    $data['total'] = normalizeAmount(extractTotalAmount($text));

    $items = extractLineItemsFromText($text);

    if (!$items) {
        $items = extractLineItemsFromFlatText($flat);
    }

    $data['line_items'] = $items;

    if ($items) {
        $sum = 0;

        foreach ($items as $item) {
            $sum += (float)$item['line_total'];
        }

        if ($data['subtotal'] <= 0) {
            $data['subtotal'] = round($sum, 2);
        }

        if ($data['total'] <= 0) {
            $data['total'] = round($data['subtotal'] + $data['tax'], 2);
        }
    }

    if ($data['total'] > 0 && $data['subtotal'] <= 0 && $data['tax'] > 0) {
        $data['subtotal'] = round($data['total'] - $data['tax'], 2);
    }

    if ($data['total'] > 0 && $data['subtotal'] <= 0 && $data['tax'] <= 0) {
        $data['subtotal'] = round($data['total'], 2);
    }

    if (!$data['currency']) {
        $data['currency'] = 'BAM';
    }

    return $data;
}

function extractSupplierName($text) {
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));

    $explicit = extractByPatterns(preg_replace('/\s+/', ' ', $text), [
        '/(?:supplier|vendor|seller|company|firma|dobavljac|dobavljač)\s*[:\-]\s*([A-Za-z0-9ČĆŽŠĐčćžšđ\s\.\-&\,]{2,80})/iu',
    ]);

    if ($explicit) {
        return $explicit;
    }

    $skip = '/invoice|purchase order|faktura|račun|racun|date|datum|number|broj|total|subtotal|tax|vat|pdv|bill to|ship to/i';

    foreach ($lines as $line) {
        if (strlen($line) < 3) continue;
        if (preg_match($skip, $line)) continue;
        if (preg_match('/^[0-9\W]+$/', $line)) continue;

        return cleanExtractedValue($line);
    }

    return null;
}

function extractTotalAmount($text) {
    $flat = preg_replace('/\s+/', ' ', $text);

    $patterns = [
        '/(?:grand\s*total|amount\s*due|total\s*due|balance\s*due|ukupno\s*za\s*placanje|ukupno\s*za\s*plaćanje|ukupan\s*iznos)\s*[:\-]?\s*(?:BAM|KM|EUR|USD|GBP|€|\$|£)?\s*([0-9\.,]+)/iu',

        '/(?:grand\s*total|amount\s*due|total\s*due|balance\s*due|ukupno\s*za\s*placanje|ukupno\s*za\s*plaćanje|ukupan\s*iznos)\s*[:\-]?\s*([0-9\.,]+)\s*(?:BAM|KM|EUR|USD|GBP|€|\$|£)?/iu',

        '/(?:total|ukupno)\s*[:\-]?\s*(?:BAM|KM|EUR|USD|GBP|€|\$|£)?\s*([0-9\.,]+)/iu',

        '/(?:total|ukupno)\s*[:\-]?\s*([0-9\.,]+)\s*(?:BAM|KM|EUR|USD|GBP|€|\$|£)?/iu',
    ];

    $matches = [];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $flat, $m)) {
            foreach ($m[1] as $amount) {
                $amount = normalizeAmount($amount);
                if ($amount > 0) {
                    $matches[] = $amount;
                }
            }
        }
    }

    if (!$matches) {
        return 0;
    }

    return max($matches);
}

function extractLineItemsFromText($text) {
    $items = [];
    $lines = explode("\n", cleanText($text));

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') continue;

        if (preg_match('/^(description|desc|item|qty|quantity|price|total|subtotal|tax|vat|pdv)/i', $line)) {
            continue;
        }

        if (preg_match('/^(.+?)\s+([0-9]+(?:[\.,][0-9]+)?)\s+([0-9]+(?:[\.,][0-9]+)?)\s+([0-9]+(?:[\.,][0-9]+)?)$/u', $line, $m)) {
            $description = cleanExtractedValue($m[1]);
            $quantity = normalizeAmount($m[2]);
            $unitPrice = normalizeAmount($m[3]);
            $lineTotal = normalizeAmount($m[4]);

            if ($description && $lineTotal > 0) {
                $items[] = [
                    'description' => $description,
                    'quantity' => $quantity > 0 ? $quantity : 1,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal
                ];
            }
        }
    }

    return $items;
}

function extractLineItemsFromFlatText($text) {
    $items = [];

    if (preg_match('/(?:description|desc|item)\s+(?:qty|quantity)\s+(?:unit\s*price|price)\s+(?:total|amount)\s+(.+?)(?:subtotal|tax|vat|pdv|grand\s*total|amount\s*due|total\s*due|ukupno)/iu', $text, $m)) {
        $itemsText = trim($m[1]);

        preg_match_all('/([A-Za-zČĆŽŠĐčćžšđ0-9\s\.\-&\/]+?)\s+([0-9]+(?:[\.,][0-9]+)?)\s+([0-9]+(?:[\.,][0-9]+)?)\s+([0-9]+(?:[\.,][0-9]+)?)/u', $itemsText, $rows, PREG_SET_ORDER);

        foreach ($rows as $row) {
            $quantity = normalizeAmount($row[2]);
            $unitPrice = normalizeAmount($row[3]);
            $lineTotal = normalizeAmount($row[4]);

            $items[] = [
                'description' => cleanExtractedValue($row[1]),
                'quantity' => $quantity > 0 ? $quantity : 1,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal
            ];
        }
    }

    return $items;
}

function parsePdfDocument($filePath) {
    $rawText = '';

    $autoload = __DIR__ . '/../vendor/autoload.php';

    if (file_exists($autoload)) {
        require_once $autoload;

        try {
            if (class_exists('\Smalot\PdfParser\Parser')) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $rawText = cleanText($pdf->getText());
            }
        } catch (Throwable $e) {
            $rawText = '';
        }
    }

    if (trim($rawText) === '') {
        $data = getEmptyParsedData('');
        $data['raw_text'] = 'PDF uploaded, but text extraction failed. This PDF may be scanned/image-based or PdfParser is not installed.';
        $data['currency'] = 'BAM';
        return $data;
    }

    $data = parseTextDocument($rawText);

    if (!$data['currency']) {
        $data['currency'] = 'BAM';
    }

    if (empty($data['due_date']) && !empty($data['issue_date'])) {
        $data['due_date'] = date('Y-m-d', strtotime($data['issue_date'] . ' +30 days'));
    }

    return $data;
}

function parseImageDocument($filePath) {
    $data = getEmptyParsedData('');

    $data['document_type'] = 'unknown';
    $data['supplier_name'] = null;
    $data['document_number'] = null;
    $data['issue_date'] = null;
    $data['due_date'] = null;
    $data['currency'] = 'BAM';
    $data['subtotal'] = 0;
    $data['tax'] = 0;
    $data['total'] = 0;
    $data['line_items'] = [];

    $data['raw_text'] = "Image uploaded successfully.\n\nOCR is not available on this server yet, so text could not be extracted automatically.\n\nPlease review this document manually.";

    return $data;
}
