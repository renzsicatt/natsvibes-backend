<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * Display a listing of user reports.
     */
    public function index(): JsonResponse
    {
        $reports = Report::with(['reporter', 'reportedUser', 'reportedHangout'])->get();
        
        $formatted = $reports->map(function ($report) {
            return [
                'id' => $report->id,
                'reporter' => $report->reporter ? $report->reporter->name : 'Unknown User',
                'reported_user' => $report->reportedUser ? $report->reportedUser->name : 'N/A',
                'reason' => $report->reason,
                'details' => $report->details ?? '',
                'hangout_title' => $report->reportedHangout ? $report->reportedHangout->title : 'N/A',
                'status' => $report->status,
                'created_at' => $report->created_at->diffForHumans()
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Update the status of a user report (resolved, dismissed).
     */
    public function update(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,resolved,dismissed'
        ]);

        $report->update([
            'status' => $validated['status']
        ]);

        return response()->json([
            'id' => $report->id,
            'status' => $report->status,
            'message' => 'Report status updated successfully.'
        ]);
    }
}
