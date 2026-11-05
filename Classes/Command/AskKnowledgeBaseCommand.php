<?php

declare(strict_types=1);

namespace W3code\W3cAIConnector\Command;

use Aws\Exception\AwsException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use W3code\W3cAIConnector\Service\BedrockKnowledgeBaseService;

/**
 * Class AskKnowledgeBaseCommand
 *
 * Asks the Amazon Bedrock Knowledge Base a question, e.g. to test its content.
 */
final class AskKnowledgeBaseCommand extends Command
{
    public function __construct(
        private readonly BedrockKnowledgeBaseService $knowledgeBaseService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Asks the Amazon Bedrock Knowledge Base a question')
            ->addArgument('question', InputArgument::REQUIRED, 'The question, between quotes')
            ->addOption('search-only', 's', InputOption::VALUE_NONE, 'Only shows the most relevant passages, without generating an answer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $question = (string)$input->getArgument('question');

        try {
            if ($input->getOption('search-only') || !$this->knowledgeBaseService->canGenerateAnswers()) {
                if (!$input->getOption('search-only')) {
                    $io->note('No model is configured to generate answers (bedrockKnowledgeBase.modelArn), showing the most relevant passages.');
                }
                $this->showPassages($io, $this->knowledgeBaseService->retrieve($question));
            } else {
                $this->showAnswer($io, $this->knowledgeBaseService->retrieveAndGenerate($question));
            }
        } catch (AwsException $e) {
            $io->error($e->getAwsErrorCode() . ': ' . ($e->getAwsErrorMessage() ?: $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showAnswer(SymfonyStyle $io, array $result): void
    {
        $io->section('Answer');
        $io->writeln($result['answer']);

        $sources = [];
        foreach ($result['citations'] as $citation) {
            foreach ($citation['sources'] as $source) {
                $sources[$source['metadata']['url'] ?? ''] = ($source['metadata']['title'] ?? '') . ' - ' . ($source['metadata']['url'] ?? '');
            }
        }
        $io->section('Sources');
        $sources === [] ? $io->writeln('none') : $io->listing(array_values($sources));
    }

    private function showPassages(SymfonyStyle $io, array $results): void
    {
        if ($results === []) {
            $io->warning(sprintf('Nothing relevant found: no passage scores at least %s (bedrockKnowledgeBase.minimumScore).', $this->knowledgeBaseService->getMinimumScore()));
            return;
        }

        foreach ($results as $result) {
            $io->section(sprintf('%.2f  %s', $result['score'], $result['metadata']['title'] ?? ''));
            $io->writeln('<comment>' . ($result['metadata']['url'] ?? '') . '</comment>');
            $io->writeln(mb_strimwidth((string)preg_replace('/\s+/', ' ', $result['content']), 0, 400, '...'));
        }
    }
}
