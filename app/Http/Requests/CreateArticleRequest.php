<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:500',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'content' => [
                'nullable',
                'string',
            ],
            'author' => [
                'nullable',
                'string',
                'max:255',
            ],
            'url' => [
                'required',
                'url',
                'unique:articles,url',
                'max:2048',
            ],
            'source' => [
                'nullable',
                'string',
                'max:100',
            ],
            'image_url' => [
                'nullable',
                'url',
                'max:2048',
            ],
            'category' => [
                'nullable',
                'string',
                'max:50',
                'exists:categories,slug',
            ],
            'language' => [
                'nullable',
                'string',
                'size:2',
            ],
            'country' => [
                'nullable',
                'string',
                'size:2',
            ],
            'published_at' => [
                'required',
                'date',
            ],
            'is_active' => [
                'boolean',
            ],
            'is_featured' => [
                'boolean',
            ],
            'sentiment_score' => [
                'nullable',
                'numeric',
                'between:-1,1',
            ],
            'tags' => [
                'nullable',
                'array',
            ],
            'tags.*' => [
                'string',
                'max:100',
            ],
            'keywords' => [
                'nullable',
                'array',
            ],
            'keywords.*' => [
                'string',
                'max:100',
            ],
            'slug' => [
                'nullable',
                'string',
                'max:500',
                'unique:articles,slug',
            ],
            'meta_description' => [
                'nullable',
                'string',
                'max:160',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'The article title is required.',
            'title.max' => 'The title cannot exceed 500 characters.',
            'url.required' => 'The article URL is required.',
            'url.unique' => 'An article with this URL already exists.',
            'url.url' => 'Please provide a valid URL.',
            'category.exists' => 'The selected category does not exist.',
            'language.size' => 'Language code must be exactly 2 characters.',
            'country.size' => 'Country code must be exactly 2 characters.',
            'published_at.required' => 'Publication date is required.',
            'published_at.date' => 'Please provide a valid publication date.',
            'sentiment_score.between' => 'Sentiment score must be between -1 and 1.',
            'meta_description.max' => 'Meta description cannot exceed 160 characters.',
        ];
    }
}