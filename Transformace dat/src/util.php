<?php

/** Gets absolute URL.
 * @param string Relative URL.
 * @param string URL with non-empty scheme, host and path.
 * @return string
 */
function absoluteUrl($href, $base) {
	if ($href == '') {
		return $base;
	} elseif (preg_match('~://~', $href)) { // Absolute URL.
		return $href;
	} elseif (preg_match('~^//~', $href)) { // Schema-less.
		return preg_replace('~//.+~', '', $base) . $href;
	} elseif (preg_match('~^\?~', $href)) { // Query-only.
		return preg_replace('~\?.*~', '', $base) . $href;
	} elseif (preg_match('~^#~', $href)) { // Fragment-only.
		return preg_replace('~#.*~', '', $base) . $href;
	} elseif (preg_match('~^/~', $href)) { // Absolute path.
		return preg_replace('~(//[^/]+).*~', '\1', $base) . $href;
	}
	// Relative path.
	preg_match('~(.+//[^/]+)([^?#]*/)~', $base, $match); // Strip filename, query and fragment.
	list(, $prefix, $path) = $match;
	preg_match('~([^?#]*)(.*)~', $href, $match);
	list(, $hrefPath, $suffix) = $match;
	$path .= preg_replace('~(?<=^|/)\.(/|$)~', '', $hrefPath); // Remove './'.
	$path = preg_replace('~[^/]++/(?R)?\.\.(/|$)~', '', $path); // Remove 'a/../'.
	$path = preg_replace('~^/(\.\.(/|$))+~', '/', $path); // Remove leading '../'.
	return $prefix . $path . $suffix;
}

/** Downloads contents of HTTP or HTTPS URL and save it to cache/.
 * @param string
 * @param string
 * @return string or false on failure.
 */
function download($url, $headers = null) {
	$cache = cachePath($url);
	if ((!defined('CACHE_READS') || CACHE_READS) && file_exists($cache)) {
		return file_get_contents($cache);
	}
	$context = stream_context_create(array('http' => array('header' => $headers)));
	$file = file_get_contents($url, false, $context);
	if ($file) {
		$dir = dirname($cache);
		if (!file_exists($dir)) {
			mkdir($dir, 0777, true);
		}
		file_put_contents($cache, $file);
	}
	return $file;
}

/** Returns path to the cached file.
 * @param string
 * @return string
 */
function cachePath($url) {
	return preg_replace('~\?~', '^', preg_replace('~^https?://~', __DIR__ . '/../cache/', $url));
}

/** Downloads URL with caching and parses HTML.
 * @param string
 * @return DOMDocument Or false on failure.
 */
function downloadHtml($url) {
	return parseHtml(download($url));
}

/** Parses HTML.
 * @param string
 * @return DOMDocument Or false on failure.
 */
function parseHtml($file) {
	$dom = new DOMDocument;
	$errors = libxml_use_internal_errors(true);
	if (!$dom->loadHTML('<?xml version="1.0" encoding="utf-8"?>' . $file)) {
		return false;
	}
	libxml_use_internal_errors($errors);
	return $dom;
}

/** Returns ISO date from human readable date.
* @param string
* @return string Or false for invalid dates.
*/
function isoDate($date) {
	if (preg_match('~^\s*(0?[1-9]|[12][0-9]|3[01])\.(0?[1-9]|1[0-2])\.(\d{4})\s+([01]?\d|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?\s*$~', $date, $match)) {
		@list(, $day, $month, $year, $hour, $minute, $second) = $match;
		return sprintf('%d-%02d-%02dT%02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
	}
	return false;
}
