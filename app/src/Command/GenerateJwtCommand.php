<?php

declare(strict_types=1);

namespace App\Command;

use DomainException;
use Firebase\JWT\JWT;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:jwt:generate',
    description: 'Generate an HS256 JWT using JWT_SECRET and print it',
)]
final class GenerateJwtCommand extends Command
{
    public function __construct(
        #[Autowire('%env(JWT_SECRET)%')]
        private readonly string $jwtSecret,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'ttl',
                null,
                InputOption::VALUE_REQUIRED,
                'Token lifetime in seconds',
                '3600',
            )
            ->addOption(
                'sub',
                null,
                InputOption::VALUE_REQUIRED,
                'Subject (`sub`) claim',
                'dev-client',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('' === trim($this->jwtSecret)) {
            $io->error('JWT_SECRET is empty. Set it in app/.env.local (or your environment).');

            return Command::FAILURE;
        }

        $ttl = (int) $input->getOption('ttl');
        if ($ttl <= 0) {
            $io->error('Option --ttl must be a positive integer.');

            return Command::INVALID;
        }

        $sub = (string) $input->getOption('sub');
        $now = time();

        try {
            $token = JWT::encode([
                'iat' => $now,
                'nbf' => $now,
                'exp' => $now + $ttl,
                'sub' => $sub,
            ], $this->jwtSecret, 'HS256');
        } catch (DomainException $e) {
            $io->error(sprintf('%s For HS256, firebase/php-jwt expects JWT_SECRET to be at least 32 bytes.', $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln($token);

        return Command::SUCCESS;
    }
}
