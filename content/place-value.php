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
    'ixl' => "Convert Between Place Values | https://www.ixl.com/math/grade-4/convert-between-place-values\nPlace Value Lesson | https://www.ixl.com/math/lessons/place-value\nPlace Value Hockey | https://www.ixl.com/games/place-value-hockey-e\nSoccer Math: Rounding to the Thousands | https://www.ixl.com/games/rounding-numbers-thousands-b",
    'khan' => "Place Value Unit | https://www.khanacademy.org/math/cc-fourth-grade-math/imp-place-value-and-rounding-2\nDecimal Place Value | https://www.khanacademy.org/math/cc-fifth-grade-math/imp-place-value-and-decimals\nPlace Value Patterns | https://www.khanacademy.org/math/5th-grade-illustrative-mathematics/xe7a2395079b692f7:place-value-patterns-and-decimal-operations/xe7a2395079b692f7:explore-place-value-relationships/v/comparing-place-values-in-decimals\nPlace Value Patterns and Decimal Operations | https://www.khanacademy.org/math/5th-grade-illustrative-mathematics/xe7a2395079b692f7:place-value-patterns-and-decimal-operations",
    'delta' => "Identify Place Value: Whole Number L1 | https://www.deltamath.com/app/teacher/solve/3370764\nIdentify Place Value: Decimal L1 | https://www.deltamath.com/app/teacher/solve/3377885\nStandard to Expanded Form: Whole Number | https://www.deltamath.com/app/teacher/solve/3376983\nCompare the Value of Digits: Whole Numbers | https://www.deltamath.com/app/teacher/solve/3425288\nRounding Numbers Level 1 | https://www.deltamath.com/app/teacher/solve/1848020",
    'real_life_math' => 'Place value is used when reading money, measuring distances, comparing data, and writing very large or very small numbers.',
    'did_you_know' => 'The base-ten place-value system developed over many centuries. Its use of zero as both a number and a placeholder made efficient calculation possible.',
    'certification' => array(
        'Identify values of digits in whole numbers.',
        'Represent numbers in standard, expanded, and word form.'
    ),
    'wordpress_bridge' => array(
        'enabled'      => true,
        'owned_fields' => array(
            'ixl'          => 'ixl',
            'khan'         => 'khan',
            'delta'        => 'delta',
            'real_life'    => 'real_life_math',
            'did_you_know' => 'did_you_know',
        ),
    ),
);
