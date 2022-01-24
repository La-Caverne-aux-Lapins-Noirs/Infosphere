#!/bin/bash

./sequencer square:isin 60 220 110 &

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
