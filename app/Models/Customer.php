<?php

namespace App\Models;

use App\Policies\CustomerPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

#[Fillable(['name'])]
#[UsePolicy(CustomerPolicy::class)]
class Customer extends Model
{
    use SoftDeletes;
}
