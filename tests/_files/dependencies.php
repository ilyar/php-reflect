<?php
$datetime1 = new DateTime('2013-09-01');
$datetime2 = new DateTime();
$interval
    = $datetime1
        ->diff($datetime2);
echo $interval->format('%R%a days');

print_r( extension_loaded('date') );

$finder = new Finder();
$finder->{$constraint}();
