<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\EpicComment;
use Illuminate\Http\JsonResponse;
use App\Queries\Epics\EpicListQuery;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\EpicCommentRequest;

class EpicCommentController extends Controller
{
    public function show(Epic $epic): JsonResponse
    {
        $this->authorize('view', $epic);

        $comments = $epic->comments()
            ->with('user:id,name')
            ->latest()
            ->latest('id')
            ->limit(EpicListQuery::RECENT_COMMENTS_LIMIT)
            ->get()
            ->map(function (EpicComment $comment): array {
                $author = $comment->user;

                return [
                    'id' => $comment->id,
                    'author' => $author === null ? __('Deleted user') : $author->name,
                    'dateTime' => $comment->created_at?->toIso8601String(),
                    'body' => $comment->body,
                ];
            })
            ->values();

        return response()->json(['comments' => $comments]);
    }

    public function store(EpicCommentRequest $request, Epic $epic): RedirectResponse
    {
        $comment = $epic->comments()->make($request->validated());
        $comment->user()->associate($request->user());
        $comment->save();

        return back(fallback: route('epics.index'))
            ->with('status', __('Comment added successfully.'))
            ->with('commented_epic_id', $epic->id);
    }
}
