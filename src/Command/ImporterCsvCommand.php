<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImportateurCsv;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:catalogue:importer-csv', description: 'Import articles and prices from a CSV file (separator ";")')]
final class ImporterCsvCommand extends Command
{
    public function __construct(
        private readonly ImportateurCsv $importateur,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Path of the CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $chemin = $input->getArgument('fichier');
        $contenu = \is_string($chemin) && is_file($chemin) ? file_get_contents($chemin) : false;
        if (false === $contenu) {
            $io->error('Cannot read the file.');

            return Command::FAILURE;
        }

        $rapport = $this->importateur->importer($contenu, new \DateTimeImmutable());
        foreach ($rapport->erreurs as $erreur) {
            $io->warning($erreur->trans($this->translator));
        }
        $io->success(\sprintf(
            '%d article(s) created, %d price(s) set, %d unchanged, %d error(s).',
            $rapport->articlesCrees,
            $rapport->prixDefinis,
            $rapport->prixInchanges,
            \count($rapport->erreurs),
        ));

        return [] === $rapport->erreurs ? Command::SUCCESS : Command::FAILURE;
    }
}
