<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Models\UserLink;
use App\Core\Mailer\Mailer;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\InfuseUtility as Utility;
use Doctrine\DBAL\Connection;

/**
 * Handles user registration.
 */
class UserRegistration implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private VerifyEmailHelper $verifyEmail,
        private Mailer $mailer,
        private Connection $database
    ) {
    }

    /**
     * Registers a new user.
     *
     * @throws AuthException when the user cannot be created
     */
    public function registerUser(array $values, bool $verifiedEmail, bool $sendWelcomeEmail): User
    {
        $email = (string) array_value($values, 'email');
        $tempUser = $this->getTemporaryUser($email);

        // upgrade temporary account
        if ($tempUser) {
            $this->upgradeTemporaryUser($tempUser, $values, $sendWelcomeEmail);

            return $tempUser;
        }

        $user = new User();

        if (!$user->create($values)) {
            throw new AuthException('Could not create user account: '.$user->getErrors());
        }

        if (!$verifiedEmail) {
            $this->verifyEmail->sendVerificationEmail($user);
        } elseif ($sendWelcomeEmail) {
            // send the user a welcome message
            $this->mailer->sendToUser($user, [
                'subject' => 'Welcome to Invoiced',
            ], 'welcome');
        }

        $this->statsd->increment('security.new_user');

        return $user;
    }

    /**
     * Gets a temporary user from an email address if one exists.
     *
     * @param string $email email address
     */
    public function getTemporaryUser(string $email): ?User
    {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $user = User::where('email', $email)->oneOrNull();

        if (!$user) {
            return null;
        }

        if (!$user->isTemporary()) {
            return null;
        }

        return $user;
    }

    /**
     * Creates a temporary user. Useful for creating invites.
     *
     * @param array $parameters user data
     *
     * @throws AuthException when the user cannot be created
     */
    public function createTemporaryUser(array $parameters): User
    {
        $email = trim(strtolower((string) array_value($parameters, 'email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Invalid email address');
        }

        $insertArray = array_replace($parameters, ['enabled' => false]);

        // create the temporary user
        $user = new User();
        $driver = User::getDriver();
        $created = $driver->createModel($user, $insertArray);

        if (!$created) {
            throw new AuthException('Could not create temporary user');
        }

        // get the new user ID
        $id = [];
        foreach (User::definition()->getIds() as $k) {
            $id[] = $driver->getCreatedID($user, $k);
        }

        $user = User::findOrFail($id);

        // create the temporary link
        $link = new UserLink();
        $link->user_id = (int) $user->id();
        $link->type = UserLink::TEMPORARY;
        $link->saveOrFail();
        $user->setTemporaryLink($link);

        $this->statsd->increment('security.new_temp_user');

        return $user;
    }

    /**
     * Upgrades the user from temporary to a fully registered account.
     *
     * @param array $values properties to set on user model
     *
     * @throws AuthException when the upgrade fails
     *
     * @return $this
     */
    public function upgradeTemporaryUser(User $user, array $values, bool $sendWelcomeEmail)
    {
        if (!$user->isTemporary()) {
            throw new AuthException('Cannot upgrade a non-temporary account');
        }

        $values = array_replace($values, [
            'created_at' => Utility::unixToDb(time()),
            'enabled' => true,
        ]);

        if (!$user->set($values)) {
            throw new AuthException('Could not upgrade temporary account: '.$user->getErrors());
        }

        // remove temporary and unverified links
        $sql = 'DELETE FROM UserLinks WHERE user_id = :userId AND (`type`="'.UserLink::TEMPORARY.'" OR `type`="'.UserLink::VERIFY_EMAIL.'")';
        $this->database->executeStatement($sql, ['userId' => $user->id()]);
        $user->clearTemporaryLink();

        // send the user a welcome message
        if ($sendWelcomeEmail) {
            $this->mailer->sendToUser($user, [
                'subject' => 'Welcome to Invoiced',
            ], 'welcome');
        }

        $this->statsd->increment('security.new_user');

        return $this;
    }
}
