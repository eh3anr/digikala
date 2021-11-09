<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class ReviewsExport implements FromCollection
{
    protected $reviews;

    public function __construct($reviews)
    {
        $this->reviews = $reviews;
    }

    public function collection()
    {
        return $this->reviews;
    }
}
