<?php

namespace App\Livewire\Inventory;

use Livewire\Component;
use Livewire\Attributes\Title;

#[Title('Barcode & Inventory Management')]
class BarcodeInventoryManager extends Component
{
    public $activeTab = 'inward';
    
    // Inward / Lot Generation State
    public $scannedBarcode = '';
    public $inwardLots = [];
    public $currentBatch = null;

    // Reconciliation State
    public $selectedPO = null;
    public $reconciliationData = [];
    public $reasonCodes = [
        'DAMAGED' => 'Damaged in Transit',
        'SHORT' => 'Short Shipment',
        'QUALITY' => 'Quality Check Failed',
        'EXPIRED' => 'Near Expiry / Expired',
    ];

    // FIFO State
    public $fifoInventory = [];
    public $ordersToPick = [];

    // Multi-Warehouse State
    public $warehouses = [];
    public $transferHistory = [];
    public $activeTransfers = [];

    // Gatepass Form State
    public $gatepass = [
        'vehicle_no' => '',
        'driver_name' => '',
        'destination' => '',
        'items' => [],
    ];

    public function mount()
    {
        $this->loadDummyData();
    }

    public function loadDummyData()
    {
        // Dummy Lots
        $this->inwardLots = [
            ['id' => 'LOT-001', 'item' => 'Plastic Container 500ml', 'qty' => 500, 'timestamp' => '2026-03-14 10:20', 'user' => 'Admin'],
            ['id' => 'LOT-002', 'item' => 'Printed Label - QG', 'qty' => 1200, 'timestamp' => '2026-03-14 10:45', 'user' => 'Admin'],
        ];

        // Dummy POs for Reconciliation
        $this->reconciliationData = [
            [
                'po_number' => 'PO-2026-001',
                'vendor' => 'Global Packing Solutions',
                'items' => [
                    ['code' => 'PK-001', 'name' => 'Plastic Container 500ml', 'expected' => 1000, 'received' => 0, 'status' => 'pending', 'reason' => ''],
                    ['code' => 'LB-442', 'name' => 'Printed Label - QG', 'expected' => 5000, 'received' => 0, 'status' => 'pending', 'reason' => ''],
                ]
            ]
        ];

        // Dummy FIFO Inventory
        $this->fifoInventory = [
            ['item' => 'Plastic Container 500ml', 'batch' => 'BATCH-FEB-01', 'lot' => 'LOT-X1', 'received_at' => '2026-02-01', 'qty' => 200, 'location' => 'KHI-SEC-A'],
            ['item' => 'Plastic Container 500ml', 'batch' => 'BATCH-MAR-01', 'lot' => 'LOT-Y2', 'received_at' => '2026-03-01', 'qty' => 500, 'location' => 'KHI-SEC-B'],
            ['item' => 'Printed Label - QG', 'batch' => 'BATCH-JAN-55', 'lot' => 'LOT-Z9', 'received_at' => '2026-01-15', 'qty' => 1500, 'location' => 'LHR-SEC-C'],
        ];

        // Dummy Warehouse Stock
        $this->warehouses = [
            'Karachi' => [
                'Plastic Container 500ml' => 700,
                'Printed Label - QG' => 0,
            ],
            'Lahore' => [
                'Plastic Container 500ml' => 1200,
                'Printed Label - QG' => 3000,
            ],
            'HBM' => [
                'Plastic Container 500ml' => 0,
                'Printed Label - QG' => 5000,
            ]
        ];

        $this->activeTransfers = [
            ['id' => 'TR-882', 'from' => 'Lahore', 'to' => 'Karachi', 'item' => 'Printed Label - QG', 'qty' => 1000, 'status' => 'In-Transit', 'sent_at' => '2026-03-13'],
        ];
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function scanInward()
    {
        if (empty($this->scannedBarcode)) return;

        // Mock scan logic
        $this->inwardLots[] = [
            'id' => 'LOT-' . rand(100, 999),
            'item' => 'Scanned Item ' . $this->scannedBarcode,
            'qty' => rand(50, 200),
            'timestamp' => now()->format('Y-m-d H:i'),
            'user' => auth()->user()->name ?? 'System',
        ];

        $this->scannedBarcode = '';
        $this->dispatch('play-scan-sound');
    }

    public function render()
    {
        return view('livewire.inventory.barcode-inventory-manager');
    }
}
