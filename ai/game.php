<?php
/*
	The single-file game code.

	The first if/else block is all POST methods

	The second is all GET methods.

	These should probably be split at some point, perhaps into several
	files depending on what is asked for. 
*/
$db = new PDO ("mysql:host=localhost", "php_api", "super-secret-password");
$db->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$db->exec ("SET ROLE ai_game");
$db->exec ("USE ai_game_db");

/*
	POST methods

	Each method is defined by the main _POST key used. Additional keys are noted afterwards.
	colons are not part of keys.

	Returns include an HTTP status code, as well as a JSON string. The key <detailed_status> 
	gives additional information about the HTTP status code. Some additional keys may be
	present, depending on the status and main _POST key.

	All 500 error codes indicate an uncaught error. Please contact the admin with your
	request, payload and headers. Also response headers if possible.

-------------------------------------------------------------------------------------------------------
	play_game: take a turn in the game

		additional keys:
			- game_secret:		secret to the game to play in
			- player_secret:	secret for the player playing
			- player_value:		value to be played

		returns:
			NOT FINISHED

-------------------------------------------------------------------------------------------------------
	new_game: create a new game and join

		additional keys:
			- player_secret:	secret of player to add to the new game
			- new_game_secret:	optional, the secret for the game.

		returns:
			- 201:	game created, game_secret returned
			- 400:	no player secret supplied, cannot add player to game, no game created
			- 400:	player secret not found, does not exist, no game created
			- 409:	supplied new_game_secret already exists, no game created (optional argument)
			- 500:	server error, new game not created.

-------------------------------------------------------------------------------------------------------
	join_game: join an existing game

		additional keys:
			- player_secret:	secret of player joining game
			- game_secret:		secret of the game to join

		returns:
			- 201:	player added to game
			- 400:	no player_secret supplied, game not joined
			- 400:	no game_secret supplied, game not joined
			- 500:	server error, game not joined

-------------------------------------------------------------------------------------------------------
	new_player: create a new player

		additional_keys:
			- N/A

		returns:
			- 201:	player created
			- 500:	server error, player not created
	
*/
if (isset ($_POST['play_game']))
{
	echo "Play game!";
	exit;
}
else if (isset ($_POST['new_game']))
{
	if (!isset ($_POST['player_secret']))
	{
		http_response_code (400);
		echo json_encode
			(array (
				"detailed_status" => "No <player_secret> supplied to create game."
			));
		exit;
	}
	// Check if player secret is in the db
	$stmt = $db->prepare ("SELECT COUNT(py.secret) AS cnt, id FROM players py WHERE py.secret = :secret");
	$stmt->execute (array ("secret" => $_POST['player_secret']));
	$player = $stmt->fetch (PDO::FETCH_ASSOC);
	$count = $player['cnt'];
	if ($count != 1)
	{
		http_response_code (404);
		echo json_encode
			(array (
				"detailed_status" => "Supplied <player_secret> not found.",
				"player_secret" => $_POST['player_secret']
			));
		exit;
	}
	// Set the secret from user, or as hashed time. 
	$secret = "";
	if (isset ($_POST['new_game_secret']))
		$secret = $_POST['new_game_secret'];
	else
		$secret = password_hash (time (), PASSWORD_DEFAULT);

	// Check if secret already exists
	$stmt = $db->prepare ("SELECT COUNT(gms.secret) AS cnt FROM games gms WHERE gms.secret = :secret");
	$stmt->execute (array ("secret" => $secret));
	$count = $stmt->fetch (PDO::FETCH_ASSOC)['cnt'];
	if ($count > 0) // Already exists, failure.
	{
		http_response_code (409);
		echo json_encode
			(array (
				"detailed_status" => "Supplied <new_game_secret> already used, no game created.",
				"new_game_secret" => $secret
			));
		exit;
	}
	// Now to insert!
	$stmt = $db->prepare
	("
		INSERT INTO games
			(player_id, secret, player_number)
		VALUES
			(:player_id, :secret, 1)
	");
	if ($stmt->execute (array ("player_id" => $player['id'], "secret" => $secret)))
	{
		http_response_code (201);
		echo json_encode
			(array (
				"detailed_status" => "Game created.",
				"game_secret" => $secret
			));
		exit;
	}
	else
	{
		http_response_code (500);
		echo json_encode
			(array (
				"detailed_status" => "Failed to create game for <player_secret>."
				. " Server error, sorry. Please contact the admin.",
				"player_secret" => $_POST['player_secret']
			));
		exit;
	}
}
else if (isset ($_POST['join_game']))
{
	if (isset ($_POST['player_secret']))
	{
		// Check if player_secret gives id.
		$stmt = $db->prepare ("SELECT id FROM players WHERE secret = :secret");
		$stmt->execute (array ("secret" => $_POST['player_secret']));
		$fetched = $stmt->fetch (PDO::FETCH_ASSOC);
		if ($fetched == false)
		{
			http_response_code (400);
			echo json_encode
				(array (
					"detailed_status" => "Supplied <player_secret> not found.",
					"player_secret" => $_POST['player_secret']
				));
			exit;
		}
		$player_id = $fetched['id'];
	}
	else
	{
		http_response_code (400);
		echo json_encode
			(array (
				"detailed_status" => "No <player_secret> supplied."
			));
		exit;
	}
	if (isset ($_POST['game_secret']))
	{
		// Check if game_secret gives an open game
		$stmt = $db->prepare ("SELECT COUNT(id) AS cnt FROM games WHERE secret = :secret");
		$stmt->execute (array ("secret" => $_POST['game_secret']));
		$fetched = $stmt->fetch (PDO::FETCH_ASSOC);
		$num_players = $fetched['cnt'];
		if ($num_players == 0)
		{
			http_response_code (400);
			echo json_encode
				(array (
					"detailed_status" => "Supplied <game_secret> not found.",
					"game_Secret" => $_POST['game_secret']
				));
			exit;
		}
		if ($num_players > 1)
		{
			http_response_code (400);
			echo json_encode
				(array (
					"detailed_status" => "Supplied <game_secret>  already full.",
					"game_Secret" => $_POST['game_secret']
				));
			exit;
		}
	}
	else
	{
		http_response_code (400);
		echo json_encode (array ("detailed_status" => "No <game_secret> supplied."));
		exit;
	}

	// Now insert player id into the game
	$stmt = $db->prepare
	("
		INSERT INTO games
			(player_id, player_number, secret)
		VALUES
			(:player_id, 2, :secret)
	");
	if ($stmt->execute (array ("player_id" => $player_id, "secret" => $_POST['game_secret'])))
	{
		http_response_code (201);
		echo json_encode
			(array (
				"detailed_status" => "Player added to game.",
				"player_secret" => $_POST['player_secreet'],
				"game_secret" => $_POST['game_secret']
			));
		exit;
	}
	else
	{
		http_response_code (500);
		echo json_encode
			(array (
				"detailed_status" => "Failed to add player to game."
					. " Server error, sorry. Please contact the admin.",
				"player_secret" => $_POST['player_secret'],
				"game_secret" => $_POST['game_secret']
			));
		exit;
	}
}
else if (isset ($_POST['new_player']))
{
	$secret = password_hash (time (), PASSWORD_DEFAULT);

	$stmt = $db->prepare ("INSERT INTO players (player, secret) VALUES (:player, :secret)");
	if ($stmt->execute (array ("player" => $_POST['new_player'], "secret" => $secret)))
	{
		http_response_code (201);
		echo json_encode
			(array (
				"detailed_status" => "Player created.",
				"player_secret" => $secret
			));
		exit;
	}
	else
	{
		http_response_code (500);
		echo json_encode
			(array (
				"detailed_status" => "Failed to add new player."
				. " Server error, sorry. Please contact the admin."
			));
		exit;
	}
}
else
	echo "<center style = 'margin: 50px;'><h1>Kesha will find you.</h1></center>\n";

?>
