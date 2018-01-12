<?php

namespace Acacha\Stateful\Traits;
use Acacha\Stateful\Events\Registered;
use Acacha\Stateful\Events\Transitioned;
use Acacha\Stateful\Exceptions\IllegalStateTransitionException;
use Acacha\Stateful\Exceptions\NotValidStatesException;
use Acacha\Stateful\Exceptions\NotValidTransitionsException;
use Illuminate\Support\MessageBag;

/**
 * Class StatefulTrait.
 *
 * @package Acacha\Stateful
 */
trait StatefulTrait
{

    /**
     * Error messages on last transition.
     *
     * @var MessageBag
     */
    protected $errorMessages;

    /**
     * Get error messages.
     *
     * @return MessageBag
     */
    public function errors()
    {
        return $this->errorMessages;
    }

    /**
     * StatefulTrait constructor.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->errorMessages = new MessageBag();
        parent::__construct($attributes);
    }

    /**
     * Obtain transitions.
     *
     * @return mixed
     */
    public function obtainTransitions()
    {
        if ($this->transitions != null) return $this->transitions;
        throw new NotValidTransitionsException('No transitions defined for class ' . get_class($this) );
    }

    /**
     * Obtain states.
     *
     * @return mixed
     */
    public function obtainStates()
    {
        if ($this->states != null) return $this->states;
        throw new NotValidStatesException('No states defined for class ' . get_class($this) );
    }

    /**
     * Overload methods.
     * 
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (array_key_exists($method, $this->obtainTransitions())) {
            return $this->performTransition($method);
        } elseif (array_key_exists($method, $this->obtainStates()) || in_array($method, $this->obtainStates())) {
            return $this->isState($method);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Determine if the state of the model is the given state.
     * 
     * @param $state
     * @return bool
     */
    private function isState($state)
    {
        return $state === $this->{$this->getStateColumn()};
    }

    /**
     * Perform a state transition.
     * 
     * @param $transition
     * @return mixed
     * @throws IllegalStateTransitionException
     */
    private function performTransition($transition)
    {
        $to = $this->obtainTransitions()[$transition]['to'];

        if ($this->canPerformTransition($transition)) {
            $this->executeBeforeTransitionHook($transition);
            $result = $this->updateState($to);
            $this->executeAfterTransitionHook($transition);
            event(new Transitioned($this,$this->{$this->getStateColumn()},$to));
            return $result;
        }
        throw new IllegalStateTransitionException($this->errorMessages);
        
    }

    /**
     * Update state.
     *
     * @param $state
     * @return mixed
     */
    protected function updateState($state) {
        return $this->update([$this->getStateColumn() => $state]);
    }

    /**
     * Execute if exists hook before transition.
     *
     * @param string $transition
     */
    protected function executeBeforeTransitionHook($transition)
    {
        $this->executeHook($transition,'before');
    }

    /**
     * Execute if exists hook after transition.
     *
     * @param string $transition
     */
    protected function executeAfterTransitionHook($transition)
    {
        $this->executeHook($transition,'after');
    }

    /**
     * Execute hook.
     *
     * @param string $transition
     * @param string $hook
     */
    protected function executeHook($transition,$hook)
    {
        if (method_exists($this, $method = $hook . studly_case($transition))) {
            $this->{$method}();
        }
    }

    /**
     * Determine if we can perform the state transition.
     *
     * @param  string $transition
     * @return boolean
     */
    protected function canPerformTransition($transition)
    {
        $from = $this->obtainTransitions()[$transition]['from'];
        $currentState = $this->{$this->getStateColumn()};

        if(method_exists($this, $method = 'validate' . studly_case($transition) ))
        {
            if ($this->{$method}() === false ) {
                return false;
            }
        }

        return $this->checkTransitionBetweenStatesIsAllowed($from, $currentState);
    }

    /**
     * @param $from
     * @param $currentState
     * @return bool
     */
    protected function checkTransitionBetweenStatesIsAllowed($from, $currentState)
    {
        $result = is_array($from) ? in_array($currentState, $from) : $currentState === $from;
        if ($result === false) {
            $this->addErrorMessage(
                'transitionNotDefined',
                is_array($from) ? 'There is no transition defined between states [' . implode(", ", $from) . '] and ' . $currentState :
                    'There is no transition defined between state ' . $from . ' and ' . $currentState);
        }
        return $result;
    }

    protected function addErrorMessage($name,$message) {
        $this->errorMessages->add($name,$message);
    }

    /**
     * Set the initial state.
     *
     * @return void
     */
    public function setInitialState()
    {
        $this->setAttribute($this->getStateColumn(), $this->getInitialState());
    }

    /**
     * Get the initial state.
     * 
     * @return bool|int|string
     */
    public function getInitialState()
    {
        foreach ($this->obtainStates() as $state => $value) {
            if ($value['initial']) {
               return $state;
            }
        }

        return false;
    }

    /**
     * Get the state attribute name.
     * @return string
     */
    public function getStateColumn()
    {
        return defined('static::STATE') ? static::STATE : 'state';
    }

}