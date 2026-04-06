<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['name', 'slug', 'description'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $data[$field] = trim($this->input($field));
            }
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', 'different:id'],
            'name' => ['required', 'string', 'max:150', Rule::unique('categories', 'name')->ignore($id)],
            'slug' => ['required', 'string', 'max:150', Rule::unique('categories', 'slug')->ignore($id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}