<?

require_once('kiwi_storage.php');
require_once('kiwi_helpers.php');

setlocale(LC_ALL, "en");
define('BASE_HREF', 'http://www.example.com');
define('PASSWORD', '<type your password here>');
$storage = new file_storage("data");

$p = isset($_GET['p']) ? $_GET['p'] : 'Home';
	
switch ($p) {
	case 'Sitemap':
		header('Content-Type: application/xml');
		echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		foreach ($storage->list() as $title)
			echo "<url><loc>" . get_url($title) . "</loc><lastmod>" . date("c", $storage->time($title)) . "</lastmod></url>";
		echo '</urlset>';
		exit();
}

function is_invalid_page($page) {
	global $storage;
	return !$storage->has($page) && $page != "Not found";
}

function create_internal_link($match) {
	global $storage;
	if (is_array($match)) $link = $match = $match[1];
	if ($match == 'Home') $link = '/';
	if (is_invalid_page($match))
		return $match;
	else
		return "<a href=\"$link\" title=\"".get_meta_description($storage->get($match), 120)."\">$match</a>";
}

if (isset($_REQUEST['pwd']) && $_REQUEST['pwd'] == PASSWORD) {
	$content = html_entity_decode($_REQUEST['content']);
	$storage->put($p, $content);
}

if (!$storage->has($p)) {
	$newp = $p;
	$p = "Not found";
	header("404");
	$content = "";
	$content_textarea = "";
	$content_rendered = "";
} else {
	$newp = false;
	$content = $storage->get($p);
	$content_textarea = htmlentities($content, ENT_QUOTES, 'UTF-8');
	$content_rendered = preg_replace_callback('/\{([^\}]+)\}/', 'create_internal_link', $content);
}

$css_page_id = ereg_replace("[^a-zA-Z0-9]", "", $p);

function keyword_extract($page, $text){
    $text = strtolower(strip_tags($text));
	$text = ereg_replace("[^[:alnum:]]+", " ", $text);
    $commonWords = "about,that's,this,that,than,then,them,there,their,they,it's,with,which,were,where,whose,when,what,her's,he's,have,from,enough";
    $commonWords = strtolower($commonWords);
    $words = explode(" ", $text);
	$words[] = strtolower($page);
    $commonWords = explode(",", $commonWords);
    foreach ($words as $value) {
        $common = false;
        if (strlen($value) > 3){
            foreach($commonWords as $commonWord){
                if ($commonWord == $value){
                    $common = true;
                }
                else{
                }
            }
            if($common != true){
                $keywords[] = $value;
            }
            else{
            }
        }
        else{
        }
    }
	$keywords = array_count_values($keywords);
    arsort($keywords);
	return implode(',', array_slice(array_keys($keywords), 0, 10));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="keywords" content="<?= keyword_extract($p, $content) ?>,CAFxX" />
<meta http-equiv="description" content="<?= get_meta_description($content, 300) ?>" />
<title>CAFxXcrossway - <?= $p; ?></title>
<link href="CAFxXcrossway.css" rel="stylesheet" type="text/css" media="screen" />
<link rel="SHORTCUT ICON" href="favicon.ico" />
<script src="http://www.google-analytics.com/urchin.js" type="text/javascript"></script>
<!--[if lt IE 8]>
<script src="http://ie7-js.googlecode.com/svn/version/2.0(beta3)/IE8.js" type="text/javascript"></script>
<![endif]-->
</head>

<body>
<div id="bg"></div>
<div id="frame">
	<form method="post" action="<?= ($newp ? $newp : $p) ?>">
		<div id="head">
				<h1><a href="http://cafxx.strayorange.com" rel="start index">CAFxXcrossway</a> - <?= $p . ($newp ? " ($newp)" : "") ?></h1>
				<div id="controls">
					<a id="switch" onclick="switchToEditMode();" href="#"><?= ($newp ? "Create" : "Edit") ?></a>
				<span id="controlform" style="display:none;">
					<input type="password" value="<?= $pwd; ?>" name="pwd" style="padding:1px; border-right:none;" /><input type="submit" value="Save" style="padding:0px;" />
				</span>
			</div>
		</div>
		<div id="content" class="content page_<?= $css_page_id ?>"><?= $content_rendered ?></div>
		<script language="javascript" type="text/javascript">
			var content = document.getElementById("content");
			var editor = document.createElement("textarea");
			editor.className = editor.name = "content";
			editor.id = "rawcontent";
			editor.value = "<?= $content_textarea ?>";
			content.appendAfter(editor);
		</script>	
	</form>
</div>
<div id="notice">1985-<?= date('Y'); ?> CAFxX</div>
<script type="text/javascript">_uacct = "UA-1618263-1";	urchinTracker(); </script>
</body>
</html>
