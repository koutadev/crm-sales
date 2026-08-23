<?php

namespace App\Models;

use App\Enums\ActivityType;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 活動履歴(電話 / 訪問 / メール / メモ)。
 *
 * 顧客には必ず紐づき、商談への紐付けは任意。
 * ※ 操作ログ(activity_logs / App\Models\ActivityLog)とは別物。
 *   こちらは営業活動の記録、あちらはシステム操作の監査ログ。
 *
 * @property int $id
 * @property int $partner_id
 * @property int|null $deal_id
 * @property int $employee_id
 * @property ActivityType $type
 * @property Carbon $activity_at
 * @property string|null $note
 * @property-read Partner|null $partner
 * @property-read Deal|null $deal
 * @property-read Employee|null $employee
 */
class Activity extends BaseModel
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'deal_id',
        'employee_id',
        'type',
        'activity_at',
        'note',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'activity_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * 紐づく商談(任意)。
     *
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * 実施者(自社の社員)。
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function activityLogLabel(): ?string
    {
        return $this->type->label().' '.$this->activity_at->format('Y/m/d H:i');
    }
}
