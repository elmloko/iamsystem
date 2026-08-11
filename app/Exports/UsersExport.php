<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Collection $accounts) {}

    public function collection(): Collection
    {
        return $this->accounts;
    }

    public function headings(): array
    {
        return ['Nombre', 'Sistema', 'Correo', 'Alias', 'Roles', 'Estado', 'Creado en sistema'];
    }

    public function map($account): array
    {
        return [
            $account['name'],
            $account['system_name'],
            $account['email'],
            $account['alias'] ?: '',
            $account['roles'] ?: '',
            match ($account['active']) {
                true => 'Activo',
                false => 'De baja',
                default => 'Sin datos',
            },
            $account['created_at'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
