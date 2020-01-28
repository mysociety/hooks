<?php

function check_payload() {
    global $CONFIG:

    $conf_dir = dirname(__FILE__) . '/../conf';
    $CONFIG = parse_ini_file("$conf_dir/general.cfg", true);
    $secret = $CONFIG['fixmystreet']['github_secret'];

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
    return $data;
}

function set_changelog_status($site, $name, $msgs, $states, $pr, $result) {
    $data = array(
        'name' => $name,
        "conclusion" => $states[$result],
        "output" => array(
            "title" => $msgs[$result],
            "summary" => "",
        ),
    );

    return create_check_run($site, $pr, $data);
}

function create_check_run($site, $pr, $data) {
    $time = date('c');
    $data['started_at'] = $time;
    $data['completed_at'] = $time;
    $data['status'] = "completed";
    $data["head_sha"] = $pr->head->sha;
    return make_api_call($site, $pr->head->repo->url . "/check-runs", 'POST', $data);
}

function make_api_call($site, $url, $method = "GET", $data = array()) {
    global $CONFIG;

    $token = trim(file_get_contents($CONFIG[$site]['token_filename']));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERAGENT, "mySociety Hooks Bot - $site");

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
