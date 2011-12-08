<?php

function get_meta_description($contents, $length=300) {
	$str = html_entity_decode(strip_tags(str_replace('>','> ',$contents)), ENT_QUOTES, 'UTF-8');
	$str = trim(strtr($str, "\n", ' '));
	if ($enddesc = strpos($str, '. '))
		$str = substr($str, 0, $enddesc);  
	$cstr = '';
	while ($cstr != $str) {
		$cstr = $str;
		$str = str_replace('  ', ' ', str_replace("\t", ' ', $str));
	}
	return htmlentities(substr($str, 0, $length), ENT_QUOTES, 'UTF-8');
}

function get_meta_keywords($contents) {
}

function get_url($title) {
	return BASE_HREF . '/' . rawurlencode($title);
}

?>
