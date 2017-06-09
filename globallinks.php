<?php

/*   ---------------------------------------------

Author : James Alexander

License: MIT (see http://opensource.org/licenses/MIT and LICENSE.txt which
should be in the root folder with this file)

Date of creation : 2015-04-06

Quick and Dirty cross wiki link search function for Wikimedia Wikis. Will make better later.

---------------------------------------------   */

ini_set( 'max_execution_time', 300 );

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;

// Create the global request object.
$request = Request::createFromGlobals();

$originalapiurl = 'https://meta.wikimedia.org/w/api.php';

$searchfor = $request->request->get( 'searchfor', null );
$searchproto = $request->request->get( 'proto', null );

$t = 0;

?>
<!DOCTYPE html>
<html lang='en-US'>
<head>
	<title>Global Link Search (ALPHA)</title>
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
				<h1>Global Link Search (ALPHA)</h1>
				<br />
				<fieldset>
					<legend>
						What do you want to search for?
						Please note this will search ALL wikis and may take time.
					</legend>
					<p>
						This form will search for an external link on all WMF wikis.
					</p>
					<p>
						<b>Enter the URL you want to find WITHOUT the 'protocol'
						(i.e. wikipedia.org not https://wikipedia.org.</b>
					</p>
					<p>
						If you want you are able to use a wildcard (*) at the start of a
						search (*.wikipedia.org not wikipedia.*)
					</p>

					<form id='inputform' method='POST'>
					<table>
						<tr>
							<td>
							</td>
						</tr>
						<tr>
							<td>
								<label for='searchfor'> Search for: </label>
							</td>
							<td>
								<select name='proto'>
									<option value='http'>http://</option>
									<option value='https'>https://</option>
									<option value='ftp'>ftp://</option>
									<option value='ftps'>ftps://</option>
									<option value='irc'>irc://</option>
									<option value='tel'>tel:</option>
									<option value='bitcoin'>bitcoin:</option>
									<option value='mailto'>mailto:</option>
								</select>
							</td>
							<td>
								<input id='searchfor' name='searchfor' size='30' type='td' value=''>
							</td>
						</tr>
						<tr>
							<td> <input type='submit' value='Search' />
						</tr>
					</table>
				</fieldset>
				<fieldset>
					<legend> Results: </legend>
					<table border='1' id='results'></table>
				</fieldset>

				</div>
		</div>
	</div>
	<?php
	echo '<script> $(\'input#searchfor\').val(\''.$searchfor.'\'); $(\'select[name="proto"]\').val(\''.$searchproto.'\'); </script>';
flush();
if ( $request->request->has( 'searchfor' ) ) {
	// $accessToken = new OAuthToken( $mwtoken, $mwsecret );
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
		$s = 0;
		$apiurl = makehttps( $sitearray['url'] ).'/w/api.php';
		$siteurl = makehttps( $sitearray['url'] );
		$dbname = $sitearray['dbname'];
		echo '<script> $("#results").append("<tr><th colspan=\'3\'> <a href=\''.$siteurl.'\' target=\'_blank\'>'.$dbname.'</a></th></tr>");</script>';
		if ( array_key_exists( 'closed', $sitearray ) ) {
			echo '<script> $("#results").append("<tr><td style=\'font-weight:bold;\' colspan=\'3\'> Closed Wiki, Skipping </td</tr>");</script>';
			continue;
		} elseif ( array_key_exists( 'private', $sitearray ) ) {
			echo '<script> $("#results").append("<tr><td style=\'font-weight:bold;\' colspan=\'3\'> Private Wiki, Skipping </td</tr>");</script>';
			continue;
		} elseif ( array_key_exists( 'fishbowl', $sitearray ) ) {
			echo '<script> $("#results").append("<tr><td style=\'font-weight:bold;\' colspan=\'3\'> Fishbowl Wiki, Skipping </td</tr>");</script>';
			continue;
		}

		$query = [
			'action' => 'query',
			'format' => 'json',
			'list' => 'exturlusage',
			'euprotocol' => $searchproto,
			'euquery' => $searchfor,
			'eulimit' => 'max',
			'euexpandurl' => '',
			'continue' => '',
		];

		curl_close( $ch );
		$ch = curl_init();

		a:

		$querystring = '?'.http_build_query( $query );
		$geturl = $apiurl.$querystring;
		curl_setopt( $ch, CURLOPT_HTTPGET, true );
		curl_setopt( $ch, CURLOPT_URL, $geturl );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$jsonresponse = curl_exec( $ch );
		$response = json_decode( $jsonresponse, true );

		if ( !empty( $response['query'] ) ) {
			if ( !empty( $response['query']['exturlusage'] ) ) {
				if ( !isset( $query['euoffset'] ) ) {
					echo '<script>$("#results").append("<tr></tr>");</script>';
					echo '<script>$("#results tr:last").append("<table id=\''.$dbname.'\' border=\'1\'></table>");</script>';
					echo '<script> $("#'.$dbname.'").append("<tr><th>Namespace</th><th>Page</th><th>Specific url found</th></tr>");</script>';

				}
				$searchresults = $response['query']['exturlusage'];
				foreach ( $searchresults as $key => $result ) {
					$title = $result['title'];
					$titleurl = $siteurl.'/wiki/'.$title;
					$title = utf8_encode( $title );
					$urlfound = utf8_encode( $result['url'] );
					$namespacefound = $result['ns'];

					echo '<script> $("#'.$dbname.'").append("<tr><td>'.$namespacefound.'</td><td><a href=\''.$titleurl.'\' target=\'_blank\'>'.$title.'</a></td><td>'.$urlfound.'</td></tr>");</script>';
					$s++;
					$t++;
					flush();
				}

				if ( array_key_exists( 'continue', $response ) && array_key_exists( 'euoffset', $response['continue'] ) ) {
					$offset = $response['continue']['euoffset'];
					$query['euoffset'] = $offset;
					//FIXME DONT USE GOTO
					goto a;
				}

				echo '<script> $("#'.$dbname.'").append("<tr><th colspan=\'3\'> Total hits: '.$s.' </th></tr>");</script>';
			} else {
				echo '<script> $("#results").append("<tr><td colspan=\'3\'>No search results found</td></tr>");</script>';
			}
		} else {
			echo '<script> $("#results").append("<tr><td style=\'color:red;\' colspan=\'3\'>There was an error with this search. If the wiki exists this appears it appears that you don\'t have an account on it. <br /> You may want to do a manual search on the wiki (click above) or re run this search after you have visited the wiki with the link above and seen your username in the top right corner. </td></tr>");</script>';
		}

	}
	echo '<script> $("#results").append("<tr><th colspan=\'3\'>DONE! - Total hits: '.$t.' </th></tr>");</script>';

}
?>
</body>
</html>
