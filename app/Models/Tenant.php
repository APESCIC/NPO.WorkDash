<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A workspace (charity). Tenant scoping of other models arrives with #91.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];
}
