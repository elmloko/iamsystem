<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemEntry extends Model
{
    protected $table = 'systems';

    protected $fillable = [
        'key', 'name', 'connection', 'users_table',
        'roles_table', 'model_type', 'status', 'notes',
        'name_column', 'last_name_column', 'email_column', 'password_column',
        'role_pivot_table', 'role_pivot_user_column', 'role_pivot_role_column',
        'role_column', 'role_json_column',
        'active_column', 'active_type', 'active_values',
        'alias_column',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(SystemAccount::class, 'system_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && filled($this->connection);
    }
}
