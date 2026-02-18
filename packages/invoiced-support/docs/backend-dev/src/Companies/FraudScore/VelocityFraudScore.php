<?php

namespace App\Companies\FraudScore;

use App\Companies\Interfaces\FraudScoreInterface;
use App\Companies\ValueObjects\FraudEvaluationState;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

/**
 * Calculates a fraud likelihood score based on
 * how many sign ups have happened recently for
 * the entire site and for different clusters,
 * e.g. IP address, email domain, and company name.
 */
class VelocityFraudScore implements FraudScoreInterface
{
    /**
     * When the recent number of sign ups is at least this many
     * standard deviations away from the mean then it will increase the score.
     */
    private const WARN_STD_DEV = 5;

    /**
     * The number of days to look back for global hourly sign up volume.
     */
    private const GLOBAL_LOOKBACK_DAYS = 30;

    public function __construct(
        private Connection $database,
        private string $projectDir,
    ) {
    }

    public function calculateScore(FraudEvaluationState $state): int
    {
        return $this->globalVelocityCheck($state) + $this->clusterVelocityCheck($state);
    }

    private function globalVelocityCheck(FraudEvaluationState $state): int
    {
        // Get the number of sign ups per hour for the past N days up to the previous hour
        $data = $this->database->fetchFirstColumn('SELECT COUNT(*) FROM Companies WHERE created_at BETWEEN :start AND :end AND fraud=0 GROUP BY DATE_FORMAT(created_at, "%Y-%m-%d %H")', [
            'start' => CarbonImmutable::now()->subHour()->subDays(self::GLOBAL_LOOKBACK_DAYS)->toDateTimeString(),
            'end' => CarbonImmutable::now()->subHour()->toDateTimeString(),
        ]);
        $data = array_pad($data, self::GLOBAL_LOOKBACK_DAYS * 24, 0); // zero pad to N days of data

        $stdDev = $this->stdDev($data);
        $mean = round($this->mean($data));
        $state->addLine('Mean Sign Ups/Hour: '.$mean.'. Standard Deviation: '.$stdDev);

        // Only run this check if there is a standard deviation
        if (!$stdDev) {
            return 0;
        }

        // Get the number of sign ups in the last hour
        $signUpsLastHour = (int) $this->database->fetchOne('SELECT COUNT(*) FROM Companies WHERE created_at BETWEEN :start AND :end', [
            'start' => CarbonImmutable::now()->subHour()->toDateTimeString(),
            'end' => CarbonImmutable::now()->toDateTimeString(),
        ]);

        // Use a minimum standard deviation of 1 to calculate
        // deviations from mean. Otherwise the numbers are too
        // small. We want to permit at minimum 5 sign ups per hour.
        $stdDev = max(1, $stdDev);
        $stdDevsFromMean = max(0, round(($signUpsLastHour - $mean) / $stdDev));
        $state->addLine('Sign Ups Past Hour: '.$signUpsLastHour.'. Standard Deviations from Mean: '.$stdDevsFromMean);

        return (int) floor($stdDevsFromMean / self::WARN_STD_DEV);
    }

    private function clusterVelocityCheck(FraudEvaluationState $state): int
    {
        $score = 0;

        // Company Name
        $name = $state->companyParams['name'] ?? '';
        if ($name) {
            $n = $this->getCompaniesWithName($name, CarbonImmutable::now()->subWeek());
            if ($n > 0) {
                $state->addLine("$n companies with same name created in last week: $name");
                $score += $n;
            }
        }

        // IP Address
        $ip = $state->requestParams['ip'] ?? '';
        if ($ip) {
            $n = $this->getCompaniesWithIpAddress($ip, CarbonImmutable::now()->subHour());
            if ($n > 0) {
                $state->addLine("$n companies with IP address created in last hour: $ip");
                $score += $n;
            }
        }

        // User Agent
        $userAgent = $state->requestParams['user_agent'] ?? '';
        if ($userAgent) {
            $n = $this->getCompaniesWithUserAgent($userAgent, CarbonImmutable::now()->subHour());
            if ($n > 5) { // exclude first 5 with same user agent
                $state->addLine("$n companies with user agent created in last hour: $userAgent");
                $score += $n;
            }
        }

        // Email Domain
        $email = $state->companyParams['email'] ?? '';
        [, $domain] = explode('@', $email);
        if ($domain) {
            // Check if a personal email domain
            $exactMatches = json_decode((string) file_get_contents($this->projectDir.'/config/personalEmails/index.json'));
            $isPersonal = false;
            foreach ($exactMatches as $match) {
                if ($domain == $match) {
                    $isPersonal = true;
                    break;
                }
            }

            $n = $this->getCompaniesWithEmailDomain($domain, CarbonImmutable::now()->subHour());
            if ($n > 0) {
                $state->addLine("$n companies with @$domain email address created in last hour");
            }

            // Apply penalty after first 3 with same email domain as long as not personal name
            if ($n > 3 && !$isPersonal) {
                $score += $n;
            }
        }

        return $score;
    }

    /**
     * Get count with the same company names.
     */
    private function getCompaniesWithName(string $name, CarbonImmutable $since): int
    {
        return (int) $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Companies')
            ->andWhere('name = :name')
            ->setParameter('name', $name)
            ->andWhere('created_at >= :createdAt')
            ->setParameter('createdAt', $since->toDateTimeString())
            ->fetchOne();
    }

    private function getCompaniesWithEmailDomain(string $domain, CarbonImmutable $since): int
    {
        return (int) $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Companies')
            ->andWhere('email LIKE :email')
            ->setParameter('email', "%@$domain")
            ->andWhere('created_at >= :createdAt')
            ->setParameter('createdAt', $since->toDateTimeString())
            ->fetchOne();
    }

    private function getCompaniesWithUserAgent(string $userAgent, CarbonImmutable $since): int
    {
        return (int) $this->database->createQueryBuilder()
            ->select('count(distinct c.id)')
            ->from('Companies', 'c')
            ->join('c', 'Users', 'u', 'creator_id=u.id')
            ->rightJoin('u', 'AccountSecurityEvents', 's', 's.user_id=u.id')
            ->andWhere('s.user_agent = :userAgent')
            ->setParameter('userAgent', $userAgent)
            ->andWhere('c.created_at >= :createdAt')
            ->setParameter('createdAt', $since->toDateTimeString())
            ->fetchOne();
    }

    private function getCompaniesWithIpAddress(string $ip, CarbonImmutable $since): int
    {
        return (int) $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Companies', 'c')
            ->join('c', 'Users', 'u', 'creator_id=u.id')
            ->andWhere('u.ip = :ip')
            ->setParameter('ip', $ip)
            ->andWhere('c.created_at >= :createdAt')
            ->setParameter('createdAt', $since->toDateTimeString())
            ->fetchOne();
    }

    /**
     * Calculates standard deviation for a population.
     */
    private function stdDev(array $data): float
    {
        $n = count($data);
        if (!$n) {
            return 0;
        }

        $mean = array_sum($data) / $n;
        $distance_sum = 0;
        foreach ($data as $i) {
            $distance_sum += ($i - $mean) ** 2;
        }

        $variance = $distance_sum / $n;

        return sqrt($variance);
    }

    /**
     * Calculates mean.
     */
    private function mean(array $data): float
    {
        $n = count($data);
        if (!$n) {
            return 0;
        }

        return array_sum($data) / $n;
    }
}
