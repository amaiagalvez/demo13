<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum accepted string lengths
    |--------------------------------------------------------------------------
    |
    | Every validated string has a ceiling, and the two ceilings come from two
    | different places. "string" backs a varchar column with a 255-character
    | schema limit; its lower validation limit also keeps unique indexes within
    | database key-size limits. "longtext" has no database length limit, so its
    | ceiling is a product decision taken here.
    |
    | Rules, the maxlength attributes of the forms and the boundary tests all
    | read these values, so a change lands everywhere at once and the three
    | surfaces cannot drift apart.
    |
    */

    'max_length' => [
        'string' => 191,
        'longtext' => 10000,
    ],

];
