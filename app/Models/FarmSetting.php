<?php

namespace App\Models;

use Database\Factories\FarmSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['eggs_per_tray'])]
class FarmSetting extends Model
{
    /** @use HasFactory<FarmSettingFactory> */
    use HasFactory;
}
