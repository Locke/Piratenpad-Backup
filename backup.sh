#!/bin/bash

MY_CMD="$(readlink -f "${0}")"
MY_DIR="$(dirname "${MY_CMD}")"

cd "$MY_DIR"

source config

./updatepadlist.sh

cd backups

for url in `cat ../pads.list`
do
	echo "fetch $url"

	if [ ! -f "$url" ]
	then
		touch "$url"
		git add "$url"
	fi
	
	wget --no-check-certificate "$base$exportpre$url$exportpost" -O "$url" -o /dev/null
	git add "$url"
done

status=$(git status)

git commit -am "pads updated: $status" > /dev/null
