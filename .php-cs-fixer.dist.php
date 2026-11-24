<?php

/**
 * Code style: PSR-12 with the additions Joomla uses for its own code.
 *
 *   composer cs       # report
 *   composer cs:fix   # fix
 */

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/build', __DIR__ . '/tests'])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setFinder($finder)
    ->setRules([
        '@PSR12'                                           => true,
        'array_syntax'                                     => ['syntax' => 'short'],
        'list_syntax'                                      => ['syntax' => 'short'],
        'no_trailing_comma_in_singleline'                  => true,
        'trailing_comma_in_multiline'                      => ['elements' => ['arrays']],
        'binary_operator_spaces'                           => ['operators' => ['=>' => 'align_single_space_minimal', '=' => 'align', '??=' => 'align']],
        'no_unused_imports'                                => true,
        'global_namespace_import'                          => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
        'ordered_imports'                                  => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'no_useless_else'                                  => true,
        'native_function_invocation'                       => ['include' => ['@compiler_optimized']],
        'nullable_type_declaration_for_default_null_value' => true,
        'no_unneeded_control_parentheses'                  => true,
        'combine_consecutive_issets'                       => true,
        'combine_consecutive_unsets'                       => true,
        'no_useless_sprintf'                               => true,
        'single_quote'                                     => true,
        'no_extra_blank_lines'                             => true,
        'no_whitespace_in_blank_line'                      => true,
        'declare_strict_types'                             => false,
    ]);
