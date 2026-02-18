<?php

namespace App\Network\Command;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Utils\RandomString;
use App\Network\Event\PostSendNetworkInvitationEvent;
use App\Network\Exception\NetworkInviteException;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkInvitation;
use Carbon\CarbonImmutable;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class InviteToNetwork
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Invites a customer to join.
     *
     * @param string $to Email address or Invoiced username
     *
     * @throws NetworkInviteException
     */
    public function inviteCustomer(Company $from, ?Member $user, string $to, Customer $customer): NetworkInvitation
    {
        // determine whether to look up via email or invoiced username
        if (str_contains($to, '@')) {
            return $this->inviteByEmail($from, $user, $to, true, $customer, null);
        }

        return $this->inviteByUsername($from, $user, $to, true, $customer, null);
    }

    /**
     * Invites a vendor to join.
     *
     * @param string $to Email address or Invoiced username
     *
     * @throws NetworkInviteException
     */
    public function inviteVendor(Company $from, ?Member $user, string $to, Vendor $vendor): NetworkInvitation
    {
        // determine whether to look up via email or invoiced username
        if (str_contains($to, '@')) {
            return $this->inviteByEmail($from, $user, $to, false, null, $vendor);
        }

        return $this->inviteByUsername($from, $user, $to, false, null, $vendor);
    }

    /**
     * @throws NetworkInviteException
     */
    private function inviteByEmail(Company $from, ?Member $user, string $email, bool $isCustomer, ?Customer $customer, ?Vendor $vendor): NetworkInvitation
    {
        // validate the email address
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new NetworkInviteException('Please enter a valid email address.');
        }

        // validate the email domain has an MX record
        $emailParts = explode('@', $email);
        $emailDomain = 'example.com';
        if (2 == count($emailParts)) {
            [, $emailDomain] = $emailParts;
        }
        if (!checkdnsrr($emailDomain.'.', 'MX')) {
            throw new NetworkInviteException('Please enter a valid email address.');
        }

        // check for an existing invitation to this email address
        if ($this->alreadyInvitedEmail($from, $email)) {
            throw new NetworkInviteException('There is already an open invitation for '.$email);
        }

        // check if they have reached max open invitation threshold
        if ($this->hasTooManyOpenInvitations($from)) {
            throw new NetworkInviteException('We were unable to complete your request.');
        }

        // create the invitation
        return $this->createInvitation($from, $user, null, $email, $isCustomer, $customer, $vendor);
    }

    /**
     * @throws NetworkInviteException
     */
    public function inviteByUsername(Company $from, ?Member $user, string $username, bool $isCustomer, ?Customer $customer, ?Vendor $vendor): NetworkInvitation
    {
        if (!$username) {
            throw new NetworkInviteException('Username cannot be blank');
        }

        /** @var Company|null $to */
        $to = Company::where('username', $username)->oneOrNull();
        if (!$to) {
            throw new NetworkInviteException('Could not find company with username: '.$username);
        }

        return $this->inviteExistingCompany($from, $user, $to, $isCustomer, $customer, $vendor);
    }

    /**
     * @throws NetworkInviteException
     */
    private function inviteExistingCompany(Company $from, ?Member $user, Company $to, bool $isCustomer, ?Customer $customer, ?Vendor $vendor): NetworkInvitation
    {
        if ($from->id() == $to->id()) {
            throw new NetworkInviteException('You cannot add your own company to your network.');
        }

        if ($to->canceled) {
            throw new NetworkInviteException('The company you are trying to connect with is disabled.');
        }

        // check if existing connection
        $existingConnection = NetworkConnection::where($isCustomer ? 'vendor_id' : 'customer_id', $from)
            ->where($isCustomer ? 'customer_id' : 'vendor_id', $to)
            ->oneOrNull();

        if ($existingConnection) {
            // not doing anything with existing connections for now
            throw new NetworkInviteException('This company is already in your network.');
        }

        // check for existing invitation
        $existingInvitation = NetworkInvitation::where('from_company_id', $from)
            ->where('to_company_id', $to)
            ->where('is_customer', $isCustomer)
            ->oneOrNull();

        if ($existingInvitation instanceof NetworkInvitation) {
            throw new NetworkInviteException('There is already an open invitation for @'.$to->username);
        }

        // check if they have reached max open invitation threshold
        if ($this->hasTooManyOpenInvitations($from)) {
            throw new NetworkInviteException('We were unable to complete your request.');
        }

        return $this->createInvitation($from, $user, $to, null, $isCustomer, $customer, $vendor);
    }

    private function alreadyInvitedEmail(Company $company, string $email): bool
    {
        return NetworkInvitation::where('from_company_id', $company)
            ->where('email', $email)
            ->where('expires_at', CarbonImmutable::now()->toDateTimeString(), '>')
            ->count() > 0;
    }

    private function hasTooManyOpenInvitations(Company $company): bool
    {
        // Users cannot have more than this many open, unexpired invitations
        // in addition to the number of connections they have. For example,
        // if their quota is 5 additional invitations and there are
        // 10 existing connections, then there can be at most 15 open invitations.
        $numConnections = NetworkConnection::where('customer_id', $company)->count();
        $numConnections += NetworkConnection::where('vendor_id', $company)->count();
        $maxOpen = $company->quota->get(QuotaType::MaxOpenNetworkInvitations);
        $threshold = $numConnections + $maxOpen;

        return NetworkInvitation::where('from_company_id', $company)
            // Include any unresolved invitation that is unexpired or expired in last week
            ->where('expires_at', CarbonImmutable::now()->subWeek()->toDateTimeString(), '>')
            ->count() >= $threshold;
    }

    private function createInvitation(Company $from, ?Member $user, ?Company $to, ?string $email, bool $isCustomer, ?Customer $customer, ?Vendor $vendor): NetworkInvitation
    {
        $invitation = new NetworkInvitation();
        $invitation->uuid = RandomString::generate(32, RandomString::CHAR_ALNUM);
        $invitation->email = $email;
        $invitation->from_company = $from;
        $invitation->to_company = $to;
        $invitation->sent_by_user = $user;
        $invitation->is_customer = $isCustomer;
        $invitation->customer = $customer;
        $invitation->vendor = $vendor;
        $invitation->expires_at = (new CarbonImmutable('+7 days'));
        $invitation->saveOrFail();

        // emit an event for other listeners to add behavior
        $this->dispatcher->dispatch(new PostSendNetworkInvitationEvent($invitation));

        return $invitation;
    }
}
