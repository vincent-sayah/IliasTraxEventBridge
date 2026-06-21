<?php

class ilIliasTraxEventBridgeHttpResult
{
    /** @var bool */
    private $success;

    /** @var int */
    private $httpStatus;

    /** @var string */
    private $body;

    /** @var string */
    private $error;

    public function __construct(bool $success, int $httpStatus, string $body = '', string $error = '')
    {
        $this->success = $success;
        $this->httpStatus = $httpStatus;
        $this->body = $body;
        $this->error = $error;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getShortMessage(): string
    {
        if ($this->success) {
            return 'HTTP ' . $this->httpStatus . ' OK';
        }

        $message = 'HTTP ' . $this->httpStatus;
        if ($this->error !== '') {
            $message .= ' - ' . $this->error;
        } elseif ($this->body !== '') {
            $message .= ' - ' . substr($this->body, 0, 500);
        }

        return $message;
    }
}
