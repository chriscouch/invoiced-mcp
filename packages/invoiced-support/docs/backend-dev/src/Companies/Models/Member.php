<?php

namespace App\Companies\Models;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\UserContextFacade;
use App\Core\Authentication\Libs\UserRegistration;
use App\Core\Authentication\Libs\VerifyEmailHelper;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\EnvironmentFacade;
use App\Core\Mailer\MailerFacade;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Statsd\StatsdFacade;
use App\Core\Utils\RandomString;
use App\Core\Utils\ValueObjects\Interval;
use App\Metadata\ValueObjects\CustomFieldRestriction;
use App\Notifications\Models\Notification;
use App\Notifications\Models\NotificationEventCompanySetting;
use App\Notifications\Models\NotificationEventSetting;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Exception;

/**
 * @property int             $id
 * @property int             $user_id
 * @property array           $user
 * @property string          $role
 * @property int|null        $last_accessed
 * @property int             $expires
 * @property string          $restriction_mode
 * @property array|null      $restrictions
 * @property string|null     $email_update_frequency
 * @property bool            $notifications
 * @property bool            $subscribe_all
 * @property CarbonImmutable $notification_viewed
 */
class Member extends MultitenantModel
{
    use AutoTimestamps;

    const UNRESTRICTED = 'none';
    const CUSTOM_FIELD_RESTRICTION = 'custom_field';
    const OWNER_RESTRICTION = 'owner';

    private static bool $skipExpiredCheck = false;

    private bool $_skipMemberCheck = false;
    private bool $_skipInvite = false;
    private array $_permissions = [];
    private bool $changedRole = false;

    protected static function getProperties(): array
    {
        return [
            'user_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                relation: User::class,
            ),
            'role' => new Property(
                required: true,
                relation: Role::class,
            ),
            'expires' => new Property(
                type: Type::DATE_UNIX,
                in_array: false,
            ),
            'last_accessed' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'restriction_mode' => new Property(
                validate: ['enum', 'choices' => ['none', 'custom_field', 'owner']],
                default: self::UNRESTRICTED,
            ),
            'restrictions' => new Property(
                type: Type::ARRAY,
                null: true,
            ),
            'email_update_frequency' => new Property(
                null: true,
                default: Interval::WEEK,
            ),
            'notifications' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'subscribe_all' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'notification_viewed' => new Property(
                type: Type::STRING,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'checkQuota']);
        self::creating([self::class, 'determineUser']);
        self::creating([self::class, 'validateRole']);
        self::creating([self::class, 'enableNotifications']);
        self::updating([self::class, 'validateRole']);
        self::saving([self::class, 'validateRestrictions']);
        self::created([self::class, 'setUpNotifications']);
        self::created([self::class, 'sendInviteAfterCreate']);
        self::created([self::class, 'setDefaultCompany']);
        self::deleted([self::class, 'cleanupNotifications']);
        self::deleted([self::class, 'cleanupCustomerOwnership']);
        self::deleted([self::class, 'cleanupChasing']);
        self::updated([self::class, 'removeApiKeys']);
        self::deleted([self::class, 'removeApiKeys']);

        parent::initialize();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['user'] = $this->user;
        $result['notification_viewed'] = CarbonImmutable::parse($this->notification_viewed)->toIso8601String();

        return $result;
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        if (!self::$skipExpiredCheck) {
            $query->where('(expires = 0 OR expires > '.time().')');
        } else {
            self::$skipExpiredCheck = false;
        }

        return $query;
    }

    public function skipInvite(): self
    {
        $this->_skipInvite = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function skipMemberCheck()
    {
        $this->_skipMemberCheck = true;

        return $this;
    }

    public static function skipExpiredCheck(): void
    {
        self::$skipExpiredCheck = true;
    }

    public static function checkQuota(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // check if the account has reached their user quota
        $company = $model->tenant();
        $quota = $company->quota->get(QuotaType::Users);

        if ($quota <= 0) {
            return;
        }

        $numUsers = Member::queryWithTenant($company)
            ->where('expires', 0)
            ->count();
        if ($numUsers >= $quota) {
            throw new ListenerException('You have reached your account\'s user limit ('.$quota.'). Please upgrade to add additional users.');
        }

        $usersCreatedToday = User::where('default_company_id', $company->id)
            ->where('created_at', date('Y-m-d 00:00:00'), '>=')
            ->where('created_at', date('Y-m-d 23:59:59'), '<=')
            ->count();

        // prevent creating more then 110% of user quota users per day
        // INVD-195
        $dailyQuota = ceil($quota * 1.1);
        if ($usersCreatedToday >= $dailyQuota) {
            throw new ListenerException('You have reached your account\'s user daily limit ('.$dailyQuota.'). Please upgrade to add additional users, or try again Tomorrow.');
        }
    }

    public static function validateRestrictions(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $restrictions = $model->restrictions;
        if (!$restrictions) {
            $model->restrictions = null;

            return;
        }

        try {
            CustomFieldRestriction::validateRestrictions($restrictions);
        } catch (\Exception $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'restrictions']);
        }
    }

    /**
     * Determines the user relationship when creating.
     */
    public static function determineUser(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($id = $model->user_id) {
            $user = User::find($id);
        } else {
            $user = null;
        }

        // handle an email being passed in, which
        // means we need to either:
        // i) look up an existing user
        // ii) create a new user
        if (isset($model->email)) {
            // validate email address
            $email = trim(strtolower($model->email));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ListenerException('Email address is invalid', ['field' => 'email']);
            }

            // does the user exist?
            $user = User::where('email', $email)->oneOrNull();

            // create temporary user
            if (!$user) {
                $params = ['email' => $email];

                if (isset($model->first_name)) {
                    $params['first_name'] = $model->first_name;
                }

                if (isset($model->last_name)) {
                    $params['last_name'] = $model->last_name;
                }

                $registrar = new UserRegistration(new VerifyEmailHelper(self::getDriver()->getConnection(null), MailerFacade::get()), MailerFacade::get(), self::getDriver()->getConnection(null));
                $registrar->setStatsd(StatsdFacade::get());

                /** @var ?CompanySamlSettings $saml */
                $saml = CompanySamlSettings::where('company_id', $model->tenant())->oneOrNull();
                try {
                    if ($saml?->disable_non_sso) {
                        $model->skipInvite();
                        // some required parameters to register the user
                        $params['ip'] = '127.0.0.1';
                        $params['password'] = RandomString::generate(32, RandomString::CHAR_ALNUM).RandomString::generate(3, '!@#$%^&*()');
                        $user = $registrar->registerUser($params, true, true);
                    } else {
                        $user = $registrar->createTemporaryUser($params);
                    }
                } catch (AuthException $e) {
                    throw new ListenerException($e->getMessage());
                }
            }

            if ($user instanceof User) {
                $model->setUser($user);
            }
            unset($model->email);
        }

        if (!($user instanceof User)) {
            throw new ListenerException('Missing user');
        }

        // make sure not already a member
        if (!$model->_skipMemberCheck && $user instanceof User && $model->tenant()->isMember($user)) {
            throw new ListenerException($user->name(true).' is already a member of the company', ['field' => 'user']);
        }
    }

    /**
     * Validates the role relationship when creating.
     */
    public static function validateRole(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->changedRole = $model->dirty('role');

        if (!$model->changedRole) {
            return;
        }

        $role = Role::queryWithTenant($model->tenant())
            ->where('id', $model->role)
            ->oneOrNull();
        if (!$role) {
            throw new ListenerException('No such role: '.$model->role, ['field' => 'role']);
        }
    }

    /**
     * Sets up notifications after creating.
     */
    public static function setUpNotifications(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $tenant = $model->tenant();

        if ($tenant->features->has('notifications_v2_default')) {
            // cleaning up the trash, just in case
            NotificationEventSetting::queryWithTenant($tenant)->where('member_id', $model->id)->delete();
            $companyNotifications = NotificationEventCompanySetting::queryWithTenant($tenant)->all();
            foreach ($companyNotifications as $notification) {
                $setting = new NotificationEventSetting();
                $setting->tenant_id = $model->tenant_id;
                $setting->notification_type = $notification->notification_type;
                $setting->member = $model;
                $setting->frequency = $notification->frequency;
                $setting->saveOrFail();
            }
        } else {
            foreach (Notification::$notifyByDefault as $event => $enabled) {
                $notif = new Notification();
                $notif->tenant_id = $model->tenant_id;
                $notif->event = $event;
                $notif->user_id = $model->user_id;
                $notif->enabled = $enabled;
                $notif->save();
            }
        }
    }

    /**
     * Sends an invite email after creating (unless company creator).
     */
    public static function sendInviteAfterCreate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->_skipInvite) {
            return;
        }

        $company = $model->tenant();
        $user = $model->user();

        // send invite email (unless company creator)
        $fromUser = UserContextFacade::get()->get();
        if ($user->id() != $company->creator_id && $fromUser) {
            $model->sendInvite($fromUser);
        }
    }

    /**
     * Sets user's default company (if not already set) after creating.
     */
    public static function setDefaultCompany(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $user = $model->user();

        if (!$user->default_company_id) {
            $user->default_company_id = $model->tenant_id;
            $user->save();
        }

        $model->_skipMemberCheck = false;
    }

    /**
     * @deprecated should be replaced with default values,
     * after finished migration to new notification system
     * for all customers
     */
    public static function enableNotifications(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->tenant()->features->has('notifications_v2_default')) {
            $model->notifications = true;
            $model->subscribe_all = true;
        }
    }

    /**
     * Cleans up notifications after delete.
     */
    public static function cleanupNotifications(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        self::getDriver()->getConnection(null)->delete('Notifications', [
            'tenant_id' => $model->tenant_id,
            'user_id' => $model->user_id,
        ]);
    }

    /**
     * Cleans up customers ownership.
     *
     * @throws Exception
     */
    public static function cleanupCustomerOwnership(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        self::getDriver()->getConnection(null)->update('Customers', [
            'owner_id' => null,
        ], [
            'tenant_id' => $model->tenant_id,
            'owner_id' => $model->user_id,
        ]);
    }

    /**
     * Cleans up customers ownership.
     *
     * @throws Exception
     */
    public static function cleanupChasing(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        self::getDriver()->getConnection(null)->update('Tasks', [
            'user_id' => null,
        ], [
            'tenant_id' => $model->tenant_id,
            'user_id' => $model->user_id,
        ]);
        self::getDriver()->getConnection(null)->update('ChasingCadenceSteps', [
            'assigned_user_id' => null,
        ], [
            'tenant_id' => $model->tenant_id,
            'assigned_user_id' => $model->user_id,
        ]);
    }

    /**
     * Invalidates any API keys after update or delete.
     */
    public static function removeApiKeys(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->changedRole && $model->persisted()) {
            return;
        }

        self::getDriver()->getConnection(null)->delete('ApiKeys', [
            'tenant_id' => $model->tenant_id,
            'user_id' => $model->user_id,
        ]);
    }

    public static function getForUser(User $user): ?self
    {
        if (!$user->persisted()) {
            return null;
        }

        return self::where('user_id', $user)->oneOrNull();
    }

    /**
     * Gets the user property.
     */
    public function getUserValue(): array
    {
        return $this->user()->toArray();
    }

    /**
     * Sets the role property.
     *
     * @param string $role
     */
    public function setRoleValue($role): string
    {
        $this->_permissions = [];

        return $role;
    }

    /**
     * Checks if a permission is allowed.
     */
    public function allowed(string $permission): bool
    {
        if (!isset($this->_permissions[$permission])) {
            $allowed = in_array(
                $permission,
                $this->role()->permissions()
            );

            $this->_permissions[$permission] = $allowed;
        }

        return $this->_permissions[$permission];
    }

    /**
     * @return CustomFieldRestriction[]|null
     */
    public function restrictions(): ?array
    {
        $restrictions = $this->restrictions;
        if (!$restrictions) {
            return null;
        }

        $result = [];
        foreach ($restrictions as $key => $value) {
            $result[] = new CustomFieldRestriction($key, $value);
        }

        return $result;
    }

    //
    // Relationships
    //

    /**
     * Gets the user.
     */
    public function user(): User
    {
        return $this->relation('user_id');
    }

    /**
     * Gets the role.
     */
    public function role(): Role
    {
        return $this->relation('role');
    }

    public function setUser(User $user): void
    {
        $this->user_id = (int) $user->id();
        $this->setRelation('user_id', $user);
    }

    /**
     * Sends an invitation to the user to join
     * the company and register on Invoiced, if
     * they have not done so already.
     */
    public function sendInvite(User $fromUser): void
    {
        $company = $this->tenant();
        $user = $this->user();

        // send invite email (unless company creator)
        $fromPersonName = $fromUser->name(true);
        $registered = !$user->isTemporary();

        // build the sign in/sign up link
        $dashboardUrl = EnvironmentFacade::getDashboardUrl();

        if (!$registered) {
            $params = ['email' => $user->email];

            if ($user->first_name) {
                $params['first_name'] = $user->first_name;
            }

            if ($user->last_name) {
                $params['last_name'] = $user->last_name;
            }

            $query = http_build_query($params);
            $link = $dashboardUrl.'/#!/register'.(($query) ? '?'.$query : '');
        } else {
            $link = $dashboardUrl.'/?account='.$this->tenant_id;
        }

        MailerFacade::get()->sendToUser(
            $user,
            [
                'subject' => $fromPersonName.' has invited you to join '.$company->name.' on Invoiced',
            ],
            'invite-to-company',
            [
                'username' => $user->name(true),
                'from_username' => $fromPersonName,
                'company' => $company->name,
                'registered' => $registered,
                'link' => $link,
            ]
        );

        StatsdFacade::get()->increment('security.user_invite');

        // set updated at so we know when the last invite was sent
        $this->updated_at = time();
        $this->save();
    }
}
