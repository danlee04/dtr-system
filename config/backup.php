<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where backups are written
    |--------------------------------------------------------------------------
    |
    | Point this at a network share or another PC. A backup on the same disk as
    | the database is not a backup: one failed drive, one ransomware run or one
    | stolen tower takes both at once. `storage/backups` is a starting place,
    | not a destination.
    |
    */

    // `?:`, not a default argument: dotenv reads a bare `BACKUP_PATH=` as an
    // empty string, which is a value, so the default would never apply.
    'path' => env('BACKUP_PATH') ?: storage_path('backups'),

    /*
    |--------------------------------------------------------------------------
    | How long to keep them
    |--------------------------------------------------------------------------
    |
    | Days. Old archives are deleted after a successful run and never before
    | one, so a failing backup cannot quietly eat the good ones behind it.
    |
    */

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Directories that go in alongside the database
    |--------------------------------------------------------------------------
    |
    | The employees' photos, on the private disk. The DTR PDFs are not kept
    | after they are read, so there is nothing else. `livewire-tmp` is
    | half-finished uploads.
    |
    */

    'include' => [
        storage_path('app/private'),
    ],

    'exclude' => [
        'livewire-tmp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Where mysqldump lives
    |--------------------------------------------------------------------------
    |
    | Laragon keeps it inside its own MySQL folder and off the PATH, so the bare
    | name fails on a machine where MySQL is plainly running. Set the full path,
    | for example C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe
    |
    */

    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),

];
