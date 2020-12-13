#!/usr/bin/python3
"""
    Example usage of the saig0_player class.
"""

# Import the saig0 library
import saig0_lib as sl

# Create two players.
# Load data if available
player_one = sl.saig0_player ("Player One", "p1.dat")
player_two = sl.saig0_player ("Player Two", "p2.dat")

# Create players if no players created yet.
if (player_one.secret == ""):
    player_one.create_player ()

if (player_two.secret == ""):
    player_two.create_player ()

# Create a game to play
game_name = player_one.create_new_game ()

# Join the game
player_two.join_game (game_name)



player_one.initialize_hand ()
player_two.initialize_hand ()

#Play 7 rounds.
for i in range (7):
    print (player_one.play_random_number ()[1].text)
    print (player_two.play_random_number ()[1].text)


all_games = player_two.get_games_from_database ()

print ("From Player Two's perspective:")
for game in all_games:
    player_two.pretty_print_game (game)
