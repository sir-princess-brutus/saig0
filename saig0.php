<?php
/*
	The single-file game code.

	The first if/else block is all POST methods

	The second is all GET methods.

	These should probably be split at some point, perhaps into several
	files depending on what is asked for. 
 */
include_once ("/var/www/phplib/saig0_gameplay.php");
include ("/var/www/phplib/ai_game_db_login.php");
$db = login_saig0_mariadb ();

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
			- 201:	play succeeded, player and game secret returned as well as new game_state
			- 400:	no play_value supplied, no play taken
			  		attempting to repeat a <play_value>, no play taken
					attempting to play an invalid value for <play_value, no play taken
					not player's turn, wait for opponent, no play taken
			- 404:	<player_secret> and <game_secret> combination not found, no game to play for player
			- 500:	server error, no play occured

-------------------------------------------------------------------------------------------------------
	new_game: create a new game and join

		additional keys:
			- player_secret:	secret of player to add to the new game
			- new_game_secret:	optional, the secret for the game.
			- public_data:		optional, set to 0 to keep community from viewing (lame)
			- public_join:		optional, set to 1 to allow community members to join (cool)
			- game_name:		optional, if not included then time () is used as the name
			- ai_game:			optional, if included, play against the ai daemon

		returns:
			- 201:	game created, game_secret and public_X returned
			- 400:	game name already in use, no game created
			- 400:	no player secret supplied, cannot add player to game, no game created
			- 400:	player secret not found, does not exist, no game created
			- 409:	supplied new_game_secret already exists, no game created (optional argument)
			- 500:	server error, new game not created.

-------------------------------------------------------------------------------------------------------
	join_game: join an existing game

		additional keys:
			- player_secret:	secret of player joining game
			- game_name:		name of the game to join

		returns:
			- 201:	player added to game, player and game secrets returned
			- 400:	no player_secret supplied, game not joined
			- 400:	no game_secret supplied, game not joined
			- 500:	server error, game not joined

-------------------------------------------------------------------------------------------------------
	new_player: create a new player

		additional_keys:
			- username:			username for the new player, can be omitted.

		returns:
			- 201:	player created, player_ecret returned
			- 500:	server error, player not created
	
*/

if (isset ($_POST['play_game']))
{
	$game_info = requested_game_info ($_POST, $db);

	if (!$game_info['game_state']['player_turn']) // not the players turn.
	{
		http_response_code (400);
		echo json_encode
			(array (
				"detailed_status" => "Opponent has not played yet, check back later.",
				"player_secret" => $_POST['player_secret'],
				"game_name" => $_POST['game_name'],
				"game_state" => get_game_state ($game_id, $player_id, $opponent_id)['game_state']
			));
		exit;
	}
	else
	{
		$player_id = $game_info['player_id'];
		$opponent_id = $game_info['opponent_id'];
		$game_id = $game_info['game_id'];
		if (valid_play_value ($player_id, $game_id, $_POST, $db))
			take_turn ($player_id, $opponent_id, $game_id, $_POST['play_value'], $_POST, $db);
		else
		{
			http_response_code (500);
			echo json_encode (array ("detailed_status" => "Server error in valid_play_value, sorry."));
			exit;
		}
	}
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

	// Set game name, check if in db
	$game_name = $_POST['game_name'] ?? time ();
	$stmt = $db->prepare ("SELECT COUNT(id) AS cnt FROM games WHERE game_name = :game_name");
	$stmt->execute (array ("game_name" => $game_name));
	if ($stmt->fetch (PDO::FETCH_ASSOC)['cnt'] > 0)
	{
		http_response_code (400);
		echo json_encode
			(array (
				"detailed_status" => "Supplied <game_name> already exists. Leave blank to use time as name."
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
	$public_data = 1;
	if (isset ($_POST['public_game']))
		if ($_post['publice_game'] == 0)
			$public_data = 0;
	$public_join = 0;
	if (isset ($_POST['public_join']))
		if ($_post['publice_join'] == 1)
			$public_join = 1;
	$ai_game = 0;
	if (isset ($_POST['ai_game']))
		$ai_game = 1;
	// Private data not yet implemented.
	$public_data = 1;
	// Private joins not yet implemented.
	$public_join = 1;
	// Now to insert!
	$stmt = $db->prepare
	("
		INSERT INTO games
			(secret, public_data, public_join, game_name)
		VALUES
			(:secret, :public_data, :public_join, :game_name)
	");
	if ($stmt->execute
			(array (
				"secret" => $secret,
				"public_data" => $public_data,
				"public_join" => $public_join,
				"game_name" => $game_name
			))
		)
	{
		// Get game_id for newly created game.
		$stmt = $db->prepare ("SELECT id FROM games WHERE secret = :secret");
		$stmt->execute (array ("secret" => $secret));
		$game_id = $stmt->fetch (PDO::FETCH_ASSOC)['id'];

		// Insert into game_players
		$stmt = $db->prepare
		("
			INSERT INTO game_players
				(game_id, player_id)
			VALUES
				(:game_id, :player_id)
		");
		$stmt->execute (array ("game_id" => $game_id, "player_id" => $player['id']));
		http_response_code (201);
		echo json_encode
			(array (
				"detailed_status" => "Game created. Note private games and private data are not implemented.",
				"public_data" => $public_data,
				"public_join" => $public_join,
				"game_secret" => $secret,
				"game_name" => $game_name,
				"ai_game" => $ai_game
			));
		if ($ai_game == 1)
			shell_exec ("/var/www/saig0_daemon/random_play_daemon.py " . $game_name . " 2>&1 > /dev/null");
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
	if (isset ($_POST['game_name']))
	{
		// Check if game_name gives an open game
		$stmt = $db->prepare
		("
			SELECT COUNT(gms.id) AS cnt, gms.id 
			FROM games gms
			RIGHT JOIN game_players gps
				ON gps.game_id = gms.id
			WHERE
				gms.game_name = :game_name
		");
		$stmt->execute (array ("game_name" => $_POST['game_name']));
		$fetched = $stmt->fetch (PDO::FETCH_ASSOC);
		$num_players = $fetched['cnt'];
		$game_id = $fetched['id'];
		if ($num_players == 0)
		{
			http_response_code (400);
			echo json_encode
				(array (
					"detailed_status" => "Supplied <game_name> not found.",
					"game_name" => $_POST['game_name']
				));
			exit;
		}
		if ($num_players > 1)
		{
			http_response_code (400);
			echo json_encode
				(array (
					"detailed_status" => "Supplied <game_name> already full.",
					"game_name" => $_POST['game_name']
				));
			exit;
		}
	}
	else
	{
		http_response_code (400);
		echo json_encode (array ("detailed_status" => "No <game_name> supplied."));
		exit;
	}

	// Now insert player_id and game_id into the game_players
	$stmt = $db->prepare
	("
		INSERT INTO game_players
			(player_id, game_id)
		VALUES
			(:player_id, :game_id)
	");
	if ($stmt->execute (array ("player_id" => $player_id, "game_id" => $game_id)))
	{
		http_response_code (201);
		echo json_encode
			(array (
				"detailed_status" => "Player added to game.",
				"player_secret" => $_POST['player_secret'],
				"game_name" => $_POST['game_name']
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
	$username = $_POST['username'] ?? "default_username";
	$username = str_replace (" ", "_", $username);
	$secret = password_hash (time (), PASSWORD_DEFAULT);

	$stmt = $db->prepare ("INSERT INTO players (username, secret) VALUES (:username, :secret)");
	if ($stmt->execute (array ("username" => $_POST['new_player'], "secret" => $secret)))
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
{
	$stmt = $db->prepare
	("
		SELECT
			gps.game_id, 
			gps.player_id
		FROM game_players gps
		LEFT JOIN games gms
			ON gms.id = gps.game_id
		WHERE
			gms.public_data = 1
			AND gms.completed = 1
	");
	$game_ids = array ();
	$stmt->execute ();
	while ($row = $stmt->fetch (PDO::FETCH_ASSOC))
		if (array_key_exists ($row['game_id'], $game_ids))
			$game_ids[$row['game_id']][] = $row['player_id'];
		else
			$game_ids[$row['game_id']] = array ($row['player_id']);
	$games = array ();
	foreach ($game_ids as $game_id => $player_ids)
		$games[] = get_game_state ($game_id, $player_ids[0], $player_ids[1]);

	$num_games = count ($games);
	echo json_encode
		(array (
			"detailed_status" => "Collected {$num_games} games for you.",
			"games" => $games
		));
	exit;
}

?>
