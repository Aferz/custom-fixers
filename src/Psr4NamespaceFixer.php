<?php

namespace Aferz\CustomFixers;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class Psr4NamespaceFixer implements ConfigurableFixerInterface
{
    private string $projectBasePath;

    /**
     * @var array<string, string>
     */
    private array $psr4Autoloads;

    public function getConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder('project_base_path', 'The project base path.'))
                ->setAllowedTypes(['string'])
                ->getOption(),
            (new FixerOptionBuilder('composer_path', 'The location of your "composer.json" file.'))
                ->setAllowedTypes(['string'])
                ->setDefault(null)
                ->getOption(),
        ]);
    }

    public function configure(array $configuration): void
    {
        $this->projectBasePath = rtrim($configuration['project_base_path'], DIRECTORY_SEPARATOR);
        $composerPath = $configuration['composer_path'] ?? $this->projectBasePath;
        $composerFile = $composerPath.DIRECTORY_SEPARATOR.'composer.json';

        if (! file_exists($composerFile)) {
            throw new \InvalidArgumentException(sprintf(
                'The "composer.json" file does not exist at "%s".',
                $composerFile
            ));
        }

        $composerContents = json_decode(file_get_contents($composerFile), true);

        $this->psr4Autoloads = array_merge(
            ($composerContents['autoload']['psr-4'] ?? []),
            ($composerContents['autoload-dev']['psr-4'] ?? [])
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return true;
    }

    public function isRisky(): bool
    {
        return true;
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Fix the namespace according to the PSR-4 autoload configuration in composer.json.',
            [
                new CodeSample(
                    "<?php\n\nnamespace App\\Invalid\\Psr4\\Name;"
                ),
                new CodeSample(
                    "<?php\n\nnamespace App\\Valid\\Name;"
                ),
            ]
        );
    }

    public function getPriority(): int
    {
        return -20;
    }

    public function getName(): string
    {
        return 'Aferz/psr4_namespace';
    }

    public function supports(\SplFileInfo $file): bool
    {
        return true;
    }

    public function fix(\SplFileInfo $file, Tokens $tokens): void
    {
        if (! $fixedNamespace = $this->resolveCorrectNamespace($file)) {
            return;
        }

        $containsNamespace = $tokens->isTokenKindFound(T_NAMESPACE);

        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind(T_OPEN_TAG) && $containsNamespace === false) {
                $tokens->insertAt(
                    $index + 2,
                    $this->createCorrectNamespaceTokens($fixedNamespace, true)
                );

                break;
            }

            if ($token->isGivenKind(T_NAMESPACE)) {
                $namespaceStartsAtIndex = $index + 2;
                $currentNamespace = $this->getCurrentNamespace($tokens, $namespaceStartsAtIndex);

                if ($currentNamespace !== $fixedNamespace) {
                    $this->clearCurrentNamespace($tokens, $namespaceStartsAtIndex);

                    $tokens->insertAt(
                        $namespaceStartsAtIndex,
                        $this->createCorrectNamespaceTokens($fixedNamespace)
                    );
                }

                break;
            }
        }
    }

    private function resolveCorrectNamespace(SplFileInfo $file): ?string
    {
        $filepath = $file->getRealPath();

        foreach ($this->psr4Autoloads as $namespace => $dir) {
            $fullDirPath = $this->projectBasePath.DIRECTORY_SEPARATOR.$dir;

            if (strpos($filepath, $fullDirPath) === 0) {
                $relativePath = substr($filepath, strlen($fullDirPath));

                $relativeNamespace = str_replace('/', '\\', trim(dirname($relativePath), '/'));

                return rtrim($namespace, '\\') . '\\' . trim($relativeNamespace, '\\');
            }
        }

        return null;
    }

    private function getCurrentNamespace(Tokens $tokens, int $namespaceStartsAtIndex): string
    {
        $namespaceTokens = [];

        while ($tokens[$namespaceStartsAtIndex]->isGivenKind([T_STRING, T_NS_SEPARATOR])) {
            $namespaceTokens[] = $tokens[$namespaceStartsAtIndex]->getContent();
            $namespaceStartsAtIndex++;
        }

        return implode('', $namespaceTokens);
    }

    private function clearCurrentNamespace(Tokens $tokens, int $namespaceStartsAtIndex): void
    {
        while ($tokens[$namespaceStartsAtIndex]->isGivenKind([T_STRING, T_NS_SEPARATOR])) {
            $tokens->clearAt($namespaceStartsAtIndex);
            $namespaceStartsAtIndex++;
        }
    }

    private function createCorrectNamespaceTokens(string $fixedNamespace, bool $withNamespaceKeyword = false): Tokens
    {
        $newTokens = [];
        $parts = explode('\\', $fixedNamespace);

        if ($withNamespaceKeyword) {
            $newTokens[] = new Token([T_NAMESPACE, 'namespace']);
            $newTokens[] = new Token([T_WHITESPACE, ' ']);
        }

        foreach ($parts as $index => $part) {
            $newTokens[] = new Token([T_STRING, $part]);

            if ($index !== count($parts) - 1) {
                $newTokens[] = new Token([T_NS_SEPARATOR, '\\']);
            }
        }

        if ($withNamespaceKeyword) {
            $newTokens[] = new Token(';');
            $newTokens[] = new Token([T_WHITESPACE, "\n"]);
            $newTokens[] = new Token([T_WHITESPACE, "\n"]);
        }

        return Tokens::fromArray($newTokens);
    }
}