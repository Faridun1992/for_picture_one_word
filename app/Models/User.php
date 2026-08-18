<?php

namespace App\Models;

use App\Jobs\DeleteS3FileJob;
use App\Services\System\EmailService;
use App\Traits\UserSettings;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, UserSettings;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'surname',
        'email',
        'password',
        'block',
        'avatar',
        'auth_token',
        'auth_token_time',
        'last_active_at',
        'utm_id',
        'password_changed',
        'ref_code',
        'email_code',
        'email_enabled',
        'nickname',
        'change_email_token',
        'change_email_code',
        'new_email',
        'deleted',
        'data',
        'email_attempts',
        'email_resends',
        'country',
        'timezone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'block' => 'boolean',
            'auth_token_time' => 'datetime',
            'last_active_at' => 'datetime',
            'password_changed' => 'boolean',
            'email_enabled' => 'boolean',
            'deleted' =>  'boolean',
            'data' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if ($user->hasSearchablePageChanges()) {
                $user->syncPageSearchIndex();
            }
        });

        static::deleting(function ($user) {

            if ($user->avatar) {

                DeleteS3FileJob::dispatch($user->avatar);

            }
        });
    }

    public function hasSearchablePageChanges(): bool
    {
        return $this->wasChanged([
            'name',
            'surname',
            'nickname',
            'deleted',
            'block',
        ]);
    }

    public function syncPageSearchIndex(): void
    {
        $page = $this->page;

        if (! $page) {
            return;
        }

        if ($page->shouldBeSearchable()) {
            $page->searchable();

            return;
        }

        $page->unsearchable();
    }

    /**
     * @return HasOneThrough
     */
    public function role(): HasOneThrough
    {
        return $this->hasOneThrough(
            Role::class,
            ModelHasRole::class,
            'model_id',
            'id',
            'id',
            'role_id'
        )->where('model_type', self::class);
    }

    public function getFullNameAttribute(): string
    {
        $full_name = $this->name;

        if ($this->surname) {
            $full_name = $this->surname.' '.$full_name;
        }

        return $full_name ?? '';
    }

    /**
     * Получить аватар пользователя
     */
    public function getUsersAvatarAttribute()
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        return '/assets/user/img/avatar.png';
    }

    public function page(): HasOne
    {
        return $this->hasOne(UserPage::class);
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(UserPage::class, 'user_page_user')
            ->withPivot('is_confirmed')
            ->withTimestamps();
    }

    public function blockedUsers(): HasMany
    {
        return $this->hasMany(BlockedUser::class, 'blocker_id')->where('is_blocked', true);
    }

    private ?Collection $subscribedPageIdsCache = null;

    /**
     * ID страниц, на которые подписан пользователь.
     * Загружается один раз за запрос, потом из памяти.
     */
    public function subscribedPageIds(): Collection
    {
        if ($this->subscribedPageIdsCache === null) {
            $this->subscribedPageIdsCache = DB::table('user_page_user')
                ->where('user_id', $this->id)
                ->pluck('user_page_id');
        }

        return $this->subscribedPageIdsCache;
    }

    private ?Collection $bookmarkedPostIdsCache = null;

    /**
     * ID постов, которые пользователь добавил в закладки.
     * Загружается один раз за запрос, потом из памяти.
     */
    public function bookmarkedPostIds(): Collection
    {
        if ($this->bookmarkedPostIdsCache === null) {
            $this->bookmarkedPostIdsCache = DB::table('post_user')
                ->where('user_id', $this->id)
                ->pluck('post_id');
        }

        return $this->bookmarkedPostIdsCache;
    }

    /**
     * Отправка Email с восстановлением пароля
     * @throws \Exception
     */
    public function sendPasswordResetNotification($token): bool
    {

        if (!$this->email)
            return false;

        return (bool)app(EmailService::class)->sendMessage(
            $this->email,
            $this->id,
            'Восстановление пароля в ' . config('app.name'),
            'Здравствуйте!<br><br>'
            . 'Вы (или кто-то с вашим email) запросили восстановление пароля в ' . config('app.name') . '.<br><br>'
            . 'Чтобы восстановить пароль, нажмите на ссылку ниже:<br><br>'
            . 'Ссылка действительна в течение 30 минут.<br><br>'
            . 'Если вы не запрашивали восстановление пароля — просто проигнорируйте это письмо. Пароль останется без изменений.<br><br>'
            . 'Если у вас возникнут вопросы, наша служба поддержки всегда готова помочь: support@' . parse_url(config('app.url'), PHP_URL_HOST) . '<br><br>'
            . 'С уважением,<br>Команда ' . config('app.name'),
            'auth',
            config('mail.from.address'),
            config('mail.from.name'),
            route('guest.home', ['token' => $token, 'email' => $this->email]),
            'Восстановить пароль',
        );
    }


    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }


    public function postReads(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_user_read')->withTimestamps();
    }

    public function readAndPayment(): HasOne
    {
        return $this->hasOne(ReadAndPayment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->where('to', '>=', now());
    }

    public function userHasAccessToPost(Post $post): bool
    {

        if ($post->user_id === $this->id) {

            return true;

        }

        $subscription = $this->getUsersSubscriptionByPage($post->page);

        if (!$subscription) {

            return false;

        }

        if ($post->tariff->sort > $subscription->tariff->sort) {

            return false;

        }

        return true;
    }

    public function getUsersSubscriptionByPage(UserPage $page)
    {
        return $this->subscriptions()
            ->where('user_page_id', $page->id)
            ->where('to', '>=', now())
            ->withWhereHas('tariff')
            ->first();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function succeedPayments(): hasMany
    {
        return $this->hasMany(Payment::class)->where('status', 'succeeded');
    }

    public static function generateRefCode(): string
    {
        $ref_code =Str::limit(Str::uuid()->toString(), 8,'');

        $exists = self::query()
            ->where('ref_code', $ref_code)
            ->exists();

        if ($exists) {
            return self::generateRefCode();
        }

        return $ref_code;
    }

    public function mutedPages(): HasMany
    {
        return $this->hasMany(MutedPage::class);
    }

    public function hiddenNotes(): HasMany
    {
        return $this->hasMany(HiddenNote::class);
    }

    public function hiddenPosts(): HasMany
    {
        return $this->hasMany(HiddenPost::class);
    }

    public function getUserSubscribersFilterAttribute(): ?string
    {
        return match (request()->query('field'))
        {
            'email' => 'Email',
            'name' => 'Фамилия, Имя',
            'email_verified_at' => 'Дата регистрации',
            'tariff_id' => 'Тариф',
            'active_to' => 'Подписка до',
            'payment_amount' => 'Общая сумма платежей',
            'read_count' => 'Прочитано публикаций',
            default => null,
        };
    }

    public function getUserAdminFilterAttribute(): ?string
    {
        return match (request()->query('filter_field'))
        {
            'email' => 'Email, Фамилия, Имя',
            'slug' => 'Поддомен',
            'email_verified_at' => 'Дата регистрации',
            'pages_count' => 'Все подписки',
            'active_subscriptions_count' => 'Платные подписки',
            'active_subscriptions_sum_price' => 'Стоимость в месяц',
            'last_thirty_days' => 'Доход за 30 дней',
            'all_payment_today' => 'Доход всего',
            default => null
        };
    }

    public function referrals(): hasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function userPageReferral(): HasOne
    {
        return $this->hasOne(UserPageReferralCount::class, 'user_id');
    }
}
