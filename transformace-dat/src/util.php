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

/** Creates stream context.
 * @param string
 * @param array
 * @return resource
 */
function getStreamContext($url, $headers = array()) {
	$userAgent = 'HlidacStatu/verejne-zakazky (https://github.com/HlidacStatu/verejne-zakazky)';
	if (defined('AUTH_TOKEN')) {
		if (preg_match('~^https://www\.hlidacstatu\.cz/Api/~i', $url)) {
			$headers[] = 'Authorization: Token ' . AUTH_TOKEN;
		} else {
			$userAgent .= ' User/' . substr(AUTH_TOKEN, 0, 8);
		}
	}
	return stream_context_create(array(
		'http' => array('header' => $headers, 'user_agent' => $userAgent),
		'ssl' => array('verify_peer' => false), // Unknown issuer at https://zakazky.lesycr.cz/.
	));
}

/** Downloads contents of HTTP or HTTPS URL and save it to cache/.
 * @param string
 * @return string or false on failure.
 */
function download($url) {
	$cache = cachePath($url);
	if ((!defined('CACHE_READS') || CACHE_READS) && file_exists($cache)) {
		return file_get_contents($cache);
	}
	$file = file_get_contents($url, false, getStreamContext($url));
	if ($file) {
		$dir = dirname($cache);
		if (!file_exists($dir)) {
			mkdir($dir, 0777, true);
		}
		file_put_contents($cache, $file);
	}
	return $file;
}

/** Posts data to HTTP or HTTPS URL.
 * @param string
 * @param string
 * @param string
 * @return string or false on failure.
 */
function upload($url, $data, $contentType) {
	$context = getStreamContext($url, array("Content-Type: $contentType"));
	stream_context_set_option($context, array(
		'http' => array('method' => 'POST', 'content' => $data),
	));
	return file_get_contents($url, false, $context);
}

/** Posts JSON data to HTTP or HTTPS URL.
 * @param string
 * @param stdClass
 * @return string or false on failure.
 */
function uploadJson($url, stdClass $data) {
	return upload($url, json_encode($data), "application/json");
}

/** Returns path to the cached file.
 * @param string
 * @return string
 */
function cachePath($url) {
	$return = $url;
	$return = preg_replace('~#.*~', '', $return);
	$return = preg_replace('~\?~', '^', $return); // Not allowed on Windows.
	// TODO: Handle preg_match('~/$~', $url).
	// TODO: Handle 'a.html' versus 'a.html/XMLdataVZ'.
	$return = preg_replace('~^https?://~', __DIR__ . '/../cache/', $return);
	return $return;
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
	if (preg_match('~^\s*(0?[1-9]|[12][0-9]|3[01])\.(0?[1-9]|1[0-2])\.(\d{4})(?:\s+([01]?\d|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?)?\s*$~', $date, $match)) {
		@list(, $day, $month, $year, $hour, $minute, $second) = $match;
		return sprintf('%d-%02d-%02d' . ($hour != '' ? 'T%02d:%02d:%02d' : '') . '', $year, $month, $day, $hour, $minute, $second);
	}
	return false;
}

/** Returns price and currency from human readable price.
 * @param string
 * @return array array('amount' => int, 'currency' => string) or false for invalid prices.
 */
function price($price) {
	if (preg_match('~^\s*([\d  ]+)(?:,(\d\d))?\s+(\w+)~u', $price, $match)) { // Contains non-breaking space.
		list(, $amount, $decimal, $currency) = $match;
		return array(
			'amount' => floatval(preg_replace('~[  ]~', '', $amount) . ".$decimal"),
			'currency' => $currency,
		);
	}
	return false;
}

/** Sets fields OdhadovanaHodnotaBezDPH and OdhadovanaHodnotaMena if $price is valid price.
 * @param string
 * @param stdClass
 * @return 
 */
function setOdhadovanaHodnota($price, stdClass $result) {
	$odhadovanaHodnota = price($price);
	if ($odhadovanaHodnota) {
		$result->OdhadovanaHodnotaBezDPH = $odhadovanaHodnota['amount'];
		$result->OdhadovanaHodnotaMena = $odhadovanaHodnota['currency'];
	}
}

/** Copy OficialUrl to DirectUrl in all $result->Dokumenty.
 * @param stdClass Modified.
 * @param stdClass
 */
function directUrl(stdClass $result, stdClass $profile) {
	foreach ($result->Dokumenty as &$dokument) {
		$dokument['DirectUrl'] = $dokument['OficialUrl'];
	}
	unset($dokument);
}
