<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportEvidence extends Model
{
    protected $table = 'report_evidence';

    protected $fillable = ['disk', 'path', 'mime_type', 'size', 'review_status'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
