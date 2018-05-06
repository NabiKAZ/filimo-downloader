<?php
echo "Filimo Downloader - version 0.1.0 - Copyright 2017\n";
echo "By Nabi KaramAliZadeh <www.nabi.ir> <nabikaz@gmail.com>\n";
echo "Signup here: http://filimo.com/invite/NabiKAZ/a8ca\n";
echo "Project link: https://github.com/NabiKAZ/filimo-downloader\n";
echo "===========================================================\n";

$config_path = 'config/';
$base_path = 'download/';
$proxy = '';

login:
@mkdir($config_path);
$contents = get_contents('https://www.filimo.com/');
preg_match('/username: \'(.*?)\',/', $contents, $match);
if ($match[1] != '0') {
    echo "Your username: $match[1]\n";
} else {
    echo "> Login to filimo.com\n";
    echo "Input username: ";
    $username = trim(fgets(STDIN));
    $contents = get_contents('https://www.filimo.com/etc/api/signinstep1/usernamemo/' . $username . '/devicetype/desktop');
    $response = json_decode($contents);
    if ($response->signinstep1->type != 'success') {
        die("Error: " . $response->signinstep1->value . "\n");
    } else {
        $tempid = @$response->signinstep1->tempid;
        echo "Input password: ";
        $password = trim(fgets(STDIN));
        echo "Logging...\n";
        $contents = get_contents('https://www.filimo.com/etc/api/signinstep2/usernamemo/' . $username . '/codepass/' . $password . '/tempid/' . $tempid . '/usernamemotype/username/');
        $response = json_decode($contents);
        if ($response->signinstep2->type != 'success') {
            die("Error: " . $response->signinstep2->value . "\n");
        } else {
            echo "Logged In.\n";
            echo "IMPORTANT: Your private cookies is stored in the '$config_path' directory, Be careful about its security!\n";
            echo "===========================================================\n";
            goto login;
        }
    }
}
echo "===========================================================\n";

echo "Input Video ID: ";
$video_id = trim(fgets(STDIN));

$contents = get_contents('https://www.filimo.com/m/' . $video_id);
preg_match('/"title_movie">\r\n.*>(.*?)<\/span>/', $contents, $match);
if (!isset($match[1])) {
    die("\nSorry! Not found any video with this ID.\n");
}
$title = $match[1];
echo "Title: $title\n";

preg_match('/"movie-time">.*?([0-9]+).*?<\/div>/', $contents, $match);
$duration = $match[1];
echo "Duration: $duration min\n";

preg_match('/rateit-current-rate-.*">(.*?)<\/i>/', $contents, $match);
$rate = $match[1];
echo "Rate: $rate / 5\n";

preg_match('/<span class="cover " >\n.*<img src="(.*?)"/', $contents, $match);
$cover = $match[1];

echo "===========================================================\n";

$contents_watch = get_contents('https://www.filimo.com/w/' . $video_id);

$subtitle = null;
preg_match('/{file\: \"(.*?\/subtitle\/.*?)\",/', $contents_watch, $match_subtitle);
if (isset($match_subtitle[1])) {
    $subtitle = $match_subtitle[1];
}

preg_match('/{file: "([^{]*?)\?dim=" \+ width \+ "," \+ height, type:"video\/mp4"}/', $contents_watch, $match);
$contents = get_contents($match[1]);
preg_match_all('/#((?:[0-9])+(?:p|k)+)\n(?:.*)BANDWIDTH=(.*),RESOLUTION=(.*)\n(.*)/', $contents, $matches);

$qualities = array();
foreach ($matches[1] as $key => $value) {
    $qualities[] = array(
        'quality' => $matches[1][$key],
        'bandwidth' => $matches[2][$key],
        'resolution' => $matches[3][$key],
        'url' => $matches[4][$key],
    );
}

$qualities = multisort($qualities, 'bandwidth', SORT_ASC);
$qualities = array_combine(range(1, count($qualities)), array_values($qualities));

echo "Select Quality:\n";
foreach ($qualities as $key => $value) {
    echo $key . ") QUALITY=" . $value['quality'] . " - BANDWIDTH=" . $value['bandwidth'] . " - RESOLUTION=" . $value['resolution'] . "\n";
}
echo "Input option number: ";
$input = trim(fgets(STDIN));

@mkdir($base_path);
$file_name = $video_id . '_' . $qualities[$input]['quality'];
$video_file = $base_path . $file_name . '.mp4';
if (isset($proxy) && $proxy) {
    $cmd_proxy = '-http_proxy http://' . $proxy;
} else {
    $cmd_proxy = '';
}
$cmd = 'ffmpeg ' . $cmd_proxy . ' -i "' . $qualities[$input]['url'] . '" -y "' . $video_file . '"';
$log_file = $base_path . $file_name . '.log';
$info_file = $base_path . $file_name . '.info';
$cover_file = $base_path . $file_name . '.jpg';
$subtitle_file = $base_path . $file_name . '.srt';

$info = array();
$info['video_id'] = $video_id;
$info['title'] = $title;
$info['duration'] = $duration;
$info['rate'] = $rate;
$info['quality'] = $qualities[$input]['quality'];
$info['bandwidth'] = $qualities[$input]['bandwidth'];
$info['resolution'] = $qualities[$input]['resolution'];
$info = json_encode($info);
file_put_contents($info_file, $info);

if ($cover) {
	file_put_contents($cover_file, get_contents($cover));
}

if ($subtitle) {
    file_put_contents($subtitle_file, normalize_subtitle(get_contents($subtitle)));
}

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    pclose(popen('start /B ' . $cmd . '<nul >nul 2>"' . $log_file . '"', 'r'));
} else {
    shell_exec($cmd . '</dev/null >/dev/null 2>"' . $log_file . '" &');
}

echo "===========================================================\n";
echo "Video file: $video_file\n";
echo "Cover file: " . ($cover ? $cover_file : 'N/A') . "\n";
echo "Subtitle file: " . ($subtitle ? $subtitle_file : 'N/A') . "\n";
echo "Log file: $log_file\n";
echo "Info file: $info_file\n";
echo "Start downloading in the background...\n";
echo "You can see stats download with 'stats.php' in the web page.\n";
echo "For stop process, kill it.\n";
echo "Bye!\n";


function get_contents($url)
{
    global $config_path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $config_path . 'cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, $config_path . 'cookies.txt');
    if (isset($proxy) && $proxy) {
        list($proxyIp, $proxyPort) = explode(':', $proxy);
        curl_setopt($ch, CURLOPT_PROXY, $proxyIp);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language:en-US,en;q=0.9',
        'Cache-Control:max-age=0',
        'Connection:keep-alive',
        'Host:www.filimo.com',
        'Upgrade-Insecure-Requests:1',
        'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Safari/537.36',
    ));
    return curl_exec($ch);
}

function multisort($mdarray, $mdkey, $sort = SORT_ASC)
{
    foreach ($mdarray as $key => $row) {
        // replace 0 with the field's index/key
        $dates[$key] = $row[$mdkey];
    }
    array_multisort($dates, $sort, $mdarray);
    return $mdarray;
}

function normalize_subtitle($sub)
{
	$sub = str_replace("WEBVTT\r\n", '', $sub);
	return $sub;
}
