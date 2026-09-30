<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\EpicCommentRequest;

class EpicCommentController extends Controller
{
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
