#!/bin/sh
date -s 'Jan 1 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Jan 31 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Feb 1 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Feb 28 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Feb 29 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Mar 1 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Mar 2 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Mar 31 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Apr 1 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Apr 30 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'May 1 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'May 31 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Jub 1 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Jun 30 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Jul 1 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Jul 30 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Nov 1 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Nov 30 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Dec 1 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1
date -s 'Dec 31 00:00:00 UTC 2020'  &&  bin/phpunit --filter $1

ntpdate -u pool.ntp.org