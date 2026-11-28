<?php

use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;

$config = require base_path('vendor/spatie/laravel-backup/config/backup.php');
$source = env('FARMWELL_BACKUP_SOURCE');
$name = 'Farmwell';
$disk = 'farmwell_backups';

$config['backup']['name'] = $name;
// The backup runner stages a PostgreSQL dump and private media in a fresh directory.
$config['backup']['source']['databases'] = [];
$config['backup']['source']['files']['include'] = $source && env('BACKUP_ARCHIVE_PASSWORD') ? [$source] : [];
$config['backup']['source']['files']['relative_path'] = $source;
$config['backup']['destination']['disks'] = [$disk];
$config['backup']['temporary_directory'] = env('FARMWELL_BACKUP_TEMP', storage_path('app/backup-temp'));
$config['backup']['encryption'] = 'aes256';
$config['backup']['verify_backup'] = true;
$config['monitor_backups'] = [[
    'name' => $name,
    'disks' => [$disk],
    'health_checks' => [MaximumAgeInDays::class => 2],
]];
// The operations workflow reports command failures through its job status.
$config['notifications']['notifications'] = array_fill_keys(array_keys($config['notifications']['notifications']), []);
$config['cleanup']['default_strategy'] = [
    'keep_all_backups_for_days' => 7,
    'keep_daily_backups_for_days' => 0,
    'keep_weekly_backups_for_weeks' => 4,
    'keep_monthly_backups_for_months' => 0,
    'keep_yearly_backups_for_years' => 0,
    'delete_oldest_backups_when_using_more_megabytes_than' => null,
];

return $config;
