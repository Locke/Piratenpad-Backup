#!/bin/sh

cp config.default config

echo "Do not forget configuring the config!"

mkdir backups
cd backups

git init
git commit -am "initial commit"

cd ..

#prevent accedential execution of this init script
chmod -x $0
