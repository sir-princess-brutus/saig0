"""
    saig0_lib.py

    Client lib for users (game players).

    A class that implements the requests library to play the game.


    The important part is deciding which number to play next, so the user
    will have to define that function, but there is a basic random selection
    function included near the bottom.
"""
import os.path, requests, time, random, json

class saig0_player:
    """
        A player in the game.
    """
    def __init__ (self, username = None, filename = None, url_endpoint = "http://gixinc.com/saig0/saig0.php"):
        """
            Initialize player. 

            Attempt to load data from filename, else leave data blank.
        """
        if (filename == None):
            filename = "default_" + str (time.time ()) + ".dat"
        else:
            filename = filename.replace (" ", "_")

        if (username == None):
            username = "username_" + str (time.time ())
        else:
            username = username.replace (" ", "_")
        self.filename = filename
        self.username = username
        self.secret = ""
        self.cur_game_name = ""
        self.cur_game_secret = ""
        self.hand = []
        self.url_endpoint = url_endpoint
        # If file exists, read it and assume proper formatting:
        #   username <value>
        #   secret <value>
        if (os.path.isfile (filename)):
            with open (filename, "r") as f:
                lines = f.readlines ()
                self.username = lines[0].split ()[1]
                self.secret = lines[1].split ()[1]

    def create_player (self, username = None):
        """
            Create a new player and write data to the filename.
        """
        # Get unique username if needed
        if (username == None):
            username = "default_username" + str (time.time ())
        self.username = username
        r = requests.post (self.url_endpoint, data = {"new_player": self.username})
        if (r.status_code != 201):
            print ("Failed to create user:\n", r.text)
            return r
        play_data = json.loads (r.text)
        self.secret = play_data['player_secret']
        with open (self.filename, "w") as f:
            f.write (f"username {self.username}\nsecret {self.secret}")

    def create_new_game (self, game_name = None, ai_game = False):
        """
            Create a new game, return the game_name for others to join.
        """
        if (game_name == None):
            game_name = "default_game_name" + str (time.time ())
        self.cur_game_name = game_name
        data =\
        {
            "new_game": True,
            "player_secret": self.secret,
            "game_name": self.cur_game_name
        }
        if (ai_game):
            data['ai_game'] = True
        r = requests.post (self.url_endpoint, data)
        if (r.status_code != 201):
            print ("Failed to create game:\n", r.text)
            return r
        # Not sure if there is any need for this--editing perhaps? Unimplemented.
        game_data = json.loads (r.text)
        self.cur_game_secret = game_data['game_secret']
        return self.cur_game_name


    def join_game (self, game_name):
        """
            Join the game given by game_name.
        """
        r = requests.post (self.url_endpoint,
            data = {"join_game": True, "player_secret": self.secret, "game_name": game_name})
        if (r.status_code != 201):
            print (f"ERROR: Failed to join game <{game_name}>:\n", r.text)
            return r

        join_data = json.loads (r.text)
        self.cur_game_name = game_name
        self.cur_game_secret = join_data ['game_name']

    def play_game_round (self, value):
        """
            Play the value in the current game.
        """
        if (self.cur_game_secret == ""):
            print ("ERROR: No game joined, join a game first.")
            return 1

        post_data = {"play_game": True, "game_name": self.game_name, "player_secret": self.secret,
                        "play_value": value}
        r = requests.post (self.url_endpoint, data = post_data)
        if (r.status_code != 201):
            print ("ERROR: Failed to make play:\n", r.text)
        return r

    def get_games_from_database (self):
        """
            Get a list of lists, the games played so far.

            Each round in a game is the form [<player_play>, <opponent_play>, <player_wlt>]
            where <player_wlt> is defined as:
                1:  player won
                0:  tie
                -1: player lost
        """
        r = requests.get (self.url_endpoint)
        if (r.status_code != 200):
            print ("Failed to get games:\n", r.text)
            return r
        
        games = json.loads (r.text)['games']
        return_list = []
        for game in games:
            return_list.append (game['game_state'])
        return return_list


    def initialize_hand (self):
        """
            Initialize a hand: list [1... 7]
        """
        self.hand = list (range (1, 8))
        self.game = []
    def play_random_number (self):
        """
            Play a random number in the current game
        """
        if (self.cur_game_secret == ""):
            print ("ERROR: No current game, join a game.")
            return 1

        play_value = self.hand.pop (random.randint (0, len (self.hand) - 1))
        r = requests.post (self.url_endpoint, data = {"play_game": True, "game_name": self.cur_game_name,
                                                    "player_secret": self.secret, "play_value": play_value})
        # Check if play was accepted
        if (r.status_code != 201):
            return [1, r]
        else:
            return [0, r]

    def pretty_print_game (self, game_list):
        """
            Print a single game, that's human readable.
        """
        hand_tally = 0
        for game in game_list:
            hand_tally += int (game[2])

        game_result = "won" if hand_tally > 0 else ("lost" if hand_tally < 0 else "tied")

        print ("I " + game_result + "!")
        for game in game_list:
            print (game)
        print ("-----------------------------------------------------------------")
    


























