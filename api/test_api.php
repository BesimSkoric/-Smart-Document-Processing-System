<?php

$url = "https://projekat.kupidres.com/api/upload_document.php";

// napravi test fajl ako ga nema
$testFile = __DIR__ . '/test.txt';

if (!file_exists($testFile)) {
    file_put_contents($testFile, "Invoice TXT-1\nTotal: 406 EUR");
}

// priprema file za upload
$postData = [
    'document' => new CURLFile($testFile)
];

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "cURL error: " . curl_error($ch);
} else {
    echo "Response:\n\n";
    echo $response;
}

curl_close($ch);