<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Placeholder vendor rows for the new Vendors AP module. Replace once the
 * sync:oracle-vendors command lands and pulls the real master.
 */
class VendorSeeder extends Seeder
{
    public function handle(): void { $this->run(); }

    public function run(): void
    {
        $rows = [
            ['vendor_code' => 'V-0001', 'vendor_name' => 'Pak Couriers (Pvt) Ltd',           'contact_person' => 'Imran Khan',   'contact_number' => '03001234567', 'city' => 'Karachi'],
            ['vendor_code' => 'V-0002', 'vendor_name' => 'Daewoo Pakistan Express Bus Svc',   'contact_person' => 'Asif Raza',    'contact_number' => '03011234567', 'city' => 'Lahore'],
            ['vendor_code' => 'V-0003', 'vendor_name' => 'K-Electric Limited',                'contact_person' => 'Sales Desk',   'contact_number' => '02199000000', 'city' => 'Karachi'],
            ['vendor_code' => 'V-0004', 'vendor_name' => 'SNGPL — Sui Northern Gas',          'contact_person' => 'Billing Desk', 'contact_number' => '04299201111', 'city' => 'Lahore'],
            ['vendor_code' => 'V-0005', 'vendor_name' => 'PTCL — Pakistan Telecommunication', 'contact_person' => 'Corp Desk',    'contact_number' => '02199119000', 'city' => 'Karachi'],
            ['vendor_code' => 'V-0006', 'vendor_name' => 'TPL Insurance Limited',             'contact_person' => 'Aftab Ahmed',  'contact_number' => '03021234567', 'city' => 'Karachi'],
            ['vendor_code' => 'V-0007', 'vendor_name' => 'Ali Trader & Sons',                 'contact_person' => 'Ali Hassan',   'contact_number' => '03031234567', 'city' => 'Karachi'],
            ['vendor_code' => 'V-0008', 'vendor_name' => 'Express Stationery Wholesale',      'contact_person' => 'Bilal Hussain','contact_number' => '03041234567', 'city' => 'Lahore'],
        ];

        foreach ($rows as $r) {
            Vendor::updateOrCreate(
                ['vendor_code' => $r['vendor_code']],
                $r + ['is_active' => true]
            );
        }
    }
}
