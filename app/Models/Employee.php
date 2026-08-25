<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Models\Concerns\HasSequentialCode;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 社員マスタ。
 *
 * 勤怠・CRM の担当者など、今後の各システムから参照される中核マスタ。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $organization_id
 * @property string|null $email
 * @property EmploymentStatus $employment_status
 * @property int|null $user_id
 * @property-read Department|null $department
 * @property-read Position|null $position
 * @property-read Organization|null $organization
 * @property-read User|null $user
 */
class Employee extends BaseModel
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    use HasSequentialCode;

    protected $fillable = [
        'name',
        'department_id',
        'position_id',
        'organization_id',
        'email',
        'employment_status',
        'user_id',
        'is_active',
    ];

    public static function codePrefix(): string
    {
        return 'EMP';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * 所属する組織(店舗)。売上などの集計はここを経由してたどる。
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * 紐付いたログインユーザー(任意)。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 営業担当として持っている商談(CRM)。
     *
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * 実施した活動履歴(CRM)。
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * 操作ログの見出し(コード + 氏名)。
     */
    public function activityLogLabel(): ?string
    {
        return $this->code.' '.$this->name;
    }
}
