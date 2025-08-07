<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Handle authorization in controller or policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->route('category')->id ?? null;

        return [
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('categories', 'slug')->ignore($categoryId),
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', // Valid slug format
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => 'The category slug is required.',
            'slug.unique' => 'A category with this slug already exists.',
            'slug.regex' => 'The slug must be in valid format (lowercase letters, numbers, and hyphens only).',
            'name.required' => 'The category name is required.',
            'name.max' => 'The category name cannot exceed 100 characters.',
            'description.max' => 'The description cannot exceed 1000 characters.',
        ];
    }
}