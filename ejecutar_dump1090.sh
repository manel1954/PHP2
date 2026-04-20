#!/bin/bash


stick=$(awk "NR==40" /home/pi/status.ini)
raw=$(awk "NR==42" /home/pi/status.ini)
ppm=$(awk "NR==44" /home/pi/status.ini)
http=$(awk "NR==46" /home/pi/status.ini)
gain=$(awk "NR==48" /home/pi/status.ini)
beast=$(awk "NR==50" /home/pi/status.ini)
index=$(awk "NR==52" /home/pi/status.ini)

if [ "$stick" = 'RSP1' ];then
xterm -geometry 73x18+18+52 -bg black -fg green -fa ‘verdana’ -fs 9x -T DUMP1090 -e sudo /home/pi/dump1090_sdrplay/dump1090 --net --interactive --gain $gain --dev-sdrplay

elif [ "$gain" = '-10' ];then
xterm -geometry 71x18+8+62 -bg black -fg green -fa ‘verdana’ -fs 9x -T DUMP1090 -e sudo /home/pi/dump1090/dump1090 --device $index --net --interactive --net-ro-port $raw --net-bo-port $beast --gain $gain --ppm $ppm --net-http-port $http
else
xterm -geometry 71x18+8+62 -bg black -fg green -fa ‘verdana’ -fs 9x -T DUMP1090 -e sudo /home/pi/dump1090/dump1090 --device $index --net --interactive --net-ro-port $raw --net-bo-port $beast --ppm $ppm --net-http-port $http
fi

