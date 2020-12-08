#!/usr/bin/python3
"""
	test_playpy

	Basic testing for ai/game.py.

	Create:	two players
	Create:	a game
	Join:	a game
	Play:	a game
	Read:	game results.

"""

import requests, json, random

url_endpoint = "http://gixinc.com/saig0.php"

def print_simp_resp (response, title):
	"""
		Print a response prettily, with a title.
	"""
	print (f"{response.status_code} -- {title}")
	try:
		for k, v in json.loads (response.text).items ():
			print (f"{k:>15} | {v}")
		print ("")
	except:
		print (response.text)


######################################################################################
# Create Two Players
#

new_player_data =\
{
	"new_player": "Player One"
}

r = requests.post (url_endpoint, data = new_player_data)
print_simp_resp (r, "Player One")
player_one = json.loads (r.text)
del player_one['detailed_status']

new_player_data['new_player'] = "Player Two"
r = requests.post (url_endpoint, data = new_player_data)
print_simp_resp (r, "Player Two")
player_two = json.loads (r.text)
del player_two['detailed_status']

######################################################################################
# Player One Creates A Game
#

new_game_data =\
{
	"new_game": True,
	"player_secret": player_one['player_secret']
} # Not passing new_game_secret, using default
r = requests.post (url_endpoint, data = new_game_data)
print_simp_resp (r, "Player One Create Game")
r_dict = json.loads (r.text)
player_one['game_secret'] = r_dict['game_secret']

######################################################################################
# Player Two Joins The Game
#

join_game_data =\
{
	"join_game": True,
	"game_secret": player_one['game_secret'],
	"player_secret": player_two['player_secret']
}
r = requests.post (url_endpoint, data = join_game_data)
print_simp_resp (r, "Player Two Join Game")


######################################################################################
# Player One and Player Two Play A Game
#

# Initialize both players hands: 1-7 integers
player_one['hand'] = list (range (1, 8))
player_two['hand'] = list (range (1, 8))

play_game_data =\
{
	"play_game": True,
	"game_secret": player_one['game_secret']
}

# make a play, and print
def make_play (player, title = "Making a play."):
	play_game_data['player_secret'] = player['player_secret']
	play_game_data['play_value'] = player['hand'].pop (random.randint (0, len (player['hand']) - 1))
	r = requests.post (url_endpoint, data = play_game_data)
	print_simp_resp (r, title)

# each player plays
for i in range (7):
	make_play (player_one, "Player One, Hand " + str (i + 1))
	make_play (player_two, "Player Two, Hand " + str (i + 1))

# Did some testing on players trying to go twice, or sending in a repeated
# value, and it passed so far.


######################################################################################
# Player One Gets A Completed Game
#



######################################################################################
# Space To Not Stare At The Bottom Of The Screen
#































