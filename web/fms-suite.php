<?php

include_once 'fns.php';

$msgs = array(
    'skip' => 'Changelog/schema check skipped',
    'pass-both' => 'Changelog and schema updates found',
    'pass-changelog' => 'Changelog updates found',
    'fail-migration' => 'No schema update found',
    'fail-changelog' => 'No changelog update found',
    'fail-all' => 'No changelog or schema update found',
);

$states = array(
    'skip' => 'neutral',
    'pass-both' => 'success',
    'pass-changelog' => 'success',
    'fail-migration' => 'failure',
    'fail-changelog' => 'failure',
    'fail-all' => 'failure',
);

$data = check_payload();

if ($data->action == 'requested_action') {
    $url = $data->check_run->url;
    # Might in future need to check check_run->name,
    # And $data->requested_action->identifier == 'done'
    $out = make_api_call('fixmystreet', $url, 'PATCH', array(
        "conclusion" => "success",
        "completed_at" => date('c'),
    ));
    exit("Requested action!");
}

$actions = array('opened', 'reopened', 'synchronize', 'edited');
if (!in_array($data->action, $actions)) {
    exit("Ignored action");
}

$pr = $data->pull_request;
if (want_to_check($pr)) {
    print "Checking PR\n";
    $diffs = diff_changes($pr);
    if ($diffs['templates'] && $data->action != 'edited') {
        print "Setting check for templates\n";
        set_template_check($pr, $diffs['templates']);
    }

    if (want_to_skip($pr)) {
        $result = 'skip';
    } else {
        $failed_migration = $diffs['new-migration'] && !($diffs['update-schema'] && $diffs['schema']);
        if ($diffs['changelog']) {
            if ($failed_migration) {
                $result = 'fail-migration';
            } elseif ($diffs['new-migration'] && $diffs['update-schema'] && $diffs['schema']) {
                $result = 'pass-both';
            } else {
                $result = 'pass-changelog';
            }
        } else {
            if ($failed_migration) {
                $result = 'fail-all';
            } else {
                $result = 'fail-changelog';
            }
        }
    }

    print "PR $result\n";
    set_changelog_status('fixmystreet', 'changelog', $msgs, $states, $pr, $result);
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
    $file_to_cobrands = collect_data();
    $diff = file_get_contents($pr->diff_url);
    $out = array();
    $out['changelog'] = preg_match('#diff --git a/CHANGELOG.md#', $diff);
    $out['schema'] = preg_match('#diff --git a/db/schema.sql#', $diff);
    $out['update-schema'] = preg_match('#diff --git a/bin/update-schema#', $diff);
    $out['new-migration'] = preg_match('#diff --git a/db/schema_\d+.*\nnew file#', $diff);

    preg_match_all('#diff --git a/templates/web/base/([^ ]*)#', $diff, $m);
    $out['templates'] = array();
    foreach ($m[1] as $template) {
        $out['templates'][] = array(
            "name" => $template,
            "cobrands" => $file_to_cobrands[$template],
        );
    }

    return $out;
}

function set_template_check($pr, $templates) {
    $repo = $pr->head->repo;
    $title = count($templates) == 0 ? 'No core template changes' : count($templates) == 1 ? 'One core template change needs checking' : count($templates) . " core template changes need checking";
    $summary = '';
    foreach ($templates as $template) {
        $summary .= "* [$template[name]](https://github.com/mysociety/$repo->name/blob/" . $pr->head->sha . "/templates/web/base/$template[name]):\n";
        foreach ($template['cobrands'] as $cobrand) {
            $summary .= "  * [$cobrand[name]](https://github.com/mysociety/$cobrand[repo]/blob/master/templates/web/$cobrand[name]/$template[name])\n";
        }
    }
    $data = array( 
        'name' => 'cobrand-templates',
        "conclusion" => count($templates) == 0 ? 'success' : "action_required",
        "output" => array( 
            "title" => $title,
            "summary" => "Please check to see that this core template change doesn’t affect any cobrands",
            "text" => $summary,
        ),
    );
    if (count($templates)) { 
        $data["actions"] = array(
            array(  
                "label" => "Checked changes",
                "description" => "See if cobrands need any changes",
                "identifier" => "done",
            ),
        );
    }

    return create_check_run('fixmystreet', $pr, $data);
}

function collect_data() {
    global $CONFIG;
    $dir = dirname($CONFIG['fixmystreet']['token_filename']) . '/repos';

    $file_to_cobrands = array();
    $cobrands = glob("$dir/*/templates/web/*");
    foreach ($cobrands as $cobrand) {
        if ($cobrand == "$dir/fixmystreet/templates/web/base") continue;
        $list = `find $cobrand -type f`;
        $list = explode("\n", $list);
        foreach ($list as $f) {
            $f = trim($f);
            if (!$f) continue;
            preg_match("#^$dir/(.*?)/templates/web/([^/]*)/(.*)#", $f, $m);
            $file_to_cobrands[$m[3]][] = array("repo" => $m[1], "name" => $m[2]);
        }
    }
    return $file_to_cobrands;
}
