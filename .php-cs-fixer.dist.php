<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor');

return PhpCsFixer\Config::create()
    ->setFinder($finder)
    ->setRules([
        'visibility_required' => false,
        'class_attributes_separation' => false,
        'no_empty_statement' => false,
        'array_syntax' => ['syntax' => 'long'],
        'single_space_around_construct' => false,
        'method_argument_space' => false,
        'no_unneeded_control_parentheses' => false,
        'single_quote' => false,
        'no_superfluous_phpdoc_tags' => false,
        'no_empty_phpdoc' => false,
        'no_spaces_after_function_name' => false,
        'control_structure_braces' => false,
        'concat_space' => ['spacing' => 'none'],
        'global_namespace_import' => false,
        'include' => false,
        'trailing_comma_in_multiline' => false,
        'yoda_style' => false,
        'braces_position' => false,
        'statement_indentation' => false,
        'phpdoc_trim' => false,
        'no_unused_imports' => false,
        'cast_spaces' => false,
        'no_extra_blank_lines' => false,
        'blank_line_before_statement' => false,
    ]);
