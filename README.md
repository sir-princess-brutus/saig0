# saig0: Shit AI Game V0

## Project Overview

This project is a simple game to play with backend RESTful APIs (for me, the author and any contributors), and to AI/Machine Learning for everyone.

The game is defined below:
* **Setup:** Each player begins with a hand of seven numbers, numbered between one and seven.
* **Overall Game:** The game consists of seven hands, with wins/losses/ties tallied at the end to calculate the winner. Overall ties are possible, so there is no winner.
* **Hands:** For each hand, both players choose a single number from their hand and play it without showing the other player. Once both players have played a number, they are revealed and the higher number wins. Ties count as neither a win or loss for either player.
  * Once a number has been played, it is removed from the players hand and cannot be played again.
  * Play continues until both players have no numbers in their hand.

## Brief File Notes

The file test_play.py can be used to see available features and examples. It just posts to ai/game.php on the gixinc.com server.

1. ai/game.php: The main game file that handles POST requests (only at the moment, GET to come soon).
2. test_play.py: A python script that plays a game between two new players randomly choosing numbers.
3. ai_game_db.sql: A blank database for setting up a server (does not include users/roles for login).
4. analysis/win_loss_tie.c: A C program that calculates the number of gae configurations for a number of hands.
5. setup.sh: Copies web pages to folders on server--not really necessary.
