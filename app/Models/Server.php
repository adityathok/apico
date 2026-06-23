<?php

namespace App\Models;

use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['server_ip', 'server_domain', 'server_name'])]
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;
}
