<?php

/*   ---------------------------------------------

Author : James Alexander

License: MIT (see http://opensource.org/licenses/MIT and LICENSE.txt which
should be in the root folder with this file)

Date of creation : 2014-07-10

Quick and Dirty tool to show the text of a given page name on all wikis.

---------------------------------------------   */

ini_set( 'max_execution_time', 300 );

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

// Create the global request object.
$request = Request::createFromGlobals();

$originalapiurl = 'https://meta.wikimedia.org/w/api.php';

?>

<!DOCTYPE>
<html lang='en-US'>
<head>
	<title>Global Page Text (ALPHA)</title>
	<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
	<link href="css/main.css" rel="stylesheet" />
	<link href="css/lca.css" rel="stylesheet" />
	<style type="text/css">
		.external, .external:visited { color: #222222; }
		.autocomment{color:gray}
	</style>
	<script
		src="https://code.jquery.com/jquery-1.12.4.min.js"
		integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ="
		crossorigin="anonymous"></script>
</head>
<body class='mediawiki'>
	<div id='globalWrapper'>
		<div id='column-content'>
			<div id='content'>
				<h1>Global Page Text (ALPHA)</h1>
				<br />
			<?php if ( !$request->request->has( 'page' ) ) : ?>
				<fieldset>
					<legend>
						What page do you want to look at?
						Please note this will search ALL wikis and may take time.
					</legend>
					<b>
						Note: You should
						<a href='https://meta.wikimedia.org/wiki/User:Krinkle/Tools/Global_SUL' target='_blank'>
						globally create</a>
						your accounts before you use this tool to avoid some random
						occasional bugs.
					</b>
					This form is designed to look for and display the text for a given
					page name on all wikis. Please include the english name space in
					the title.
					<form id='inputform' method='POST'>
					<table>
						<tr>
							<td></td>
						</tr>
						<tr>
							<td> <label for='page'> page title: </label>
							<td>
								<input id='page' name='page' size='30' type='td' value=''>
							</td>
						</tr>
						<tr>
							<td> <input type='submit' value='Get text' />
						</tr>
					</table>
				</fieldset>
			<?php else: ?>
				<fieldset>
					<legend> Results: </legend>
					<table border='1' id='results'></table>
				</fieldset>
			<?php endif; ?>

				</div>
		</div>
	</div>
	<?php
flush();
if ( $request->request->has( 'page' ) ) {
	$page = $request->request->get( 'page' );
	$query = [
		'action' => 'sitematrix',
		'format' => 'json',
	];

	$ch = curl_init();

	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_URL, $originalapiurl );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $query ) );
	curl_setopt( $ch, CURLOPT_HEADER, 0 );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	$jsonresponse = curl_exec( $ch );
	if ( !$jsonresponse ) {
		echo json_encode( 'Curl error: ' . htmlspecialchars( curl_error( $ch ) ) );
	}

	$response = json_decode( $jsonresponse, true );
	$sites = [];

	foreach ( $response['sitematrix'] as $key => $langarray ) {
		if ( $key != 'count' && $key != 'specials' ) {
			foreach ( $langarray['site'] as $langkey => $sitearray ) {
					$sites[] = $sitearray;
			}
		}
	}

	foreach ( $response['sitematrix']['specials'] as $key => $sitearray ) {
		$sites[] = $sitearray;
	}

	foreach ( $sites as $key => $sitearray ) {
		$apiurl = makehttps( $sitearray['url'] ).'/w/api.php';
		$siteurl = makehttps( $sitearray['url'] );
		$dbname = $sitearray['dbname'];
		echo '<script> $("#results").append("<tr><th colspan=\'2\'> <a href=\''.$siteurl.'\' target=\'_blank\'>'.$dbname.'</a></th></tr>");</script>';
		if ( array_key_exists( 'closed', $sitearray ) ) {
			echo '<script> $("#results").append("<tr><td colspan=\'2\' style=\'font-weight:bold;\'> Closed Wiki, Skipping </td</tr>");</script>';
			continue;
		} elseif ( array_key_exists( 'private', $sitearray ) ) {
			echo '<script> $("#results").append("<tr><td colspan=\'2\' style=\'font-weight:bold;\'> Private Wiki, Skipping </td</tr>");</script>';
			continue;
		} elseif ( array_key_exists( 'fishbowl', $sitearray ) ) {
			echo '<script> $("#results").append("<tr><td colspan=\'2\' style=\'font-weight:bold;\'> Fishbowl Wiki, Skipping </td</tr>");</script>';
			continue;
		}

		$query = [
			'action'  => 'query',
			'format'  => 'json',
			'titles'  => $page,
			'prop'    => 'revisions',
			'rvprop'  => 'content',
			'rvlimit' => '1',
		];

		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_URL, $apiurl );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $query ) );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$jsonresponse = curl_exec( $ch );
		$response = json_decode( $jsonresponse, true );

		if ( $response ) {
				$pageresults = $response['query']['pages'];

				if ( is_array( $pageresults ) ) {
					foreach ( $pageresults as $key => $result ) {
						if ( $key !='-1' ) {
							$location = $siteurl.'/wiki/'.$result['title'];
							echo '<script> $("#results").append("<tr><td><a href=\''.htmlentities( $location, ENT_QUOTES ).'\' target=\'_blank\'>'.$result['title'].'</a></td><td>'.htmlentities( $result['revisions'][0]['*'], ENT_QUOTES ).'</td></tr>");</script>';
						} else {
							$location = $siteurl.'/wiki/'.$result['title'];
							echo '<script> $("#results").append("<tr><td><a href=\''.htmlentities( $location, ENT_QUOTES ).'\' target=\'_blank\'>'.$result['title'].'</a></td><td>No page found or mediawiki page set as default</td></tr>");</script>';
						}
						flush();
					}
				} else {
					echo '<script> $("#results").append("<tr><td colspan=\'2\' style=\'color:red;\'>There was an error with this search. If the wiki exists this appears it appears that you don\'t have an account on it. <br /> You may want to do a manual search on the wiki (click above) or re run this search after you have visited the wiki with the link above and seen your username in the top right corner. </td></tr>");</script>';
				}
		} else {
			echo '<script> $("#results").append("<tr><td colspan=\'2\' style=\'color:red;\'>There was an error with this search. If the wiki exists this appears it appears that you don\'t have an account on it. <br /> You may want to do a manual search on the wiki (click above) or re run this search after you have visited the wiki with the link above and seen your username in the top right corner. </td></tr>");</script>';
		}

	}
	echo '<script> $("#results").append("<tr><th colspan=\'2\' >DONE!</th></tr>");</script>';

}
?>
</body>
</html>
