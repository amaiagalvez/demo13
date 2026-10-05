<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum accepted string lengths
    |--------------------------------------------------------------------------
    |
    | Every validated string has a ceiling, and the two ceilings come from two
    | different places. "string" backs a varchar column with a 255-character
    | schema limit, and stays below it so a bad payload is rejected before it
    | reaches the column. "longtext" has no database length limit, so its
    | ceiling is a product decision taken here.
    |
    | Note that the unique index on a name is not what sets this number: it is
    | built on the generated `active_name` column, which is varchar(255) whether
    | the name is validated at 191 or 255.
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
