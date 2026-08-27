<?php
/** Complete high-school and advanced course lessons from the canonical catalog. */
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/verified-video-map.php';

$records=[];
foreach(MathBinder_K_Calculus_Catalog::lessons() as $lesson){
    $pathway=$lesson['pathway'];
    if(in_array($pathway,['k','1','2','3','4','5','6','7','8'],true)) continue;
    $title=$lesson['title']; $course=$lesson['pathway_label']; $slug=$lesson['slug']; $domain=$lesson['domain'];
    $records[$slug]=[
        'title'=>$title,
        'subtitle'=>$course.': Develop conceptual understanding, procedural fluency, modeling, and proof in '.$title.'.',
        'essential_question'=>'How can the structures in '.$title.' be represented, justified, and applied to unfamiliar problems?',
        'learning_targets'=>"I can define and represent the central ideas in {$title}.\nI can select and execute an appropriate mathematical method.\nI can justify each transformation and evaluate the reasonableness of a result.\nI can transfer the idea to a new model or proof.",
        'vocabulary'=>"Domain language — precise terms associated with {$domain}\nRepresentation — symbolic, graphical, numerical, geometric, or verbal form\nConstraint — a condition restricting possible solutions\nEquivalent transformation — a step preserving the relevant relationship\nModel — a mathematical description of a situation",
        'worked_examples'=>"Analyze a {$title} problem | State the given information, restrictions, and goal. | Select a representation and justify the method. | Carry out equivalent steps, interpret the result, and verify it independently.\nConnect representations | Translate the result into a second form. | Compare what each form reveals. | State which representation is most useful and why.",
        'common_mistakes'=>"Ignoring restrictions or domain | Record restrictions before manipulating expressions or figures.\nUsing a theorem or rule without its conditions | Name the conditions and verify that they hold.\nStopping at a symbolic answer | Interpret, check, and communicate the result in context.",
        'real_life'=>$title.' supports advanced modeling in science, engineering, economics, technology, design, statistics, and decision-making.',
        'videos'=>mathbinder_verified_video_for_topic($title,$pathway),
        'video_chapters'=>"0:00 | Prerequisite connection\n1:30 | Core definition or theorem\n3:15 | Representative example\n5:30 | Multiple representations\n7:15 | Error analysis and verification",
        'practice_warmup'=>"Activate prior knowledge || State one prerequisite idea for {$title}, give an example, and explain the connection. | Answers vary. | Use the course sequence. | Name the representation. | Verify the claimed connection.",
        'guided_practice'=>"Develop the method || Solve a teacher-selected {$title} problem, annotating why each step is valid. | Answers depend on assigned values. | Record restrictions first. | Connect symbolic work to a graph, table, diagram, or context. | Verify with substitution, an inverse process, a theorem, or technology.",
        'independent_practice'=>"Demonstrate transfer || Create and solve a nonroutine {$title} problem with different parameters. | Answers vary. | Preserve the underlying structure. | Include two representations. | Provide a complete argument and independent check.",
        'challenge_practice'=>"Synthesis challenge: Combine {$title} with an earlier {$course} topic. State assumptions, solve using two approaches, compare efficiency, and defend the conclusion.",
        'master_it'=>"I can define and represent {$title} precisely.\nI can select a valid and efficient method.\nI can justify transformations, theorems, or modeling choices.\nI can verify, interpret, and communicate a conclusion.\nI can transfer the idea to an unfamiliar problem.",
        'mastery_questions'=>"What belongs at the start of an advanced problem? | Given information, restrictions, and goal ; A guessed formula ; A decimal answer ; A graph with no labels | A\nWhat makes a transformation valid? | It preserves the required relationship under stated conditions ; It shortens the work ; It uses technology ; It changes the domain | A\nWhich response is strongest? | Answer only ; Connected representations and justification ; Copied steps ; Unlabeled sketch | B\nWhat is an independent verification? | Substitute, reverse, compare, or apply a theorem ; Repeat the same arithmetic ; Hide restrictions ; Round early | A\nWhat demonstrates mastery? | Transfer to an unfamiliar case with justified reasoning ; Memorization only ; Speed only ; One example copied | A",
        'parent_summary'=>"In {$course}, students study {$title} through representations, procedures, modeling, and justification. Encourage explanation of assumptions, restrictions, and verification rather than answer-only work.",
        'parent_conversation'=>"Ask: What earlier idea does this build on?\nAsk: What conditions make the method valid?\nAsk: How can the conclusion be checked in another way?",
        'parent_five_minute'=>"Ask your student to teach one definition, theorem, or representation from {$title}, then show a short example and one verification method.",
        'teacher_objectives'=>"Students will represent and solve problems involving {$title}.\nStudents will justify procedures, theorems, and modeling decisions.\nStudents will connect multiple representations and analyze errors.\nStudents will transfer learning to nonroutine contexts.",
        'teacher_pacing'=>"Launch | 7 minutes | Use a contrasting case or unresolved model.\nDevelop | 18 minutes | Connect definition, representation, and method.\nWatch It | 8 minutes | Pause for prediction and critique.\nPractice | 22 minutes | Guided reasoning followed by independent transfer.\nMastery | 10 minutes | Require justification and independent verification.",
        'teacher_misconceptions'=>"Students manipulate without tracking restrictions | Require a conditions line before solution work.\nStudents overgeneralize from one representation | Compare examples, nonexamples, and boundary cases.\nStudents trust technology without interpretation | Require estimates and written conclusions.",
        'teacher_differentiation'=>"Scaffold | Use worked-example fading, annotated diagrams, and prerequisite retrieval.\nCore | Mix representations and require justification.\nExtension | Add parameters, proof, optimization, or counterexamples.\nMultilingual Learners | Preteach domain vocabulary and provide argument sentence frames.",
        'teacher_formative'=>"Use a prerequisite retrieval prompt, a representation match, and one error analysis. Collect an exit ticket requiring conditions, method, result, interpretation, and check.",
        'standards'=>$lesson['standards_band'].' — '.$domain.' and '.$title.'.\nCCSS.MATH.PRACTICE.MP1, MP2, MP3, MP4, MP5, MP6, MP7, and MP8 — Integrated advanced mathematical practice.',
        '_mb_course_slug'=>$pathway,'_mb_course_title'=>$course,'_mb_domain_title'=>$domain,'_mb_sequence'=>$lesson['sequence'],
    ];
}
return $records;
