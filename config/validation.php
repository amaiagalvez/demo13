<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum accepted string lengths
    |--------------------------------------------------------------------------
    |
    | Every validated string has a ceiling, and the two ceilings come from two
    | different places. "string" backs a varchar column, so its value is fixed
    | by the schema (255). "longtext" backs a longText column, which the
    | database does not bound, so the limit is a product decision taken here.
    |
    | Rules, the maxlength attributes of the forms and the boundary tests all
    | read these values, so a change lands everywhere at once and the three
    | surfaces cannot drift apart.
    |
    */

    'max_length' => [
        'string' => 255,
        'longtext' => 5000,
    ],

];
