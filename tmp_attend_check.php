<?php

echo 'relation FK: ' . (new App\Models\ClubActivity)->attendance()->getForeignKeyName() . PHP_EOL;

$a = App\Models\ClubActivity::first();
if ($a) {
    // The exact query that was failing.
    $count = $a->attendance()->whereNotNull('time_in')->count();
    echo "attendance(time_in) count for activity {$a->id}: {$count}" . PHP_EOL;
} else {
    echo 'no club_activities rows; running raw relationship query instead' . PHP_EOL;
    echo 'count: ' . App\Models\ClubActivity::find(1)?->attendance()->whereNotNull('time_in')->count() . PHP_EOL;
}
