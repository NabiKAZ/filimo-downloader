<?php

require_once __DIR__ . '/../subtitle_utils.php';

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$tracks = array(
    (object) array('src' => 'https://cdn.example.com/sub-fa.vtt', 'language' => 'fa'),
    (object) array('src' => 'https://cdn.example.com/sub-en.vtt', 'label' => 'English'),
    (object) array('src' => 'https://cdn.example.com/sub-fa.vtt', 'label' => 'duplicate'),
    (object) array('src' => ''),
);

$subtitle_tracks = get_subtitle_tracks($tracks);
assert_same(2, count($subtitle_tracks), 'Should keep all unique subtitle tracks with source URL.');
assert_same('https://cdn.example.com/sub-fa.vtt', $subtitle_tracks[0]['src'], 'First subtitle source should be preserved.');
assert_same('fa', $subtitle_tracks[0]['label'], 'Language label should be slugified.');
assert_same('english', $subtitle_tracks[1]['label'], 'Fallback label should be slugified.');

$base_path = 'download/';
$file_name = 'video_1080p';
assert_same('download/video_1080p.srt', build_subtitle_file_path($base_path, $file_name, $subtitle_tracks[0], 0, 2), 'First subtitle should use legacy .srt file name.');
assert_same('download/video_1080p.subtitle-2-english.srt', build_subtitle_file_path($base_path, $file_name, $subtitle_tracks[1], 1, 2), 'Second subtitle should include index and language label.');

$normalized = normalize_subtitle("WEBVTT\r\n00:00.000 --> 00:01.000\r\nHello");
assert_true(strpos($normalized, 'WEBVTT') === false, 'WEBVTT header should be removed.');

echo "subtitle_utils tests passed.\n";
