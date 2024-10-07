<?php declare(strict_types=1);

$workspacePath = getenv('WORKSPACE_PATH');

if (! $workspacePath) {
    throw new \RuntimeException('WORKSPACE_PATH environment variable is not set.');
}

return (new \PhpCsFixer\Config())
    ->registerCustomFixers([
        new Aferz\CustomFixers\Psr4NamespaceFixer(),
        new PhpCsFixerCustomFixers\Fixer\DeclareAfterOpeningTagFixer(),
        new PhpCsFixerCustomFixers\Fixer\MultilinePromotedPropertiesFixer(),
    ])
    ->setRules([
        'Aferz/psr4_namespace' => [
            'project_base_path' => $workspacePath,
        ],
        'PhpCsFixerCustomFixers/declare_after_opening_tag' => true,
        'PhpCsFixerCustomFixers/multiline_promoted_properties' => true,
    ])
    ->setUsingCache(false)
    ->setRiskyAllowed(true)
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([
                $workspacePath.'/app',
                $workspacePath.'/database/factories',
                $workspacePath.'/database/migrations',
                $workspacePath.'/src',
                $workspacePath.'/tests',
            ])
    );
