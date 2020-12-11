#!/usr/bin/python3
"""
    Example usage of the saig0_player class.
"""

# Import the saig0 library
import saig0_lib as sl
import json

# Create player and Load data if available
player = sl.saig0_player ("Myspace Tom", "myspace_tom.dat")

# Create player if not created yet.
if (player.secret == ""):
    player.create_player ()

# Create a game to play
game_name = player.create_new_game (ai_game = True)

# Initialize the player's ahnd
player.initialize_hand ()

def make_a_play (player_in):
    """
        Make a play in the current game
    """
    response = player_in.play_game_round (7 - len (play_in.game))
    if (response.status_code == 201):
        player_in.game = json.loads (response.text)['game_state']


# Play 7 rounds.
for i in range (7):
    make_a_play (player_in)
    time.sleep (3.0 * random.random ())


all_games = player.get_games_from_database ()

print ("From Player's perspective:")
for game in all_games:
    player.pretty_print_game (game)

