<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WMS\Location;
use App\Models\WMS\Grn;
use App\Models\WMS\GrnLine;
use App\Models\WMS\Lpn;
use App\Models\WMS\WmsItemUomRule;
use Illuminate\Support\Str;

class WmsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Locations (Warehouse Structure)
        $zoneA = Location::create([
            'location_code' => 'ZONE-A',
            'type'          => 'zone',
            'description'   => 'Main Storage Zone A',
        ]);

        $aisle1 = Location::create([
            'location_code' => 'A1',
            'type'          => 'aisle',
            'parent_id'     => $zoneA->id,
            'description'   => 'Aisle 1 in Zone A',
        ]);

        $rack1 = Location::create([
            'location_code' => 'A1-R1',
            'type'          => 'rack',
            'parent_id'     => $aisle1->id,
            'description'   => 'Rack 1 in Aisle 1',
        ]);

        $bin1 = Location::create([
            'location_code' => 'A1-R1-B1',
            'type'          => 'bin',
            'parent_id'     => $rack1->id,
            'qr_code'       => 'QR-A1R1B1',
            'barcode'       => 'BC-A1R1B1',
            'description'   => 'Bin 1 on Rack 1',
        ]);

        $bin2 = Location::create([
            'location_code' => 'A1-R1-B2',
            'type'          => 'bin',
            'parent_id'     => $rack1->id,
            'qr_code'       => 'QR-A1R1B2',
            'barcode'       => 'BC-A1R1B2',
            'description'   => 'Bin 2 on Rack 1',
        ]);

        // 2. Create UOM Rules for items
        $itemCodes = ['ITEM001', 'ITEM002'];

        foreach ($itemCodes as $code) {
            WmsItemUomRule::create([
                'item_code'      => $code,
                'uom_level'      => 'pallet',
                'qty_per_parent' => 1,
            ]);
            WmsItemUomRule::create([
                'item_code'      => $code,
                'uom_level'      => 'carton',
                'qty_per_parent' => 50, // 50 cartons per pallet
            ]);
            WmsItemUomRule::create([
                'item_code'      => $code,
                'uom_level'      => 'unit',
                'qty_per_parent' => 10, // 10 units per carton
            ]);
        }

        // 3. Create GRNs and Lines
        $grn1 = Grn::create([
            'grn_number'     => 'GRN-2026-001',
            'po_number'      => 'PO-99901',
            'supplier_name'  => 'Global Supplies Inc.',
            'received_date'  => now(),
            'status'         => 'completed',
            'ou_id'          => 'OU101',
            'warehouse_name' => 'Main Warehouse',
        ]);

        $line1 = GrnLine::create([
            'grn_id'              => $grn1->id,
            'item_code'           => 'ITEM001',
            'description'         => 'Standard Widget A',
            'supplier_lot_number' => 'LOT-SUP-A1',
            'system_sub_lot'      => 'SL-001-A',
            'mfg_date'            => now()->subMonths(1),
            'expiry_date'         => now()->addYear(),
            'ordered_qty'         => 100,
            'received_qty'        => 100,
            'cost_price'          => 15.50,
            'pallet_size'         => 500, // 500 units per pallet
            'lpns_generated'      => true,
        ]);

        $line2 = GrnLine::create([
            'grn_id'              => $grn1->id,
            'item_code'           => 'ITEM002',
            'description'         => 'Premium Gadget B',
            'supplier_lot_number' => 'LOT-SUP-B2',
            'system_sub_lot'      => 'SL-002-B',
            'mfg_date'            => now()->subMonths(2),
            'expiry_date'         => now()->addYears(2),
            'ordered_qty'         => 50,
            'received_qty'        => 50,
            'cost_price'          => 45.00,
            'pallet_size'         => 50, // 50 units per pallet
            'lpns_generated'      => true,
        ]);

        // 4. Create LPNs
        // Create 2 Pallets for ITEM001 (50 units each pallet)
        for ($i = 1; $i <= 2; $i++) {
            Lpn::create([
                'lpn_number'     => Lpn::generateNumber(),
                'grn_line_id'    => $line1->id,
                'item_code'      => $line1->item_code,
                'lot_number'     => $line1->supplier_lot_number,
                'system_sub_lot' => $line1->system_sub_lot,
                'mfg_date'       => $line1->mfg_date,
                'expiry_date'    => $line1->expiry_date,
                'quantity'       => 50,
                'uom'            => 'pallet',
                'location_id'    => $bin1->id,
                'status'         => Lpn::STATUS_STORED,
                'ou_id'          => $grn1->ou_id,
            ]);
        }

        // Create 1 Pallet for ITEM002 (50 units)
        Lpn::create([
            'lpn_number'     => Lpn::generateNumber(),
            'grn_line_id'    => $line2->id,
            'item_code'      => $line2->item_code,
            'lot_number'     => $line2->supplier_lot_number,
            'system_sub_lot' => $line2->system_sub_lot,
            'mfg_date'       => $line2->mfg_date,
            'expiry_date'    => $line2->expiry_date,
            'quantity'       => 50,
            'uom'            => 'pallet',
            'location_id'    => $bin2->id,
            'status'         => Lpn::STATUS_RECEIVED,
            'ou_id'          => $grn1->ou_id,
        ]);
        
        // Example of a broken LPN
        Lpn::create([
            'lpn_number'     => Lpn::generateNumber(),
            'grn_line_id'    => $line2->id,
            'item_code'      => $line2->item_code,
            'lot_number'     => $line2->supplier_lot_number,
            'system_sub_lot' => $line2->system_sub_lot,
            'mfg_date'       => $line2->mfg_date,
            'expiry_date'    => $line2->expiry_date,
            'quantity'       => 5,
            'uom'            => 'unit',
            'location_id'    => null, // In transit or quarantine
            'status'         => Lpn::STATUS_BROKEN,
            'ou_id'          => $grn1->ou_id,
        ]);
    }
}
