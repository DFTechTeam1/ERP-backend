<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class JwtGenerateKeys extends Command
{
    /**
     * @var string
     */
    protected $signature = 'jwt:keygen
        {--bits=2048 : RSA key size in bits (2048 minimum)}
        {--write : Also write the PEM keypair to storage/jwt (gitignored)}
        {--force : Overwrite existing key files when used with --write}';

    /**
     * @var string
     */
    protected $description = 'Generate an RSA keypair for RS256 access tokens and print base64 env vars (one keypair per environment)';

    public function handle(): int
    {
        $bits = max(2048, (int) $this->option('bits'));

        $resource = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->error('Failed to generate RSA key: '.openssl_error_string());

            return self::FAILURE;
        }

        openssl_pkey_export($resource, $privatePem);
        $publicPem = openssl_pkey_get_details($resource)['key'];

        if ($this->option('write')) {
            $this->writeKeyFiles($privatePem, $publicPem);
        }

        $this->newLine();
        $this->info('Add these to the .env for THIS environment only (never reuse across environments):');
        $this->newLine();
        $this->line('JWT_PRIVATE_KEY='.base64_encode($privatePem));
        $this->newLine();
        $this->line('JWT_PUBLIC_KEY='.base64_encode($publicPem));
        $this->newLine();
        $this->warn('Distribute ONLY JWT_PUBLIC_KEY to the non-Laravel services. Never commit the private key.');

        return self::SUCCESS;
    }

    private function writeKeyFiles(string $privatePem, string $publicPem): void
    {
        $dir = storage_path('jwt');

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $privatePath = $dir.'/private.key';
        $publicPath = $dir.'/public.key';

        if ((file_exists($privatePath) || file_exists($publicPath)) && ! $this->option('force')) {
            $this->error('Key files already exist in storage/jwt. Use --force to overwrite.');

            return;
        }

        file_put_contents($privatePath, $privatePem);
        chmod($privatePath, 0600);
        file_put_contents($publicPath, $publicPem);

        $this->info("Wrote keypair to {$dir} (gitignored).");
    }
}
