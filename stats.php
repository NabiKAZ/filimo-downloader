<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Filimo Downloader Stats</title>
    <script type="text/javascript">
        var timeout;
        document.addEventListener("DOMContentLoaded", function (event) {
            startReload();
        });

        function stopReload() {
            document.getElementById('refresh_status').innerHTML = 'Off';
            clearTimeout(timeout);
        }

        function startReload() {
            document.getElementById('refresh_status').innerHTML = 'On';
            timeout = setTimeout(function () {
                window.location.reload();
            }, 2000);
        }
    </script>
    <script>
        function sortTable(n) {
            var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
            table = document.getElementById("myTable");
            switching = true;
            // Set the sorting direction to ascending:
            dir = "asc";
            /* Make a loop that will continue until
            no switching has been done: */
            while (switching) {
                // Start by saying: no switching is done:
                switching = false;
                rows = table.getElementsByTagName("TR");
                /* Loop through all table rows (except the
                first, which contains table headers): */
                for (i = 1; i < (rows.length - 1); i++) {
                    // Start by saying there should be no switching:
                    shouldSwitch = false;
                    /* Get the two elements you want to compare,
                    one from current row and one from the next: */
                    x = rows[i].getElementsByTagName("TD")[n];
                    y = rows[i + 1].getElementsByTagName("TD")[n];
                    /* Check if the two rows should switch place,
                    based on the direction, asc or desc: */
                    if (dir == "asc") {
                        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                            // If so, mark as a switch and break the loop:
                            shouldSwitch = true;
                            break;
                        }
                    } else if (dir == "desc") {
                        if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                            // If so, mark as a switch and break the loop:
                            shouldSwitch = true;
                            break;
                        }
                    }
                }
                if (shouldSwitch) {
                    /* If a switch has been marked, make the switch
                    and mark that a switch has been done: */
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    // Each time a switch is done, increase this count by 1:
                    switchcount++;
                } else {
                    /* If no switching has been done AND the direction is "asc",
                    set the direction to "desc" and run the while loop again. */
                    if (switchcount == 0 && dir == "asc") {
                        dir = "desc";
                        switching = true;
                    }
                }
            }
        }
    </script>

    <style>
        table {
            font-size: 20px;
            width: 100%;
        }

        td, th {
            padding: 10px;
        }

        th {
            cursor: pointer;
        }
    </style>
</head>

<body>
<h1>Filimo Downloader Stats</h1>

Refresh:
<button onclick="startReload();">Start</button>
<button onclick="stopReload();">Stop</button>
<span id="refresh_status"></span>

<table border='1' id="myTable">
    <thead>
    <tr style='font-weight:bold;'>
		<?php $sort_index = -1; ?>
		<th onclick="sortTable(<?php echo ++$sort_index; ?>)">cover</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">file name</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">video id</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">title</th>
		<th onclick="sortTable(<?php echo ++$sort_index; ?>)">subtitle</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">rate(x/5)</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">duration(min)</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">quality</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">bandwidth</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">resolution</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">current file size</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">last speed</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">progress</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">progress bar</th>
        <th onclick="sortTable(<?php echo ++$sort_index; ?>)">last modified date</th>
		<th onclick="sortTable(<?php echo ++$sort_index; ?>)">info</th>
		<th onclick="sortTable(<?php echo ++$sort_index; ?>)">log</th>
    </tr>
    </thead>
    <?php
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    date_default_timezone_set('Asia/Tehran');
    $base_path = 'download/';
    $files = array_filter(glob($base_path . '*.log', GLOB_BRACE), 'is_file');
    usort($files, function($a,$b){
        return filemtime($b) - filemtime($a);
    });
    //usort($files, create_function('$a,$b', 'return filemtime($b) - filemtime($a);'));
    foreach ($files as $file) {
        echo "<tr>\n";

        $dirname = pathinfo($file, PATHINFO_DIRNAME);
        $filename = pathinfo($file, PATHINFO_FILENAME);

        $filename_log = $dirname . DIRECTORY_SEPARATOR . $filename . '.log';
        $filename_video = $dirname . DIRECTORY_SEPARATOR . $filename . '.mp4';
        $filename_info = $dirname . DIRECTORY_SEPARATOR . $filename . '.info';
		$filename_cover = $dirname . DIRECTORY_SEPARATOR . $filename . '.jpg';
		$filename_subtitle = $dirname . DIRECTORY_SEPARATOR . $filename . '.srt';

        $modified_date = date('Y-m-d H:i:s', filemtime($filename_log));
        if (is_file($filename_video)) {
            $filesize = filesize_formatted($filename_video);
        } else {
            $filesize = 0;
        }

		echo "<td><a href='$filename_cover' target='_blank'><img src='$filename_cover' width='70'></a></td>\n";
        echo "<td><a href='$filename_video' target='_blank'>$filename.mp4</a></td>\n";

        $content = @file_get_contents($filename_info);
        $info = json_decode($content);

        echo "<td>{$info->video_id}</td>\n";
        echo "<td style='direction:rtl;'><a href='http://www.filimo.com/m/{$info->video_id}' target='_blank'>{$info->title}</a></td>\n";
		echo "<td>" . (file_exists($filename_subtitle) ? "<a href='$filename_subtitle' target='_blank'>download</a>" : "") . "</td>\n";
        echo "<td>{$info->rate}</td>\n";
        echo "<td>{$info->duration}</td>\n";
        echo "<td>{$info->quality}</td>\n";
        echo "<td>{$info->bandwidth}</td>\n";
        echo "<td>{$info->resolution}</td>\n";
        echo "<td>{$filesize}</td>\n";

        $content = @file_get_contents($filename_log);

        //get duration of source
        preg_match("/Duration: (.*?), start:/", $content, $matches);

        $rawDuration = $matches[1];

        //rawDuration is in 00:00:00.00 format. This converts it to seconds.
        $ar = array_reverse(explode(":", $rawDuration));
        $duration = floatval($ar[0]);
        if (!empty($ar[1])) $duration += intval($ar[1]) * 60;
        if (!empty($ar[2])) $duration += intval($ar[2]) * 60 * 60;

        //get the time in the file that is already encoded
        preg_match_all("/time=(.*?) bitrate/", $content, $matches);

        $rawTime = array_pop($matches);

        //this is needed if there is more than one match
        if (is_array($rawTime)) {
            $rawTime = array_pop($rawTime);
        }

        //rawTime is in 00:00:00.00 format. This converts it to seconds.
        $ar = array_reverse(explode(":", $rawTime));
        $time = floatval($ar[0]);
        if (!empty($ar[1])) $time += intval($ar[1]) * 60;
        if (!empty($ar[2])) $time += intval($ar[2]) * 60 * 60;

        //calculate the progress
        $progress = round(($time / $duration) * 100, 2);

        //echo "Duration: " . $duration . "\n";
        //echo "Current Time: " . $time . "\n";
        //echo "Progress: " . $progress . "%\n";

        preg_match_all("/ speed=(.*?)x/", $content, $matches);
        $last_speed = array_pop($matches);
        $last_speed = array_pop($last_speed);

        echo "<td>{$last_speed}x</td>\n";
        echo "<td>{$progress}%</td>\n";
        echo "<td><div style='width:100px;height: 20px;border: 1px solid #0095ff;'><div style='width:$progress%; background-color: #0095ff;height: 20px;'></div></div></td>\n";

        echo "<td>$modified_date</td>\n";
		echo "<td><a href='$filename_info' target='_blank'>info</a></td>\n";
		echo "<td><a href='$filename_log' target='_blank'>log</a></td>\n";


        echo "</tr>\n";
    }
    ?>
</table>

<pre>
===========================================================
Filimo Downloader - version 0.1.0 - Copyright 2017
By Nabi KaramAliZadeh &lt;www.nabi.ir&gt; &lt;nabikaz@gmail.com&gt;
Signup here: http://filimo.com/invite/NabiKAZ/a8ca
Project link: https://github.com/NabiKAZ/filimo-downloader
===========================================================
</pre>

</body>
</html>
<?php
function filesize_formatted($path)
{
    $size = filesize($path);
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}

?>
