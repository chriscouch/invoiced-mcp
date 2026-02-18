<?php

namespace App\Sending\Libs;

use App\Sending\Models\ScheduledSend;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use InvalidArgumentException;

class ScheduledSendParameterValidator
{
    /**
     * Validates ScheduledSend parameters.
     *
     * @throws InvalidArgumentException
     */
    public static function validate(int $channel, array $parameters): void
    {
        try {
            $resolver = self::getParameterResolver($channel);
            $resolver->resolve($parameters);
        } catch (ExceptionInterface $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        switch ($channel) {
            case ScheduledSend::EMAIL_CHANNEL:
                self::validateEmailParameters($parameters);
                break;
            case ScheduledSend::SMS_CHANNEL:
                self::validateSmsParameters($parameters);
                break;
            case ScheduledSend::LETTER_CHANNEL:
                self::validateLetterParameters($parameters);
                break;
        }
    }

    /**
     * Returns an option resolver configured to validate the parameters value
     * for a specified channel.
     */
    private static function getParameterResolver(int $channel): OptionsResolver
    {
        $resolver = new OptionsResolver();

        // email
        if (ScheduledSend::EMAIL_CHANNEL === $channel) {
            $resolver->setDefined([
                'role',
                'to',
                'cc',
                'bcc',
                'subject',
                'message',
            ]);
            $resolver->setAllowedTypes('to', ['array', 'null']);
            $resolver->setAllowedTypes('cc', ['array', 'null']);
            $resolver->setAllowedTypes('bcc', ['array', 'null']);
            $resolver->setAllowedTypes('subject', ['string', 'null']);
            $resolver->setAllowedTypes('message', ['string', 'null']);
        }
        // sms
        if (ScheduledSend::SMS_CHANNEL === $channel) {
            $resolver->setDefined([
                'to',
                'message',
            ]);
            $resolver->setAllowedTypes('to', ['array', 'null']);
            $resolver->setAllowedTypes('message', ['string', 'null']);
        }
        // letter
        if (ScheduledSend::LETTER_CHANNEL === $channel) {
            $resolver->setDefined(['to']);
            $resolver->setAllowedTypes('to', ['array', 'null']);
        }

        return $resolver;
    }

    /**
     * Validates the structure of the email specific parameters.
     *
     * @throws InvalidArgumentException
     */
    private static function validateEmailParameters(array $parameters): void
    {
        // validate to
        if (isset($parameters['to'])) {
            self::verifyEmailContacts($parameters['to'], 'to');
        }
        // validate cc
        if (isset($parameters['cc'])) {
            self::verifyEmailContacts($parameters['cc'], 'cc');
        }
        // validate bcc
        if (isset($parameters['bcc'])) {
            self::verifyEmailContacts($parameters['bcc'], 'bcc');
        }
    }

    /**
     * Verifies the structure of email contacts in the list provided.
     *
     * @throws InvalidArgumentException
     */
    private static function verifyEmailContacts(array $contacts, string $property): void
    {
        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                throw new InvalidArgumentException("Malformed contact in '$property' array. Array value was expected.");
            }

            if (!isset($contact['email'])) {
                throw new InvalidArgumentException("Contact is missing required property 'email' in '$property' array.");
            }

            if (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Contact's email address is malformed");
            }
        }
    }

    /**
     * Validates the structure of the sms specific parameters.
     *
     * @throws InvalidArgumentException
     */
    private static function validateSmsParameters(array $parameters): void
    {
        if (!isset($parameters['to'])) {
            return;
        }

        foreach ($parameters['to'] as $contact) {
            if (!is_array($contact)) {
                throw new InvalidArgumentException("Malformed contact in 'to' array. Array value was expected.");
            }

            if (!isset($contact['phone'])) {
                throw new InvalidArgumentException("Contact is missing required property 'phone' in 'to' array.");
            }
        }
    }

    /**
     * Validates the structure of the letter specific parameters.
     *
     * @throws InvalidArgumentException
     */
    private static function validateLetterParameters(array $parameters): void
    {
        if (!isset($parameters['to'])) {
            return;
        }

        $requiredProperties = [
            'address1',
            'state',
            'postal_code',
            'country',
        ];

        $recipient = $parameters['to'];
        foreach ($requiredProperties as $requiredProperty) {
            if (!isset($recipient[$requiredProperty])) {
                throw new InvalidArgumentException("Invalid mailing address. Address is missing property '$requiredProperty'");
            }
        }
    }
}
