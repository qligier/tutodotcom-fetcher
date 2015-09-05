#!/usr/bin/php
<?php
require_once 'vendor/autoload.php';
$cli = new League\CLImate\CLImate;
$cli->description('tutodotcom-fetcher is a small program that downloads tuto.com tutorial videos');
$cli->arguments->add([
    'id' => [
        'prefix' => 'i',
        'longPrefix' => 'id',
        'description' => 'The ID(s) of the tuto to download (comma-separated)',
        'required'    => true,
    ],
    'user' => [
        'prefix'      => 'u',
        'longPrefix'  => 'user',
        'description' => 'The email address to log in with',
        'required'    => true,
    ],
    'password' => [
        'prefix'      => 'p',
        'longPrefix'  => 'password',
        'description' => 'The password to log in with',
        'required'    => true,
    ],
    'output' => [
        'prefix'      => 'o',
        'longPrefix'  => 'output',
        'description' => 'The output directory',
        'required'    => true,
    ],
    'help' => [
        'prefix'      => 'h',
        'longPrefix'  => 'help',
        'description' => 'Prints an usage statement',
        'noValue'     => true,
    ],
]);

define('PADDING', 60);
$browser_headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', // */
    'Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
];







// Check: arguments
$arguments_valid = true;
try {
    $cli->arguments->parse();
}
catch (Exception $e) {
    $arguments_valid = false;
    if (!$cli->arguments->defined('help'))
        $cli->red()->error($e->getMessage());
}

if (!$arguments_valid || $cli->arguments->defined('help')) {
    $cli->usage();
    die;
}



// Check: output directory
$cli->inline(str_pad('Output directory exists and is writable', PADDING));
if (!is_dir($cli->arguments->get('output')) || !is_writeable($cli->arguments->get('output'))) {
    $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
    $cli->red()->out('Please choose a writable directory!');
    die;
}
$cli->inline('[ ')->green()->inline('OK')->white()->out(' ]');
define('OUTPUT_DIR', realpath($cli->arguments->get('output')).'/');




// Check: input
$input_ids = [];
if (!empty($cli->arguments->get('id'))) {
    $input_ids = explode(',', $cli->arguments->get('id'));
    array_walk($input_ids, 'trim');
    $input_ids = array_filter($input_ids, 'is_numeric');
    $cli->inline(str_pad('Input: '.count($input_ids).' links', PADDING));
    if (0 === count($input_ids)) {
        $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
        clean_and_die();
    }
    $cli->inline('[ ')->green()->inline('OK')->white()->out(' ]');
}



// Check: CURL extension
$cli->inline(str_pad('CURL extension is available', PADDING));
if (!extension_loaded('curl')) {
    $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
    $cli->red()->out('Please install and activate the CURL extension!');
    clean_and_die();
}
$cli->inline('[ ')->green()->inline('OK')->white()->out(' ]');



// GET Login
$cli->inline(str_pad('Cross-site request forgery protection', PADDING));
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, 'https://fr.tuto.com/connexion/');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_COOKIESESSION, true);
curl_setopt($curl, CURLOPT_COOKIEJAR, OUTPUT_DIR.'tmp_cookies.txt');
curl_setopt($curl, CURLOPT_HTTPHEADER, $browser_headers);
$login_page = curl_exec($curl);
curl_close($curl);

if (false === preg_match('/name="tuto-csrf" value="([a-z0-9]+)"/', $login_page)) {
    $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
    clean_and_die();
}
$csrf_token = preg_replace('/.+name="tuto-csrf" value="([a-z0-9]+)".+/s', '$1', $login_page);
$cli->inline('[ ')->green()->inline('OK')->white()->out(' ]');



// POST Login
$cli->inline(str_pad('Logging to tuto.com', PADDING));
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, 'https://fr.tuto.com/connexion/?redirect=http://fr.tuto.com/');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_COOKIESESSION, true);
curl_setopt($curl, CURLOPT_COOKIEJAR, OUTPUT_DIR.'tmp_cookies.txt');
curl_setopt($curl, CURLOPT_COOKIEFILE, OUTPUT_DIR.'tmp_cookies.txt');
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, [
    'tuto-csrf' => $csrf_token,
    'login'     => urlencode($cli->arguments->get('user')),
    'pwd'       => urlencode($cli->arguments->get('password')),
    'submit_login' => 'se+connecter',
]);
curl_setopt($curl, CURLOPT_HTTPHEADER, $browser_headers);
curl_setopt($curl, CURLOPT_HEADER, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
$result_login = curl_exec($curl);
curl_close($curl);


if (false !== strpos($result_login, 'Il y a eu des erreurs lors de la validation')) {
    $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
    $cli->red()->out('Please verify the email and the password!');
    clean_and_die();
}
$cli->inline('[ ')->green()->inline('OK')->white()->out(' ]');



// Tutorials
$video_links = [];
$cli->blue()->out('Downloading '.count($input_ids).' tutorials');
foreach ($input_ids AS $input_id) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'http://fr.tuto.com/compte/achats/video/'.$input_id.'/player/');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_COOKIESESSION, true);
    curl_setopt($curl, CURLOPT_COOKIEJAR, OUTPUT_DIR.'tmp_cookies.txt');
    curl_setopt($curl, CURLOPT_COOKIEFILE, OUTPUT_DIR.'tmp_cookies.txt');
    curl_setopt($curl, CURLOPT_HTTPHEADER, $browser_headers);
    $tutorial_page = curl_exec($curl);
    curl_close($curl);

    $tutorial_title = preg_replace('/.+span class="title-22">([^<]+)<\/span>.+/s', '$1', $tutorial_page);
    $tutorial_title_sanitized = preg_replace('/.+\/([^,]+),'.$input_id.'\.html.+/s', '$1', $tutorial_page);
    $tutorial_playlist = json_decode(preg_replace('/.+var playlists = ([^;]+);.+/s', '$1', $tutorial_page));

    preg_match_all('/data-hash="([^"]+)" data-slug="([^"]*)">/U', $tutorial_page, $chapters_preg);

    if (0 === count($chapters_preg[0])) {
        $cli->inline(str_pad('Downloading id '.$input_id, PADDING));
        $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
        $cli->red()->out('No valid video found here');
        continue 1;
    }

    $chapters_uid = [];
    for ($j = 0, $count = count($chapters_preg[0]); $j < $count; ++$j)
        $chapters_uid[$chapters_preg[1][$j]] = $chapters_preg[2][$j];

    if (1 === count($chapters_uid)) {
        $cli->inline(str_pad('['.$input_id.'] '.$tutorial_title, PADDING));
        $chapter_uid = array_keys($chapters_uid)[0];
        $chapter_title = (!empty(array_values($chapters_uid)[0])) ? array_values($chapters_uid)[0] : $tutorial_title_sanitized;

        if (empty($tutorial_playlist->{$chapter_uid}->urls->hls)) {
            $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
            continue 1;
        }

        $result_hls = exec('./hls-fetch -f --playlist '.escapeshellarg($tutorial_playlist->{$chapter_uid}->urls->hls).' --output='.escapeshellarg(OUTPUT_DIR.$chapter_title.'.ts'));
        if (preg_match('/\d+\/\d+/', $result_hls))
            $cli->inline('[ ')->green()->inline('OK')->white()->out(' ]');
        else
            $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
    }
    else {
        $cli->out('['.$input_id.'] '.$tutorial_title.' ('.count($chapters_uid).' chapters)');

        is_dir(OUTPUT_DIR.$tutorial_title_sanitized) || mkdir(OUTPUT_DIR.$tutorial_title_sanitized);
        $j = 0;
        foreach ($chapters_uid AS $chapter_uid => $chapter_title) {
            ++$j;
            $cli->inline(str_pad(' - '.$chapter_title, PADDING));

            if (empty($tutorial_playlist->{$chapter_uid}->urls->hls)) {
                $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
                continue 1;
            }

            $result_hls = exec('./hls-fetch -f --playlist '.escapeshellarg($tutorial_playlist->{$chapter_uid}->urls->hls).' --output='.escapeshellarg(OUTPUT_DIR.$tutorial_title_sanitized.'/'.$j.'-'.$chapter_title.'.ts'));
            if (preg_match('/\d+\/\d+/', $result_hls))
                $cli->inline('[ ')->green()->inline('OK')->white()->out(' ]');
            else
                $cli->inline('[')->red()->inline('FAIL')->white()->out(']');
        }
    }
}



// Cleaning
clean_and_die();
function clean_and_die() {
    global $cli;
    !is_file(OUTPUT_DIR.'tmp_cookies.txt') || unlink(OUTPUT_DIR.'tmp_cookies.txt');
    $cli->blue()->out('Cleaning and quitting');
    die;
}
