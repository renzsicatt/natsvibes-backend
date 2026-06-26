<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reported_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reported_hangout_id' => ['nullable', 'integer', 'exists:hangouts,id'],
            'reported_venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'reported_message_id' => ['nullable', 'integer', 'exists:group_messages,id'],
            'reason' => ['required', Rule::in(['harassment', 'unsafe_behavior', 'fake_profile', 'scam', 'no_show', 'venue_issue', 'inappropriate_message', 'other'])],
            'details' => ['required', 'string', 'max:5000'],
        ]);
        abort_unless(collect($validated)->only(['reported_user_id', 'reported_hangout_id', 'reported_venue_id', 'reported_message_id'])->filter()->isNotEmpty(), 422, 'A report target is required.');
        $severity = in_array($validated['reason'], ['unsafe_behavior', 'harassment'], true) ? 'high' : 'medium';
        $report = Report::create([...$validated, 'reporter_id' => $request->user()->id, 'status' => 'new', 'severity' => $severity]);

        return response()->json(['data' => $report], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => Report::where('reporter_id', $request->user()->id)->latest()->cursorPaginate(20)]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Report::with(['reporter', 'reportedUser', 'reportedHangout', 'reportedVenue'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        return response()->json(['data' => $query->cursorPaginate(25)]);
    }

    public function update(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'triaged', 'investigating', 'action_taken', 'dismissed', 'resolved', 'appealed'])],
            'severity' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($request, $report, $validated): void {
            $before = $report->toArray();
            $report->update([...$validated, 'assigned_admin_id' => $request->user()->id, 'resolved_at' => in_array($validated['status'], ['resolved', 'dismissed'], true) ? now() : null]);
            DB::table('admin_actions')->insert([
                'admin_id' => $request->user()->id, 'action_type' => 'report_updated',
                'details' => json_encode(['before' => $before, 'after' => $report->fresh()->toArray()]),
                'target_type' => Report::class, 'target_id' => $report->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return response()->json(['data' => $report->fresh()]);
    }
}
