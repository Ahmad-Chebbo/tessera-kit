<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Weekend
    |--------------------------------------------------------------------------
    |
    | The days of the week nobody works, comma separated. They are not counted against a leave request. Many countries
    | in the region rest on Friday and Saturday: set LEAVE_WEEKEND=friday,saturday.
    |
    */

    'weekend' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) env('LEAVE_WEEKEND', 'saturday,sunday')))))),

];
