<!DOCTYPE html>
<html>
<head>
	<title>Regressers</title>
<link rel="stylesheet" href="https://unpkg.com/purecss@1.0.0/build/pure-min.css" integrity="sha384-nn4HPE8lTHyVtfCBi5yW9d20FjT8BJwUXyWZT9InLYax14RDjBj46LmSztkmNP9w" crossorigin="anonymous">
</head>
<body>


<?php

$version = isset($_GET['release']) ? (int) $_GET['release'] : 65;

function get_cache($cache_source, $cache_file, $cache_time=10800) {
	// Serve from cache if it is younger than $cache_time
	$cache_ok = file_exists($cache_file) && time() - $cache_time < filemtime($cache_file);

	if (! $cache_ok) {
		file_put_contents($cache_file, file_get_contents($cache_source, true));
	}

	return file_get_contents($cache_file);
}

function secureText($string)
    {
        $sanitize = function ($v) {
            // CRLF XSS
            $v = str_replace(['%0D', '%0A'], '', $v);
            // We want to convert line breaks into spaces
            $v = str_replace("\n", ' ', $v);
            // Escape HTML tags and remove ASCII characters below 32
            $v = filter_var(
                $v,
                FILTER_SANITIZE_SPECIAL_CHARS,
                FILTER_FLAG_STRIP_LOW
            );
            return $v;
        };
        return is_array($string) ? array_map($sanitize, $string) : $sanitize($string);
    }

$cache_source = 'https://bugzilla.mozilla.org/rest/bug?include_fields=id,summary,status&bug_status=UNCONFIRMED&bug_status=NEW&bug_status=ASSIGNED&bug_status=REOPENED&bug_status=RESOLVED&bug_status=VERIFIED&classification=Client%20Software&classification=Developer%20Infrastructure&classification=Components&classification=Server%20Software&classification=Other&f1=cf_status_firefox' . $version .'&f2=blocked&keywords=regression&keywords_type=allwords&o1=anyexact&o2=isnotempty&resolution=---&resolution=FIXED&v1=affected%2Cfixed%2Cwontfix';

$regressions = json_decode(get_cache($cache_source, 'regressions_' . $version . '.json'), true);
$regressions = array_column($regressions['bugs'], 'id');
$regressions = implode(',', $regressions);

$regresser_list = json_decode(get_cache('https://bugzilla.mozilla.org/rest/bug?id=' . $regressions . '&include_fields=blocks', 'regresserslist_' . $version . '.json'), true)['bugs'];


$output = [];
foreach ($regresser_list as $data) {
	if (! isset($data['blocks'])) {
		continue;
	}
	foreach ($data['blocks'] as $bug_number) {
		if (! isset($output[$bug_number])) {
			$output[$bug_number] = 1;
		} else {
			$output[$bug_number]++;
		}
	}
}
arsort($output);

$regressers = json_decode(
	get_cache(
		'https://bugzilla.mozilla.org/rest/bug?id='
		. implode(',', array_keys($output))
		. '&include_fields=id,summary', 'regressers_'
		. $version
		. '.json')
	, true)['bugs'];


$final = [];

foreach($output as $key => $value) {
	$final[] = [
		'bug' 		=> $key,
		'summary' 	=> $regressers[array_search($key, array_column($regressers, 'id'))]['summary'],
		'count' 	=> $output[$key]
	];
}

print "<h3>Bugs that causes regressions in " . secureText($version) . "</h3>
<table class='pure-table pure-table-bordered'>
<thead>
<tr><th>#</th><th>Bug</th></tr>\n
</thead>\n
<tbody>\n";

foreach($final as $values) {
	print "<tr>\n";
	print '<td>' . $values['count'] ."</td>\n";
	print '<td><a href="https://bugzilla.mozilla.org/' . secureText($values['bug']) . '">' . secureText($values['bug']) . '</a> - ' . $values['summary'] . "</td>\n";
	print "</tr>\n";
}
print "</tbody>\n</table>";

?>
</body>
</html>
