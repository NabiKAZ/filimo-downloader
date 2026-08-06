<?php

function get_subtitle_tracks($tracks)
{
    $subtitle_tracks = array();
    $seen_sources = array();

    if (!is_array($tracks)) {
        return $subtitle_tracks;
    }

    foreach ($tracks as $track) {
        $source = '';
        $label = '';

        if (is_object($track)) {
            $source = isset($track->src) ? $track->src : '';
            if (isset($track->language)) {
                $label = $track->language;
            } elseif (isset($track->srclang)) {
                $label = $track->srclang;
            } elseif (isset($track->label)) {
                $label = $track->label;
            } elseif (isset($track->name)) {
                $label = $track->name;
            } elseif (isset($track->lang)) {
                $label = $track->lang;
            }
        } elseif (is_array($track)) {
            $source = isset($track['src']) ? $track['src'] : '';
            if (isset($track['language'])) {
                $label = $track['language'];
            } elseif (isset($track['srclang'])) {
                $label = $track['srclang'];
            } elseif (isset($track['label'])) {
                $label = $track['label'];
            } elseif (isset($track['name'])) {
                $label = $track['name'];
            } elseif (isset($track['lang'])) {
                $label = $track['lang'];
            }
        }

        $source = trim((string) $source);
        if ($source === '' || isset($seen_sources[$source])) {
            continue;
        }

        $seen_sources[$source] = true;
        $subtitle_tracks[] = array(
            'src' => $source,
            'label' => subtitle_label_to_slug($label),
        );
    }

    return $subtitle_tracks;
}

function build_subtitle_file_path($base_path, $file_name, $subtitle_track, $index, $total_count)
{
    if ($index === 0) {
        return $base_path . $file_name . '.srt';
    }

    $suffix = '.subtitle-' . ($index + 1);
    if ($total_count > 1 && isset($subtitle_track['label']) && $subtitle_track['label'] !== '') {
        $suffix .= '-' . $subtitle_track['label'];
    }

    return $base_path . $file_name . $suffix . '.srt';
}

function normalize_subtitle($subtitle_content)
{
    return str_replace(array("WEBVTT\r\n", "WEBVTT\n"), '', $subtitle_content);
}

function subtitle_label_to_slug($label)
{
    $label = trim((string) $label);
    if ($label === '') {
        return '';
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '-', $label);
    return strtolower(trim($slug, '-'));
}
