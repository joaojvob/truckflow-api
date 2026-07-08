<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DopingTestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'freight_id'     => $this->freight_id,
            'driver_id'      => $this->driver_id,
            'status'         => $this->status,
            'status_label'   => $this->status->label(),
            'has_file'       => (bool) $this->file_path,
            'reviewer_notes' => $this->reviewer_notes,
            'reviewed_at'    => $this->reviewed_at,
            'created_at'     => $this->created_at,
        ];
    }
}
