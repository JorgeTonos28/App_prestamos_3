<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoanApplication extends Model
{
    use HasFactory;

    public const STATUS_COLLECTING_DATA = 'collecting_data';

    public const STATUS_COLLECTING_DOCUMENTS = 'collecting_documents';

    public const STATUS_READY_FOR_ANALYSIS = 'ready_for_analysis';

    public const STATUS_ANALYZING = 'analyzing';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ERROR = 'error';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (LoanApplication $application): void {
            $application->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'whatsapp_profile_name' => 'encrypted',
            'applicant_data' => 'encrypted:array',
            'loan_request' => 'encrypted:array',
            'required_documents' => 'array',
            'review_notes' => 'encrypted',
            'risk_score' => 'decimal:2',
            'consent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'decision_notified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_COLLECTING_DATA,
            self::STATUS_COLLECTING_DOCUMENTS,
            self::STATUS_READY_FOR_ANALYSIS,
            self::STATUS_ANALYZING,
            self::STATUS_PENDING_REVIEW,
        ]);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function conversation()
    {
        return $this->hasOne(WhatsAppConversation::class);
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function documents()
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    public function riskAssessments()
    {
        return $this->hasMany(RiskAssessment::class);
    }

    public function latestRiskAssessment()
    {
        return $this->hasOne(RiskAssessment::class)->latestOfMany('version');
    }

    public function events()
    {
        return $this->hasMany(LoanApplicationEvent::class);
    }
}
