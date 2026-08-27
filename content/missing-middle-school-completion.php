<?php
/** Missing legacy lesson cards in Ratios and The Number System. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/verified-video-map.php';

$topics = [
    ['review-of-proportions','Review of Proportions','ratios-proportional','Review equivalent ratios, cross products, and proportional reasoning.'],
    ['markups','Markups','ratios-proportional','Find a markup amount and the resulting selling price.'],
    ['discounts','Discounts','ratios-proportional','Find discount amounts and sale prices.'],
    ['taxes','Taxes','ratios-proportional','Calculate sales tax and total purchase price.'],
    ['tips','Tips','ratios-proportional','Estimate and calculate gratuity and total cost.'],
    ['proportional-relationship-equations','Proportional Relationship Equations','ratios-proportional','Represent proportional relationships with y=kx.'],
    ['absolute-value','Absolute Value','number-system','Interpret absolute value as distance from zero.'],
    ['integers','Integers','number-system','Represent, compare, and operate with positive and negative whole numbers.'],
    ['rational-numbers','Rational Numbers','number-system','Recognize numbers expressible as a ratio of integers.'],
    ['irrational-numbers','Irrational Numbers','number-system','Recognize and approximate nonterminating, nonrepeating values.'],
];

$lessons=[];
foreach($topics as $topic){
    [$slug,$title,$domain,$focus]=$topic;
    $lessons[$slug]=[
        'title'=>$title,'subtitle'=>$focus,'essential_question'=>'How can I use '.$title.' accurately and explain why the method works?',
        'learning_targets'=>"I can represent the central idea in {$title}.\nI can solve a related problem and show each step.\nI can interpret and verify my result.",
        'vocabulary'=>"Representation — a model, table, graph, expression, or equation\nEquivalent — having the same mathematical value\nReasoning — connected statements that justify a conclusion\nConstraint — a condition a solution must satisfy",
        'worked_examples'=>"Model the lesson idea | Identify the known and unknown quantities. | Choose a matching representation. | Complete equivalent steps and verify the conclusion.",
        'common_mistakes'=>"Using a procedure before identifying quantities | Label the information and explain why the procedure applies.\nStopping after calculating | Interpret the result and check it against the original conditions.",
        'real_life'=>$title.' appears in shopping, measurement, data, finance, science, and everyday comparisons.',
        'videos'=>mathbinder_verified_video_for_topic($title,$domain),
        'video_chapters'=>"0:00 | Connect to prior knowledge\n1:15 | Define the lesson idea\n2:45 | Work through an example\n4:30 | Analyze a common error\n6:00 | Try and verify",
        'practice_warmup'=>"Before you try || Define {$title} in your own words and give one example. | Answers vary; definition and example must agree. | Use the vocabulary. | Label the quantities. | Compare with the worked example.",
        'guided_practice'=>"Practice with support || Solve a teacher-selected {$title} problem and explain each step. | Answers depend on assigned values. | Identify known and unknown information. | Choose a model or equation. | Solve, label, and verify.",
        'independent_practice'=>"Show what you know || Create and solve a new {$title} example. | Answers vary. | Keep the same mathematical structure. | Include a second representation. | Provide the problem, work, answer, and check.",
        'challenge_practice'=>"Create a real-life {$title} problem with at least two constraints. Solve it in two ways and compare the methods.",
        'master_it'=>"I can represent {$title}.\nI can select and carry out an accurate strategy.\nI can explain each step.\nI can verify and interpret the result.",
        'mastery_questions'=>"What should happen first? | Identify and label the information ; Guess ; Ignore units ; Round everything | A\nWhich response shows strongest understanding? | Answer only ; Labeled model and reasoning ; Copied rule ; Estimate only | B\nWhat is an effective check? | Compare with the original conditions ; Hide work ; Change the question ; Remove labels | A\nWhy show equivalent steps? | Make reasoning visible ; Make answers larger ; Replace vocabulary ; Avoid checking | A\nWhat follows an error? | Revise the step and recheck ; Erase everything ; Keep it ; Guess | A",
        'parent_summary'=>'Students learn to '.$focus.' Ask your child to identify the quantities, explain the strategy, and show a check.',
        'parent_conversation'=>"Ask: What information is given?\nAsk: Why does this strategy fit?\nAsk: How can you prove the result is reasonable?",
        'parent_five_minute'=>'Choose a simple household or shopping example. Have your child model it, solve it, and explain one accuracy check.',
        'teacher_objectives'=>"Students will model and solve problems involving {$title}.\nStudents will communicate reasoning with precise vocabulary.\nStudents will analyze errors and verify conclusions.",
        'teacher_pacing'=>"Launch | 5 minutes | Notice and wonder.\nLearn It | 15 minutes | Connect models and procedures.\nWatch It | 7 minutes | Pause for predictions.\nPractice It | 20 minutes | Guided to independent.\nMastery | 8 minutes | Require reasoning and a check.",
        'teacher_misconceptions'=>"Procedure without meaning | Require labels and a model.\nUnreasonable result accepted | Require estimation, substitution, or contextual comparison.",
        'teacher_differentiation'=>"Below Level | Use manipulatives and partially completed examples.\nOn Level | Mix representations.\nAdvanced | Add reverse reasoning or proof.\nMultilingual Learners | Pair vocabulary with visuals and sentence frames.",
        'teacher_formative'=>"Listen for precise vocabulary. Use one error-analysis prompt. Collect an exit ticket with a result, reasoning, and check.",
        'standards'=>($domain==='ratios-proportional'?'CA CCSS 6–7.RP':'CA CCSS 6–8.NS').' — Standards-aligned lesson content.\nCCSS.MATH.PRACTICE.MP1, MP3, MP4, and MP6 — Persevere, justify, model, and attend to precision.',
        '_mb_domain_slug'=>$domain,
    ];
}
return $lessons;
