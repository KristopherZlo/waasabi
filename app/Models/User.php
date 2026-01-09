<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\SupportTicket;
use App\Models\UserBadge;
use App\Models\UserNotification;
use App\Models\UserReportProfile;
use App\Services\BadgeCatalogService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'password',
        'role',
        'avatar',
        'banner_url',
        'bio',
        'privacy_share_activity',
        'privacy_allow_mentions',
        'privacy_personalized_recommendations',
        'notify_comments',
        'notify_reviews',
        'notify_follows',
        'connections_allow_follow',
        'connections_show_follow_counts',
        'security_login_alerts',
        'is_banned',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'privacy_share_activity' => 'boolean',
            'privacy_allow_mentions' => 'boolean',
            'privacy_personalized_recommendations' => 'boolean',
            'notify_comments' => 'boolean',
            'notify_reviews' => 'boolean',
            'notify_follows' => 'boolean',
            'connections_allow_follow' => 'boolean',
            'connections_show_follow_counts' => 'boolean',
            'security_login_alerts' => 'boolean',
            'is_banned' => 'boolean',
        ];
    }

    public function postComments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function postReviews()
    {
        return $this->hasMany(PostReview::class);
    }

    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function reportProfile(): HasOne
    {
        return $this->hasOne(UserReportProfile::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'user_follows', 'following_id', 'follower_id')->withTimestamps();
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'following_id')->withTimestamps();
    }

    public function roleKey(): string
    {
        $role = strtolower((string) ($this->role ?? 'user'));
        $order = $this->roleOrder();
        return in_array($role, $order, true) ? $role : 'user';
    }

    public function roleRank(): int
    {
        $order = $this->roleOrder();
        $index = array_search($this->roleKey(), $order, true);
        return $index === false ? 0 : (int) $index;
    }

    public function hasRole(string $role): bool
    {
        $target = strtolower($role);
        $order = $this->roleOrder();
        $targetIndex = array_search($target, $order, true);
        if ($targetIndex === false) {
            return false;
        }
        return $this->roleRank() >= (int) $targetIndex;
    }

    public function canPerform(string $ability): bool
    {
        $ability = strtolower(trim($ability));
        if ($ability === '') {
            return false;
        }

        $order = $this->roleOrder();
        $abilities = (array) config('roles.abilities', []);
        $roleRank = $this->roleRank();
        $granted = [];

        foreach ($order as $index => $role) {
            if ($index > $roleRank) {
                break;
            }
            $roleAbilities = $abilities[$role] ?? [];
            foreach ($roleAbilities as $item) {
                $item = strtolower(trim((string) $item));
                if ($item !== '') {
                    $granted[$item] = true;
                }
            }
        }

        return isset($granted[$ability]);
    }

    public function isAdmin(): bool
    {
        return $this->roleKey() === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->hasRole('moderator');
    }

    public function sendNotification(string $type, string $text, ?string $link = null): ?UserNotification
    {
        if (!$this->safeHasTable('user_notifications')) {
            return null;
        }

        return $this->notifications()->create([
            'type' => $type,
            'text' => $text,
            'link' => $link,
        ]);
    }

    public function grantBadge(string $badgeKey, array $attributes = [], bool $notify = true): UserBadge
    {
        if (!$this->safeHasTable('user_badges')) {
            throw new \RuntimeException('Badge storage unavailable.');
        }

        $catalog = $attributes['catalog'] ?? app(BadgeCatalogService::class)->find($badgeKey);
        if (!$catalog) {
            throw new \InvalidArgumentException('Unknown badge.');
        }

        $issuedAt = $attributes['issued_at'] ?? now();
        $issuedBy = $attributes['issued_by'] ?? null;
        if ($issuedBy instanceof self) {
            $issuedBy = $issuedBy->id;
        }

        $badge = $this->badges()->create([
            'badge_key' => $badgeKey,
            'badge_name' => $attributes['name'] ?? null,
            'badge_description' => $attributes['description'] ?? null,
            'reason' => $attributes['reason'] ?? null,
            'issued_by' => $issuedBy,
            'issued_at' => $issuedAt,
        ]);

        if ($notify) {
            $badgeName = trim((string) ($attributes['name'] ?? ''));
            if ($badgeName === '') {
                $badgeName = (string) ($catalog['name'] ?? $badgeKey);
            }
            $link = $attributes['link'] ?? null;
            $this->sendNotification('Badge', 'You received the badge "' . $badgeName . '".', $link);
        }

        return $badge;
    }

    public function revokeBadge(int|UserBadge $badge): bool
    {
        if (!$this->safeHasTable('user_badges')) {
            return false;
        }

        $badgeId = $badge instanceof UserBadge ? $badge->id : $badge;
        if (!$badgeId) {
            return false;
        }

        return $this->badges()->where('id', $badgeId)->delete() > 0;
    }

    private function roleOrder(): array
    {
        $order = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
        return array_values(array_filter(array_map('strval', (array) $order)));
    }

    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
