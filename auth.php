<?php

function makeAuth1($realm, $message)
{
	header('HTTP/1.1 401 Unauthorized');
	header('WWW-Authenticate: Digest realm="'.$realm.
           '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
	die($message);
}

function getAuth($realm)
{
	if (empty($_SERVER['PHP_AUTH_DIGEST']))
		return false;

  //  update users set passwd=md5(login || ':' || realm || ':' || $password), admin=true where login=$login;

	// function to parse the http auth header
	function http_digest_parse($txt)
	{
	    // protect against missing data
	    $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
	    $data = array();
	    $keys = implode('|', array_keys($needed_parts));

	    preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

	    foreach ($matches as $m) {
	        $data[$m[1]] = $m[3] ? $m[3] : $m[4];
	        unset($needed_parts[$m[1]]);
	    }

	    return $needed_parts ? false : $data;
	}
	// analyze the PHP_AUTH_DIGEST variable
	if (!($data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])))
    makeAuth1($realm, 'Invalid credentials!');
  $result = pg_query_params("SELECT * from users WHERE login=$1 AND realm=$2", array($data['username'], $realm));
  $userinfo = @pg_fetch_array($result);
  pg_free_result($result);
  if (!$userinfo)
    makeAuth1($realm, 'No such user!');

	// generate the valid response
	$A1 = $userinfo['passwd'];
	$A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
	$valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

	if ($data['response'] != $valid_response)
    makeAuth1($realm, 'Wrong password!');

	return $userinfo;
}
?>
