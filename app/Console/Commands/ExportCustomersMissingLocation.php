<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Writes a CSV report of customers whose address fields (city, area,
 * address1) are all missing (null or empty) to
 *   storage/app/reports/customers-missing-location-YYYY-MM-DD.csv
 *
 *   php artisan report:customers-missing-location
 *   php artisan report:customers-missing-location --path=custom/path.csv
 */
class ExportCustomersMissingLocation extends Command
{
    protected $signature = 'report:customers-missing-location
                            {--field=all : Which field must be missing: city, area, address1, or all (default: all three must be missing)}
                            {--path= : Optional relative path under storage/app (default: reports/customers-missing-location-YYYY-MM-DD.csv)}';

    protected $description = 'Generate a CSV report of customers with no city/area/address1 on file.';

    public function handle(): int
    {
        $field = $this->option('field');
        $fields = $field === 'all' ? ['city', 'area', 'address1'] : [$field];

        if (! in_array($field, ['all', 'city', 'area', 'address1'], true)) {
            $this->error("Invalid --field value: $field (expected: city, area, address1, or all)");
            return self::FAILURE;
        }

        $query = Customer::query();
        foreach ($fields as $f) {
            $query->where(fn ($q) => $q->whereNull($f)->orWhere($f, ''));
        }
        $customers = $query->orderBy('ou_name')->orderBy('customer_name')->get();

        if ($customers->isEmpty()) {
            $this->info('No customers are missing location data.');
            return self::SUCCESS;
        }

        $suffix = $field === 'all' ? '' : "-$field";
        $relPath = $this->option('path')
            ?: "reports/customers-missing-location{$suffix}-" . now()->format('Y-m-d') . '.csv';

        Storage::disk('local')->makeDirectory(dirname($relPath));
        $absPath = Storage::disk('local')->path($relPath);

        $fh = fopen($absPath, 'w');
        if ($fh === false) {
            $this->error("Could not open $absPath for writing.");
            return self::FAILURE;
        }

        fputcsv($fh, [
            'Customer Number', 'Customer Name', 'OU', 'Salesperson',
            'City', 'Area', 'Address', 'Contact Number', 'Email',
        ]);

        foreach ($customers as $c) {
            fputcsv($fh, [
                $c->customer_number,
                $c->customer_name,
                $c->ou_name,
                $c->salesperson,
                $c->city,
                $c->area,
                $c->address1,
                $c->contact_number,
                $c->email_address,
            ]);
        }

        fclose($fh);

        $this->info(sprintf('CSV written: %s  (%d customers missing location)', $absPath, $customers->count()));

        return self::SUCCESS;
    }
}
