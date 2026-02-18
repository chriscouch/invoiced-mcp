<?php

namespace App\Companies\FraudScore;

use App\Companies\Interfaces\FraudScoreInterface;
use App\Companies\ValueObjects\FraudEvaluationState;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

/**
 * Scores the fraud likelihood of a sign up based on its email address.
 */
class EmailFraudScore implements FraudScoreInterface
{
    /**
     * Sources:
     * - https://www.spamhaus.org/statistics/tlds/.
     * - http://www.surbl.org/tld/.
     */
    private const BLOCKED_TLDS = [
        '.asia',
        '.beauty',
        '.buzz',
        '.cam',
        '.casa',
        '.cf',
        '.click',
        '.club',
        '.cn',
        '.date',
        '.degree',
        '.fit',
        '.fyi',
        '.ga',
        '.gq',
        '.icu',
        '.info',
        '.link',
        '.live',
        '.ml',
        '.okinawa',
        '.rest',
        '.ru',
        '.shop',
        '.su',
        '.surf',
        '.tk',
        '.top',
        '.tw',
        '.work',
        '.xyz',
        '.zone',
    ];

    private const BLOCKED_EMAIL_DOMAINS = [
        'qq.com',
        'snapmail.cc',
        // potential competitors
        'armatic.com',
        'bectran.com',
        'bill.com',
        'billergenie.com',
        'billtrust.com',
        'blackline.com',
        'blixo.com',
        'bluesnap.com',
        'celonis.com',
        'chargebee.com',
        'coupa.com',
        'fisglobal.com',
        'fortispay.com',
        'gaviti.com',
        'growfin.ai',
        'highradius.com',
        'intuit.com',
        'invoicesherpa.com',
        'lockstep.io',
        'monite.com',
        'netsuite.com',
        'paya.com',
        'paystand.com',
        'quadient.com',
        'resolvepay.com',
        'salesforce.com',
        'sap.com',
        'stripe.com',
        'synder.com',
        'tesorio.com',
        'upflow.io',
        'versapay.com',
        'zuora.com',
        // mail.com domain names
        'email.com',
        'groupmail.com',
        'post.com',
        'homemail.com',
        'housemail.com',
        'writeme.com',
        'mail.com',
        'mail-me.com',
        'workmail.com',
        'accountant.com',
        'activist.com',
        'adexec.com',
        'allergist.com',
        'alumni.com',
        'alumnidirector.com',
        'alumnidirector.com',
        'archaeologist.com',
        'auctioneer.net',
        'bartender.net',
        'brew-master.com',
        'chef.net',
        'chemist.com',
        'collector.org',
        'columnist.com',
        'comic.com',
        'consultant.com',
        'contractor.net',
        'counsellor.com',
        'deliveryman.com',
        'diplomats.com',
        'dr.com',
        'engineer.com',
        'financier.com',
        'fireman.net',
        'gardener.com',
        'geologist.com',
        'graphic-designer.com',
        'graduate.org',
        'hairdresser.net',
        'instructor.net',
        'insurer.com',
        'journalist.com',
        'legislator.com',
        'lobbyist.com',
        'minister.com',
        'musician.org',
        'optician.com',
        'orthodontist.net',
        'pediatrician.com',
        'photographer.net',
        'physicist.net',
        'politician.com',
        'presidency.com',
        'priest.com',
        'programmer.net',
        'publicist.com',
        'radiologist.net',
        'realtyagent.com',
        'registerednurses.com',
        'repairman.com',
        'representative.com',
        'salesperson.net',
        'secretary.net',
        'socialworker.net',
        'sociologist.com',
        'songwriter.net',
        'teachers.org',
        'techie.com',
        'technologist.com',
        'therapist.net',
        'umpire.com',
        'worker.com',
        'activist.com',
        'artlover.com',
        'bikerider.com',
        'birdlover.com',
        'blader.com',
        'kittymail.com',
        'lovecat.com',
        'marchmail.com',
        'musician.org',
        'boardermail.com',
        'brew-master.com',
        'catlover.com',
        'chef.net',
        'clubmember.org',
        'nonpartisan.com',
        'petlover.com',
        'photographer.net',
        'songwriter.net',
        'collector.org',
        'doglover.com',
        'gardener.com',
        'greenmail.net',
        'hackermail.com',
        'techie.com',
        'theplate.com',
        'bsdmail.com',
        'computer4u.com',
        'consultant.com',
        'contractor.net',
        'coolsite.net',
        'cyberdude.com',
        'cybergal.com',
        'cyberservices.com',
        'cyber-wizard.com',
        'engineer.com',
        'graphic-designer.com',
        'hackermail.com',
        'linuxmail.org',
        'null.net',
        'physicist.net',
        'post.com',
        'programmer.net',
        'solution4u.com',
        'tech-center.com',
        'techie.com',
        'technologist.com',
        'webname.com',
        'workmail.com',
        'writeme.com',
        'acdcfan.com',
        'angelic.com',
        'discofan.com',
        'elvisfan.com',
        'hiphopfan.com',
        'housemail.com',
        'kissfans.com',
        'madonnafan.com',
        'metalfan.com',
        'musician.org',
        'ninfan.com',
        'ravemail.com',
        'reggaefan.com',
        'snakebite.com',
        'songwriter.net',
        'bellair.net',
        'californiamail.com',
        'dallasmail.com',
        'nycmail.com',
        'pacific-ocean.com',
        'pacificwest.com',
        'sanfranmail.com',
        'usa.com',
        'africamail.com',
        'asia-mail.com',
        'australiamail.com',
        'berlin.com',
        'brazilmail.com',
        'chinamail.com',
        'dublin.com',
        'dutchmail.com',
        'englandmail.com',
        'europe.com',
        'arcticmail.com',
        'europemail.com',
        'germanymail.com',
        'irelandmail.com',
        'israelmail.com',
        'italymail.com',
        'koreamail.com',
        'mexicomail.com',
        'moscowmail.com',
        'munich.com',
        'asia.com',
        'polandmail.com',
        'safrica.com',
        'samerica.com',
        'scotlandmail.com',
        'spainmail.com',
        'swedenmail.com',
        'swissmail.com',
        'torontomail.com',
        'aircraftmail.com',
        'cash4u.com',
        'computer4u.com',
        'comic.com',
        'consultant.com',
        'contractor.net',
        'coolsite.net',
        'cyberservices.com',
        'disposable.com',
        'email.com',
        'execs.com',
        'fastservice.com',
        'greenmail.net',
        'groupmail.com',
        'instruction.com',
        'insurer.com',
        'job4u.com',
        'mail-me.com',
        'net-shopping.com',
        'post.com',
        'planetmail.com',
        'planetmail.net',
        'qualityservice.com',
        'rescueteam.com',
        'solution4u.com',
        'surgical.net',
        'tech-center.com',
        'theplate.com',
        'workmail.com',
        'webname.com',
        'writeme.com',
        'angelic.com',
        'atheist.com',
        'disciples.com',
        'minister.com',
        'muslim.com',
        'priest.com',
        'protestant.com',
        'reborn.com',
        'reincarnate.com',
        'religious.com',
        'saintly.com',
        'brew-meister.com',
        'cutey.com',
        'dbzmail.com',
        'doramail.com',
        'elvisfan.com',
        'galaxyhit.com',
        'hilarious.com',
        'humanoid.net',
        'hot-shot.com',
        'inorbit.com',
        'iname.com',
        'innocent.com',
        'keromail.com',
        'myself.com',
        'rocketship.com',
        'snakebite.com',
        'toothfairy.com',
        'toke.com',
        'tvstar.com',
        'uymail.com',
        '2trom.com',
    ];

    public function __construct(
        private string $projectDir,
        private Connection $database,
    ) {
    }

    public function calculateScore(FraudEvaluationState $state): int
    {
        $score = 0;
        $email = $state->companyParams['email'] ?? '';
        $emailParts = explode('@', $email);

        // Check for a blocked TLD
        [$username, $domain] = $emailParts;
        foreach (self::BLOCKED_TLDS as $tld) {
            if (substr($domain, -strlen($tld)) == $tld) {
                $state->addLine('Email domain ('.$domain.') has a banned TLD: '.$tld);
                $score += $state->blockScoreThreshold + 1;
                break;
            }
        }

        // Check for a blocked domain
        foreach (self::BLOCKED_EMAIL_DOMAINS as $blockedDomain) {
            if ($domain == $blockedDomain) {
                $state->addLine('Email domain is blocked: '.$domain);
                $score += $state->blockScoreThreshold + 1;
                break;
            }
        }

        // Check if a personal email domain
        $exactMatches = json_decode((string) file_get_contents($this->projectDir.'/config/personalEmails/index.json'));
        foreach ($exactMatches as $match) {
            if ($domain == $match) {
                $state->addLine('Detected personal email domain (no penalty): '.$domain);
                break;
            }
        }

        $wildcardMatches = json_decode((string) file_get_contents($this->projectDir.'/config/disposableEmails/wildcard.json'));
        foreach ($wildcardMatches as $match) {
            if ($domain == $match || substr($domain, -strlen($match) - 1) == '.'.$match) {
                $state->addLine('Detected disposable email domain from wildcard match on '.$match.': '.$domain);
                $score += $state->blockScoreThreshold + 1;
                break;
            }
        }

        // Check if email address matches other fraudulent accounts
        $count = (int) $this->database->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Companies')
            ->andWhere('email = :email')
            ->setParameter('email', $email)
            ->andWhere('fraud = 1')
            ->fetchOne();
        if ($count > 0) {
            $state->addLine('Email address '.$email.' is associated with '.$count.' fraudulent accounts');
            $score += $count;
        }

        // Notate if the email address matches a personal email domain (does not affect score)
        $exactMatches = json_decode((string) file_get_contents($this->projectDir.'/config/disposableEmails/index.json'));
        foreach ($exactMatches as $match) {
            if ($domain == $match) {
                $state->addLine('Detected disposable email domain from exact match: '.$domain);
                $score += $state->blockScoreThreshold + 1;
                break;
            }
        }

        // Check if email domain was used to create a fraudulent account in last 24 hours
        $count = (int) $this->database->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Companies')
            ->andWhere('email LIKE :domain')
            ->setParameter('domain', "%$domain%")
            ->andWhere('fraud = 1')
            ->andWhere('created_at >= :since')
            ->setParameter('since', CarbonImmutable::now()->subDay()->toDateTimeString())
            ->fetchOne();
        if ($count > 0) {
            $state->addLine('Email domain '.$domain.' has been used to create '.$count.' fraudulent accounts in the past 24 hours');
            $score += $count;
        }

        // Check for numbers in username (weak signal)
        if (preg_match('~[0-9]+~', $username)) {
            $state->addLine('Email username contains numbers: '.$username);
            ++$score;
        }

        return $score;
    }
}
