#!/bin/bash

./sequencer square:itriangle 360 \
	    82 220 82 196 82 164 82 82 164 82 164 82 164 147 82 164 \
	    82 220 82 196 82 164 82 82 164 82 164 82 164 147 82 164 \
	    82 247 92 220 92 185 92 92 185 92 185 92 185 164 92 185 &

function ctrl_c() {
    pkill sequencer
    exit 0
}

trap "ctrl_c" 2

echo "Press [CTRL+C] to stop.."
while true
do
	sleep 1
done
