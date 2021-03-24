<?php

$conf_dir = dirname(__FILE__) . '/../conf';
$CONFIG = parse_ini_file("$conf_dir/general.cfg", true);
$repo_dir = dirname($CONFIG['fixmystreet']['token_filename']) . '/repos';

function make_api_call($url, $method = "GET", $data = array()) {
    global $CONFIG;

    $token = trim(file_get_contents($CONFIG['fixmystreet']['token_filename']));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERAGENT, "mySociety FixMyStreet Chase Suite Bot");

    curl_setopt($ch, CURLOPT_URL, $url);
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    } elseif ($method != 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        "Authorization: token $token",
        "Accept: application/vnd.github.antiope-preview+json",
    ));
    $out = curl_exec($ch);
    $out = json_decode($out);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code >= 400) {
        print_r($out);
    }
    curl_close($ch);
    return $out;
}
