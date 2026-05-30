<?php
echo "Filimo Downloader - version 2.0.0 - Copyright 2017-2026\n";
echo "By Nabi KaramAliZadeh <www.nabi.ir> <nabikaz60@gmail.com>\n";
echo "Signup here: http://filimo.com/invite/NabiKAZ/a8ca\n";
echo "Project link: https://github.com/NabiKAZ/filimo-downloader\n";
echo "===========================================================\n";

$config_path = 'config/';
$base_path = 'download/';
$proxy = '';

login:
@mkdir($config_path);

//check logged in user via uxEvent config endpoint (returns full user info)
$logged_username = '';
$contents = get_contents('https://www.filimo.com/api/fa/v1/web/config/uxEvent?ref=signin');
$user_info = json_decode($contents);
if (isset($user_info->data->user->username) && $user_info->data->user->username != '' && $user_info->data->user->username != 'null') {
	$logged_username = $user_info->data->user->username;
	$logged_name = isset($user_info->data->user->name) ? $user_info->data->user->name : '';
	$logged_mobile = isset($user_info->data->user->mobile) ? $user_info->data->user->mobile : '';
	$logged_email = isset($user_info->data->user->email) ? $user_info->data->user->email : '';
}

//you already logged in
if ($logged_username !== '') {
    echo "Your username: $logged_username\n";
    if (!empty($logged_name) && $logged_name != $logged_username) echo "Name: $logged_name\n";
    if (!empty($logged_mobile)) echo "Mobile: $logged_mobile\n";
    if (!empty($logged_email)) echo "Email: $logged_email\n";
} else {
	
	//login and get token
	echo "> Login to filimo.com\n";

	//generate a GUID (UUID v4, upper-case) on client side
	$guid = strtoupper(sprintf(
		'%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
		mt_rand(0, 0xffff), mt_rand(0, 0xffff),
		mt_rand(0, 0xffff),
		mt_rand(0, 0x0fff) | 0x4000,
		mt_rand(0, 0x3fff) | 0x8000,
		mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
	));

	//visit signin page to set initial cookies
	get_contents('https://www.filimo.com/signin');

	//fetch ui_config (mimics browser behavior)
	get_contents('https://www.filimo.com/api/fa/v1/user/Authenticate/ui_config?guid=' . $guid);

	//auth and get temp id
	$post_data = array('guid' => $guid);
	$contents = get_contents('https://www.filimo.com/api/fa/v1/user/Authenticate/auth', $post_data);
	$contents = json_decode($contents);
	if (!isset($contents->data->attributes->temp_id)) {
		die("Error: Cannot get auth token.\n");
	}
	$temp_id = $contents->data->attributes->temp_id;
	
	//get username (mobile number)
    echo "Input username (mobile number, e.g. 0912xxxxxxx): ";
    $username = trim(fgets(STDIN));
	
	//send username
	$post_data = array(
		'account' => $username,
		'temp_id' => $temp_id,
		'guid' => $guid,
	);
	$contents = get_contents('https://www.filimo.com/api/fa/v1/user/Authenticate/signin_step1', $post_data);
	$contents = json_decode($contents);
	if (isset($contents->errors[0]->detail)) {
		die("Error: " . $contents->errors[0]->detail . "\n");
    }
	$temp_id = $contents->data->attributes->temp_id;
	$code_pass_type = isset($contents->data->attributes->code_pass_type) ? $contents->data->attributes->code_pass_type : 'pass';
	
	//if server already decided otp (no password set), skip directly to otp flow
	if ($code_pass_type === 'otp') {
		$mobile = isset($contents->data->attributes->mobile_valid) ? $contents->data->attributes->mobile_valid : $username;
		echo "Input SMS code sent to $mobile: ";
		$code = trim(fgets(STDIN));
		echo "Logging...\n";

		$post_data = array(
			'temp_id' => $temp_id,
			'account' => $username,
			'codepass_type' => 'otp',
			'code' => $code,
			'guid' => $guid,
		);
		$contents = get_contents('https://www.filimo.com/api/fa/v1/user/Authenticate/signin_step2', $post_data);
		$contents = json_decode($contents);
		if (isset($contents->errors[0]->detail)) {
			die("Error: " . $contents->errors[0]->detail . "\n");
		}
	} else {
		//get password
		echo "Input password: ";
		$password = trim(fgets(STDIN));
		echo "Logging...\n";

		//post username and password
		$post_data = array(
			'temp_id' => $temp_id,
			'account' => $username,
			'codepass_type' => 'pass',
			'code' => $password,
			'guid' => $guid,
		);
		$contents = get_contents('https://www.filimo.com/api/fa/v1/user/Authenticate/signin_step2', $post_data);
		$contents = json_decode($contents);

		//error handle for login with sms (server forces OTP)
		if (isset($contents->errors[0]->detail) && (
			(isset($contents->errors[0]->type_info) && $contents->errors[0]->type_info == 'force_mobile_signin') ||
			strpos($contents->errors[0]->detail, 'پیامک') !== false ||
			strpos($contents->errors[0]->detail, 'موبایل') !== false
		)) {

			//request otp
			$post_data = array(
				'temp_id' => $temp_id,
				'account' => $username,
				'codepass_type' => 'otp',
				'guid' => $guid,
			);
			$contents = get_contents('https://www.filimo.com/api/fa/v1/user/Authenticate/signin_step1', $post_data);
			$contents = json_decode($contents);
			if (isset($contents->errors[0]->detail)) {
				die("Error: " . $contents->errors[0]->detail . "\n");
			}
			$temp_id = $contents->data->attributes->temp_id;
			$mobile = isset($contents->data->attributes->mobile_valid) ? $contents->data->attributes->mobile_valid : $username;

			//get sms code
			echo "Input SMS code sent to $mobile: ";
			$code = trim(fgets(STDIN));
			echo "Logging...\n";

			//post sms code
			$post_data = array(
				'temp_id' => $temp_id,
				'account' => $username,
				'codepass_type' => 'otp',
				'code' => $code,
				'guid' => $guid,
			);
			$contents = get_contents('https://www.filimo.com/api/fa/v1/user/Authenticate/signin_step2', $post_data);
			$contents = json_decode($contents);
			if (isset($contents->errors[0]->detail) && (!isset($contents->errors[0]->type_info) || $contents->errors[0]->type_info != 'get_max_tokens')) {
				die("Error: " . $contents->errors[0]->detail . "\n");
			}

		//general errors handling of login
		} elseif (isset($contents->errors[0]->detail) && (!isset($contents->errors[0]->type_info) || $contents->errors[0]->type_info != 'get_max_tokens')) {
			die("Error: " . $contents->errors[0]->detail . "\n");
		}
	}

	//handle "too many active sessions" by listing devices and letting user choose which to revoke
	if (isset($contents->errors[0]->type_info) && $contents->errors[0]->type_info == 'get_max_tokens') {
		$session_list_uri = $contents->errors[0]->uri;
		echo "Too many active sessions on your account.\n";

		$autologin_uri = null;
		while ($autologin_uri === null) {
			//fetch current session list
			$sessions = get_contents('https://www.filimo.com' . $session_list_uri . '?guid=' . $guid);
			$sessions = json_decode($sessions);

			if (!isset($sessions->data->attributes->data) || count((array)$sessions->data->attributes->data) == 0) {
				die("Error: Could not fetch session list.\n");
			}

			//build an indexed list of sessions
			$session_items = array();
			foreach ($sessions->data->attributes->data as $token => $session) {
				$session_items[] = array('token' => $token, 'session' => $session);
			}

			//show devices
			echo "===========================================================\n";
			echo "Connected devices (you must revoke at least one to continue):\n";
			foreach ($session_items as $i => $item) {
				$s = $item['session'];
				$label = array();
				if (isset($s->model)) $label[] = $s->model;
				if (isset($s->os)) $label[] = $s->os;
				if (isset($s->dt_title)) $label[] = $s->dt_title;
				$line = ($i + 1) . ') ' . implode(' - ', $label);
				if (isset($s->ip)) $line .= ' | IP: ' . $s->ip;
				if (isset($s->last_access)) $line .= ' | Last: ' . $s->last_access;
				echo $line . "\n";
			}
			echo "Input device number to revoke: ";
			$rev_input = trim(fgets(STDIN));
			$rev_index = intval($rev_input) - 1;

			if (!isset($session_items[$rev_index])) {
				echo "Invalid selection, try again.\n";
				continue;
			}

			$chosen = $session_items[$rev_index]['session'];
			if (!isset($chosen->revoke_link)) {
				echo "This device cannot be revoked, try another.\n";
				continue;
			}

			echo "Revoking session: " . (isset($chosen->model) ? $chosen->model : $session_items[$rev_index]['token']) . " ...\n";
			$rv = get_contents('https://www.filimo.com' . $chosen->revoke_link . '?guid=' . $guid);
			$rv = json_decode($rv);
			if (isset($rv->data->attributes->uri)) {
				$autologin_uri = $rv->data->attributes->uri;
			} else {
				echo "Revoke did not return autologin link, refreshing list...\n";
			}
		}

		echo "Performing autologin...\n";
		$al = get_contents('https://www.filimo.com' . $autologin_uri . '?guid=' . $guid);
		$al = json_decode($al);
		if (!isset($al->data->attributes->token)) {
			die("Error: Autologin failed.\n");
		}
		//visit the final redirect URI to ensure all login cookies are set
		if (isset($al->data->attributes->uri)) {
			get_contents($al->data->attributes->uri);
		}
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

//fetch all movie/watch data from the new API endpoint
$contents = get_contents('https://www.filimo.com/api/fa/v1/movie/watch/watch/uid/' . $video_id);
$watch = json_decode($contents);

if (!isset($watch->data->attributes)) {
	//check for API error message
	if (isset($watch->errors[0]->detail)) {
		die("\nError: " . $watch->errors[0]->detail . "\n");
	}
	die("\nSorry! Not found any video with this ID.\n");
}
$attr = $watch->data->attributes;

$title = isset($attr->movie_title) ? $attr->movie_title : (isset($attr->movie_name) ? $attr->movie_name : '');
if ($title === '') {
    die("\nSorry! Not found any video with this ID.\n");
}
echo "Title: $title\n";

$duration = isset($attr->duration) ? round($attr->duration / 60) : 0;
echo "Duration: $duration min\n";

$rate = '';
if (isset($attr->rate->percent)) {
    $rate = $attr->rate->percent;
    if ($rate) echo "Rate: $rate%\n";
}

$cover = isset($attr->poster) ? $attr->poster : '';

echo "===========================================================\n";

//subtitle and video URL come from the same watch API response
$subtitle = '';
if (isset($attr->tracks) && is_array($attr->tracks) && count($attr->tracks) > 0) {
	$subtitle = $attr->tracks[0]->src;
}

//multiSRC is now an object: { value: [ { src, type } ], Count: N }
$video_url = '';
$multi_src = array();
if (isset($attr->multiSRC->value) && is_array($attr->multiSRC->value)) {
	$multi_src = $attr->multiSRC->value;
} elseif (isset($attr->multiSRC) && is_array($attr->multiSRC)) {
	//backward compatibility with old array-of-arrays format
	foreach ($attr->multiSRC as $group) {
		if (is_array($group)) {
			foreach ($group as $item) { $multi_src[] = $item; }
		} else {
			$multi_src[] = $group;
		}
	}
}
foreach ($multi_src as $objecturl) {
	if (isset($objecturl->type) && $objecturl->type == 'application/vnd.apple.mpegurl') {
		$video_url = $objecturl->src;
		break;
	}
}

if (!$video_url) {
	die("Error: Can not fetch video URL.\n");
}

$contents = get_contents($video_url);
preg_match_all('/#((?:[0-9])+(?:p|k)+)\n.*?BANDWIDTH=([0-9]+).*?RESOLUTION=([0-9]+x[0-9]+).*?\n([^\n]+)/', $contents, $matches);

$qualities = array();
foreach ($matches[1] as $key => $value) {
    $qualities[] = array(
        'quality' => $matches[1][$key],
        'bandwidth' => (int)$matches[2][$key],
        'resolution' => $matches[3][$key],
        'url' => trim($matches[4][$key]),
    );
}

$qualities = multisort($qualities, 'bandwidth', SORT_DESC);
$qualities = array_combine(range(1, count($qualities)), array_values($qualities));

echo "Select Quality:\n";
foreach ($qualities as $key => $value) {
    echo $key . ") QUALITY=" . $value['quality'] . " - BANDWIDTH=" . $value['bandwidth'] . " - RESOLUTION=" . $value['resolution'] . "\n";
}
echo "Input option number: ";
$input = trim(fgets(STDIN));

$cmd_audio = '';
preg_match_all('/#EXT-X-MEDIA:TYPE=AUDIO.*?LANGUAGE="(.*?)".*?URI="(.*?)"/', $contents, $audios, PREG_SET_ORDER, 0);
if ($audios) {
	echo "Select Audio:\n";
	foreach ($audios as $key => $value) {
		echo $key+1 . ") LANGUAGE = " . $value[1] . "\n";
	}
	echo "Input option number: ";
	$a_input = trim(fgets(STDIN));
	$audio_key = $audios[$a_input-1][1];
	$audio_url = $audios[$a_input-1][2];
	$cmd_audio = ' -i "' . $audio_url . '" -map 0:v:0 -map 1:a:0';
}

@mkdir($base_path);
$file_name = $video_id . '_' . $qualities[$input]['quality'];
$video_file = $base_path . $file_name . '.mp4';
if (isset($proxy) && $proxy) {
    $cmd_proxy = '-http_proxy http://' . $proxy;
} else {
    $cmd_proxy = '';
}
$cmd = 'ffmpeg ' . $cmd_proxy . ' -i "' . $qualities[$input]['url'] . '"' . $cmd_audio . ' -c:v copy -c:a copy -bsf:a aac_adtstoasc -y "' . $video_file . '"';
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
    $is_win = true;
} else {
    $is_win = false;
}

echo "===========================================================\n";
echo "Video file: $video_file\n";
echo "Cover file: " . ($cover ? $cover_file : 'N/A') . "\n";
echo "Subtitle file: " . ($subtitle ? $subtitle_file : 'N/A') . "\n";
echo "Log file: $log_file\n";
echo "Info file: $info_file\n";
echo "===========================================================\n";
echo "Controls:  [p] Pause/Resume    [s] Stop (keep partial file)\n";
echo "Starting download...\n\n";

// Run ffmpeg in foreground with live progress and interactive control.
// ffmpeg writes machine-readable progress to a file (reliable on both Windows
// and Unix, unlike non-blocking pipe reads), while we keep stdin open so we can
// send 'q' to ask ffmpeg to stop gracefully.
$progress_file = $base_path . $file_name . '.progress';
@unlink($progress_file);
$ff_cmd = 'ffmpeg ' . $cmd_proxy . ' -hide_banner -loglevel error'
    . ' -i "' . $qualities[$input]['url'] . '"' . $cmd_audio
    . ' -c:v copy -c:a copy -bsf:a aac_adtstoasc -y "' . $video_file . '"'
    . ' -progress "' . $progress_file . '"';

$descriptors = array(
    0 => array('pipe', 'r'), // stdin  (we control)
    1 => array('file', $log_file, 'w'), // stdout -> log file
    2 => array('file', $log_file, 'w'), // stderr -> log file
);

$process = proc_open($ff_cmd, $descriptors, $pipes);

if (!is_resource($process)) {
    echo "Failed to start ffmpeg.\n";
    exit(1);
}

// Prepare keyboard input (non-blocking) for STDIN.
$paused = false;
$stopped = false;

// Single-key (no Enter) is available on PHP 7.4+ via sapi_windows_kbhit.
$win_kbhit = $is_win && function_exists('sapi_windows_kbhit') && function_exists('sapi_windows_getch');

// On older Windows PHP (< 7.4), non-blocking STDIN does not work on the console:
// fread(STDIN) blocks the whole loop, freezing the progress line. To keep progress
// live, we spawn a tiny child PHP that blocks on STDIN and appends each key to a
// flag file, which the main loop polls without blocking. (Key still needs Enter.)
$key_file = $base_path . $file_name . '.keys';
$key_pos = 0;
$key_reader = null;
@unlink($key_file);

if (!$is_win) {
    // Put terminal in raw, non-blocking mode on Unix for single-key controls.
    shell_exec('stty -icanon -echo min 0 time 0 < /dev/tty 2>/dev/null');
    stream_set_blocking(STDIN, false);
} elseif (!$win_kbhit) {
    // Spawn a PowerShell side-reader that reads single keys (no Enter, no echo)
    // via [Console]::ReadKey and appends each to a flag file the main loop polls.
    $ps_reader = '$f=\'' . str_replace('\'', '\'\'', realpath_or($base_path) . $file_name . '.keys') . '\';'
        . 'while($true){$k=[Console]::ReadKey($true);'
        . 'Add-Content -Path $f -Value $k.KeyChar -NoNewline;}';
    $reader_ps1 = $base_path . $file_name . '.reader.ps1';
    @file_put_contents($reader_ps1, $ps_reader);
    $reader_desc = array(0 => STDIN, 1 => array('file', 'NUL', 'w'), 2 => array('file', 'NUL', 'w'));
    $key_reader = @proc_open('powershell -NoProfile -ExecutionPolicy Bypass -File "' . $reader_ps1 . '"', $reader_desc, $reader_pipes);
}

$total_us = $duration > 0 ? $duration * 60 * 1000000 : 0; // duration is in minutes
$progress_pos = 0; // byte offset already read from progress file

while (true) {
    $status = proc_get_status($process);
    if (!$status['running']) {
        break;
    }

    // Read keyboard control.
    if ($is_win && !$win_kbhit) {
        $key = read_key_file($key_file, $key_pos);
    } else {
        $key = read_key($win_kbhit);
    }
    if ($key === 's') {
        // If paused, resume first so ffmpeg can process the quit request.
        if ($paused) {
            $pid = $status['pid'];
            if ($is_win) { win_suspend_resume($pid, false); }
            else { @exec('kill -CONT ' . $pid . ' 2>/dev/null'); }
            $paused = false;
        }
        // Ask ffmpeg to finish gracefully (writes moov atom / proper end).
        @fwrite($pipes[0], 'q');
        @fflush($pipes[0]);
        $stopped = true;
        echo "\nStopping...\n";
        $waited = 0;
        while (proc_get_status($process)['running'] && $waited < 50) {
            usleep(100000);
            $waited++;
        }
        if (proc_get_status($process)['running']) {
            proc_terminate($process, 9);
        }
        break;
    } elseif ($key === 'p') {
        $paused = !$paused;
        $pid = $status['pid'];
        if ($is_win) {
            // Suspend/resume the ffmpeg process on Windows via NtSuspendProcess/NtResumeProcess.
            win_suspend_resume($pid, $paused);
            echo $paused ? "\n[Paused] Press [p] to resume.\n" : "\n[Resumed]\n";
        } else {
            if ($paused) {
                @exec('kill -STOP ' . $pid . ' 2>/dev/null');
                echo "\n[Paused] Press [p] to resume.\n";
            } else {
                @exec('kill -CONT ' . $pid . ' 2>/dev/null');
                echo "\n[Resumed]\n";
            }
        }
    }

    // Read newly appended progress data from the file.
    if (!$paused && is_file($progress_file)) {
        clearstatcache(true, $progress_file);
        $fh = @fopen($progress_file, 'rb');
        if ($fh) {
            fseek($fh, $progress_pos);
            $progress = array();
            $have_block = false;
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $progress[$k] = $v;
                    if ($k === 'progress') {
                        $have_block = true;
                    }
                }
            }
            $progress_pos = ftell($fh);
            fclose($fh);

            if ($have_block) {
                render_progress($progress, $total_us);
            }
        }
    }

    usleep(200000);
}

@fclose($pipes[0]);
$exit_code = proc_close($process);
@unlink($progress_file);

// Stop the side key-reader (Windows < 7.4) and clean up its files.
if (is_resource($key_reader)) {
    @proc_terminate($key_reader, 9);
    @proc_close($key_reader);
}
@unlink($key_file);
if (isset($reader_php)) {
    @unlink($reader_php);
}
if (isset($reader_ps1)) {
    @unlink($reader_ps1);
}

if (!$is_win) {
    // Restore terminal.
    shell_exec('stty sane < /dev/tty 2>/dev/null');
}

echo "\n===========================================================\n";
if ($stopped) {
    echo "Download stopped by user. Partial file: $video_file\n";
} elseif ($exit_code === 0 || file_exists($video_file)) {
    echo "Download finished: $video_file\n";
} else {
    echo "ffmpeg exited with code $exit_code. See log: $log_file\n";
}
echo "Bye!\n";


function render_progress($progress, $total_us)
{
    // ffmpeg may report out_time_us, out_time_ms (also microseconds), or out_time (HH:MM:SS.ms).
    $out_us = 0;
    if (isset($progress['out_time_us']) && is_numeric($progress['out_time_us'])) {
        $out_us = (int)$progress['out_time_us'];
    } elseif (isset($progress['out_time_ms']) && is_numeric($progress['out_time_ms'])) {
        $out_us = (int)$progress['out_time_ms'];
    } elseif (isset($progress['out_time']) && strpos($progress['out_time'], ':') !== false) {
        $parts = explode(':', $progress['out_time']);
        if (count($parts) === 3) {
            $out_us = (int)(((int)$parts[0] * 3600 + (int)$parts[1] * 60 + (float)$parts[2]) * 1000000);
        }
    }
    $size = isset($progress['total_size']) && is_numeric($progress['total_size']) ? (int)$progress['total_size'] : 0;
    $speed = isset($progress['speed']) ? trim($progress['speed']) : '?';
    $bitrate = isset($progress['bitrate']) ? trim($progress['bitrate']) : '?';

    $done = $out_us > 0 ? gmdate('H:i:s', (int)($out_us / 1000000)) : '00:00:00';
    $pct = '';
    if ($total_us > 0) {
        $p = min(100, ($out_us / $total_us) * 100);
        $pct = sprintf(' %5.1f%%', $p);
    }
    $mb = $size > 0 ? sprintf('%.1f MB', $size / 1048576) : '0 MB';

    printf("\r[Downloading]%s  time=%s  size=%s  speed=%s  bitrate=%s   ",
        $pct, $done, $mb, $speed, $bitrate);
}

function read_key($win_kbhit)
{
    if ($win_kbhit) {
        // Windows console: single-key, non-blocking.
        if (sapi_windows_kbhit()) {
            return strtolower(sapi_windows_getch());
        }
        return null;
    }
    // Unix raw mode (non-blocking STDIN).
    $c = fread(STDIN, 1);
    if ($c === false || $c === '') {
        return null;
    }
    return strtolower($c);
}

function realpath_or($path)
{
    // Return an absolute base path with a trailing separator (directory must exist).
    $rp = realpath($path);
    if ($rp === false) {
        return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }
    return rtrim($rp, '/\\') . DIRECTORY_SEPARATOR;
}

function read_key_file($key_file, &$pos)
{
    // Read the next key written by the side reader process (Windows < 7.4).
    if (!is_file($key_file)) {
        return null;
    }
    clearstatcache(true, $key_file);
    $size = filesize($key_file);
    if ($size <= $pos) {
        return null;
    }
    $fh = @fopen($key_file, 'rb');
    if (!$fh) {
        return null;
    }
    fseek($fh, $pos);
    $chunk = fread($fh, $size - $pos);
    $pos = ftell($fh);
    fclose($fh);
    if ($chunk === false || $chunk === '') {
        return null;
    }
    // Return the last meaningful key character.
    $chunk = trim($chunk);
    if ($chunk === '') {
        return null;
    }
    return strtolower(substr($chunk, -1));
}

function win_suspend_resume($pid, $suspend)
{
    // Use ntdll NtSuspendProcess / NtResumeProcess through an inline C# helper in PowerShell.
    $action = $suspend ? 'Suspend' : 'Resume';
    $ps = '$code = @"' . "\n"
        . 'using System;' . "\n"
        . 'using System.Runtime.InteropServices;' . "\n"
        . 'using System.Diagnostics;' . "\n"
        . 'public static class P {' . "\n"
        . '  [DllImport("ntdll.dll")] public static extern int NtSuspendProcess(IntPtr h);' . "\n"
        . '  [DllImport("ntdll.dll")] public static extern int NtResumeProcess(IntPtr h);' . "\n"
        . '  public static void Do(int pid, bool suspend){' . "\n"
        . '    var pr = Process.GetProcessById(pid);' . "\n"
        . '    if(suspend) NtSuspendProcess(pr.Handle); else NtResumeProcess(pr.Handle);' . "\n"
        . '  }' . "\n"
        . '}' . "\n"
        . '"@' . "\n"
        . 'Add-Type -TypeDefinition $code -Language CSharp;' . "\n"
        . '[P]::Do(' . (int)$pid . ',$' . ($suspend ? 'true' : 'false') . ')';
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flm_pause_' . getmypid() . '.ps1';
    file_put_contents($tmp, $ps);
    @exec('powershell -NoProfile -ExecutionPolicy Bypass -File "' . $tmp . '" 2>nul');
}


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
    $headers = array(
        'Accept: application/json, text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9,fa;q=0.8',
        'Cache-Control: no-cache',
        'Connection: keep-alive',
        'Host: www.filimo.com',
        'Origin: https://www.filimo.com',
        'Referer: https://www.filimo.com/signin',
        'Upgrade-Insecure-Requests: 1',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0',
        'X-Requested-With: XMLHttpRequest',
    );
    if ($data !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
