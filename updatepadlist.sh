#!/bin/bash

source config

php recentpads.php "$base" "$email" "$password" "$check_public" "$interval" &> pads.list
