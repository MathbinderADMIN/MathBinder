<?php
/** Complete grade 6–8 content for Binder Sections 7–11. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/verified-video-map.php';

$domains = [
    'ratios-proportional' => ['title'=>'Ratios & Proportional Relationships','number'=>'07','standards'=>'CA CCSS 6–7.RP','terms'=>'ratio, rate, unit rate, proportion, percent','application'=>'recipes, maps, pricing, speed, and percent decisions'],
    'number-system' => ['title'=>'The Number System','number'=>'08','standards'=>'CA CCSS 6–8.NS','terms'=>'rational number, integer, absolute value, operation, exponent','application'=>'money, temperature, measurement, science, and data'],
    'expressions-equations' => ['title'=>'Expressions & Equations','number'=>'09','standards'=>'CA CCSS 6–8.EE','terms'=>'variable, coefficient, expression, equation, inequality','application'=>'budgets, comparisons, patterns, formulas, and constraints'],
    'functions' => ['title'=>'Functions','number'=>'10','standards'=>'CA CCSS 8.F','terms'=>'function, input, output, domain, rate of change','application'=>'distance, cost, growth, tables, graphs, and modeling'],
    'statistics-probability' => ['title'=>'Statistics & Probability','number'=>'11','standards'=>'CA CCSS 6–8.SP','terms'=>'data, distribution, variability, sample, probability','application'=>'surveys, experiments, predictions, risk, and evidence'],
];

$topics = [
    ['6','Ratios','ratios-proportional'], ['6','Rates and Unit Rates','ratios-proportional'], ['6','Percents','ratios-proportional'],
    ['7','Proportional Relationships','ratios-proportional'], ['7','Unit Rates with Fractions','ratios-proportional'], ['7','Percent Applications','ratios-proportional'],
    ['6','Divide Fractions','number-system'], ['6','Multi-Digit and Decimal Operations','number-system'], ['6','Factors, Multiples, and Distributive Property','number-system'], ['6','Positive and Negative Numbers','number-system'],
    ['7','Operations with Rational Numbers','number-system'], ['8','Rational and Irrational Numbers','number-system'],
    ['6','Expressions and Properties','expressions-equations'], ['6','One-Variable Equations and Inequalities','expressions-equations'], ['6','Dependent and Independent Variables','expressions-equations'],
    ['7','Equivalent Expressions','expressions-equations'], ['7','Equations and Inequalities','expressions-equations'], ['8','Integer Exponents and Scientific Notation','expressions-equations'], ['8','Linear Equations','expressions-equations'], ['8','Systems of Linear Equations','expressions-equations'],
    ['8','Functions','functions'], ['8','Linear Functions','functions'],
    ['6','Statistical Questions and Distributions','statistics-probability'], ['6','Measures of Center and Variability','statistics-probability'],
    ['7','Random Sampling','statistics-probability'], ['7','Comparing Populations','statistics-probability'], ['7','Probability Models','statistics-probability'], ['7','Compound Probability','statistics-probability'],
    ['8','Scatter Plots and Association','statistics-probability'], ['8','Two-Way Tables','statistics-probability'],
];

$focus = [
    'Ratios'=>'compare two quantities multiplicatively and use equivalent ratios',
    'Rates and Unit Rates'=>'compare quantities with different units and find a per-one rate',
    'Percents'=>'interpret percent as a rate per 100',
    'Proportional Relationships'=>'identify and represent constant ratios in tables, graphs, equations, and contexts',
    'Unit Rates with Fractions'=>'divide fractional quantities to determine a rate per one',
    'Percent Applications'=>'solve tax, tip, discount, markup, increase, decrease, and simple-interest problems',
    'Divide Fractions'=>'interpret and compute quotients involving fractions',
    'Multi-Digit and Decimal Operations'=>'use standard algorithms accurately and estimate to verify results',
    'Factors, Multiples, and Distributive Property'=>'use common factors, common multiples, and structure to rewrite sums',
    'Positive and Negative Numbers'=>'locate, compare, and interpret signed numbers and absolute value',
    'Operations with Rational Numbers'=>'add, subtract, multiply, and divide positive and negative rational numbers',
    'Rational and Irrational Numbers'=>'classify real numbers and approximate irrational values',
    'Expressions and Properties'=>'write and evaluate expressions and apply operation properties',
    'One-Variable Equations and Inequalities'=>'represent and solve one-variable relationships',
    'Dependent and Independent Variables'=>'describe how one quantity changes in response to another',
    'Equivalent Expressions'=>'use properties and combining like terms to create equivalent forms',
    'Equations and Inequalities'=>'solve multistep equations and inequalities and interpret solution sets',
    'Integer Exponents and Scientific Notation'=>'apply exponent rules and represent very large or small quantities',
    'Linear Equations'=>'solve linear equations with variables on one or both sides',
    'Systems of Linear Equations'=>'find and interpret a pair that satisfies two linear equations',
    'Functions'=>'decide whether a relation assigns exactly one output to each input',
    'Linear Functions'=>'compare and model constant rates of change',
    'Statistical Questions and Distributions'=>'recognize variability and describe the shape of a data distribution',
    'Measures of Center and Variability'=>'use mean, median, range, IQR, and MAD to summarize data',
    'Random Sampling'=>'use representative random samples to draw cautious inferences',
    'Comparing Populations'=>'compare centers and variability to make informal comparative inferences',
    'Probability Models'=>'compare theoretical probability with experimental relative frequency',
    'Compound Probability'=>'organize multistage outcomes and calculate compound-event probabilities',
    'Scatter Plots and Association'=>'describe bivariate patterns, outliers, clustering, and lines of fit',
    'Two-Way Tables'=>'analyze paired categorical data with joint and conditional frequencies',
];

$examples = [
    'Ratios'=>'A class has 12 laptops for 18 students. | Write 12:18. | Divide both terms by 6. | The equivalent ratio is 2:3.',
    'Rates and Unit Rates'=>'A cyclist travels 45 miles in 3 hours. | Divide distance by time. | 45 ÷ 3 = 15. | The unit rate is 15 miles per hour.',
    'Percents'=>'Find 35% of 80. | Write 35%=0.35. | Multiply 0.35×80. | The result is 28.',
    'Proportional Relationships'=>'The table contains (2,6), (4,12), and (7,21). | Compute y÷x. | Every ratio equals 3. | The relationship is y=3x.',
    'Unit Rates with Fractions'=>'Three-fourths mile takes one-half hour. | Divide 3/4 by 1/2. | Multiply 3/4×2. | The rate is 3/2 or 1.5 miles per hour.',
    'Percent Applications'=>'A $60 item is discounted 25%. | Find 0.25×60=15. | Subtract 60−15. | The sale price is $45.',
    'Divide Fractions'=>'Compute 3/4 ÷ 2/5. | Multiply by the reciprocal. | 3/4×5/2=15/8. | The quotient is 1 7/8.',
    'Positive and Negative Numbers'=>'Compare −7 and −3. | Locate both on a number line. | −3 is farther right. | Therefore −3>−7.',
    'Operations with Rational Numbers'=>'Compute −6+9−4. | Add −6+9=3. | Then 3−4=−1. | The result is −1.',
    'Rational and Irrational Numbers'=>'Classify √50. | 50 is not a perfect square. | √50 lies between 7 and 8. | It is irrational and approximately 7.07.',
    'Expressions and Properties'=>'Evaluate 3(2x−1) when x=4. | Substitute 4. | 3(8−1)=3×7. | The value is 21.',
    'One-Variable Equations and Inequalities'=>'Solve 4x+3=19. | Subtract 3. | Divide 16 by 4. | x=4.',
    'Dependent and Independent Variables'=>'A taxi costs $4 plus $2 per mile. | Miles are the input x. | Cost depends on miles. | y=2x+4.',
    'Equivalent Expressions'=>'Simplify 4x+3+2x−5. | Combine x-terms and constants. | 4x+2x=6x and 3−5=−2. | The result is 6x−2.',
    'Equations and Inequalities'=>'Solve 3x−5≤16. | Add 5. | Divide by 3. | x≤7.',
    'Integer Exponents and Scientific Notation'=>'Multiply (3×10⁴)(2×10³). | Multiply coefficients. | Add exponents. | The product is 6×10⁷.',
    'Linear Equations'=>'Solve 5x+8=2x+20. | Subtract 2x. | Subtract 8, then divide by 3. | x=4.',
    'Systems of Linear Equations'=>'Solve y=x+2 and y=−x+8. | Set expressions equal. | 2x=6, so x=3. | Substitute to get y=5.',
    'Functions'=>'Use pairs (1,4),(2,5),(1,7). | Check repeated inputs. | Input 1 has two outputs. | The relation is not a function.',
    'Linear Functions'=>'Compare y=3x+1 with a table increasing 5 for every 2 in x. | Equation rate is 3. | Table rate is 5/2. | The equation has the greater rate.',
    'Statistical Questions and Distributions'=>'Is “How many minutes do students read nightly?” statistical? | It anticipates varied answers. | Collect multiple values. | Yes, it is statistical.',
    'Measures of Center and Variability'=>'For 2,4,4,6,9 find the mean. | Sum to get 25. | Divide by 5. | Mean=5.',
    'Random Sampling'=>'Survey 30 randomly selected students from every grade. | Selection is random and covers grades. | Avoid surveying only one club. | The sample better represents the school.',
    'Comparing Populations'=>'Group A median=72,IQR=8; Group B median=80,IQR=9. | Compare centers. | Compare spreads. | B is typically higher with similar variability.',
    'Probability Models'=>'A bag has 3 red and 5 blue counters. | Count 8 total. | Three outcomes are favorable. | P(red)=3/8.',
    'Compound Probability'=>'Flip two fair coins. | List HH, HT, TH, TT. | One outcome has two heads. | P(HH)=1/4.',
    'Scatter Plots and Association'=>'Points rise from left to right. | Describe direction. | Note strength and outliers. | The variables show positive association.',
    'Two-Way Tables'=>'In a row of 25 students, 15 answer yes. | The condition fixes the row total. | Divide 15÷25. | The conditional percent is 60%.',
];

$default_example = 'Study the given representation. | Identify the quantities and operation. | Apply the lesson strategy carefully. | Verify the result with estimation or substitution.';
$lessons = [];
foreach ($topics as $topic) {
    [$grade,$title,$domain_key] = $topic;
    $domain = $domains[$domain_key];
    $slug = sanitize_title($grade . '-' . $title);
    $idea = $focus[$title] ?? ('represent and solve problems involving ' . strtolower($title));
    $example = $examples[$title] ?? $default_example;
    $lessons[$slug] = [
        'title' => $title,
        'subtitle' => 'Grade ' . $grade . ': Learn to ' . $idea . '.',
        'essential_question' => 'How can I ' . $idea . ' and explain why my method works?',
        'learning_targets' => "I can identify the important quantities and representations in {$title}.\nI can apply an accurate strategy and show each step.\nI can explain and verify my conclusion.",
        'vocabulary' => ucwords(str_replace(', ', " — key lesson language\n", $domain['terms'])) . ' — key lesson language',
        'worked_examples' => $example,
        'common_mistakes' => "Using a rule before identifying what the quantities represent | Label the information and connect the rule to a model.\nSkipping intermediate steps | Show equivalent steps so an error can be located and corrected.\nAccepting an unreasonable result | Estimate, substitute, or compare with the original context.",
        'real_life' => $title . ' supports reasoning about ' . $domain['application'] . '.',
        'videos' => mathbinder_verified_video_for_topic($title, $domain_key),
        'video_chapters' => "0:00 | Connect to prior knowledge\n1:15 | Define the core idea\n2:45 | Work through an example\n4:30 | Analyze a common error\n6:00 | Try and verify",
        'practice_warmup' => "Before you try || State the main idea of {$title} in your own words and give one example. | Answers vary; the definition and example must agree. | Use the vocabulary list. | Label what each number or symbol represents. | Compare the response with the worked example.",
        'guided_practice' => "Practice with support || Solve a Grade {$grade} {$title} problem chosen by your teacher. Explain the strategy after every step. | Answers depend on the assigned values. | Start by identifying known and unknown information. | Use a model, table, graph, equation, or organized list. | Complete the steps, label the result, and verify it.",
        'independent_practice' => "Show what you know || Create and solve a new {$title} example using different values. | Answers vary. | Keep the same mathematical structure. | Include a second representation or accuracy check. | A complete response includes the problem, work, answer, and verification.",
        'challenge_practice' => "Challenge: Create a real-life situation involving {$title} with at least two constraints. Solve it in two ways, compare the methods, and explain which is more efficient.",
        'master_it' => "I can represent {$title} accurately.\nI can choose and carry out an efficient strategy.\nI can justify each step and verify the result.\nI can apply the idea in a new context.",
        'mastery_questions' => "What should happen first in a {$title} problem? | Identify and label the given information ; Guess a rule ; Ignore units ; Round everything | A\nWhich response gives the strongest evidence of understanding? | An answer only ; A labeled model with connected reasoning ; A copied rule ; An estimate only | B\nWhat is an effective accuracy check? | Compare the result with the original conditions ; Hide the work ; Change the question ; Remove labels | A\nWhy should equivalent steps be shown? | They make reasoning visible and errors correctable ; They always make answers larger ; They replace vocabulary ; They are optional | A\nWhat should happen after finding a mistake? | Locate the step, revise it, and recheck ; Erase all work ; Keep the answer ; Choose a random value | A",
        'parent_summary' => "In this Grade {$grade} lesson, students learn to {$idea}. Ask your child to name the quantities, explain why the strategy fits, and show how the answer was checked.",
        'parent_conversation' => "Ask: What information is given and what must be found?\nAsk: Which representation makes the relationship easiest to see?\nAsk: How can you prove that your answer satisfies the original problem?",
        'parent_five_minute' => "Choose a simple example from {$domain['application']}. Have your child label the information, solve or interpret it, and explain one accuracy check aloud.",
        'teacher_objectives' => "Students will model and solve Grade {$grade} problems involving {$title}.\nStudents will use precise vocabulary and connect multiple representations.\nStudents will analyze errors and verify conclusions.",
        'teacher_pacing' => "Launch | 5 minutes | Use a notice-and-wonder context.\nLearn It | 15 minutes | Connect vocabulary, models, and procedures.\nWatch It | 7 minutes | Pause for predictions and error analysis.\nPractice It | 20 minutes | Move from guided to independent reasoning.\nMastery | 8 minutes | Require a result, explanation, and check.",
        'teacher_misconceptions' => "Students may apply a memorized rule without attending to meaning | Require labels and a visual or contextual explanation.\nStudents may stop after computing | Require interpretation and verification.\nStudents may confuse similar representations | Compare examples and nonexamples side by side.",
        'teacher_differentiation' => "Below Level | Use manipulatives, number lines, color coding, and partially completed examples.\nOn Level | Mix representations and require written justification.\nAdvanced | Add reverse reasoning, multiple constraints, or a proof.\nMultilingual Learners | Pair vocabulary with diagrams, gestures, symbols, and sentence frames.",
        'teacher_formative' => "Listen for precise vocabulary and representation choices. Use one error-analysis prompt during guided practice. Collect an exit ticket requiring a result, supporting work, interpretation, and check.",
        'standards' => $domain['standards'] . ' — ' . $title . " concepts and applications.\nCCSS.MATH.PRACTICE.MP1, MP3, MP4, and MP6 — Persevere, justify, model, and attend to precision.",
        '_mb_domain_slug' => $domain_key,
        '_mb_domain_title' => $domain['title'],
        '_mb_section_number' => $domain['number'],
        '_mb_grade' => $grade,
    ];
}
return $lessons;
