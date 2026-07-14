<?php

/**
 * @author Gabriel Ruelas
 * @license MIT
 * @version 1.4.0
 */

namespace Ometra\Caronte\Models;

use Equidna\BeeHive\Traits\BelongsToTenant;
use Equidna\Toolkit\Traits\Database\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for Caronte users.
 *
 * @author Gabriel Ruelas
 * @license MIT
 * @version 1.4.0
 */
class CaronteUser extends Model
{
    use BelongsToTenant;
    use HasCompositePrimaryKey;

    protected $table;
    protected $primaryKey = ['uri_user', 'tenant_id'];
    protected $keyType    = 'string';

    public $timestamps   = false;
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'uri_user',
        'name',
        'email'
    ];

    protected $hidden = [];

    protected string $tenantKey = 'tenant_id';

    /**
     * Initialize the model.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('caronte.table_prefix') . 'Users';
    }

    /**
     * Get the metadata relationship for the user.
     *
     * @return HasMany
     */
    public function metadata(): HasMany
    {
        return $this->hasMany(CaronteUserMetadata::class, 'uri_user')
            ->where('tenant_id', $this->tenant_id);
    }

    /**
     * Mutator to set the user's name with proper casing.
     *
     * @param string $value Name value.
     * @return void
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = ucwords($value);
    }

    /**
     * Scope to search users by name or email.
     *
     * @param Builder $query Eloquent query builder.
     * @param string|null $search Search term.
     * @return Builder
     */
    public function scopeSearch(Builder $query, ?string $search = null): Builder
    {
        if ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
