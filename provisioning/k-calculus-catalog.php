<?php
/**
 * Planned Kindergarten-through-Calculus curriculum catalog.
 *
 * These records describe curriculum coverage and sequencing only. They do not
 * create or certify lesson posts, preventing unfinished shells from appearing
 * as published instructional content.
 */
defined('ABSPATH') || exit;

final class MathBinder_K_Calculus_Catalog {
    public static function pathways() {
        return [
            'k' => ['label'=>'Kindergarten','band'=>'Elementary K–2','standards'=>'CA CCSS K.CC, K.OA, K.NBT, K.MD, K.G'],
            '1' => ['label'=>'Grade 1','band'=>'Elementary K–2','standards'=>'CA CCSS 1.OA, 1.NBT, 1.MD, 1.G'],
            '2' => ['label'=>'Grade 2','band'=>'Elementary K–2','standards'=>'CA CCSS 2.OA, 2.NBT, 2.MD, 2.G'],
            '3' => ['label'=>'Grade 3','band'=>'Elementary 3–5','standards'=>'CA CCSS 3.OA, 3.NBT, 3.NF, 3.MD, 3.G'],
            '4' => ['label'=>'Grade 4','band'=>'Elementary 3–5','standards'=>'CA CCSS 4.OA, 4.NBT, 4.NF, 4.MD, 4.G'],
            '5' => ['label'=>'Grade 5','band'=>'Elementary 3–5','standards'=>'CA CCSS 5.OA, 5.NBT, 5.NF, 5.MD, 5.G'],
            '6' => ['label'=>'Grade 6','band'=>'Middle School 6–8','standards'=>'CA CCSS 6.RP, 6.NS, 6.EE, 6.G, 6.SP'],
            '7' => ['label'=>'Grade 7','band'=>'Middle School 6–8','standards'=>'CA CCSS 7.RP, 7.NS, 7.EE, 7.G, 7.SP'],
            '8' => ['label'=>'Grade 8','band'=>'Middle School 6–8','standards'=>'CA CCSS 8.NS, 8.EE, 8.F, 8.G, 8.SP'],
            'algebra-1' => ['label'=>'Algebra I','band'=>'High School','standards'=>'CA CCSS HSN, HSA, HSF, HSS'],
            'geometry' => ['label'=>'Geometry','band'=>'High School','standards'=>'CA CCSS HSG'],
            'algebra-2' => ['label'=>'Algebra II','band'=>'High School','standards'=>'CA CCSS HSN, HSA, HSF, HSS'],
            'statistics' => ['label'=>'Statistics','band'=>'High School / Advanced','standards'=>'CA CCSS HSS'],
            'precalculus' => ['label'=>'Precalculus','band'=>'High School / Advanced','standards'=>'CA CCSS HSN, HSA, HSF, HSG plus advanced preparation'],
            'calculus-ab' => ['label'=>'Calculus AB','band'=>'Calculus','standards'=>'College-preparatory / AP Calculus AB alignment'],
            'calculus-bc' => ['label'=>'Calculus BC','band'=>'Calculus','standards'=>'College-preparatory / AP Calculus BC alignment'],
        ];
    }

    private static function topic_map() {
        return [
            'k' => [
                ['Counting to 20','Counting & Cardinality'],['Counting to 100','Counting & Cardinality'],['Compare Numbers','Counting & Cardinality'],['Compose and Decompose Numbers','Operations & Algebraic Thinking'],['Addition Within 10','Operations & Algebraic Thinking'],['Subtraction Within 10','Operations & Algebraic Thinking'],['Teen Numbers','Number & Operations in Base Ten'],['Describe and Compare Measurement','Measurement & Data'],['Sort and Classify Objects','Measurement & Data'],['Identify and Describe Shapes','Geometry'],['Compose Shapes','Geometry'],
            ],
            '1' => [
                ['Addition and Subtraction Stories','Operations & Algebraic Thinking'],['Properties of Addition','Operations & Algebraic Thinking'],['Addition and Subtraction Within 20','Operations & Algebraic Thinking'],['Unknowns in Equations','Operations & Algebraic Thinking'],['Counting and Place Value to 120','Number & Operations in Base Ten'],['Compare Two-Digit Numbers','Number & Operations in Base Ten'],['Add Within 100','Number & Operations in Base Ten'],['Measure Lengths','Measurement & Data'],['Time to the Hour and Half Hour','Measurement & Data'],['Represent and Interpret Data','Measurement & Data'],['Two- and Three-Dimensional Shapes','Geometry'],['Partition Shapes into Equal Shares','Geometry'],
            ],
            '2' => [
                ['Addition and Subtraction Within 100','Operations & Algebraic Thinking'],['Equal Groups and Arrays','Operations & Algebraic Thinking'],['Place Value to 1,000','Number & Operations in Base Ten'],['Compare Three-Digit Numbers','Number & Operations in Base Ten'],['Add and Subtract Within 1,000','Number & Operations in Base Ten'],['Measure and Estimate Length','Measurement & Data'],['Relate Addition and Subtraction to Length','Measurement & Data'],['Time to Five Minutes','Measurement & Data'],['Money','Measurement & Data'],['Picture and Bar Graphs','Measurement & Data'],['Reason with Shapes','Geometry'],['Partition Rectangles and Circles','Geometry'],
            ],
            '3' => [
                ['Meaning of Multiplication','Operations & Algebraic Thinking'],['Meaning of Division','Operations & Algebraic Thinking'],['Multiplication and Division Facts','Operations & Algebraic Thinking'],['Properties and Patterns','Operations & Algebraic Thinking'],['Multi-Step Word Problems','Operations & Algebraic Thinking'],['Rounding and Multi-Digit Arithmetic','Number & Operations in Base Ten'],['Fractions as Numbers','Fractions'],['Equivalent Fractions','Fractions'],['Compare Fractions','Fractions'],['Time, Mass, and Volume','Measurement & Data'],['Area and Perimeter','Measurement & Data'],['Scaled Graphs','Measurement & Data'],['Classify Quadrilaterals','Geometry'],
            ],
            '4' => [
                ['Factors and Multiples','Operations & Algebraic Thinking'],['Number and Shape Patterns','Operations & Algebraic Thinking'],['Place Value and Rounding','Number & Operations in Base Ten'],['Multi-Digit Addition and Subtraction','Number & Operations in Base Ten'],['Multi-Digit Multiplication','Number & Operations in Base Ten'],['Division with Remainders','Number & Operations in Base Ten'],['Equivalent Fractions and Simplest Form','Fractions'],['Add and Subtract Fractions','Fractions'],['Multiply Fractions by Whole Numbers','Fractions'],['Decimal Notation and Comparison','Fractions'],['Measurement Conversions','Measurement & Data'],['Angles and Angle Measurement','Geometry'],['Lines, Symmetry, and Shape Classification','Geometry'],
            ],
            '5' => [
                ['Numerical Expressions and Patterns','Operations & Algebraic Thinking'],['Place Value with Decimals','Number & Operations in Base Ten'],['Multi-Digit Multiplication and Division','Number & Operations in Base Ten'],['Decimal Operations','Number & Operations in Base Ten'],['Add and Subtract Fractions with Unlike Denominators','Fractions'],['Multiply Fractions','Fractions'],['Divide Unit Fractions and Whole Numbers','Fractions'],['Convert Measurement Units','Measurement & Data'],['Volume','Measurement & Data'],['Coordinate Plane','Geometry'],['Classify Two-Dimensional Figures','Geometry'],['Represent and Interpret Data','Measurement & Data'],
            ],
            '6' => [
                ['Ratios','Ratios & Proportional Relationships'],['Rates and Unit Rates','Ratios & Proportional Relationships'],['Percents','Ratios & Proportional Relationships'],['Divide Fractions','The Number System'],['Multi-Digit and Decimal Operations','The Number System'],['Factors, Multiples, and Distributive Property','The Number System'],['Positive and Negative Numbers','The Number System'],['Expressions and Properties','Expressions & Equations'],['One-Variable Equations and Inequalities','Expressions & Equations'],['Dependent and Independent Variables','Expressions & Equations'],['Area and Surface Area','Geometry'],['Volume','Geometry'],['Statistical Questions and Distributions','Statistics & Probability'],['Measures of Center and Variability','Statistics & Probability'],
            ],
            '7' => [
                ['Proportional Relationships','Ratios & Proportional Relationships'],['Unit Rates with Fractions','Ratios & Proportional Relationships'],['Percent Applications','Ratios & Proportional Relationships'],['Operations with Rational Numbers','The Number System'],['Equivalent Expressions','Expressions & Equations'],['Equations and Inequalities','Expressions & Equations'],['Scale Drawings','Geometry'],['Circles','Geometry'],['Angle Relationships','Geometry'],['Area, Surface Area, and Volume','Geometry'],['Random Sampling','Statistics & Probability'],['Comparing Populations','Statistics & Probability'],['Probability Models','Statistics & Probability'],['Compound Probability','Statistics & Probability'],
            ],
            '8' => [
                ['Rational and Irrational Numbers','The Number System'],['Integer Exponents and Scientific Notation','Expressions & Equations'],['Linear Equations','Expressions & Equations'],['Systems of Linear Equations','Expressions & Equations'],['Functions','Functions'],['Linear Functions','Functions'],['Transformations','Geometry'],['Congruence and Similarity','Geometry'],['Pythagorean Theorem and Distance','Geometry'],['Volume of Cylinders, Cones, and Spheres','Geometry'],['Scatter Plots and Association','Statistics & Probability'],['Two-Way Tables','Statistics & Probability'],
            ],
            'algebra-1' => [
                ['Quantities and Units','Number & Quantity'],['Expressions and Polynomials','Algebra'],['Linear Equations and Inequalities','Algebra'],['Systems of Equations and Inequalities','Algebra'],['Relations and Functions','Functions'],['Linear Functions and Modeling','Functions'],['Sequences','Functions'],['Exponents and Exponential Functions','Functions'],['Quadratic Functions','Functions'],['Solving Quadratic Equations','Algebra'],['Data Displays and Regression','Statistics & Probability'],['Interpreting Categorical and Quantitative Data','Statistics & Probability'],
            ],
            'geometry' => [
                ['Foundations, Definitions, and Proof','Congruence'],['Transformations and Symmetry','Congruence'],['Triangle Congruence','Congruence'],['Geometric Constructions','Congruence'],['Similarity and Dilations','Similarity, Right Triangles & Trigonometry'],['Right Triangles and Trigonometry','Similarity, Right Triangles & Trigonometry'],['Polygons and Quadrilaterals','Geometry'],['Circles','Circles'],['Coordinate Geometry and Proof','Expressing Geometric Properties'],['Area and Surface Area','Geometric Measurement & Dimension'],['Volume','Geometric Measurement & Dimension'],['Probability with Geometric Models','Modeling with Geometry'],
            ],
            'algebra-2' => [
                ['Complex Numbers','Number & Quantity'],['Polynomial Operations','Algebra'],['Polynomial Equations and Identities','Algebra'],['Rational Expressions and Equations','Algebra'],['Radical Expressions and Equations','Algebra'],['Function Transformations and Inverses','Functions'],['Quadratic Functions and Equations','Functions'],['Exponential and Logarithmic Functions','Functions'],['Sequences and Series','Functions'],['Trigonometric Functions','Functions'],['Probability and Conditional Probability','Statistics & Probability'],['Statistical Inference and Modeling','Statistics & Probability'],
            ],
            'statistics' => [
                ['Study Design and Data Collection','Statistical Reasoning'],['Exploring One-Variable Data','Interpreting Data'],['Normal Distributions','Interpreting Data'],['Exploring Two-Variable Data','Interpreting Data'],['Probability Rules','Probability'],['Conditional Probability','Probability'],['Random Variables','Probability'],['Sampling Distributions','Inference'],['Confidence Intervals','Inference'],['Significance Tests','Inference'],['Chi-Square Inference','Inference'],['Regression Inference','Inference'],
            ],
            'precalculus' => [
                ['Functions and Their Representations','Functions'],['Transformations and Compositions','Functions'],['Inverse Functions','Functions'],['Polynomial and Rational Functions','Functions'],['Exponential and Logarithmic Functions','Functions'],['Trigonometric Functions and the Unit Circle','Trigonometry'],['Trigonometric Identities and Equations','Trigonometry'],['Analytic Trigonometry','Trigonometry'],['Systems, Matrices, and Determinants','Algebra'],['Conic Sections','Analytic Geometry'],['Parametric and Polar Equations','Analytic Geometry'],['Vectors','Number & Quantity'],['Sequences and Series','Functions'],['Introduction to Limits','Calculus Preparation'],
            ],
            'calculus-ab' => [
                ['Limits from Graphs, Tables, and Algebra','Limits & Continuity'],['Continuity','Limits & Continuity'],['Definition of the Derivative','Differentiation'],['Derivative Rules','Differentiation'],['Implicit and Inverse Differentiation','Differentiation'],['Applications of Derivatives','Applications of Differentiation'],['Related Rates','Applications of Differentiation'],['Optimization','Applications of Differentiation'],['Accumulation and Riemann Sums','Integration'],['Fundamental Theorem of Calculus','Integration'],['Techniques and Applications of Integration','Integration'],['Differential Equations and Slope Fields','Differential Equations'],['Area and Volume','Applications of Integration'],
            ],
            'calculus-bc' => [
                ['Calculus AB Review and Extension','Calculus Foundations'],['Advanced Integration Techniques','Integration'],['Euler Method and Logistic Models','Differential Equations'],['Parametric Derivatives and Arc Length','Parametric Calculus'],['Polar Calculus','Polar Calculus'],['Improper Integrals','Integration'],['Infinite Sequences','Sequences & Series'],['Infinite Series and Convergence','Sequences & Series'],['Power Series','Sequences & Series'],['Taylor and Maclaurin Series','Sequences & Series'],['Error Bounds and Polynomial Approximation','Sequences & Series'],
            ],
        ];
    }

    public static function lessons() {
        $pathways = self::pathways();
        $lessons = [];
        foreach (self::topic_map() as $pathway_key => $topics) {
            $previous = '';
            foreach ($topics as $index => $topic) {
                $slug = sanitize_title($pathway_key . '-' . $topic[0]);
                $lessons[] = [
                    'slug' => $slug,
                    'title' => $topic[0],
                    'pathway' => $pathway_key,
                    'pathway_label' => $pathways[$pathway_key]['label'],
                    'band' => $pathways[$pathway_key]['band'],
                    'domain' => $topic[1],
                    'standards_band' => $pathways[$pathway_key]['standards'],
                    'sequence' => $index + 1,
                    'prerequisite_slug' => $previous,
                    'status' => 'planned',
                ];
                $previous = $slug;
            }
        }
        return $lessons;
    }
}
