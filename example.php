<?PHP
require_once('SteamPipe/steamapi.php');
/*
Constructor accepts steamID64 or steamID

steamID64: 76561197960395507 (number after /profile/ in the steam community url)
steamID: mcgregok (string after /id/ in the steam community url)

*/

$steam = new SteamApi('mcgregok');

/*functions return associative array*/
?><pre><?

var_export($steam->fetch_profile());

?></pre><?
