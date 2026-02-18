<?php

namespace App\Core\Authentication\Models;

use App\Companies\Models\Company;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\InfuseUtility as U;
use App\Core\Utils\RandomString;
use PasswordPolicy\Policy;

/**
 * @property int         $id
 * @property string      $first_name
 * @property string      $last_name
 * @property string      $email
 * @property string      $password
 * @property string      $ip
 * @property bool        $enabled
 * @property int         $default_company_id
 * @property string|null $intuit_claimed_id
 * @property string|null $google_claimed_id
 * @property string|null $microsoft_claimed_id
 * @property string|null $xero_claimed_id
 * @property string|null $authy_id
 * @property bool        $verified_2fa
 * @property bool        $two_factor_enabled
 * @property bool        $registered
 * @property bool        $has_password
 * @property int|null    $support_pin
 * @property bool        $disable_ip_check
 */
class User extends Model
{
    use AutoTimestamps;

    const INVOICED_USER = -2;
    const API_USER = -3;

    public static array $usernameProperties = ['email'];
    public static array $testUser = [
        'first_name' => 'Bob',
        'last_name' => 'Loblaw',
        'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
    ];

    private bool $fullySignedIn = false;
    private bool $is2faVerified = false;
    private ?UserLink $_temporaryLink = null;
    private bool $temporary;
    /**
     * @var ?int[]
     */
    private ?array $allowedCompanies = null;

    protected static function getProperties(): array
    {
        return [
            'first_name' => new Property(
                required: true,
                validate: ['string', 'min' => 1, 'max' => 100],
            ),
            'last_name' => new Property(),
            'email' => new Property(
                required: true,
                validate: ['email', ['unique', 'column' => 'email']],
            ),
            'password' => new Property(
                required: true,
                in_array: false,
            ),
            'ip' => new Property(
                required: true,
                validate: 'ip',
                in_array: false,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                required: true,
                validate: 'boolean',
                default: true,
                in_array: false,
            ),
            'default_company_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: Company::class,
            ),
            'google_claimed_id' => new Property(
                null: true,
                in_array: false,
            ),
            'intuit_claimed_id' => new Property(
                null: true,
                in_array: false,
            ),
            'microsoft_claimed_id' => new Property(
                null: true,
                in_array: false,
            ),
            'xero_claimed_id' => new Property(
                null: true,
                in_array: false,
            ),
            'authy_id' => new Property(
                null: true,
                in_array: false,
            ),
            'verified_2fa' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
            'has_password' => new Property(
                type: Type::BOOLEAN,
                default: true,
                in_array: false,
            ),
            'support_pin' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'disable_ip_check' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::saving([self::class, 'validatePassword']);
    }

    //
    // Hooks
    //

    public static function validatePassword(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $password = $model->password;

        if (!$model->dirty('password')) {
            return;
        }

        // validate the passwords match
        if (is_array($password)) { /* @phpstan-ignore-line */
            $matches = true;
            $cur = reset($password);
            foreach ($password as $v) {
                $matches = ($v == $cur) && $matches;
                $cur = $v;
            }

            if (!$matches) {
                throw new ListenerException('The supplied passwords do not match. Please check that you have entered in the correct password.', ['field' => 'password']);
            }

            $password = $password[0];
        }

        // validate password meets our policy
        $policy = new Policy();
        $policy->length($policy->atLeast(8))
            ->contains('lowercase', $policy->atLeast(1))
            ->contains('uppercase', $policy->atLeast(1))
            ->contains('digit', $policy->atLeast(1))
            ->contains('symbol', $policy->atLeast(1));

        $result = $policy->test($password);
        if (!$result->result) {
            $reasons = [];
            foreach ($result->messages as $message) {
                if (!$message->result) {
                    $reasons[] = '- '.$message->message;
                }
            }

            throw new ListenerException("The supplied password did not meet the password policy. Please correct the following issues:\n".implode("\n", $reasons), ['field' => 'password']);
        }

        if (password_verify($password, (string) $model->ignoreUnsaved()->password)) {
            throw new ListenerException('The supplied password should not match current password', ['field' => 'password']);
        }
        // hash the password for storage
        $model->password = password_hash($password, PASSWORD_DEFAULT);
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['two_factor_enabled'] = $this->two_factor_enabled;
        $result['registered'] = $this->registered;

        return $result;
    }

    /**
     * Gets the two_factor_enabled property value.
     */
    protected function getTwoFactorEnabledValue(): bool
    {
        return $this->authy_id > 0 && $this->verified_2fa;
    }

    /**
     * Gets the registered property value.
     */
    protected function getRegisteredValue(): bool
    {
        return !$this->isTemporary();
    }

    /**
     * Gets the user's name.
     *
     * @param bool $full when true gets full name
     */
    public function name(bool $full = false): string
    {
        if ($this->id() <= 0) {
            return '(not registered)';
        }

        if ($this->first_name) {
            if ($full) {
                return $this->first_name.' '.$this->last_name;
            }

            return $this->first_name;
        }

        return $this->email;
    }

    /**
     * Retrieves the user's unique support PIN. If not
     * already generated this function will generate a new PIN.
     */
    public function getSupportPin(): int
    {
        if (!$this->support_pin) {
            $pin = RandomString::generate(1, '123456789').RandomString::generate(7, '0123456789');
            $this->support_pin = (int) $pin;
            $this->saveOrFail();
        }

        return (int) $this->support_pin;
    }

    /**
     * @return ?int[] $companies
     */
    public function getAllowedCompanies(): ?array
    {
        return $this->allowedCompanies;
    }

    //
    // UserInterface
    //

    public function email(): string
    {
        return $this->email;
    }

    public function isTemporary(): bool
    {
        if (isset($this->_temporaryLink)) {
            return true;
        }

        if (isset($this->temporary)) {
            return $this->temporary;
        }

        if ($this->id() <= 0) {
            return true;
        }

        $this->temporary = UserLink::where('user_id', $this)
            ->where('type', UserLink::TEMPORARY)
            ->count() > 0;

        return $this->temporary;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): bool
    {
        $this->enabled = true;

        return $this->save();
    }

    public function isVerified(bool $withinTimeWindow = true): bool
    {
        $timeWindow = ($withinTimeWindow) ? time() - UserLink::$verifyTimeWindow : time();

        return 0 == UserLink::where('user_id', $this->id())
            ->where('type', UserLink::VERIFY_EMAIL)
            ->where('created_at', U::unixToDb($timeWindow), '<=')
            ->count();
    }

    public function isFullySignedIn(): bool
    {
        return $this->fullySignedIn;
    }

    public function setIsFullySignedIn(bool $fullySignedIn = true): self
    {
        $this->fullySignedIn = $fullySignedIn;

        return $this;
    }

    public function isTwoFactorVerified(): bool
    {
        return $this->is2faVerified;
    }

    public function markTwoFactorVerified(): self
    {
        $this->is2faVerified = true;

        return $this;
    }

    public function getHashedPassword(): string
    {
        if ($this->id() > 0) {
            return $this->ignoreUnsaved()->password;
        }

        return $this->password;
    }

    //
    // Getters
    //

    /**
     * Generates the URL for the user's profile picture.
     *
     * Gravatar is used for profile pictures. To accomplish this we need to generate a hash of the user's email.
     *
     * @param int $size size of the picture (it is square, usually)
     */
    public function profilePicture(int $size = 200): string
    {
        // use Gravatar
        $hash = md5(strtolower(trim($this->email)));

        return "https://secure.gravatar.com/avatar/$hash?s=$size&d=mm";
    }

    //
    // Registration
    //

    /**
     * Sets the temporary link attached to the user.
     *
     * @return $this
     */
    public function setTemporaryLink(UserLink $link)
    {
        $this->_temporaryLink = $link;

        return $this;
    }

    /**
     * Clears the temporary link attached to the user.
     */
    public function clearTemporaryLink(): void
    {
        $this->_temporaryLink = null;
        $this->temporary = false;
    }

    /**
     * Gets the temporary link from user registration.
     */
    public function getTemporaryLink(): ?UserLink
    {
        return $this->_temporaryLink;
    }
}
