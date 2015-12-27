<?php
namespace Minibus\Tests;

use InvalidArgumentException;
use LogicException;
use Minibus\Bus;

class TestCmd
{}
class TestCmd2
{}

class BusTest extends \PHPUnit_Framework_TestCase
{

    /** Tests that passing an explicit type to addHandler overrides the reflection-derived type */
    public function testAddHandlerUsesTypeHintParam()
    {
        $bus = new Bus;
        $bus->addHandler($this->getHandler(TestCmd::class), 'Foo');

        $cmd = new TestCmd;
        $this->assertEquals($cmd, $bus->dispatch($cmd, 'Foo'));
    }

    /** Tests that addHandler throws an error when it can't figure out what type it should use */
    public function testAddHandlerThrowsWhenAmbiguous()
    {
        $this->setExpectedException(InvalidArgumentException::class,
            "The handler given did not have an explicit type hint, and no typeHint was given");

        $bus = new Bus;
        $bus->addHandler($this->getHandler());
    }

    /** Tests that addHandler throws when you try to add multiple handlers for the same type */
    public function testAddHandlerThrowsForDuplicateType()
    {
        $this->setExpectedException(InvalidArgumentException::class,
            "A handler has already been registered for type " . TestCmd::class);

        $bus = new Bus;
        $bus->addHandler($this->getHandler(TestCmd::class));
        $bus->addHandler($this->getHandler(TestCmd::class));
    }

    /** Tests that passing an explicit type to addListener overrides the reflection-derived type */
    public function testAddListenerUsesTypeHintParam()
    {
        $bus = new Bus;
        $called = false;
        $bus->addListener(function (TestCmd $cmd) use (&$called) {
            $called = true;
        }, 'Foo');

        $cmd = new TestCmd;
        $bus->publish($cmd, 'Foo');
        $this->assertTrue($called);
    }

    /** Tests that addListener throws an error when it can't figure out what type it should use */
    public function testAddListenerThrowsWhenAmbiguous()
    {
        $this->setExpectedException(InvalidArgumentException::class,
            "The handler given did not have an explicit type hint, and no typeHint was given");

        $bus = new Bus;
        $bus->addListener($this->getHandler());
    }

    /** Tests that dispatch throws when no handler has been registered for the command type */
    public function testDispatchThrowsWithNoHandlers()
    {
        $this->setExpectedException(LogicException::class,
            "No handler has been registered for type " . TestCmd::class);

        $bus = new Bus;
        $bus->dispatch(new TestCmd);
    }

    /** Tests that dispatch uses the provided typehint to find the correct handler */
    public function testDispatchWithTypeHint()
    {
        $bus = new Bus;
        $bus->addHandler($this->getHandler(), 'x');

        $this->assertEquals('asdf', $bus->dispatch('asdf', 'x'));
    }

    /** Tests that dispatch throws when it can't figure out what command type you gave it */
    public function testDispatchAmbiguous()
    {
        $this->setExpectedException(InvalidArgumentException::class,
            "The command given was not an object, and no typeHint was given");

        $bus = new Bus;
        $bus->dispatch('x');
    }

    /** Tests that publish uses the provided typehint to find the correct list of listeners */
    public function testPublishWithTypeHint()
    {
        $bus = new Bus;
        $called = false;
        $bus->addListener(function ($cmd) use (&$called) {
            $called = true;
        }, 'x');

        $bus->publish('asdf', 'x');
        $this->assertTrue($called);
    }

    /** Tests that publish throws when it can't figure out what command type you gave it */
    public function testPublishAmbiguous()
    {
        $this->setExpectedException(InvalidArgumentException::class,
            "The command given was not an object, and no typeHint was given");

        $bus = new Bus;
        $bus->publish('x');
    }

    /** Tests that dispatch dispatches different commands to different handlers */
    public function testDispatchMultipleTypes()
    {
        $bus = new Bus;
        $bus->addHandler($this->getHandler(TestCmd::class));
        $bus->addHandler($this->getHandler(TestCmd2::class));

        $cmd = new TestCmd;
        $this->assertEquals($cmd, $bus->dispatch($cmd));

        $cmd2 = new TestCmd2;
        $this->assertEquals($cmd, $bus->dispatch($cmd));
    }

    /** Tests that publish will call all its registered listeners in the order they were registered */
    public function testPublishCallsAllListeners()
    {
        $bus = new Bus;
        $called = [false, false];
        $bus->addListener(function (TestCmd $cmd) use (&$called) {
            $called[0] = true;
        });
        $bus->addListener(function (TestCmd $cmd) use (&$called) {
            $called[1] = $called[0];
        });

        $cmd = new TestCmd;
        $bus->publish(new TestCmd);
        $this->assertTrue($called[0]);
        $this->assertTrue($called[1]);
    }

    /** Tests that publish will call different lists of listeners for different event types */
    public function testPublishMultipleTypes()
    {
        $bus = new Bus;
        $called = [false, false];
        $bus->addListener(function (TestCmd $cmd) use (&$called) {
            $called[0] = true;
        });
        $bus->addListener(function (TestCmd2 $cmd) use (&$called) {
            $called[1] = true;
        });

        $cmd = new TestCmd;
        $bus->publish(new TestCmd);
        $bus->publish(new TestCmd2);
        $this->assertTrue($called[0]);
        $this->assertTrue($called[1]);
    }

    /** Tests that any wildcard listeners are called after all specific listeners have been called */
    public function testPublishWildcardsCalledAfterListeners()
    {
        $bus = new Bus;
        $called = [false, false, false];
        $bus->addListener(function (TestCmd $cmd) use (&$called) {
            $called[0] = true;
        });
        $bus->addWildcard(function ($cmd) use (&$called) {
            $called[2] = $called[0] && $called[1];
        });
        $bus->addListener(function (TestCmd $cmd) use (&$called) {
            $called[1] = $called[0];
        });

        $cmd = new TestCmd;
        $bus->publish($cmd);
        $this->assertTrue($called[0]);
        $this->assertTrue($called[1]);
        $this->assertTrue($called[2]);
    }

    private function getHandler($type = null)
    {
        if ($type === null) {
            $type = '';
        }

        return eval("return function($type \$cmd) { return \$cmd; };");
    }
}
