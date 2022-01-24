#!/bin/bash

# "eighties"

./sequencer square:isaw 300 \
	    175: 175 175 220 175 220 175 165 165 494 165: 165 494 165 440 165 &
./sequencer sin:isaw 300 \
	    175: 175 175 220 175 220 175 165 165 494 165: 165 494 165 440 165 &
sleep 0.1
./sequencer sin:isaw 300 \
	    175: 175 175 220 175 220 175 165 165 494 165: 165 494 165 440 165 &

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
