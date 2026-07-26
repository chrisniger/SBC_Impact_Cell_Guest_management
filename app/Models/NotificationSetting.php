<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = ['action', 'recipient_email', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
