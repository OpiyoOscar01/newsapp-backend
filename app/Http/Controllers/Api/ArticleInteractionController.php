<?php
// app/Http/Controllers/Api/ArticleInteractionController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Article;
use App\Models\ArticleInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ArticleInteractionController extends Controller
{
    use ApiResponseTrait;

    /**
     * Record an article view
     */
    public function recordView(Request $request, Article $article)
    {
        $validator = Validator::make($request->all(), [
            'referrer' => 'nullable|string|max:500',
            'time_spent' => 'nullable|integer|min:0',
            'scroll_depth' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $sessionKey = "article_view_{$article->id}_" . (session()->getId() ?? request()->ip());
            
            // Rate limit: only record one view per session per hour
            if (!cache()->has($sessionKey)) {
                $interaction = ArticleInteraction::create([
                    'article_id' => $article->id,
                    'user_id' => Auth::id(),
                    'interaction_type' => 'view',
                    'session_id' => session()->getId(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'referrer' => $request->referrer,
                    'metadata' => [
                        'time_spent' => $request->time_spent,
                        'scroll_depth' => $request->scroll_depth,
                    ],
                    'interaction_date' => now(),
                ]);

                // Increment article view count
                $article->increment('view_count');
                
                cache()->put($sessionKey, true, now()->addHour());
            }

            return $this->successResponse(null, 'View recorded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record view', 500, $e->getMessage());
        }
    }

    /**
     * Toggle like on an article
     */
    public function toggleLike(Request $request, Article $article)
    {
        try {
            $userId = Auth::id();
            $sessionId = session()->getId();
            
            $existingLike = ArticleInteraction::where('article_id', $article->id)
                ->where('interaction_type', 'like')
                ->where(function ($query) use ($userId, $sessionId) {
                    if ($userId) {
                        $query->where('user_id', $userId);
                    } else {
                        $query->where('session_id', $sessionId);
                    }
                })
                ->first();

            if ($existingLike) {
                $existingLike->delete();
                $liked = false;
            } else {
                ArticleInteraction::create([
                    'article_id' => $article->id,
                    'user_id' => $userId,
                    'interaction_type' => 'like',
                    'session_id' => $sessionId,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'interaction_date' => now(),
                ]);
                $liked = true;
            }

            $likeCount = ArticleInteraction::where('article_id', $article->id)
                ->where('interaction_type', 'like')
                ->count();

            return $this->successResponse([
                'liked' => $liked,
                'like_count' => $likeCount,
            ], $liked ? 'Article liked' : 'Like removed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle like', 500, $e->getMessage());
        }
    }

    /**
     * Record a share
     */
    public function recordShare(Request $request, Article $article)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            ArticleInteraction::create([
                'article_id' => $article->id,
                'user_id' => Auth::id(),
                'interaction_type' => 'share',
                'session_id' => session()->getId(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['platform' => $request->platform],
                'interaction_date' => now(),
            ]);

            $shareCount = ArticleInteraction::where('article_id', $article->id)
                ->where('interaction_type', 'share')
                ->count();

            return $this->successResponse([
                'share_count' => $shareCount,
            ], 'Share recorded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record share', 500, $e->getMessage());
        }
    }

    /**
     * Toggle bookmark on an article
     */
    public function toggleBookmark(Request $request, Article $article)
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return $this->errorResponse('Please login to bookmark articles', 401);
            }

            $existingBookmark = ArticleInteraction::where('article_id', $article->id)
                ->where('user_id', $userId)
                ->where('interaction_type', 'bookmark')
                ->first();

            if ($existingBookmark) {
                $existingBookmark->delete();
                $bookmarked = false;
            } else {
                ArticleInteraction::create([
                    'article_id' => $article->id,
                    'user_id' => $userId,
                    'interaction_type' => 'bookmark',
                    'session_id' => session()->getId(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'interaction_date' => now(),
                ]);
                $bookmarked = true;
            }

            $bookmarkCount = ArticleInteraction::where('article_id', $article->id)
                ->where('interaction_type', 'bookmark')
                ->count();

            return $this->successResponse([
                'bookmarked' => $bookmarked,
                'bookmark_count' => $bookmarkCount,
            ], $bookmarked ? 'Article bookmarked' : 'Bookmark removed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle bookmark', 500, $e->getMessage());
        }
    }

    /**
     * Add a comment to an article
     */
    public function addComment(Request $request, Article $article)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|min:1|max:5000',
            'parent_comment_id' => 'nullable|exists:article_interactions,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $userId = Auth::id();
            $sessionId = session()->getId();

            $comment = ArticleInteraction::create([
                'article_id' => $article->id,
                'user_id' => $userId,
                'interaction_type' => 'comment',
                'comment_content' => $request->comment,
                'parent_comment_id' => $request->parent_comment_id,
                'session_id' => $userId ? null : $sessionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'interaction_date' => now(),
            ]);

            $comment->load('user');

            return $this->successResponse([
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->comment_content,
                    'user' => $comment->user ? [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name ?? $comment->user->first_name . ' ' . $comment->user->last_name,
                        'email' => $comment->user->email,
                        'avatar' => null, // Add avatar URL if available
                    ] : [
                        'name' => 'Anonymous',
                        'avatar' => null,
                    ],
                    'created_at' => $comment->created_at->toISOString(),
                    'is_edited' => false,
                    'reply_count' => 0,
                ],
            ], 'Comment added successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to add comment', 500, $e->getMessage());
        }
    }

    /**
     * Update a comment
     */
    public function updateComment(Request $request, ArticleInteraction $comment)
    {
        if ($comment->interaction_type !== 'comment') {
            return $this->errorResponse('Invalid interaction type', 400);
        }

        $userId = Auth::id();
        if ($comment->user_id !== $userId) {
            return $this->errorResponse('You can only edit your own comments', 403);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|min:1|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $comment->update([
                'comment_content' => $request->comment,
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            return $this->successResponse([
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->comment_content,
                    'is_edited' => true,
                    'edited_at' => $comment->edited_at->toISOString(),
                ],
            ], 'Comment updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update comment', 500, $e->getMessage());
        }
    }

    /**
     * Delete a comment
     */
    public function deleteComment(ArticleInteraction $comment)
    {
        if ($comment->interaction_type !== 'comment') {
            return $this->errorResponse('Invalid interaction type', 400);
        }

        $userId = Auth::id();
        $isAdmin = Auth::user()?->hasRole('admin') ?? false;
        
        if ($comment->user_id !== $userId && !$isAdmin) {
            return $this->errorResponse('You can only delete your own comments', 403);
        }

        try {
            $comment->delete();

            return $this->successResponse(null, 'Comment deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete comment', 500, $e->getMessage());
        }
    }

    /**
     * Get comments for an article
     */
    public function getComments(Request $request, Article $article)
    {
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        $comments = ArticleInteraction::where('article_id', $article->id)
            ->where('interaction_type', 'comment')
            ->whereNull('parent_comment_id')
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $formattedComments = $comments->through(function ($comment) {
            return [
                'id' => $comment->id,
                'content' => $comment->comment_content,
                'user' => $comment->user ? [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name ?? $comment->user->first_name . ' ' . $comment->user->last_name,
                    'email' => $comment->user->email,
                    'avatar' => null,
                ] : [
                    'name' => 'Anonymous',
                    'avatar' => null,
                ],
                'created_at' => $comment->created_at->toISOString(),
                'is_edited' => $comment->is_edited,
                'edited_at' => $comment->edited_at?->toISOString(),
                'replies' => $comment->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'content' => $reply->comment_content,
                        'user' => $reply->user ? [
                            'id' => $reply->user->id,
                            'name' => $reply->user->name ?? $reply->user->first_name . ' ' . $reply->user->last_name,
                            'email' => $reply->user->email,
                            'avatar' => null,
                        ] : [
                            'name' => 'Anonymous',
                            'avatar' => null,
                        ],
                        'created_at' => $reply->created_at->toISOString(),
                        'is_edited' => $reply->is_edited,
                        'edited_at' => $reply->edited_at?->toISOString(),
                    ];
                }),
                'reply_count' => $comment->replies->count(),
            ];
        });

        return $this->successResponse([
            'comments' => $formattedComments,
            'pagination' => [
                'current_page' => $comments->currentPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
                'last_page' => $comments->lastPage(),
            ],
        ], 'Comments retrieved successfully');
    }

    /**
     * Get user's liked articles
     */
    public function getUserLikes(Request $request)
    {
        $userId = Auth::id();
        
        if (!$userId) {
            return $this->errorResponse('Please login to view your likes', 401);
        }

        $perPage = $request->get('per_page', 20);
        
        $likes = ArticleInteraction::where('user_id', $userId)
            ->where('interaction_type', 'like')
            ->with('article')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->successResponse($likes, 'User likes retrieved successfully');
    }

    /**
     * Get user's bookmarked articles
     */
    public function getUserBookmarks(Request $request)
    {
        $userId = Auth::id();
        
        if (!$userId) {
            return $this->errorResponse('Please login to view your bookmarks', 401);
        }

        $perPage = $request->get('per_page', 20);
        
        $bookmarks = ArticleInteraction::where('user_id', $userId)
            ->where('interaction_type', 'bookmark')
            ->with('article')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->successResponse($bookmarks, 'User bookmarks retrieved successfully');
    }

    /**
     * Get interaction counts for an article
     */
    public function getInteractionCounts(Article $article)
    {
        $userId = Auth::id();
        $sessionId = session()->getId();

        $counts = [
            'views' => ArticleInteraction::where('article_id', $article->id)
                ->where('interaction_type', 'view')
                ->count(),
            'likes' => ArticleInteraction::where('article_id', $article->id)
                ->where('interaction_type', 'like')
                ->count(),
            'shares' => ArticleInteraction::where('article_id', $article->id)
                ->where('interaction_type', 'share')
                ->count(),
            'bookmarks' => ArticleInteraction::where('article_id', $article->id)
                ->where('interaction_type', 'bookmark')
                ->count(),
            'comments' => ArticleInteraction::where('article_id', $article->id)
                ->where('interaction_type', 'comment')
                ->count(),
        ];

        // Check if current user has interacted
        $userInteractions = [];
        
        if ($userId) {
            $userLiked = ArticleInteraction::where('article_id', $article->id)
                ->where('user_id', $userId)
                ->where('interaction_type', 'like')
                ->exists();
                
            $userBookmarked = ArticleInteraction::where('article_id', $article->id)
                ->where('user_id', $userId)
                ->where('interaction_type', 'bookmark')
                ->exists();
                
            $userInteractions = [
                'liked' => $userLiked,
                'bookmarked' => $userBookmarked,
            ];
        } else {
            $userLiked = ArticleInteraction::where('article_id', $article->id)
                ->where('session_id', $sessionId)
                ->where('interaction_type', 'like')
                ->exists();
                
            $userInteractions = [
                'liked' => $userLiked,
                'bookmarked' => false,
            ];
        }

        return $this->successResponse([
            'counts' => $counts,
            'user_interactions' => $userInteractions,
        ], 'Interaction counts retrieved successfully');
    }
}