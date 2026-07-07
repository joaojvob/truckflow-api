<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FreightFiscalDocument */
class FreightFiscalDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'freight_id'       => $this->freight_id,
            'type'             => $this->type->value,
            'type_label'       => $this->type->label(),
            'status'           => $this->status->value,
            'status_label'     => $this->status->label(),
            'access_key'       => $this->access_key,
            'protocol_number'  => $this->protocol_number,
            'rejection_reason' => $this->rejection_reason,
            'has_xml'          => (bool) $this->xml_path,
            'has_pdf'          => (bool) $this->pdf_path,
            'payload'          => $this->payload,
            'authorized_at'    => $this->authorized_at?->toISOString(),
            'cancelled_at'     => $this->cancelled_at?->toISOString(),
            'creator'          => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
