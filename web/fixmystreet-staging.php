<?php

require_once 'utils.php';
require_once 'Parsedown.php';
$Parsedown = new Parsedown();

header('Cache-Control: max-age=15');

?>
<!DOCTYPE html>
<html>
  <head>
    <title>Staging deployment</title>
    <link rel="stylesheet" href="https://gaze.mysociety.org/assets/css/global.css">
    <meta name="viewport" content="initial-scale=1">
    <link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,600,700,900,400italic' rel='stylesheet' type='text/css'>
    <style>
      summary { display: list-item; }
    </style>
  </head>
  <body>
    <div class="ms-header">
      <nav class="ms-header__row">
        <a class="ms-header__logo" href="https://www.mysociety.org">mySociety</a>
      </nav>
    </div>

    <header class="site-header">
      <div class="container">
        <h1>Staging deployment</h1>
      </div>
    </header>

    <div class="page-wrapper">
      <div class="page">

        <div class="main-content-column">
          <main role="main" class="main-content">

<p>Code branches and PRs on staging, not on live.
Staging may additionally have other new configuration etc.</p>

<?php

$repos = [
    [ 'repo' => 'fixmystreet', 'branch' => 'commercial-staging', 'site' => 'staging.fixmystreet.com' ],
    [ 'repo' => 'open311-adapter', 'branch' => 'staging' ],
    [ 'repo' => 'fixmystreet.com', 'branch' => 'staging', 'site' => 'tilma layers / .com scripts' ],
];

foreach ($repos as $repo_info) {
    $repo = $repo_info['repo'];
    $staging_branch = $repo_info['branch'];
    $site = $repo_info['site'] ?? $repo_info['repo'];

    # List of SHAs on staging, not on master (spots cherry-picks and rebases)
    #$cherry = `git -C $repo_dir/$repo cherry origin/master origin/$staging_branch`;
    #$cherry = explode("\n", $cherry);

    # List of merge commits on staging, not on master
    $log = `git -C $repo_dir/$repo log --merges origin/$staging_branch --not origin/master`;
    $log = explode("\n", $log);
    $branches = [];
    foreach ($log as $line) {
        if (preg_match("#^ *Merge (?:remote-tracking )?branch(?:es)? ('.*?'(?:, '.*?')*(?: and '.*?')?) into#", $line, $m)) {
            preg_match_all("#'(.*?)'#", $m[1], $mm);
            foreach ($mm[1] as $branch) {
                $branch = preg_replace('#^origin/#', '', $branch);
                exec("git -C $repo_dir/$repo for-each-ref --contains origin/$branch --format '%(refname:short)' | grep -q master", $output, $result_code);
                if ($result_code) {
                    $branches[] = $branch;
                }
            }
        }
    }

    $data = make_api_call("https://api.github.com/repos/mysociety/$repo/pulls");

    $prs = [];
    foreach ($data as $pr) {
        $branch = $pr->head->ref;
        if (in_array($branch, $branches)) {
            $prs[$branch] = $pr;
        }
    }

    usort($branches, function($a, $b) {
        global $prs;
        if ($prs[$a] && $prs[$b]) {
            $bb = strtotime($prs[$b]->updated_at);
                   $aa = strtotime($prs[$a]->updated_at);
            if ($aa != $bb) {
                return $bb - $aa;
            } else {
                return $prs[$b]->id - $prs[$a]->id;
            }
        } else if ($prs[$a]) {
            return -1;
        } else if ($prs[$b]) {
            return 1;
        } else {
            return 0;
        }
    });
?>

<h2><?=$site ?></h2>
<ul>
<?php
    foreach ($branches as $branch) {
        if ($pr = $prs[$branch]) {
            $user = $pr->user;
            $body = $Parsedown->text($pr->body);
            print "<li><details><summary><img alt='$user->login' src='$user->avatar_url' width=16> <a title='$branch' href='https://github.com/mysociety/$repo/pull/$pr->number'>#$pr->number</a> $pr->title</summary>\n$body</details>";
        } else {
            print "<li><a href='https://github.com/mysociety/$repo/compare/$branch'>$branch</a>";
        }
    }
?>
</ul>
<?php
}
?>

          </main>
        </div>

        <div class="secondary-content-column">
          <nav class="sidebar">
            <ul>
              <li><a href="https://github.com/mysociety/fixmystreet">fixmystreet GitHub</a></li>
              <li><a href="https://github.com/mysociety/open311-adapter">open311-adapter GitHub</a></li>
              <li><a href="https://github.com/mysociety/fixmystreet.com">fixmystreet.com GitHub</a></li>
            </ul>
          </nav>
        </div>
      </div>
    </div>

    <footer class="site-footer">
      <div class="container">
        <div class="column">
          <h3>mySociety</h3>
          <ul>
            <li><a href="https://www.mysociety.org/about/">About us</a></li>
            <li><a href="https://www.mysociety.org/contact/">Contact us</a></li>
            <li><a href="https://www.mysociety.org/donate/">Donate</a></li>
          </ul>
        </div>
        <div class="column central">
          <h3>Our Apps</h3>
          <ul>
            <li><a href="https://www.fixmystreet.com/">FixMyStreet</a></li>
            <li><a href="https://www.writetothem.com/">WriteToThem</a></li>
            <li><a href="https://www.whatdotheyknow.com/">WhatDoTheyKnow</a></li>
            <li><a href="https://www.theyworkforyou.com/">TheyWorkForYou</a></li>
            <li><a href="https://www.alaveteli.org/">Alaveteli</a></li>
            <li><a href="https://sayit.mysociety.org/">SayIt</a></li>
            <li><a href="https://mapit.mysociety.org/">MapIt</a></li>
          </ul>
        </div>
        <div class="column">
          <h3>Connect</h3>
          <ul>
            <li><a href="https://groups.google.com/a/mysociety.org/forum/#!forum/mysociety-community">Mailing list</a></li>
            <li><a href="https://github.com/mysociety">GitHub</a></li>
            <li><a href="https://twitter.com/mysociety">Twitter</a></li>
          </ul>
        </div>
      </div>
    </footer>
  </body>
</html>
