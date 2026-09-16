<?php

require __DIR__.'/../../vendor/autoload.php';
$lock = new App\Health\ConsoleRunLock($argv[1]);
if (! $lock->get()) {
    exit(2);
}
echo "LOCKED\n";
fflush(STDOUT);
while (true) {
    usleep(100_000);
}
