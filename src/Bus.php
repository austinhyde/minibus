<?php
namespace Minibus;

use InvalidArgumentException;
use LogicException;
use ReflectionFunction;

class Bus implements CommandBusInterface {
	private $handlers = [];
	private $listeners = [];
	private $wildcards = [];

	/**
	 * Registers a handler for a particular command type.
	 *
	 * The type is derived from the type hint of the first argument of the callable, and is
	 * overridden by the $typeHint argument. Only one handler of a particular type may be
	 * registered.
	 *
	 * @param callable $handler
	 * @param string   $typeHint  Optional.
	 */
	public function addHandler(callable $handler, $typeHint = null) {
		$type = $this->getHandlerType($handler, $typeHint);

		if (array_key_exists($type, $this->handlers)) {
			throw new InvalidArgumentException("A handler has already been registered for type $type");
		}

		$this->handlers[$type] = $handler;
		return $this;
	}

	/**
	 * Registers an event listener for a particular event type.
	 *
	 * The type is derived from the type hint of the first argument of the callable, and
	 * is overridden by the $typeHint argument. Any number of listeners by be registered
	 * for a particular event type, and they are invoked in the order they are registered.
	 * Their returns are discarded.
	 *
	 * @param callable $listener
	 * @param string   $typeHint  Optional.
	 */
	public function addListener(callable $listener, $typeHint = null) {
		$type = $this->getHandlerType($listener, $typeHint);

		if (array_key_exists($type, $this->listeners)) {
			$this->listeners[$type][] = $listener;
		} else {
			$this->listeners[$type] = [$listener];
		}

		return $this;
	}

	/**
	 * Registers an event listener for any event type.
	 *
	 * Any number of wildcard listeners may be registered, and they will be invoked in the order
	 * they are registered, after all normal event listeners have fired. Their returns are discarded.
	 *
	 * @param callable $listener
	 */
	public function addWildcard(callable $listener) {
		$this->wildcards[] = $listener;
		return $this;
	}

	/**
	 * Dispatch a command.
	 *
	 * The command type is derived from the class name of the given command, and may be
	 * overridden by the $typeHint parameter. This will find the registered handler for
	 * the command type, invoke it with the command, and return its result.
	 *
	 * @param  mixed  $command
	 * @param  string $typeHint
	 * @return mixed
	 */
	public function dispatch($command, $typeHint = null) {
		$type = $this->getCommandType($command, $typeHint);
		if (!array_key_exists($type, $this->handlers)) {
			throw new LogicException("No handler has been registered for type $type");
		}

		return call_user_func($this->handlers[$type], $command);
	}

	/**
	 * Publishes an event.
	 *
	 * The event type is derived from the class name of the given event, and may be
	 * overridden by the $typeHint parameter. This will find the list of registered event
	 * listeners for the event type, invoke them in the order they were defined with the
	 * event, then invoke all wildcard listeners in the order they were registered. All
	 * listener returns will be discarded.
	 *
	 * @param  mixed  $event
	 * @param  string $typeHint
	 */
	public function publish($event, $typeHint = null) {
		$type = $this->getCommandType($event, $typeHint);
		if (array_key_exists($type, $this->listeners)) {
			foreach ($this->listeners[$type] as $listener) {
				call_user_func($listener, $event);
			}
		}
		foreach ($this->wildcards as $wildcard) {
			call_user_func($wildcard, $event);
		}
	}

	/** Resolves the type of command/event the handler is for */
	private function getHandlerType(callable $handler, $typeHint) {
		if (is_string($typeHint)) {
			return $typeHint;
		}

		if (is_array($handler)) {
			$rf = new ReflectionMethod($handler[0], $handler[1]);
		} else {
			$rf = new ReflectionFunction($handler);
		}

		$params = $rf->getParameters();
		if (count($params) == 0 || $params[0]->getClass() === null) {
			throw new InvalidArgumentException("The handler given did not have an explicit type hint, and no typeHint was given");
		}

		return $params[0]->getClass()->getName();
	}

	/** Resolves the type of the command/event */
	private function getCommandType($command, $typeHint) {
		if (is_string($typeHint)) {
			return $typeHint;
		}
		if (!is_object($command)) {
			throw new InvalidArgumentException("The command given was not an object, and no typeHint was given");
		}
		return get_class($command);
	}
}