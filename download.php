<?php
echo "Filimo Downloader - version 0.1.0 - Copyright 2017-2019\n";
echo "By Nabi KaramAliZadeh <www.nabi.ir> <nabikaz@gmail.com>\n";
echo "Signup here: http://filimo.com/invite/NabiKAZ/a8ca\n";
echo "Project link: https://github.com/NabiKAZ/filimo-downloader\n";
echo "===========================================================\n";

$config_path = 'config/';
$base_path = 'download/';
$proxy = '';

login:
@mkdir($config_path);

//check logged in user
$contents = get_contents('https://www.filimo.com/');
preg_match('/username.*= \'(.*?)\';/', $contents, $match);

//you already logged in
if (isset($match[1]) && $match[1] != '') {
    echo "Your username: $match[1]\n";
} else {
	
	//login and get token
	echo "> Login to filimo.com\n";
	$contents = get_contents('https://www.filimo.com/_/login');
	preg_match('/guid: "(.*?)",/', $contents, $match);
	if (!isset($match[1])) {
		die("Error: Expire auth token.\n");
	}
	$guid = $match[1];
	
	//auth and get temp id
	$post_data = array('guid' => $guid);
	$contents = get_contents('https://www.filimo.com/_/api/fa/v1/user/Authenticate/auth', $post_data);
	$contents = json_decode($contents);
	$temp_id = $contents->data->attributes->temp_id;
	
	//get username
    echo "Input username: ";
    $username = trim(fgets(STDIN));
	
	//send username
	$post_data = array(
		'account' => $username,
		'guid' => $guid,
		'temp_id' => $temp_id,
	);
	$contents = get_contents('https://www.filimo.com/_/api/fa/v1/user/Authenticate/signin_step1', $post_data);
	$contents = json_decode($contents);
	if (isset($contents->errors[0]->detail)) {
		die("Error: " . $contents->errors[0]->detail . "\n");
    }
	$temp_id = $contents->data->attributes->temp_id;
	
	//get password
	echo "Input password: ";
	$password = trim(fgets(STDIN));
	echo "Logging...\n";
	
	//post username and password
	$post_data = array(
		'account' => $username,
		'code' => $password,
		'codepass_type' => 'pass',
		'guid' => $guid,
		'temp_id' => $temp_id,
	);
	$contents = get_contents('https://www.filimo.com/_/api/fa/v1/user/Authenticate/signin_step2', $post_data);
	$contents = json_decode($contents);
	
	//error handle for login with sms
	if (isset($contents->errors[0]->detail) && $contents->errors[0]->detail = 'شما مجاز به ورود با رمز نمی باشید لطفا با شماره موبایل خود وارد شوید') {
		
		//post username and get mobile
		$post_data = array(
			'account' => $username,
			'codepass_type' => 'otp',
			'guid' => $guid,
			'temp_id' => $temp_id,
		);
		$contents = get_contents('https://www.filimo.com/_/api/fa/v1/user/Authenticate/signin_step1', $post_data);
		$contents = json_decode($contents);
		if (isset($contents->errors[0]->detail)) {
			die("Error: " . $contents->errors[0]->detail . "\n");
		}
		$temp_id = $contents->data->attributes->temp_id;
		$mobile = $contents->data->attributes->mobile_valid;
		
		//get sms code
		echo "Input SMS code sent to $mobile: ";
		$code = trim(fgets(STDIN));
		echo "Logging...\n";
		
		//post sms code
		$post_data = array(
			'account' => $username,
			'code' => $code,
			'codepass_type' => 'otp',
			'guid' => $guid,
			'temp_id' => $temp_id,
		);
		$contents = get_contents('https://www.filimo.com/_/api/fa/v1/user/Authenticate/signin_step2', $post_data);
		$contents = json_decode($contents);
		if (isset($contents->errors[0]->detail)) {
			die("Error: " . $contents->errors[0]->detail . "\n");
		}
	
	//general errors handling of login
	} elseif (isset($contents->errors[0]->detail)) {
		die("Error: " . $contents->errors[0]->detail . "\n");
	}
	
	//logged in
	echo "\n";
	echo "Logged In.\n";
	echo "IMPORTANT: Your private cookies is stored in the '$config_path' directory, Be careful about its security!\n";
	echo "===========================================================\n";
	
	//try again and check login
	goto login;
}
echo "===========================================================\n";

echo "Input Video ID: ";
$video_id = trim(fgets(STDIN));

$contents = get_contents('https://www.filimo.com/m/' . $video_id);
$contents = str_replace(array("\r\n", "\n\r", "\r", "\n"), '', $contents);

preg_match('/movie\.nameFa.*?= "(.*?)";/', $contents, $match);
if (!isset($match[1])) {
    die("\nSorry! Not found any video with this ID.\n");
}
$title = $match[1];
echo "Title: $title\n";

preg_match('/movie\.totalDuration.*?= "(.*?)";/', $contents, $match);
$duration = round($match[1] / 60);
echo "Duration: $duration min\n";

preg_match('/movie\.filimoRate.*?= "(.*?)";/', $contents, $match);
$rate = @$match[1];
if ($rate) echo "Rate: $rate%\n";

preg_match('/movie\.poster.*?= "(.*?)";/', $contents, $match);
$cover = $match[1];

echo "===========================================================\n";

$contents_watch = get_contents('https://www.filimo.com/w/' . $video_id);

preg_match('/var player_data = (.*?);\n/', $contents_watch, $match);
$match = end($match);
$match = json_decode($match);

$subtitle = @$match->tracks[0]->src;

foreach (@$match->multiSRC as $objecturl) {
	if ($objecturl[0]->type == 'application/vnd.apple.mpegurl') {
		$video_url = $objecturl[0]->src;
		break;
	}
}

if (!$video_url) {
	die("Error: Can not fetch video URL.\n");
}

$contents = get_contents($video_url);
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
$cmd = 'ffmpeg ' . $cmd_proxy . ' -i "' . $qualities[$input]['url'] . '" -c:v copy -c:a copy -bsf:a aac_adtstoasc -y "' . $video_file . '"';
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


function get_contents($url, $data = null)
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
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
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
