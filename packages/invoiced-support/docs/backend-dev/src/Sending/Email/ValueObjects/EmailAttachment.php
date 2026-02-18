<?php

namespace App\Sending\Email\ValueObjects;

class EmailAttachment
{
    /** @var string|callable */
    private $content;
    private int $size = 0;

    /**
     * @param callable|string $content
     */
    public function __construct(private string $filename, private string $type, $content)
    {
        $this->content = $content;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Gets the file size in bytes. We get the size with
     * base64 encoding because the email services that
     * we use encode attachments with base64 encoding.
     */
    public function getEncodedSize(): int
    {
        // If the size is 0 bytes then that means
        // that the file has not been built yet.
        // We must generate the contents and get
        // the size from that.
        if (!$this->size) {
            $this->size = strlen(base64_encode($this->getContent()));
        }

        return $this->size;
    }

    public function getContent(): string
    {
        if (is_callable($this->content)) {
            $this->content = call_user_func($this->content);
        }

        return $this->content;
    }

    /**
     * Only used for testing.
     */
    public function setSize(int $size): void
    {
        $this->size = $size;
    }
}
