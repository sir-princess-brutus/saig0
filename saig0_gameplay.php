<?php
/*
	Functions for checking the state of the game, etc.
 */



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

//
//	check if the game exists, and return ids if so, along with the gamestate.
//
//	args:
//		- post:	The post variable
//		- db:	The database connection to check game in
//
//	returns:
//		- array:	As described above.
//
//	exits:
//		- With 404 status code, game not found.
//
function requested_game_info ($post, $db)
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
			AND gms.game_name = :game_name
	");
	$stmt->execute (array ("player_secret" => $post['player_secret'], "game_name" => $post['game_name']));
	$fetched = $stmt->fetch (PDO::FETCH_ASSOC);

	if ($fetched['count'] != 1)
    {
        http_response_code (404);
        echo json_encode
            (array (
                "detailed_status" => "Supplied <player_secret>, <game_name> combination not found.",
                "player_secret" => $post['player_secret'],
                "game_name" => $post['game_name']
            )); 
        exit;
	}
	// return player, opponent and game ids, along with the current game state.
	$fetched['game_state'] = get_game_state (
								$fetched['game_id'], $fetched['player_id'], $fetched['opponent_id']);
	return $fetched;
} // end requested_game_info

//
//	Check if the play value was valid.
//
//	Exits with response code and message on failure, returns true if
//	the value is a valid play.
//
function valid_play_value ($player_id, $game_id, $post, $db)
{
	if (!isset ($post['play_value']))
	{
		http_response_code (400);
		echo json_encode
			(array (
				"detailed_response" => "No <play_value> supplied.",
				"player_secret" => $post['player_secret'],
				"game_name" => $post['game_name']
			));
		exit;
	}
	$play_value = $post['play_value'];
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
					"detailed_status" => "Repeated <play_value> error, you already played that value.",
					"play_value" => $play_value,
					"player_secret" => $post['player_secret'],
					"game_name" => $post['game_name']
				));
			exit;
		}
		else if ($play_value < 1 or 7 < $play_value)
		{
			http_response_code (400);
			echo json_encode
				(array (
					"detailed_status" => "Incorrect <play_value> error, not between 1 and 7 inclusive.",
					"play_value" => $play_value,
					"player_secret" => $post['player_secret'],
					"game_name" => $post['game_name']
				));
			exit;
		}
	} // end while rows
	return True;
} // end valid_play_value


//
//	Insert players turn into game.
//
//	exits with response code in all cases.
//
function take_turn ($player_id, $opponent_id, $game_id, $play_value, $post, $db)
{
	$stmt = $db->prepare
	("
		INSERT INTO game_round_logs
			(game_id, player_id, play_value)
		VALUES
			(:game_id, :player_id, :play_value)
	");
	if ($stmt->execute (array ("player_id" => $player_id, "game_id" => $game_id,
								"play_value" => $play_value)))
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
					"detailed_status" => "Final play made.",
					"player_secret" => $post['player_secret'],
					"game_name" => $post['game_name'],
					"game_state" => get_game_state ($game_id, $player_id, $opponent_id)['game_state']
				));
			exit;
			
		}
		else 
		{
			echo json_encode
				(array (
					"detailed_status" => "Play made.",
					"player_secret" => $post['player_secret'],
					"game_name" => $post['game_name'],
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
				"detailed_status" => "Failed play for <player_secret> in <game_secret>."
										. " Server error, sorry. Please contact the admin.",
				"player_secret" => $post['player_secret'],
				"game_name" => $post['game_name'],
				"game_state" => get_game_state ($game_id, $player_id, $opponent_id)['game_state']
			));
		exit;
	}
} // end take_turn
