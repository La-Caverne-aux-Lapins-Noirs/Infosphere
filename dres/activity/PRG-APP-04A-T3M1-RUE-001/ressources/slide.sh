#!/bin/bash

LA=220
SI=247
DO=261
RE=293
MI=330
FA=349
SOL=392

./sequencer sin:isaw 180 \
	    $LA: $SI $RE $SOL $SI: $LA $FA~ $FA~

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
