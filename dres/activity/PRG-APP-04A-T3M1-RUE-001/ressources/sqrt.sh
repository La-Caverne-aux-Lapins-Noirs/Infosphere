#!/bin/bash

SOLB=196
LA=220
SI=247
DO=261
RE=293
MI=330
FA=349
SOL=392

./sequencer sqrt:sqrt 120 \
	    $SI $SI $DO $RE $RE $DO $SI $LA $SOLB $SOLB $LA $SI $LA 0 $SOLB: $SOLB

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
