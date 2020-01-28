<?php

include_once 'fns.php';

$msgs = array(
    'skip' => 'File check skipped',
    'pass' => 'All needed files found',
    'fail' => 'Not all needed files found',
    'none' => 'No files changed',
);

$states = array(
    'skip' => 'neutral',
    'pass' => 'success',
    'fail' => 'failure',
    'none' => 'neutral',
);

$data = get_payload();

$actions = array('opened', 'reopened', 'synchronize', 'edited');
if (!in_array($data->action, $actions)) {
    exit("Ignored action");
}

$pr = $data->pull_request;
if (want_to_check($pr)) {
    print "Checking PR\n";

    if (want_to_skip($pr)) {
        $result = 'skip';
    } else {
        $diffs = diff_changes($pr);
        if ($diffs['rails4gem'] && $diffs['rails5gem']) {
            $result = 'pass';
        } elseif ($diffs['rails4gem'] || $diffs['rails5gem']) {
            $result = 'fail';
        } else {
            $result = 'none';
        }
    }

    print "PR $result\n";
    set_changelog_status('whatdotheyknow', 'gem-check', $msgs, $states, $pr, $result);
    print "Status set\n";
} else {
    print "Ignoring PR\n";
}

function want_to_check($pr) {
    return $pr->state == 'open' && $pr->base->ref == 'master';
}

function want_to_skip($pr) {
    return preg_match('#\[skip changelog\]#', $pr->body);
}

function diff_changes($pr) {
    $diff = file_get_contents($pr->diff_url);
    $out = array();
    $out['rails4gem'] = preg_match('#diff --git a/Gemfile.lock#', $diff);
    $out['rails5gem'] = preg_match('#diff --git a/Gemfile.rails_next.lock#', $diff);
    return $out;
}
