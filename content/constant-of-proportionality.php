<?php

return array(
    'definition_status' => 'production',
    'live_replacement' => false,
    'version' => '1.0.0',
    'title' => 'Constant of Proportionality',
    'overview' => 'Students learn how to find, interpret, and use the constant of proportionality in tables, graphs, equations, and real-world situations. They connect the constant to unit rates and equations in the form y = kx.',
    'teach_it' => 'The constant of proportionality describes the constant relationship between two proportional quantities. It tells how much one quantity corresponds to one unit of another quantity. The constant is represented by the letter k in the equation y = kx.

To find the constant of proportionality from a table, divide each y-value by its corresponding x-value. The formula is k = y ÷ x. For example, suppose a table contains the pairs (2, 10), (4, 20), and (7, 35). Dividing each y-value by its x-value gives 10 ÷ 2 = 5, 20 ÷ 4 = 5, and 35 ÷ 7 = 5. The constant of proportionality is 5, and the equation is y = 5x.

The order of division matters. In y = kx, divide y by x to find k. The units can help determine the correct order. If y represents dollars and x represents pounds, then k is measured in dollars per pound. For example, if 6 pounds of fruit cost $15, then k = 15 ÷ 6 = 2.5. The constant is $2.50 per pound.

To find the constant of proportionality from an equation, identify the number multiplying x. In y = 8x, the constant is 8. In y = 0.75x, the constant is 0.75. In y = 3/5x, the constant is 3/5. If an equation is not written as y = kx, solve for y first. For example, 4y = 12x becomes y = 3x after both sides are divided by 4, so k = 3.

On a graph, the constant of proportionality can be found by selecting any nonzero point (x, y) and calculating y ÷ x. A proportional graph is a straight line that passes through the origin. For the point (4, 12), k = 12 ÷ 4 = 3. This means the graph rises 3 units for each increase of 1 unit in x.

The constant of proportionality is also the slope of a proportional relationship. Because the graph passes through the origin, its rate of change can be calculated using any point and (0, 0). The slope is the change in y divided by the change in x, which produces the same value as y divided by x.

A constant of proportionality must be interpreted in context. If a car travels 180 miles in 3 hours, then k = 180 ÷ 3 = 60 miles per hour. The number 60 alone is incomplete; its units explain what the constant means.

The value of k may be greater than 1, less than 1, equal to 1, a fraction, or a decimal. If y = 1/4x, then each y-value is one-fourth of its corresponding x-value. If y = 1.6x, then each y-value is 1.6 times its corresponding x-value.

Constants can be used to compare proportional relationships. Suppose Company A charges $18 for 3 items and Company B charges $28 for 4 items. Company A has a constant of $6 per item, while Company B has a constant of $7 per item. Comparing the constants shows that Company A has the lower unit price.

Once k is known, substitute it into y = kx to find missing values. If a machine produces 24 parts per hour, then y = 24x. In 7.5 hours, y = 24(7.5) = 180 parts. To find the time needed for 300 parts, use 300 = 24x and divide by 24. The machine needs 12.5 hours.

Common misconceptions and corrections: Students may divide x by y instead of y by x, select the wrong coefficient from an equation, ignore units, or assume that any rate is a constant of proportionality. Use k = y ÷ x, rewrite the equation as y = kx, include the units, and verify that the relationship is proportional before identifying k.',
    'at_a_glance' => array(
        'The constant of proportionality is represented by k.',
        'A proportional equation can be written as y = kx.',
        'Find the constant using k = y divided by x.',
        'In a table, every value of y divided by x must produce the same constant.',
        'In an equation, k is the coefficient of x after the equation is solved for y.',
        'On a proportional graph, k can be found from any nonzero point using y divided by x.',
        'The constant of proportionality is also the unit rate and the slope of a proportional graph.',
        'Always interpret the constant using its units and the situation.'
    ),
    'common_questions' => array(
        'What is the constant of proportionality? It is the constant ratio between corresponding quantities in a proportional relationship.',
        'How do I find k from a table? Divide each y-value by its corresponding x-value and confirm that the quotient stays the same.',
        'How do I find k from an equation? Rewrite the equation as y = kx and identify the number multiplying x.',
        'How do I find k from a graph? Choose a nonzero point and divide its y-coordinate by its x-coordinate.',
        'Is the constant of proportionality the same as the unit rate? Yes. Both describe the amount of y for one unit of x.',
        'Is the constant of proportionality the same as slope? In a proportional relationship, yes. Its graph passes through the origin, so k equals the slope.',
        'Can k be a fraction or decimal? Yes. The constant may be any positive or negative value allowed by the context, including a fraction or decimal.',
        'Why are units important? Units explain what the constant represents and help determine the correct order of division.'
    ),
    'watch_it' => 'A verified Constant of Proportionality video will be added before publication.',
    'practice_it' => array(
        'A table contains the pairs (2, 14), (5, 35), and (8, 56). Find k and write the equation. Answer: 14 divided by 2 equals 7, so k = 7 and y = 7x.',
        'Find the constant of proportionality in y = 9x. Answer: k = 9.',
        'Find the constant of proportionality in 5y = 20x. Answer: Divide both sides by 5 to get y = 4x, so k = 4.',
        'A graph contains the origin and the point (6, 15). Find k. Answer: 15 divided by 6 equals 2.5, so k = 2.5.',
        'Four notebooks cost $11. Find and interpret the constant of proportionality. Answer: 11 divided by 4 equals 2.75, so k = $2.75 per notebook.',
        'A cyclist travels 52.5 miles in 3.5 hours. Find the constant and write an equation. Answer: 52.5 divided by 3.5 equals 15 miles per hour, so y = 15x.',
        'Relationship A is represented by y = 4.5x. Relationship B contains the point (6, 24). Which has the greater constant? Answer: Relationship A has k = 4.5. Relationship B has k = 24 divided by 6, or 4. Relationship A has the greater constant.',
        'A recipe uses 3/4 cup of sugar for 2 batches. Find the constant in cups per batch. Answer: 3/4 divided by 2 equals 3/8, so k = 3/8 cup per batch.',
        'A machine produces 32 items per hour. How many items will it produce in 6.5 hours? Answer: y = 32x, so y = 32(6.5) = 208 items.',
        'A proportional relationship has k = 12.5 and y = 87.5. Find x. Answer: 87.5 = 12.5x, so x = 7.'
    ),
    'my_math_notes' => array(
        'The constant of proportionality is the constant ratio between y and x.',
        'Use k = y divided by x.',
        'Write the equation as y = kx.',
        'From a table, check more than one pair to verify that the ratio is constant.',
        'From a graph, use any nonzero point and calculate y divided by x.',
        'From an equation, identify the coefficient of x after solving for y.',
        'In proportional relationships, k is the unit rate and the slope.',
        'Include units when explaining what k means.'
    ),
    'real_life_math' => 'Constants of proportionality are used to compare prices, calculate hourly wages, measure speed, scale recipes, convert measurements, estimate fuel use, determine production rates, and interpret scale drawings.',
    'did_you_know' => 'The constant of proportionality connects three representations at once: it is the unit rate in a situation, the coefficient of x in the equation y = kx, and the slope of the line on a proportional graph.'
);
