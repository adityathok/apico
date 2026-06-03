<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'ip_address' => $this->ip_address,
            'license_key' => $this->license_key,
            'status' => $this->status,
            'theme_version' => $this->theme_version,
            'plugin_version' => $this->plugin_version,
            'wp_version' => $this->wp_version,
            'php_version' => $this->php_version,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
