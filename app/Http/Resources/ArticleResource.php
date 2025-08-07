<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->when($request->routeIs('articles.show'), $this->content),
            'author' => $this->author,
            'url' => $this->url,
            'source' => $this->source,
            'image_url' => $this->image_url,
            'category' => $this->category,
            'language' => $this->language,
            'country' => $this->country,
            'published_at' => $this->published_at?->toISOString(),
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'view_count' => $this->view_count,
            'sentiment_score' => $this->sentiment_score,
            'tags' => $this->tags,
            'keywords' => $this->keywords,
            'slug' => $this->slug,
            'meta_description' => $this->meta_description,
            'reading_time' => $this->reading_time,
            'excerpt' => $this->excerpt,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Include relationships when loaded
            'category_model' => new CategoryResource($this->whenLoaded('categoryModel')),
            'source_model' => new SourceResource($this->whenLoaded('sourceModel')),
            'article_keywords' => ArticleKeywordResource::collection($this->whenLoaded('articleKeywords')),
        ];
    }
}