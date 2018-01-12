<?php

namespace Acacha\Stateful\Exceptions;

use Illuminate\Support\MessageBag;

/**
 * Class IllegalStateTransitionException.
 * 
 * @package Acacha\Stateful\Exceptions
 */
class IllegalStateTransitionException extends \LogicException
{
    /**
     * Messages related to this exception.
     * 
     * @var MessageBag
     */
    protected $message;

    /**
     * IllegalStateTransitionException constructor.
     * 
     * @param $messages
     */
    public function __construct(MessageBag $message)
    {
        $this->message = $message;
    }

    /**
     * Get messages. 
     * 
     * @return MessageBag
     */
    public function messages()
    {
        return $this->message;
    }

    
}