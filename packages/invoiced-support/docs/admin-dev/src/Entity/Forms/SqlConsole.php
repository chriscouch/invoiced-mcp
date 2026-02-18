<?php

namespace App\Entity\Forms;

use Symfony\Component\Validator\Constraints as Assert;

class SqlConsole
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/(^|\s)(insert|update|delete|truncte)($|\s)/i', match: false, message: 'The only allowed commands are: SELECT, SHOW, EXPLAIN, and DESCRIBE')]
    #[Assert\Regex(pattern: '/^(select|show|describe|explain)\s/i', message: 'The only allowed commands are: SELECT, SHOW, EXPLAIN, and DESCRIBE')]
    #[Assert\Regex(pattern: '/;/', match: false, message: "The query can't contain ';'")]
    private string $sql = '';

    public function toString(): string
    {
        $parts = [];

        if ($this->sql) {
            $parts['SQL'] = $this->sql;
        }

        return join(', ', array_map(function ($v, $k) {
            return "$k: $v";
        }, $parts, array_keys($parts)));
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function setSql(string $sql): self
    {
        $this->sql = trim(trim($sql), ';');

        return $this;
    }
}
