<?php
echo "<center><h1>NOT IN USE.</h1></center>";
exit;

include ("/var/www/phplib/ai_game_db_login.php");
$db = login_saig0_mariadb ();

/*
	POST methods

	Each method is defined by the main _POST key used. Additional keys are noted afterwards.
	colons are not part of keys.

	Returns include an HTTP status code, as well as a JSON string. The key <status> 
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

		returns:
			- 201:	game created, game_secret and public_X returned
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
			- 201:	player added to game, player and game secrets returned
			- 400:	no player_secret supplied, game not joined
			- 400:	no game_secret supplied, game not joined
			- 500:	server error, game not joined

-------------------------------------------------------------------------------------------------------
	new_player: create a new player

		additional_keys:
			- N/A

		returns:
			- 201:	player created, player_ecret returned
			- 500:	server error, player not created
	
*/

//
//	Check if a secret exists in the secret_table.
//
//	Whitelist for secret_tables:
//		- players
//		- games
//
//	returns:
//		array with key-values:
//			(exists, true/false)
//			(id, <id of secret)
//
function check_secret_exists ($secret, $secret_table)
{
	global $db;
	$ret = array ("count" => 0, "id" => "invalid secret_table");

	if ($secre_table == "players")
		$qry = "SELECT COUNT(secret) AS count, id FROM players WHERE secret = :secret";
	else if ($secre_table == "games")
		$qry = "SELECT COUNT(secret) AS count, id FROM games WHERE secret = :secret";
	else
		return $ret;

	$stmt = $db->prepare ($qry);
	$stmt->execute (array ("secret" => $secret));
	$fetched = $stmt->fetch (PDO::FETCH_ASSOC);
	return $fetched;
}
//
//	Get the game state for the game defined byt
//		- game_id
//		- player_id
//		- opponent_id
//
//	See if it the player's turn, and get the state of the game at the moment,
//	considering all completed hands (no peaking if you opponnet played but you haven't
//	yet).
//
//	If you've played more hands than your opponent, you cannot play. Since this is checked
//	every time, once you played one more than your opponent, you can't play until they
//	play their hand.
//
//	Return:
//		player_turn:	true if the player can play, else false and they should not
//		game_state:		array of (player_value, opponent_value, hand_status)
//						hand_status is: (win, 1), (lose, -1), (tie, 0)
//						only completed hands can be seen, no peaking at what your opponent played
//
//
function get_game_state ($game_id, $player_id, $opponent_id)
{
	global $db;
	$stmt = $db->prepare
	("
		SELECT player_id, play_value
		FROM game_round_logs
		WHERE
			game_id = :game_id
		ORDER BY
			id
	");
	$stmt->execute (array ("game_id" => $game_id));
	$game_data = array ($player_id => array (), $opponent_id => array ());
	while ($row = $stmt->fetch (PDO::FETCH_ASSOC))
		$game_data[$row['player_id']][] = $row['play_value'];

	$player_count = count ($game_data[$player_id]);
	$opponent_count = count ($game_data[$opponent_id]);
	$completed_rounds = min ($player_count, $opponent_count);

	$game_state = array ();
	for ($i = 0; $i < $completed_rounds; $i++)
	{
		// player value, opponent value
		$pval = $game_data[$player_id][$i];
		$oval = $game_data[$opponent_id][$i];
		// win, lose, tie: 1, 0, -1
		$wlt = ($pval > $oval) ? 1 : (($pval == $oval) ? 0 : -1);
		$game_state[] = array ($pval, $oval, $wlt);
	}
	return array (
					"player_turn" => ($player_count <= $opponent_count ? true : false),
					"game_state" => $game_state
				);
}

function play_game ()
{
	// Check if player and game secrets have a record
	$stmt = $db->prepare
	("
		SELECT
			COUNT(gms.id) AS count,
			pls.id AS player_id,
		    gms.id AS game_id,
			(SELECT players.id
     			FROM games 
				LEFT JOIN game_players ON game_players.game_id = games.id
				LEFT JOIN players ON players.id = game_players.player_id
     			WHERE games.secret = gms.secret AND players.secret != pls.secret
				) AS opponent_id
		FROM games gms
		LEFT JOIN game_players gmps
			ON gmps.game_id = gms.id
		LEFT JOIN players pls
			ON pls.id = gmps.player_id
		WHERE
			pls.secret = :player_secret
			AND gms.secret = :game_secret
	");
	$stmt->execute (array ("player_secret" => $_POST['player_secret'], "game_secret" => $_POST['game_secret']));
	$fetched = $stmt->fetch (PDO::FETCH_ASSOC);
	if ($fetched['count'] != 1)
	{
		http_response_code (404);
		echo json_encode
			(array (
				"status" => "Supplied <player_secret>, <game_seret> combination not found.",
				"player_secret" => $_POST['player_secret'],
				"game_secret" => $_POST['game_secret']
			));
		exit;
	}
	// Get game state via ids
	$player_id = $fetched['player_id'];
	$game_id = $fetched['game_id'];
	$opponent_id = $fetched['opponent_id'];
	$game_state = get_game_state ($game_id, $player_id, $opponent_id);

	// Check existence of play value.
	if (isset ($_POST['play_value']))
	{
		$play_value = $_POST['play_value'];
		$stmt = $db->prepare ("SELECT play_value FROM game_round_logs"
							. " WHERE game_id = :game_id AND player_id = :player_id");
		$stmt->execute (array ("game_id" => $game_id, "player_id" => $player_id));
		while ($row = $stmt->fetch (PDO::FETCH_ASSOC))
		{
			if ($row['play_value'] == $play_value)
			{
				http_response_code (400);
				echo json_encode
					(array (
						"status" => "Repeated <play_value> error, you already played that value.",
						"play_value" => $play_value,
						"player_secret" => $_POST['player_secret'],
						"game_secret" => $_POST['game_secret']
					));
				exit;
			}
			else if ($play_value < 1 or 7 < $play_value)
			{
				http_response_code (400);
				echo json_encode
					(array (
						"status" => "Incorrect <play_value> error, not between 1 and 7 inclusive.",
						"play_value" => $play_value,
						"player_secret" => $_POST['player_secret'],
						"game_secret" => $_POST['game_secret']
					));
				exit;
			}
		}
	}
	else
	{
		http_response_code (400);
		echo json_encode
			(array (
				"detailed_response" => "No <play_value> supplied.",
				"player_secret" => $_POST['player_secret'],
				"game_secret" => $_POST['game_secret']
			));
		exit;
	}

	// If it's the players turn, go ahead
	if ($game_state['player_turn'])
	{
		
		$stmt = $db->prepare
		("
			INSERT INTO game_round_logs
				(game_id, player_id, play_value)
			VALUES
				(:game_id, :player_id, :play_value)
		");
		if ($stmt->execute (array ("player_id" => $player_id, "game_id" => $game_id,
									"play_value" => $_POST['play_value'])))
		{
			http_response_code (201);
			$game_state = get_game_state ($game_id, $player_id, $opponent_id)['game_state'];
			// Check if final hand
			if (count ($game_state) == 7)
			{
				$stmt = $db->prepare ("UPDATE games SET completed = 1 WHERE id = :game_id");
				$stmt->execute (array ("game_id" => $game_id));
				echo json_encode
					(array (
						"status" => "Final play made.",
						"player_secret" => $_POST['player_secret'],
						"game_secret" => $_POST['game_secret'],
						"game_state" => get_game_state ($game_id, $player_id, $opponent_id)['game_state']
					));
				exit;
				
			}
			else 
			{
				echo json_encode
					(array (
						"status" => "Play made.",
						"player_secret" => $_POST['player_secret'],
						"game_secret" => $_POST['game_secret'],
						"game_state" => get_game_state ($game_id, $player_id, $opponent_id)['game_state']
					));
				exit;
			}
		}
		else
		{
			http_response_code (500);
			echo json_encode
				(array (
					"status" => "Failed play for <player_secret> in <game_secret>."
											. " Server error, sorry. Please contact the admin.",
					"player_secret" => $_POST['player_secret'],
					"game_secret" => $_POST['game_secret']
				));
			exit;
		}
	}
	else
	{
		http_response_code (400);
		echo json_encode
			(array (
				"status" => "Opponent has not played yet, check back later.",
				"player_secret" => $_POST['player_secret'],
				"game_secret" => $_POST['game_secret']
			));
		exit;
	}
} // end of play_game ()


else if (isset ($_POST['new_game']))
{
	if (!isset ($_POST['player_secret']))
	{
		http_response_code (400);
		echo json_encode
			(array (
				"status" => "No <player_secret> supplied to create game."
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
				"status" => "Supplied <player_secret> not found.",
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
				"status" => "Supplied <new_game_secret> already used, no game created.",
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

	// Now to insert!
	$stmt = $db->prepare
	("
		INSERT INTO games
			(secret, public_data, public_join)
		VALUES
			(:secret, :public_data, :public_join)
	");
	if ($stmt->execute (array ("secret" => $secret,
								"public_data" => $public_data, "public_join" => $public_join)))
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
				"status" => "Game created.",
				"public_data" => $public_data,
				"public_join" => $public_join,
				"game_secret" => $secret
			));
		exit;
	}
	else
	{
		http_response_code (500);
		echo json_encode
			(array (
				"status" => "Failed to create game for <player_secret>."
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
					"status" => "Supplied <player_secret> not found.",
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
				"status" => "No <player_secret> supplied."
			));
		exit;
	}
	if (isset ($_POST['game_secret']))
	{
		// Check if game_secret gives an open game
		$stmt = $db->prepare
		("
			SELECT COUNT(gms.id) AS cnt, gms.id 
			FROM games gms
			RIGHT JOIN game_players gps
				ON gps.game_id = gms.id
			WHERE
				gms.secret = :secret
		");
		$stmt->execute (array ("secret" => $_POST['game_secret']));
		$fetched = $stmt->fetch (PDO::FETCH_ASSOC);
		$num_players = $fetched['cnt'];
		$game_id = $fetched['id'];
		if ($num_players == 0)
		{
			http_response_code (400);
			echo json_encode
				(array (
					"status" => "Supplied <game_secret> not found.",
					"game_Secret" => $_POST['game_secret']
				));
			exit;
		}
		if ($num_players > 1)
		{
			http_response_code (400);
			echo json_encode
				(array (
					"status" => "Supplied <game_secret>  already full.",
					"game_Secret" => $_POST['game_secret']
				));
			exit;
		}
	}
	else
	{
		http_response_code (400);
		echo json_encode (array ("status" => "No <game_secret> supplied."));
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
				"status" => "Player added to game.",
				"player_secret" => $_POST['player_secret'],
				"game_secret" => $_POST['game_secret']
			));
		exit;
	}
	else
	{
		http_response_code (500);
		echo json_encode
			(array (
				"status" => "Failed to add player to game."
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

	$stmt = $db->prepare ("INSERT INTO players (name, secret) VALUES (:name, :secret)");
	if ($stmt->execute (array ("name" => $_POST['new_player'], "secret" => $secret)))
	{
		http_response_code (201);
		echo json_encode
			(array (
				"status" => "Player created.",
				"player_secret" => $secret
			));
		exit;
	}
	else
	{
		http_response_code (500);
		echo json_encode
			(array (
				"status" => "Failed to add new player."
				. " Server error, sorry. Please contact the admin."
			));
		exit;
	}
}
else
	echo "<center style = 'margin: 50px;'><h1>Kesha will find you.</h1></center>\n";

?>
