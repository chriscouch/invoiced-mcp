<?php

namespace App\Chasing\Models;

use App\AccountsReceivable\Models\ContactRole;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Sms\Models\SmsTemplate;

/**
 * Model for representing chasing cadence steps.
 *
 * @property int              $id
 * @property string           $name
 * @property int              $chasing_cadence_id
 * @property string           $action
 * @property string           $schedule
 * @property string|null      $email_template_id
 * @property int|null         $sms_template_id
 * @property int|null         $assigned_user_id
 * @property int              $order
 * @property int|null         $role_id
 * @property ContactRole|null $role
 */
class ChasingCadenceStep extends MultitenantModel
{
    use AutoTimestamps;

    const ACTION_EMAIL = 'email';
    const ACTION_SMS = 'sms';
    const ACTION_PHONE = 'phone';
    const ACTION_MAIL = 'mail';
    const ACTION_ESCALATE = 'escalate';

    const SCHEDULE_AGE = 'age';
    const SCHEDULE_PAST_DUE_AGE = 'past_due_age';

    private static array $allowedScheduleTypes = [
        self::SCHEDULE_AGE,
        self::SCHEDULE_PAST_DUE_AGE,
    ];

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'chasing_cadence_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
                relation: ChasingCadence::class,
            ),
            'action' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['enum', 'choices' => ['email', 'sms', 'phone', 'mail', 'escalate']],
            ),
            'schedule' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateSchedule']],
            ),
            'email_template_id' => new Property(
                type: Type::STRING,
                null: true,
                relation: EmailTemplate::class,
            ),
            'sms_template_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: SmsTemplate::class,
            ),
            'assigned_user_id' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: User::class,
            ),
            'order' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
            'role' => new Property(
                null: true,
                belongs_to: ContactRole::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::saving([self::class, 'validateUser']);
        self::saving([self::class, 'validateEmailTemplate']);
        self::saving([self::class, 'validateSmsTemplate']);
    }

    public static function validateUser(AbstractEvent $e): void
    {
        /** @var self $model */
        $model = $e->getModel();

        if (!$model->assigned_user_id) {
            return;
        }

        $user = $model->user();
        $company = $model->tenant();
        if (!$user || !$company->isMember($user)) {
            throw new ListenerException('No such user: '.$model->assigned_user_id, ['field' => 'assigned_user_id']);
        }
    }

    public static function validateEmailTemplate(AbstractEvent $e): void
    {
        /** @var self $model */
        $model = $e->getModel();

        if (self::ACTION_EMAIL != $model->action) {
            return;
        }

        $emailTemplate = $model->emailTemplate();
        if (!$emailTemplate) {
            throw new ListenerException('No such email template: '.$model->email_template_id, ['field' => 'email_template_id']);
        }

        if (EmailTemplate::TYPE_CHASING != $emailTemplate->type) {
            throw new ListenerException('Email template must be a chasing email template: '.$model->email_template_id, ['field' => 'email_template_id']);
        }
    }

    public static function validateSmsTemplate(AbstractEvent $e): void
    {
        /** @var self $model */
        $model = $e->getModel();

        if (self::ACTION_SMS != $model->action) {
            return;
        }

        $smsTemplate = $model->smsTemplate();
        if (!$smsTemplate) {
            throw new ListenerException('No such SMS template: '.$model->sms_template_id, ['field' => 'sms_template_id']);
        }
    }

    /**
     * Validates the schedule for a step.
     */
    public static function validateSchedule(string $schedule): bool
    {
        $parts = explode(':', $schedule);

        if (2 != count($parts)) {
            return false;
        }

        $scheduleType = $parts[0];
        if (!in_array($scheduleType, self::$allowedScheduleTypes)) {
            return false;
        }

        $value = $parts[1];
        if (!is_numeric($value)) {
            return false;
        }

        // negative age or past due age values are not permitted
        if ($value < 0) {
            return false;
        }

        return true;
    }

    public function user(): ?User
    {
        return $this->relation('assigned_user_id');
    }

    public function emailTemplate(): ?EmailTemplate
    {
        return EmailTemplate::where('id', $this->email_template_id)->oneOrNull();
    }

    public function smsTemplate(): ?SmsTemplate
    {
        return SmsTemplate::where('id', $this->sms_template_id)->oneOrNull();
    }
}
