#!/bin/bash

./sequencer noise:sin 360 \
	    500 5000 500 500 5000 500 500 5000 500 5000

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
