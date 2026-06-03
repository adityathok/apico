<?php

namespace App\Models;

use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['domain', 'ip_address', 'license_key', 'status', 'theme_version', 'plugin_version', 'wp_version', 'php_version'])]
class Website extends Model
{
    /** @use HasFactory<WebsiteFactory> */
    use HasFactory;
}
