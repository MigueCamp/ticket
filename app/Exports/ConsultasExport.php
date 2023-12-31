<?php

namespace App\Exports;
use DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;



class ConsultasExport implements FromCollection
{
    
    /**
    * @return \Illuminate\Support\Collection
    */
    public function headings(): array
    {
        return [
            'Articulo',
            'Descripcion',
            'Cantidad',
            'Importe',
        ];
    }
    public function collection()
    {
        return collect();
    }
}
