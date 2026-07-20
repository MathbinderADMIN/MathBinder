<?php

return array(
    'definition_status' => 'sample',
    'live_replacement' => false,
    'version' => '1.0.0',
    'title' => 'Place Value',
    'overview' => 'Students explore how digits represent different values based on their position in a number.',
    'teach_it' => 'Teach students to read and write numbers by identifying ones, tens, hundreds, and thousands places.',
    'watch_it' => 'place_value',
    'practice_it' => array(
        'Build numbers with base-ten blocks.',
        'Write numbers in expanded form.',
        'Compare values using place-value charts.'
    ),
    'notes' => array(
        'Each digit has a value determined by its place.',
        'Moving left increases value by powers of ten.'
    ),
    'real_life_math' => 'Place value is used when reading money, measuring distances, comparing data, and writing very large or very small numbers.',
    'did_you_know' => 'The base-ten place-value system developed over many centuries. Its use of zero as both a number and a placeholder made efficient calculation possible.',
    'certification' => array(
        'Identify values of digits in whole numbers.',
        'Represent numbers in standard, expanded, and word form.'
    ),
    'wordpress_bridge' => array(
        'enabled'      => true,
        'owned_fields' => array(
            'real_life'    => 'real_life_math',
            'did_you_know' => 'did_you_know',
        ),
    ),
);
