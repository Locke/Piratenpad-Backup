This script downloads the contents of a team-etherpad and stores them for backup in a git repo.

It was forked from a drupal module that displayed the recent pads of a team-etherpad.

#### Prerequisites

You need to have php and git installed.

#### Usage

* execute `init.sh` to create an empty backup folder

* edit `config.inc` and change the variables `$base`, `$email` and `$password` accordingly

* generate a backup via `php recentpads.php`

  * you should run this at least two times manually to check the results
  * you can add it now to crontab or similar

#### Status

I'm no longer using or actively maintaining this script. Small contributions are welcome, however feel free to adopt this project and leave me a note, so that I can link it from here.
