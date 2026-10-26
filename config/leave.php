<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Start date
    |--------------------------------------------------------------------------
    |
    | The day the system takes over the leave records, always the 1st of a
    | month. HR's opening balances are as of the day before; from the next 1st
    | on, the system posts the credits itself.
    |
    */

    'start_date' => env('LEAVE_START_DATE'),

    /*
    |--------------------------------------------------------------------------
    | The printed leave form
    |--------------------------------------------------------------------------
    |
    | HR's HRM-07 (CS Form No. 6, Revised 2020): its document box, and who
    | signs 7.A and 7.C. The Division Head who signs 7.B is typed on each
    | application. Names print in capitals, as on HR's form.
    |
    */

    'form' => [
        'doc_code' => 'HRM-07',
        'effectivity' => 'June 3, 2026',
        'revision' => '1',
        'certifying_officer' => [
            'name' => 'Mary Jane Lao Guico',
            'title' => 'Human Resource Development Officer',
        ],
        'approving_officer' => [
            'name' => 'Edhel S. Miro, MD, DPCAM, MM-PA',
            'title' => 'Chief of Hospital III',
        ],
        'recommending_title' => 'Division Head',
    ],

];
