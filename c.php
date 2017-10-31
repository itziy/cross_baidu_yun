<?php

crossYunCookie();

// 自动获取绕过云防护的cookie
// 需要 v8js 与 curl 扩展
// 成功返回 cookie 字符串 失败返回 false
function crossYunCookie($domain = 'www.xxx.cn')
{
	$cookie = null;
	$domain = trim($domain);
	$url = $domain;

	if (substr($url, strlen($url) - 1) != '/') {
		$url .= '/';
	} else {
		$domain = substr($domain, 0, -1);
	}
	$domain = str_replace(['http://', 'https://'], '', $domain);

	$url .= 'cdn-cgi/l/chk_jschl?';

	$ch = curl_init($domain);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36');
	$ret = curl_exec($ch);
	curl_close($ch);
	unset($ch);

	$ret = str_replace(["\n", "\r"], '', $ret);

	// get all cookies
	preg_match_all('/Set\-Cookie:\s?(.*?);/i', $ret, $match);

	if (!isset($match[1]) || !is_array($match[1]) || empty($match[1])) {
		return false;
	}
	$cookie = implode('; ', $match[1]);

	preg_match_all('/type="hidden"\s+name="(.*?)"\s+value="(.*?)"/i', $ret, $match);

	if (!isset($match[1]) || !isset($match[2]) || empty($match[1]) || empty($match[2])) {
		return false;
	}

	foreach ($match[1] as $key => $name) {
		$url .= $name.'='.$match[2][$key].'&';
	}

	$url .= 'jschl_answer=';

	// get core js
	preg_match('/setTimeout\(function\(\)\{(.*?)\+\s+\w\.length;/i', $ret, $match);

	if (!isset($match[1])) {
		return false;
	}

	// filter js
	$jsAry = explode(';', $match[1]);
	foreach ($jsAry as $jk => $js) {
		if (strpos($js, 'document') !== false || strpos($js, '.href') !== false || strpos($js, 'substr') !== false || strpos($js, 'innerHTML') !== false || strpos($js, '.match') !== false) {
			unset($jsAry[$jk]);
			continue;
		}
		if (strpos($js, 'parseInt') !== false) {
			$jsAry[$jk] = str_replace('.value', '', $js);
		}
		if (empty($jsAry[$jk])) {
			unset($jsAry[$jk]);
		}
	}

	$js = implode('; ', $jsAry);
	$js .= ' + '.strlen($domain).'; ';

	$v8 = new V8Js();
	$ret = $v8->executeString($js);


	if (empty($ret) || !is_numeric($ret)) {
		return false;
	}


	$url .= $ret;


	# very important
	sleep(5);

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
	curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36');
	$ret = curl_exec($ch);
	curl_close($ch);
	unset($ch);


	$ret = str_replace(["\n", "\r"], '', $ret);

	// get all cookies
	preg_match_all('/Set\-Cookie:\s?(.*?);/i', $ret, $match);

	if (!isset($match[1]) || !is_array($match[1]) || empty($match[1])) {
		return false;
	}


	$cookie = $cookie.'; '. implode('; ', $match[1]);

	return $cookie;
}