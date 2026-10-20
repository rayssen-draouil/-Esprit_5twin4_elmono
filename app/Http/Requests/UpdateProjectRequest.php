<?php

namespace App\Http\Requests;

class UpdateProjectRequest extends StoreProjectRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        // On modification, la date de début existante peut être dans le passé
        $rules['start_date'] = ['nullable', 'date'];

        return $rules;
    }
}
