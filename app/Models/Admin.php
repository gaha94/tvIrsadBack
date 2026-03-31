<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    protected $table = 'admins';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'api_token',
        'role',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password_hash',
        'api_token',
    ];
}