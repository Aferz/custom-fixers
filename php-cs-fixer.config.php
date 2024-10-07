<?php declare(strict_types=1);

return (new \PhpCsFixer\Config)
    ->registerCustomFixers([
        new Aferz\CustomFixers\Psr4NamespaceFixer,
        new PhpCsFixerCustomFixers\Fixer\DeclareAfterOpeningTagFixer,
        new PhpCsFixerCustomFixers\Fixer\MultilinePromotedPropertiesFixer,
    ])
    ->setRules([
        /* 'Aferz/psr4_namespace' => [
            'project_base_path' => __DIR__,
            'exclude_paths' => [
                'tests/Utils/bootstrap.php',
                'tests/Utils/helpers.php',
            ],
        ], */
        'PhpCsFixerCustomFixers/declare_after_opening_tag' => true,
        'PhpCsFixerCustomFixers/multiline_promoted_properties' => true,
    ])
    ->setUsingCache(false)
    ->setRiskyAllowed(true);
