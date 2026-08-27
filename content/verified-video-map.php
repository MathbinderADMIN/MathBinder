<?php
/** Verified, public, directly embeddable topic-video routing. */
if (!defined('ABSPATH')) exit;

if (!function_exists('mathbinder_verified_video_for_topic')) {
    function mathbinder_verified_video_for_topic($title, $pathway = '') {
        $key = strtolower(trim((string)$title));
        $rules = [
            ['absolute value', 'What is Absolute Value? | https://www.youtube.com/watch?v=LnfhdtjcpVY'],
            ['irrational', 'An Intro to Rational and Irrational Numbers | https://www.youtube.com/watch?v=Th9mT4TxvOI'],
            ['rational number', 'What is a Rational Number? | https://www.youtube.com/watch?v=jhV_G-LZ4EU'],
            ['integer', 'Integer Operations Review | https://www.youtube.com/watch?v=O6bRgxVRoZ4'],
            ['discount', 'Discount and Sales Tax Word Problems | https://www.youtube.com/watch?v=emSK-LrWsEE'],
            ['markup', 'Discounts and Markups Using Percent | https://www.youtube.com/watch?v=l5yqYdT07a4'],
            ['tax', 'Tax, Tip, and Discount with Proportions | https://www.youtube.com/watch?v=ZuAFRA7MevU'],
            ['tip', 'Find Total Cost with Tax and Tip | https://www.youtube.com/watch?v=U8HHe6uvP1Y'],
            ['proportion', 'Solving Proportions with Variables | https://www.youtube.com/watch?v=wT8tGc-SwKk'],
            ['unit rate', 'Ratios: All About Ratios | https://www.youtube.com/watch?v=7AnQUy207Ms'],
            ['rate', 'Ratios: All About Ratios | https://www.youtube.com/watch?v=7AnQUy207Ms'],
            ['ratio', 'Ratios: All About Ratios | https://www.youtube.com/watch?v=7AnQUy207Ms'],
            ['percent', 'Discount and Sales Tax Word Problems | https://www.youtube.com/watch?v=emSK-LrWsEE'],
            ['system', 'Systems of Equations | https://www.youtube.com/watch?v=hjigR_rHKDI'],
            ['linear equation', 'Linear Equations | https://www.youtube.com/watch?v=bAerID24QJ0'],
            ['quadratic', 'Quadratic Formula | https://www.youtube.com/watch?v=i7idZfS8t8w'],
            ['exponent', 'Exponent Expressions and Equations | https://www.youtube.com/watch?v=U8kmaUXaPJY'],
            ['polynomial', 'Polynomial Division | https://www.youtube.com/watch?v=_FSXJmESFmQ'],
            ['logarith', 'Logarithmic Expressions | https://www.youtube.com/watch?v=Lu1HyJ1nvg8'],
            ['rational function', 'Rational Functions | https://www.youtube.com/watch?v=z2375vICoVI'],
            ['parametric', 'Introduction to Parametric Equations | https://www.youtube.com/watch?v=97pe-QlSGqA'],
            ['vector', 'Vectors in Precalculus | https://www.youtube.com/watch?v=1dOpijjH2c8'],
            ['summation', 'Sigma and Summation Notation | https://www.youtube.com/watch?v=xavgv1m9feE'],
            ['taylor', 'Taylor and Maclaurin Series | https://www.youtube.com/watch?v=LDBnS4c7YbA'],
            ['power series', 'Power Series: Differentiation and Integration | https://www.youtube.com/watch?v=nD6hai32ykQ'],
            ['sequence', 'Sigma and Summation Notation | https://www.youtube.com/watch?v=xavgv1m9feE'],
            ['series', 'Calculus 2 Series Review | https://www.youtube.com/watch?v=0YeON4p0ogw'],
            ['limit', 'Introduction to Limits | https://www.youtube.com/watch?v=YNstP0ESndU'],
            ['continuity', 'Limits and Continuity | https://www.youtube.com/watch?v=9brk313DjV8'],
            ['integr', 'Integration and Antiderivatives | https://www.youtube.com/watch?v=6WUjbJEeJwM'],
            ['riemann', 'Integration Rules and Riemann Sums | https://www.youtube.com/watch?v=WDZMjYJyH6k'],
            ['derivative', 'Calculus: Derivatives and Integrals | https://www.youtube.com/watch?v=LokGs7zcwKk'],
            ['differential equation', 'Calculus: Derivatives and Integrals | https://www.youtube.com/watch?v=LokGs7zcwKk'],
            ['congruen', 'Congruent and Similar Triangles | https://www.youtube.com/watch?v=ChSz9zSx-9c'],
            ['similar', 'Congruent and Similar Triangles | https://www.youtube.com/watch?v=ChSz9zSx-9c'],
            ['pythagorean', 'Pythagorean Theorem | https://www.youtube.com/watch?v=d8EA5TxGzcY'],
            ['regression', 'Linear Regression Using Least Squares | https://www.youtube.com/watch?v=P8hT5nDai6A'],
            ['correlation', 'Linear Regression Using Least Squares | https://www.youtube.com/watch?v=P8hT5nDai6A'],
            ['scatter', 'Linear Regression Using Least Squares | https://www.youtube.com/watch?v=P8hT5nDai6A'],
            ['sampling', 'Probability, Inference, and Regression | https://www.youtube.com/watch?v=l-jtJuzaBrU'],
            ['population', 'Probability, Inference, and Regression | https://www.youtube.com/watch?v=l-jtJuzaBrU'],
            ['two-way', 'Probability, Inference, and Regression | https://www.youtube.com/watch?v=l-jtJuzaBrU'],
            ['data', 'Probability, Inference, and Regression | https://www.youtube.com/watch?v=l-jtJuzaBrU'],
            ['probability', 'Probability and Combinatorics | https://www.youtube.com/watch?v=DROZVHObeko'],
            ['statistic', 'Probability, Inference, and Regression | https://www.youtube.com/watch?v=l-jtJuzaBrU'],
            ['function', 'Linear, Quadratic, and Exponential Models | https://www.youtube.com/watch?v=CxEFOozrMSE'],
        ];
        foreach ($rules as $rule) if (strpos($key, $rule[0]) !== false) return $rule[1];
        if (strpos($pathway, 'calculus') !== false) return 'Calculus Basics: Limits, Derivatives, and Integrals | https://www.youtube.com/watch?v=LokGs7zcwKk';
        if ($pathway === 'geometry') return 'High School Geometry: Congruence and Similarity | https://www.youtube.com/watch?v=ChSz9zSx-9c';
        if ($pathway === 'statistics' || strpos($key, 'data') !== false) return 'Probability, Inference, and Regression | https://www.youtube.com/watch?v=l-jtJuzaBrU';
        return 'Algebraic Models and Functions | https://www.youtube.com/watch?v=CxEFOozrMSE';
    }
}
