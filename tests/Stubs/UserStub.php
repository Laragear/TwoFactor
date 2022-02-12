<?php

namespace Tests\Stubs;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

class UserStub extends Model implements AuthenticatableContract
{
    use Authenticatable;

    public const PASSWORD_SECRET = '$2y$10$O.vJ1iYXIoNH3orPUNWuNui7BUkl4fWYY1R/8GC5KRCXQ7tnqId8K';

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
