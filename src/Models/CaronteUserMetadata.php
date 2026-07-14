<?php

/**
 * @author Gabriel Ruelas
 * @license MIT
 * @version 1.4.0
 */

namespace Ometra\Caronte\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Equidna\Toolkit\Traits\Database\HasCompositePrimaryKey;

/**
 * Eloquent model for Caronte user metadata.
 *
 * @author Gabriel Ruelas
 * @license MIT
 * @version 1.4.0
 */
class CaronteUserMetadata extends Model
{
    use HasCompositePrimaryKey;

    protected $table;
    protected $primaryKey = ['uri_user', 'tenant_id', 'scope', 'key'];

    public $timestamps = false;

    protected $fillable = [
        'uri_user',
        'tenant_id',
        'key',
        'value',
        'scope'
    ];

    /**
     * Initialize the model.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('caronte.table_prefix') . 'UsersMetadata';
    }

    /**
     * Get the user relationship for this metadata.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(CaronteUser::class, 'uri_user');
    }
}
