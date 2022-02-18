<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/test',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                              => true,
        '@Symfony'                            => true,
        'array_syntax'                        => [
            'syntax' => 'short'
        ],
        'binary_operator_spaces'              => [
            'operators' => [
                '*'  => 'align',
                '='  => 'align',
                '=>' => 'align',
            ],
        ],
        'class_keyword_remove'                => false,
        'concat_space'                        => [
            'spacing' => 'one'
        ],
        'combine_consecutive_unsets'          => true,
        'general_phpdoc_annotation_remove'    => [
            'annotations' => ['@author'],
        ],
        'linebreak_after_opening_tag'         => true,
        'no_blank_lines_after_class_opening'  => false,
        'echo_tag_syntax'                     => [
            'format' => 'long',
        ],
        'no_useless_else'                     => true,
        'no_useless_return'                   => true,
        'not_operator_with_space'             => false,
        'not_operator_with_successor_space'   => false,
        'ordered_class_elements'              => true,
        'ordered_imports'                     => true,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_order'                        => true,
        'protected_to_private'                => true,
        'semicolon_after_instruction'         => true,
        'single_line_throw'                   => false,
    ])
    ->setFinder($finder);
