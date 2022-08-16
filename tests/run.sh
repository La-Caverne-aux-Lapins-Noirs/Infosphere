#!/bin/sh

rm -f database.sql
rm -rf html/*
# -v --debug
phpunit --whitelist ../ \
	--coverage-html html/ \
	--bootstrap src/bootstrap.php \
	--strict-coverage \
	src/
#rm -f database.sql
rm -f final0.png
rm -f final1.png
