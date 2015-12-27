<?php
namespace Minibus;

interface CommandBusInterface {
	/** Dispatch a command to a handler */
	public function dispatch($command, $typeHint = null);

	/** Publish an event to any listeners */
	public function publish($event, $typeHint = null);
}