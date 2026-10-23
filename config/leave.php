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

];
