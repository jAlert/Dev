<?php

namespace App\Http\Controllers;

use App\Models\Record;
use App\Models\WorkflowStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TextEditorController extends Controller
{
    /**
     * Validate a Sanctum token for Hocuspocus WebSocket auth.
     * Called by the Hocuspocus Node.js server to authenticate users.
     */
    public function validateToken(Request $request)
    {
        // auth:sanctum middleware handles this — just return user info
        return response()->json([
            'user' => [
                'id'    => $request->user()->id,
                'name'  => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }

    private function authorizeRecordAccess(Request $request, Record $record): void
    {
        $user = $request->user();
        if ($user->hasRole('super admin')) return;

        $module = $record->module;
        if (!$module) abort(403);
        $slug = $module->slug;

        if ($user->can("view-{$slug}") || $user->can("edit-{$slug}") ||
            $user->can("approve-{$slug}") || $user->can("review-{$slug}")) {
            return;
        }

        $stageRoleIds = WorkflowStage::where('module_id', $module->id)
            ->pluck('approver_role_id')->filter();
        if ($user->roles->pluck('id')->intersect($stageRoleIds)->isNotEmpty()) return;

        abort(403);
    }

    /**
     * Get input history log for a specific field on a record.
     */
    public function getHistory(Request $request, Record $record, string $fieldSlug)
    {
        $this->authorizeRecordAccess($request, $record);

        $history = DB::table('text_editor_histories')
            ->join('users', 'text_editor_histories.user_id', '=', 'users.id')
            ->where('text_editor_histories.record_id', $record->id)
            ->where('text_editor_histories.field_slug', $fieldSlug)
            ->select(
                'text_editor_histories.*',
                'users.name as user_name'
            )
            ->orderByDesc('text_editor_histories.created_at')
            ->limit(200)
            ->get();

        return response()->json($history);
    }

    /**
     * Store a history entry (called by Hocuspocus server or client).
     * Requires a shared secret header for Hocuspocus server calls.
     */
    public function storeHistory(Request $request, Record $record, string $fieldSlug)
    {
        $request->validate([
            'action'  => 'required|in:insert,delete',
            'content' => 'required|string|min:1|max:10000',
        ]);

        DB::table('text_editor_histories')->insert([
            'record_id'  => $record->id,
            'field_slug' => $fieldSlug,
            'user_id'    => $request->user()->id,
            'action'     => $request->action,
            'content'    => $request->content,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * List all unresolved inline comments for a field.
     */
    public function getComments(Request $request, Record $record, string $fieldSlug)
    {
        $this->authorizeRecordAccess($request, $record);

        $comments = DB::table('text_editor_comments')
            ->join('users', 'text_editor_comments.user_id', '=', 'users.id')
            ->where('text_editor_comments.record_id', $record->id)
            ->where('text_editor_comments.field_slug', $fieldSlug)
            ->whereNull('text_editor_comments.resolved_at')
            ->select(
                'text_editor_comments.comment_id',
                'text_editor_comments.quoted_text',
                'text_editor_comments.body',
                'text_editor_comments.created_at',
                'users.name as user_name'
            )
            ->orderBy('text_editor_comments.created_at')
            ->get();

        return response()->json($comments);
    }

    /**
     * Store a new inline comment.
     */
    public function storeComment(Request $request, Record $record, string $fieldSlug)
    {
        $request->validate([
            'comment_id'  => 'required|uuid',
            'quoted_text' => 'required|string|max:500',
            'body'        => 'required|string|max:5000',
        ]);

        DB::table('text_editor_comments')->insert([
            'record_id'   => $record->id,
            'field_slug'  => $fieldSlug,
            'comment_id'  => $request->comment_id,
            'user_id'     => $request->user()->id,
            'quoted_text' => $request->quoted_text,
            'body'        => $request->body,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'comment_id'  => $request->comment_id,
            'quoted_text' => $request->quoted_text,
            'body'        => $request->body,
            'user_name'   => $request->user()->name,
            'created_at'  => now()->toDateTimeString(),
        ], 201);
    }

    /**
     * Upload an image for use in the text editor.
     */
    public function storeImage(Request $request, Record $record, string $fieldSlug)
    {
        $this->authorizeRecordAccess($request, $record);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        $path = $request->file('image')->store('text-editor-images', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }

    /**
     * Resolve (soft-delete) an inline comment.
     */
    public function resolveComment(Request $request, Record $record, string $fieldSlug, string $commentId)
    {
        $this->authorizeRecordAccess($request, $record);

        DB::table('text_editor_comments')
            ->where('record_id', $record->id)
            ->where('field_slug', $fieldSlug)
            ->where('comment_id', $commentId)
            ->update(['resolved_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Get review status — who has and hasn't marked "Review Done" for a field.
     */
    public function getReviewStatus(Request $request, Record $record, string $fieldSlug)
    {
        $this->authorizeRecordAccess($request, $record);

        $reviews = DB::table('text_editor_reviews')
            ->join('users', 'text_editor_reviews.user_id', '=', 'users.id')
            ->where('text_editor_reviews.record_id', $record->id)
            ->where('text_editor_reviews.field_slug', $fieldSlug)
            ->select('users.name', 'text_editor_reviews.reviewed_at')
            ->get();

        return response()->json($reviews);
    }
}
