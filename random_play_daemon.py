#!/usr/bin/python3
"""
    AI Daemon to play against.
"""

# Import the saig0 library
import saig0_lib as sl
import sys, time

game_name = sys.argv[1]

ai_player = sl.saig0_player ("AI Player", "ai_player.dat")

# Create players if not created yet.
if (ai_player.secret == ""):
    ai_player.create_player ()

# Join the game
ai_player.join_game (game_name)



ai_player.initialize_hand ()

#Play 7 rounds.
for i in range (7):
    wait_seconds = 1
    error = 1
    while (error and wait_seconds < 512):
        [error, response] = player.play_random_number ()
        time.sleep (wait_seconds)
        wait_seconds *= 2
        
    if (wait_seconds > 256):
        break

ai_games = ai_player.get_games_from_datase ()
with open ("ai_games.dat", "w") as f:
    game_count = 1
    f.write ("ai_play,opponent_play,win-loss-tie\n")
    for game in ai_games:
        f.write (f"{game_count}\n")
        for gr in game:
            f.write (f"{gr[0]},{gr[1]},{gr[2]}\n")
    game_count += 1
