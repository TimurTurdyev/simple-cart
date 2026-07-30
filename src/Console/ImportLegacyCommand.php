<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface;
use TimurTurdyev\Cart\Legacy\LegacyImporter;
use TimurTurdyev\Cart\Storage\CartRecord;

final class ImportLegacyCommand extends Command
{
    protected $signature = 'cart:import-legacy
        {table : Legacy table with darryldecode cart data}
        {--owner-column=identifier : Column holding the cart owner}
        {--data-column=cart_data : Column holding the serialized cart}
        {--list=cart : Target list name}
        {--connection= : Database connection for the legacy table}
        {--dry-run : Convert without writing}';

    protected $description = 'Convert darryldecode/laravelshoppingcart data into the cart_lists format';

    public function handle(ConnectionResolverInterface $db, LegacyImporter $importer): int
    {
        $ownerColumn = (string) $this->option('owner-column');
        $dataColumn = (string) $this->option('data-column');
        $imported = 0;
        $skipped = 0;

        $rows = $db->connection($this->option('connection'))
            ->table($this->argument('table'))
            ->get();

        foreach ($rows as $row) {
            $legacy = $this->decode($row->{$dataColumn} ?? null);

            if ($legacy === null) {
                $this->warn("Skipping owner [{$row->{$ownerColumn}}]: unreadable payload.");
                $skipped++;

                continue;
            }

            $payload = $importer->payload($legacy);

            if (! $this->option('dry-run')) {
                CartRecord::query()->updateOrCreate(
                    ['owner' => (string) $row->{$ownerColumn}, 'list' => (string) $this->option('list')],
                    ['payload' => $payload],
                );
            }

            $imported++;
        }

        $this->info("Imported: {$imported}, skipped: {$skipped}.");

        return self::SUCCESS;
    }

    private function decode(mixed $data): ?array
    {
        if (! is_string($data) || $data === '') {
            return null;
        }

        $decoded = json_decode($data, true);

        if (is_array($decoded)) {
            return $this->normalize($decoded);
        }

        $decoded = @unserialize($data, ['allowed_classes' => false]);

        return is_array($decoded) ? $this->normalize($decoded) : null;
    }

    private function normalize(array $decoded): array
    {
        if (isset($decoded['items']) || isset($decoded['conditions'])) {
            return $decoded;
        }

        return ['items' => $decoded];
    }
}
