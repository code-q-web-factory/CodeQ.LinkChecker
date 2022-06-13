<?php

declare(strict_types=1);

namespace CodeQ\LinkChecker\Domain\Model;

use DateTime;
use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;

/**
 * @Flow\Entity
 */
class ResultItem
{
    /**
     * @var string
     */
    protected string $domain;

    /**
     * @var string
     */
    protected string $source;

    /**
     * @var string
     */
    protected string $target;

    /**
     * @var integer
     */
    protected int $statusCode;

    /**
     * @var boolean
     */
    protected bool $done = false;

    /**
     * @ORM\Column(name="`ignore`")
     * @var boolean
     * ignore is a reserved mysql word, therefor escape it manually
     */
    protected bool $ignore = false;

    /**
     * @var DateTime
     */
    protected DateTime $createdAt;

    /**
     * @var DateTime
     */
    protected DateTime $checkedAt;

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @param string $domain
     * @return void
     */
    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @param string $source
     * @return void
     */
    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @param string $target
     * @return void
     */
    public function setTarget(string $target): void
    {
        $this->target = $target;
    }

    /**
     * @return integer
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @param integer $statusCode
     * @return void
     */
    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return boolean
     */
    public function getDone(): bool
    {
        return $this->done;
    }

    /**
     * @param boolean $done
     * @return void
     */
    public function setDone(bool $done): void
    {
        $this->done = $done;
    }

    /**
     * @return boolean
     */
    public function getIgnore(): bool
    {
        return $this->ignore;
    }

    /**
     * @param boolean $ignore
     * @return void
     */
    public function setIgnore(bool $ignore): void
    {
        $this->ignore = $ignore;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     * @return void
     */
    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return DateTime
     */
    public function getCheckedAt(): DateTime
    {
        return $this->checkedAt;
    }

    /**
     * @param DateTime $checkedAt
     * @return void
     */
    public function setCheckedAt(DateTime $checkedAt): void
    {
        $this->checkedAt = $checkedAt;
    }
}
