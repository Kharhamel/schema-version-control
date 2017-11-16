<?php
namespace BrainDiminished\SchemaVersionControl\Command;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Command to apply schema in description file into database.
 */
class ApplySchemaCommand extends AbstractSchemaCommand
{
    protected function configure()
    {
        $this
            ->setName('schema:apply')
            ->setDescription('Update schema from schema description file.')
            ->addOption('assume-yes', 'y', InputOption::VALUE_NONE, 'Automatic yes to prompts')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'dry run')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('dry-run')) {
            $this->getMigrationSql($input, $output);
        } else {
            $this->updateSchema($input, $output);
        }
    }

    protected function updateSchema(InputInterface $input, OutputInterface $output)
    {
        $diff = $this->schemaVersionControlService->getSchemaDiff();

        if (!empty($diff->changedTables)) {
            $output->writeln(count($diff->changedTables).' changed tables');
        }
        if (!empty($diff->newTables)) {
            $output->writeln(count($diff->newTables).' new tables');
        }
        if (!empty($diff->removedTables)) {
            $output->writeln(count($diff->removedTables).' removed tables');
        }

        if (!$input->getOption('assume-yes')) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Continue? (yes/no)');
            if (!$helper->ask($input, $output, $question)) {
                return;
            }
        }

        $this->schemaVersionControlService->applySchema();
    }

    protected function getMigrationSql(InputInterface $input, OutputInterface $output)
    {
        $sqlStatements = $this->schemaVersionControlService->getMigrationSql();
        if (empty($sqlStatements)) {
            $output->writeln('# schema up-to-date.');
        } else {
            $output->writeln('# Would execute following statements');
            foreach ($sqlStatements as $sqlStatement) {
                $output->writeln($sqlStatement);
            }
        }
    }
}
