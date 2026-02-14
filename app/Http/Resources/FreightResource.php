<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FreightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,

            // Carga
            'cargo_name'              => $this->cargo_name,
            'cargo_description'       => $this->cargo_description,
            'weight'                  => $this->weight,
            'is_hazardous'            => $this->is_hazardous,
            'is_fragile'              => $this->is_fragile,
            'requires_refrigeration'  => $this->requires_refrigeration,

            // Status
            'status'                  => $this->status,
            'status_label'            => $this->status->label(),
            'checklist_completed'     => $this->checklist_completed,

            // Workflow
            'driver_response'         => $this->driver_response,
            'driver_response_label'   => $this->driver_response?->label(),
            'rejection_reason'        => $this->rejection_reason,
            'driver_responded_at'     => $this->driver_responded_at,
            'doping_approved'         => $this->doping_approved,
            'manager_approved'        => $this->manager_approved,
            'approved_at'             => $this->approved_at,

            // Requisitos
            'required_trailer_type'   => $this->required_trailer_type,
            'required_trailer_label'  => $this->required_trailer_type?->label(),
            'required_hitch_type'     => $this->required_hitch_type,

            // Endereços
            'origin_address'          => $this->origin_address,
            'destination_address'     => $this->destination_address,

            // Distância e tempo
            'distance_km'             => $this->distance_km,
            'estimated_hours'         => $this->estimated_hours,

            // Financeiro
            'price_per_km'            => $this->price_per_km,
            'price_per_ton'           => $this->price_per_ton,
            'toll_cost'               => $this->toll_cost,
            'fuel_cost'               => $this->fuel_cost,
            'total_price'             => $this->total_price,

            // Avaliação
            'driver_rating'           => $this->driver_rating,
            'driver_notes'            => $this->driver_notes,

            // Datas
            'deadline_at'             => $this->deadline_at,
            'started_at'              => $this->started_at,
            'completed_at'            => $this->completed_at,
            'created_at'              => $this->created_at,

            // Relacionamentos
            'driver'                  => UserResource::make($this->whenLoaded('driver')),
            'creator'                 => UserResource::make($this->whenLoaded('creator')),
            'approver'                => UserResource::make($this->whenLoaded('approver')),
            'truck'                   => TruckResource::make($this->whenLoaded('truck')),
            'trailer'                 => TrailerResource::make($this->whenLoaded('trailer')),
            'checklists'              => ChecklistResource::collection($this->whenLoaded('checklists')),
            'incidents'               => IncidentResource::collection($this->whenLoaded('incidents')),
            'doping_tests'            => $this->whenLoaded('dopingTests'),
        ];
    }
}
