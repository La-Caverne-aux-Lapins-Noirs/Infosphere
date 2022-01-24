#!/bin/bash

./sequencer noise:squarenoise 120 \
	    110 220 330 440 550 660 770 880

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
