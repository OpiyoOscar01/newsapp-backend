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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ArticleInteractionController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get article by ID helper
     */
    private function findArticle($id)
    {
        $article = Article::find($id);
        if (!$article) {
            return null;
        }
        return $article;
    }

    /**
     * Record an article view
     */
    public function recordView(Request $request, $articleId)
    {
        $article = $this->findArticle($articleId);
        if (!$article) {
            return $this->errorResponse('Article not found', 404);
        }

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
                ArticleInteraction::create([
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
            Log::error('Failed to record view: ' . $e->getMessage());
            return $this->errorResponse('Failed to record view', 500, $e->getMessage());
        }
    }

    /**
     * Toggle like on an article
     */
    public function toggleLike(Request $request, $articleId)
    {
        $article = $this->findArticle($articleId);
        if (!$article) {
            return $this->errorResponse('Article not found', 404);
        }

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
            Log::error('Failed to toggle like: ' . $e->getMessage());
            return $this->errorResponse('Failed to toggle like', 500, $e->getMessage());
        }
    }

    /**
     * Record a share
     */
    public function recordShare(Request $request, $articleId)
    {
        $article = $this->findArticle($articleId);
        if (!$article) {
            return $this->errorResponse('Article not found', 404);
        }

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
            Log::error('Failed to record share: ' . $e->getMessage());
            return $this->errorResponse('Failed to record share', 500, $e->getMessage());
        }
    }

    /**
     * Toggle bookmark on an article
     */
    public function toggleBookmark(Request $request, $articleId)
    {
        $article = $this->findArticle($articleId);
        if (!$article) {
            return $this->errorResponse('Article not found', 404);
        }

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
            Log::error('Failed to toggle bookmark: ' . $e->getMessage());
            return $this->errorResponse('Failed to toggle bookmark', 500, $e->getMessage());
        }
    }

    /**
     * Add a comment to an article
     */

    public function addComment(Request $request, $articleId)
    {
        // DEBUG: Log request start
        Log::info('[ADD COMMENT] Request started', [
            'article_id' => $articleId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_comment' => $request->has('comment'),
            'comment_preview' => $request->has('comment') ? substr($request->comment, 0, 50) : null,
            'parent_comment_id' => $request->parent_comment_id,
        ]);

        $article = $this->findArticle($articleId);
        if (!$article) {
            Log::warning('[ADD COMMENT] Article not found', ['article_id' => $articleId]);
            return $this->errorResponse('Article not found', 404);
        }

        Log::info('[ADD COMMENT] Article found', [
            'article_id' => $article->id,
            'article_title' => $article->title,
        ]);

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|min:1|max:5000',
            'parent_comment_id' => 'nullable|exists:article_interactions,id',
        ]);

        if ($validator->fails()) {
            Log::warning('[ADD COMMENT] Validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            // DEBUG: Authentication check
            Log::info('[ADD COMMENT] Authentication check', [
                'auth_check' => Auth::check(),
                'auth_id' => Auth::id(),
                'bearer_token' => $request->bearerToken(),
                'has_bearer_token' => !empty($request->bearerToken()),
                'authorization_header' => $request->header('Authorization') ? 'present' : 'missing',
                'sanctum_check' => Auth::guard('sanctum')->check(),
                'sanctum_id' => Auth::guard('sanctum')->id(),
            ]);

            $userId = Auth::id();
            $sessionId = session()->getId();
            $authUser = Auth::user();

            Log::info('[ADD COMMENT] User data', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'has_auth_user' => !is_null($authUser),
                'auth_user_email' => $authUser?->email,
                'auth_user_name' => $authUser?->first_name . ' ' . $authUser?->last_name,
            ]);

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

            Log::info('[ADD COMMENT] Comment created', [
                'comment_id' => $comment->id,
                'user_id' => $comment->user_id,
                'article_id' => $comment->article_id,
            ]);

            // Load the user relationship
            $comment->load('user');

            Log::info('[ADD COMMENT] User relationship loaded', [
                'has_user_relation' => !is_null($comment->user),
                'comment_user_id' => $comment->user?->id,
                'comment_user_email' => $comment->user?->email,
            ]);

            // Format user data from the authenticated user
            $userData = [];
            
            if ($authUser) {
                // Get the user's display name
                $displayName = '';
                if ($authUser->first_name) {
                    $displayName = trim($authUser->first_name . ' ' . ($authUser->last_name ?? ''));
                } elseif ($authUser->name) {
                    $displayName = $authUser->name;
                } else {
                    $displayName = $authUser->email;
                }
                
                $userData = [
                    'id' => $authUser->id,
                    'name' => $displayName,
                    'email' => $authUser->email,
                    'avatar' => null,
                ];
                
                Log::info('[ADD COMMENT] User data formatted for authenticated user', [
                    'user_id' => $authUser->id,
                    'display_name' => $displayName,
                ]);
            } else {
                $userData = [
                    'id' => null,
                    'name' => 'Anonymous',
                    'email' => null,
                    'avatar' => null,
                ];
                
                Log::warning('[ADD COMMENT] No authenticated user, using anonymous');
            }

            $response = [
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->comment_content,
                    'user' => $userData,
                    'created_at' => $comment->created_at->toISOString(),
                    'is_edited' => false,
                    'reply_count' => 0,
                    'replies' => [],
                ],
            ];

            Log::info('[ADD COMMENT] Success response prepared', [
                'comment_id' => $comment->id,
                'response_user_name' => $response['comment']['user']['name'],
            ]);

            return $this->successResponse($response, 'Comment added successfully');
            
        } catch (\Exception $e) {
            Log::error('[ADD COMMENT] Exception occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->errorResponse('Failed to add comment', 500, $e->getMessage());
        }
    }
    /**
     * Update a comment
     */
    public function updateComment(Request $request, $commentId)
    {
        $comment = ArticleInteraction::find($commentId);
        
        if (!$comment) {
            return $this->errorResponse('Comment not found', 404);
        }

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
            Log::error('Failed to update comment: ' . $e->getMessage());
            return $this->errorResponse('Failed to update comment', 500, $e->getMessage());
        }
    }

    /**
     * Delete a comment
     */
    public function deleteComment($commentId)
    {
        $comment = ArticleInteraction::find($commentId);
        
        if (!$comment) {
            return $this->errorResponse('Comment not found', 404);
        }

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
            Log::error('Failed to delete comment: ' . $e->getMessage());
            return $this->errorResponse('Failed to delete comment', 500, $e->getMessage());
        }
    }

    /**
     * Get comments for an article
     */
 

/**
 * Get comments for an article
 */
public function getComments(Request $request, $articleId)
{
    // DEBUG: Log request start
    Log::info('[GET COMMENTS] Request started', [
        'article_id' => $articleId,
        'page' => $request->get('page', 1),
        'per_page' => $request->get('per_page', 20),
    ]);

    $article = $this->findArticle($articleId);
    if (!$article) {
        Log::warning('[GET COMMENTS] Article not found', ['article_id' => $articleId]);
        return $this->errorResponse('Article not found', 404);
    }

    // DEBUG: Authentication status for this request
    Log::info('[GET COMMENTS] Authentication check', [
        'auth_check' => Auth::check(),
        'auth_id' => Auth::id(),
        'bearer_token' => $request->bearerToken(),
        'has_bearer_token' => !empty($request->bearerToken()),
        'authorization_header' => $request->header('Authorization') ? 'present' : 'missing',
        'sanctum_check' => Auth::guard('sanctum')->check(),
        'sanctum_id' => Auth::guard('sanctum')->id(),
    ]);

    $perPage = $request->get('per_page', 20);
    $page = $request->get('page', 1);

    $comments = ArticleInteraction::where('article_id', $article->id)
        ->where('interaction_type', 'comment')
        ->whereNull('parent_comment_id')
        ->with(['user', 'replies.user'])
        ->orderBy('created_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);

    Log::info('[GET COMMENTS] Comments retrieved', [
        'total_comments' => $comments->total(),
        'current_page' => $comments->currentPage(),
        'per_page' => $comments->perPage(),
        'items_count' => $comments->count(),
    ]);

    // Get authenticated user if any (but don't require it)
    $currentUserId = null;
    if (Auth::guard('sanctum')->check()) {
        $currentUserId = Auth::id();
        Log::info('[GET COMMENTS] Authenticated user found', ['user_id' => $currentUserId]);
    } else {
        Log::info('[GET COMMENTS] No authenticated user for this request');
    }

    $formattedComments = collect($comments->items())->map(function ($comment) use ($currentUserId) {
        // Format main comment user data
        $userData = [];
        if ($comment->user) {
            $displayName = '';
            if ($comment->user->first_name) {
                $displayName = trim($comment->user->first_name . ' ' . ($comment->user->last_name ?? ''));
            } elseif ($comment->user->name) {
                $displayName = $comment->user->name;
            } else {
                $displayName = $comment->user->email;
            }
            
            $userData = [
                'id' => $comment->user->id,
                'name' => $displayName,
                'email' => $comment->user->email,
                'avatar' => null,
            ];
        } else {
            $userData = [
                'id' => null,
                'name' => 'Anonymous',
                'email' => null,
                'avatar' => null,
            ];
        }

        $isOwner = $currentUserId && $comment->user_id === $currentUserId;

        return [
            'id' => $comment->id,
            'content' => $comment->comment_content,
            'user' => $userData,
            'is_owner' => $isOwner,
            'created_at' => $comment->created_at->toISOString(),
            'is_edited' => $comment->is_edited,
            'edited_at' => $comment->edited_at?->toISOString(),
            'replies' => $comment->replies->map(function ($reply) use ($currentUserId) {
                $replyUserData = [];
                if ($reply->user) {
                    $replyDisplayName = '';
                    if ($reply->user->first_name) {
                        $replyDisplayName = trim($reply->user->first_name . ' ' . ($reply->user->last_name ?? ''));
                    } elseif ($reply->user->name) {
                        $replyDisplayName = $reply->user->name;
                    } else {
                        $replyDisplayName = $reply->user->email;
                    }
                    
                    $replyUserData = [
                        'id' => $reply->user->id,
                        'name' => $replyDisplayName,
                        'email' => $reply->user->email,
                        'avatar' => null,
                    ];
                } else {
                    $replyUserData = [
                        'id' => null,
                        'name' => 'Anonymous',
                        'email' => null,
                        'avatar' => null,
                    ];
                }
                
                $isReplyOwner = $currentUserId && $reply->user_id === $currentUserId;
                
                return [
                    'id' => $reply->id,
                    'content' => $reply->comment_content,
                    'user' => $replyUserData,
                    'is_owner' => $isReplyOwner,
                    'created_at' => $reply->created_at->toISOString(),
                    'is_edited' => $reply->is_edited,
                    'edited_at' => $reply->edited_at?->toISOString(),
                ];
            }),
            'reply_count' => $comment->replies->count(),
        ];
    })->toArray();

    Log::info('[GET COMMENTS] Response prepared', [
        'formatted_comments_count' => count($formattedComments),
        'has_owner_flags' => !is_null($currentUserId),
    ]);

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
    public function getInteractionCounts($articleId)
    {
        $article = $this->findArticle($articleId);
        if (!$article) {
            return $this->errorResponse('Article not found', 404);
        }

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