<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Employee profiles
    |--------------------------------------------------------------------------
    |
    | Employment details on each user (employee number, job title, hire date...). Leave empty to follow the Company
    | module: on when it is installed, off for a plain users-and-roles project. Set true or false to decide yourself.
    |
    */

    'employee_profiles' => env('TEAM_EMPLOYEE_PROFILES'),

    /*
    |--------------------------------------------------------------------------
    | Activity log
    |--------------------------------------------------------------------------
    |
    | Records who signed in and what they changed, and shows it on the Activity page. Same rule as above.
    |
    */

    'activity_log' => env('TEAM_ACTIVITY_LOG'),

];
