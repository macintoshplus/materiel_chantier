<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImportateurDefauts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:catalogue:importer-defauts', description: 'Create the default articles and the "Défaut" supplier with their prices')]
final class ImporterDefautsCommand extends Command
{
    public function __construct(private readonly ImportateurDefauts $importateur)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $res = $this->importateur->importer(new \DateTimeImmutable());
        $io->success(\sprintf('%d article(s) and %d price(s) created.', $res['articles'], $res['prix']));

        return Command::SUCCESS;
    }
}
