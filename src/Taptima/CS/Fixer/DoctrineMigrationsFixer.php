<?php

declare(strict_types=1);

namespace Taptima\CS\Fixer;

use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\Phpdoc\NoEmptyPhpdocFixer;
use PhpCsFixer\Fixer\Whitespace\NoExtraBlankLinesFixer;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use Taptima\CS\AbstractFixer;
use Taptima\CS\Priority;

final class DoctrineMigrationsFixer extends AbstractFixer implements ConfigurableFixerInterface
{
    public function getSampleConfigurations(): array
    {
        return [
            null,
            ['instanceof' => ['Doctrine\Migrations\AbstractMigration']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens): bool
    {
        foreach ($this->configuration['instanceof'] as $parent) {
            if ($this->extendsClass($tokens, $parent)) {
                return true;
            }

            if ($this->implementsInterface($tokens, $parent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getSampleCode(): string
    {
        return <<<'SPEC'
<?php

declare(strict_types=1);

namespace Infrastructure\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190323095102 extends AbstractMigration
{
    public function getDescription()
    {
        return '';
    }

    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE admin (identifier CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', PRIMARY KEY(identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE admin');
    }
}
SPEC;
    }

    public function getDocumentation(): string
    {
        return 'Remove useless getDescription(), up(), down() and comments from Doctrine\Migrations\AbstractMigration if needed.';
    }

    public function getPriority(): int
    {
        return Priority::before(ClassAttributesSeparationFixer::class, NoEmptyPhpdocFixer::class, NoExtraBlankLinesFixer::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder('instanceof', 'Parent classes of your migration classes.'))
                ->setDefault(['Doctrine\Migrations\AbstractMigration'])
                ->getOption(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $this->removeUselessGetDocumentation($tokens);
        $this->removeUselessComments($tokens);
    }

    private function removeUselessGetDocumentation(Tokens $tokens): void
    {
        foreach ($this->analyze($tokens)->getElements() as $element) {
            if ($element['type'] !== 'method') {
                continue;
            }

            if ($element['methodName'] !== 'getDescription') {
                continue;
            }

            $sequences = $this->analyze($tokens)->findAllSequences(
                [
                    [
                        '{',
                        [T_RETURN],
                        [T_CONSTANT_ENCAPSED_STRING, "''"],
                        ';',
                        '}',
                    ],
                    [
                        '{',
                        [T_RETURN],
                        [T_CONSTANT_ENCAPSED_STRING, '""'],
                        ';',
                        '}',
                    ],
                ],
                $element['start'],
                $element['end']
            );

            if (empty($sequences)) {
                return;
            }

            $tokens->clearRange($element['start'], $element['end']);
        }
    }

    private function removeUselessComments(Tokens $tokens): void
    {
        $comments = $this->getComments($tokens);

        $blackList = [
            'Auto-generated Migration: Please modify to your needs!',
            'this up() migration is auto-generated, please modify it to your needs',
            'this down() migration is auto-generated, please modify it to your needs',
        ];

        foreach ($comments as $position => $comment) {
            $lines   = explode("\n", $comment->getContent());
            $changed = false;

            foreach ($lines as $index => $line) {
                if (\in_array(trim($line, '/* '), $blackList, true)) {
                    unset($lines[$index]);
                    $changed = true;
                }
            }

            if ($changed === false) {
                continue;
            }

            if (empty(trim(implode("\n", $lines), " /*\n"))) {
                $tokens->clearAt($position);
                $tokens->removeTrailingWhitespace($position);

                continue;
            }

            $tokens[$position] = new Token([T_COMMENT, implode("\n", $lines)]);
        }
    }
}
