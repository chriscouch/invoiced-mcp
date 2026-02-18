<?php

namespace App\Sending\Email\Models;

use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use Doctrine\DBAL\Statement;

/**
 * An InboxEmail represents an email that was sent to a company's inbox.
 *
 * @property int             $id
 * @property int             $thread_id
 * @property EmailThread     $thread
 * @property int|null        $reply_to_email_id
 * @property InboxEmail|null $reply_to_email
 * @property string|null     $message_id
 * @property string          $subject
 * @property int             $date
 * @property bool            $incoming
 * @property string|null     $tracking_id
 * @property User|null       $sent_by
 * @property int             $opens
 * @property array           $from
 * @property array           $to
 * @property array           $cc
 * @property array           $bcc
 * @property bool            $bounce
 * @property bool            $complaint
 */
class InboxEmail extends MultitenantModel
{
    use ApiObjectTrait;
    use AutoTimestamps;

    private array $participants;

    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->sort('id DESC');
    }

    protected static function getProperties(): array
    {
        return [
            'thread' => new Property(
                required: true,
                belongs_to: EmailThread::class,
            ),
            'reply_to_email' => new Property(
                null: true,
                belongs_to: InboxEmail::class,
            ),
            'message_id' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'tracking_id' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'subject' => new Property(
                type: Type::STRING,
                default: '',
            ),
            'date' => new Property(
                type: Type::DATE_UNIX,
                required: true,
                validate: 'timestamp',
                default: 'now',
            ),
            'incoming' => new Property(
                type: Type::BOOLEAN,
                required: true,
                default: true,
            ),
            'sent_by' => new Property(
                null: true,
                belongs_to: User::class,
            ),
            'opens' => new Property(
                type: Type::INTEGER,
                required: true,
                default: 0,
            ),
            'bounce' => new Property(
                type: Type::BOOLEAN,
            ),
            'complaint' => new Property(
                type: Type::BOOLEAN,
            ),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['cc'] = $this->cc;
        $result['bcc'] = $this->bcc;
        $result['from'] = $this->from;
        $result['to'] = $this->to;

        return $result;
    }

    protected function getObjectValue(): string
    {
        // This has to be overriden for BC
        return 'email';
    }

    public function getFromValue(): array
    {
        $from = $this->getParticipantsByType(EmailParticipant::FROM);

        return count($from) > 0 ? $from[0] : [];
    }

    public function getToValue(): array
    {
        return $this->getParticipantsByType(EmailParticipant::TO);
    }

    public function getCcValue(): array
    {
        return $this->getParticipantsByType(EmailParticipant::CC);
    }

    public function getBccValue(): array
    {
        return $this->getParticipantsByType(EmailParticipant::BCC);
    }

    private function getParticipants(): array
    {
        if (!isset($this->participants)) {
            $db = self::getDriver()->getConnection(null);
            $id = $this->id();
            $sql = 'SELECT EmailParticipants.id, `name`, `email_address`, `type` FROM EmailParticipantAssociations JOIN EmailParticipants ON `participant_id`=EmailParticipants.id WHERE `email_id` = ?';
            /** @var Statement $stmt */
            $stmt = $db->prepare($sql);
            $stmt->bindValue(1, $id);
            $this->participants = $stmt->executeQuery()->fetchAllAssociative();
        }

        return $this->participants;
    }

    private function getParticipantsByType(string $type): array
    {
        if (!isset($this->participants)) {
            $this->getParticipants();
        }

        $result = [];
        foreach ($this->participants as $participant) {
            if ($participant['type'] == $type) {
                $result[] = [
                    'name' => $participant['name'],
                    'email_address' => $participant['email_address'],
                ];
            }
        }

        return $result;
    }

    public function setParticipants(array $participants): void
    {
        $this->participants = $participants;
    }
}
