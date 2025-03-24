<?php

require_once 'utils.php';

$secret = $CONFIG['packaging']['github_secret'];

$payload = file_get_contents('php://input');

$signature_header = $_SERVER['HTTP_X_HUB_SIGNATURE'];
$signature_calc = 'sha1=' . hash_hmac('sha1', $payload, $secret, false);
if (!hash_equals($signature_header, $signature_calc)) {
    exit("Signature did not match");
}

$data = json_decode($payload);
if (!$data) {
    exit("No JSON data");
}

if (!$data->artifact_id) {
    exit("No artifact ID given");
}

if (!$data->distribution) {
    exit("No distribution given");
}

if (!$data->arch) {
    exit("No arch given");
}

# TODO: call into bin script.
