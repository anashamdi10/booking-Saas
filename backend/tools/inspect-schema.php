<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$rows = DB::select(
    "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND DATA_TYPE IN ('timestamp','datetime','date')
     ORDER BY TABLE_NAME, COLUMN_NAME"
);

printf("%-26s %-22s %s\n", 'TABLE', 'COLUMN', 'TYPE');
echo str_repeat('-', 62).PHP_EOL;

$timestampColumns = [];

foreach ($rows as $row) {
    $flag = $row->COLUMN_TYPE === 'timestamp' ? '  <= 2038 limit!' : '';
    printf("%-26s %-22s %s%s\n", $row->TABLE_NAME, $row->COLUMN_NAME, $row->COLUMN_TYPE, $flag);

    if ($row->COLUMN_TYPE === 'timestamp') {
        $timestampColumns[] = $row->TABLE_NAME.'.'.$row->COLUMN_NAME;
    }
}

echo PHP_EOL.'TIMESTAMP columns (MySQL max 2038-01-19): '.count($timestampColumns).PHP_EOL;
echo implode(PHP_EOL, $timestampColumns).PHP_EOL;
