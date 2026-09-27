<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['Admin', 'Super Admin']) ?? false;
    }

    public function rules(): array
    {
        return ['direction' => ['required', Rule::in(['up', 'down'])]];
    }
}
