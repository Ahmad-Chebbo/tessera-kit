<?php

namespace App\Modules\Leave\Models;

use App\Modules\Leave\Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(HolidayFactory::class)]
class Holiday extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'date', 'name'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
