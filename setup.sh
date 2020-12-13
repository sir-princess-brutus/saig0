#!/bin/bash

echo "cp saig0.php /var/www/html/saig0"
echo ""
cp saig0.php /var/www/html/saig0

echo "cp saig0_gameplay.php /var/www/phplib"
echo ""
cp saig0_gameplay.php /var/www/phplib

echo "cp saig0_lib.py random_play_daemon.py /var/www/saig0_daemon/"
cp saig0_lib.py random_play_daemon.py /var/www/saig0_daemon/
