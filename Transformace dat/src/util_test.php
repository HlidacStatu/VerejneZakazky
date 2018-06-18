<?php
include_once __DIR__ . '/testing.php';
include_once __DIR__ . '/util.php';

function testAbsoluteUrl() {
	$base = 'https://example.com/a/b/c.html?query#fragment';
	assertThat(absoluteUrl('', $base))->isEqualTo($base);
	assertThat(absoluteUrl('http://a', $base))->isEqualTo('http://a');
	assertThat(absoluteUrl('//a', $base))->isEqualTo('https://a');
	assertThat(absoluteUrl('?a=1', $base))->isEqualTo('https://example.com/a/b/c.html?a=1');
	assertThat(absoluteUrl('#part', $base))->isEqualTo('https://example.com/a/b/c.html?query#part');
	assertThat(absoluteUrl('/d', $base))->isEqualTo('https://example.com/d');
	assertThat(absoluteUrl('/d?a=1#part', $base))->isEqualTo('https://example.com/d?a=1#part');
	assertThat(absoluteUrl('d/e?a=1#part', $base))->isEqualTo('https://example.com/a/b/d/e?a=1#part');
	assertThat(absoluteUrl('.', $base))->isEqualTo('https://example.com/a/b/');
	assertThat(absoluteUrl('./', $base))->isEqualTo('https://example.com/a/b/');
	assertThat(absoluteUrl('..', $base))->isEqualTo('https://example.com/a/');
	assertThat(absoluteUrl('../', $base))->isEqualTo('https://example.com/a/');
	assertThat(absoluteUrl('../d', $base))->isEqualTo('https://example.com/a/d');
	assertThat(absoluteUrl('../../d/', $base))->isEqualTo('https://example.com/d/');
}

function testCachePath() {
	assertThat(cachePath('https://example.com/index?a=b'))->isEqualTo(__DIR__ . '/../cache/example.com/index^a=b');
}

function testIsoDate() {
	$expected = '2018-06-18T18:35:00';
	assertThat(isoDate('18.06.2018 18:35'))->isEqualTo($expected);
	assertThat(isoDate('18.06.2018 18:35:00'))->isEqualTo($expected);
	assertThat(isoDate('18.6.2018 18:35:00'))->isEqualTo($expected);
	assertThat(isoDate('18.6.2018 7:35:00'))->isEqualTo('2018-06-18T07:35:00');
	assertThat(isoDate('abc'))->isEqualTo(false);
}
