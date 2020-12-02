
#include <stdio.h>
#include <stdlib.h>
#include <math.h>

int
sum_array (int *array, int N)
{
	int i;
	int ret = 0;
	for (i = 0; i < N; i++)
		ret += array[i];
	return ret;
}


int
increment_array (int *array, int N)
{
	int digit = 0;
	char next = 1;
	while (next && digit < N)
	{
		if (array[digit] == 1)
		{
			array[digit] = -1;
			next = 1;
			digit++;
		}
		else
		{
			array[digit]++;
			next = 0;
		}
	}
	// if next is 1, then we reached the end and could not increment.
	if (next)
		return 0;
	else
		return 1;
}

int
main (int argc, char **argv)
{
	// Set game length and initialize to all losses.
	int game_length = atoi (argv[1]);
	int *game = (int *) calloc (game_length, sizeof (int));
	int i, net_game, game_result;
	for (i = 0; i < game_length; i++)
		game[i] = -1;
	
	FILE *f = fopen ("outcomes.dat", "w");
	double wins = 0.0;
	double losses = 0.0;
	double ties = 0.0;

	//int break_count = 0;
	i = 0; // game count
	do
	{
		net_game = sum_array (game, game_length);
		if (net_game > 0)
		{
			game_result = 1;
			wins += 1.0;
		}
		else if (net_game < 0)
		{
			game_result = -1;
			losses += 1.0;
		}
		else
		{
			game_result = 0;
			ties += 1.0;
		}
		//printf ("%d | %d, %d\n", ++i, game_result, net_game);
		fprintf (f, "%d,%d,%d\n", ++i, game_result, net_game);
		
		//break_count++;
		//if (break_count > pow (3, game_length + 1))
		//	break;
	} while (increment_array (game, game_length) == 1);

	fclose (f);

	printf ("Wins: %.2f\nLoss: %.2f\nTies: %.2f\n", wins / (double)i, losses / (double)i, ties / (double)i);
	return 0;
}

