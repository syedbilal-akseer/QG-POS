<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CustomerFormsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(protected Collection $forms) {}

    public function collection(): Collection
    {
        return $this->forms;
    }

    public function headings(): array
    {
        return [
            'ID', 'Event', 'Form Type', 'Name', 'Designation', 'Company',
            'Address', 'City', 'Mobile', 'Phone', 'Email', 'Skype',
            'Shoe Material Dealer', 'Shoe Manufacturer', 'Merchandise',
            'Cottage', 'Ladies', 'Gents', 'Capacity',
            'Inquiry', 'Sample Given', 'Sample Required',
            'Submitted By', 'Linked Customer Code', 'Linked Customer Name',
            'Created By (User)', 'Created At',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            optional($row->event)->name,
            $row->form_type,
            $row->name,
            $row->designation,
            $row->company_name,
            $row->address,
            $row->city,
            $row->mobile,
            $row->phone,
            $row->email,
            $row->skype,
            $row->is_shoe_material_dealer ? 'Yes' : 'No',
            $row->is_shoe_manufacturer    ? 'Yes' : 'No',
            $row->is_merchandise          ? 'Yes' : 'No',
            $row->is_cottage              ? 'Yes' : 'No',
            $row->is_ladies               ? 'Yes' : 'No',
            $row->is_gents                ? 'Yes' : 'No',
            $row->capacity,
            $row->inquiry,
            $row->sample_given,
            $row->sample_required,
            $row->submitted_by,
            $row->customer_code,
            $row->customer_name_linked,
            optional($row->user)->name,
            optional($row->created_at)->format('Y-m-d H:i'),
        ];
    }
}
