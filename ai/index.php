<?php
echo "<center><h1>NOT IN USE.</h1></center>";
exit;
/*
	index.php 

	URI Parser file to hand the GET/POST requets.

	games/
		POST:	Create a new Game
		PUT:	Take a turn at a game
		GET:	Get a game, or many games
		

	users/
		POST:	Create a new user
		GET:	Get username from secret
	
	
 */

// Parse URI
$method = strtolower ($_SERVER['REQUEST_METHOD']);

$uri = str_replace ("/saig0/", $_SERVER['REQUEST_URI']);
$uri = explode ("/", $uri);
$num_uris = count ($uri);

if ($uri[0] == "games")
{
	if ($method == "post") // Create new game
	{
	}
	else if ($method == "put") // play a hand
	{

	}
	else if ($method == "get") // get completed games
	{

	}
	else
	{
		echo json_encode
		(array (
			"status"	=> "Unknown method of <{$method}> to games."
		));
		exit;
	}
}// end if uri[0] == games
else if ($uri[0] == "users")
{
	if ($method == "post") // Create new user
	{
	}
	else if ($method == "get") // get user
	{
	}
	else
	{
		echo json_encode
		(array (
			"status"	=> "Unknown method of <{$method}> to users."
		));
		exit;
	}

}// end if uri[0] == users

