# saig0: Shit AI Game V0

## Server/Game Status

The game is currently hosted on gixinc.com and can be played there, see test_play.py.

During this initial testing, the database will likely get deleted often, but will be available to play and use.

## Project Overview

This project is a simple game to play with backend RESTful APIs (for me, the author and any contributors), and to AI/Machine Learning for everyone.

The game is defined below:
* **Setup:** Each player begins with a hand of seven numbers, numbered between one and seven.
* **Overall Game:** The game consists of seven hands, with wins/losses/ties tallied at the end to calculate the winner. Overall ties are possible, so there is no winner.
* **Hands:** For each hand, both players choose a single number from their hand and play it without showing the other player. Once both players have played a number, they are revealed and the higher number wins. Ties count as neither a win or loss for either player.
  * Once a number has been played, it is removed from the players hand and cannot be played again.
  * Play continues until both players have no numbers in their hand.

## Current State

The Python3 scripts saig0_lib.py and lib_example_use.py can show how to play the game simply. 

Most of the files are leftovers and part of half-formed changes in organization. The releveant and important piece are :
* saig0.php: The main game program, a RESTful API interface.
* saig0_lib.py: The client library for playing the game, or code your own requests interface!
* db_4_ai_game.sql: A blank database if you want to play with it on your own server.

All other files are part of thinking about the best way to organize URL endpoints, apologies for the clutter.
