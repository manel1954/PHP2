#!/bin/bash

stick=$(awk "NR==40" /home/pi/status.ini)
raw=$(awk "NR==42" /home/pi/status.ini)
ppm=$(awk "NR==44" /home/pi/status.ini)
http=$(awk "NR==46" /home/pi/status.ini)
gain=$(awk "NR==48" /home/pi/status.ini)
beast=$(awk "NR==50" /home/pi/status.ini)
index=$(awk "NR==52" /home/pi/status.ini)

LOG=/tmp/dump1090.log
PID_FILE=/tmp/dump1090.pid

# Matar instancia previa si existe
if [ -f "$PID_FILE" ]; then
    OLD_PID=$(cat "$PID_FILE")
    kill "$OLD_PID" 2>/dev/null
    rm -f "$PID_FILE"
fi

if [ "$stick" = 'RSP1' ]; then
    /home/pi/dump1090_sdrplay/dump1090 --net --interactive --gain $gain --dev-sdrplay \
        >> "$LOG" 2>&1 &

elif [ "$gain" = '-10' ]; then
    /home/pi/dump1090/dump1090 --device $index --net --interactive \
        --net-ro-port $raw --net-bo-port $beast --gain $gain --ppm $ppm \
        --net-http-port $http >> "$LOG" 2>&1 &
else
    /home/pi/dump1090/dump1090 --device $index --net --interactive \
        --net-ro-port $raw --net-bo-port $beast --ppm $ppm \
        --net-http-port $http >> "$LOG" 2>&1 &
fi

echo $! > "$PID_FILE"
echo "dump1090 iniciado con PID $(cat $PID_FILE)"