# Minibus - A tiny Command Bus

Minibus helps separate your interface from your implementation - like your REST handlers from your database calls.

It was inspired by [Grafana's command bus](https://github.com/grafana/grafana/blob/master/pkg/bus/bus.go) and
the [Tactician](http://tactician.thephpleague.com/) command bus library.

## Why do I need this?

What a command bus allows is the decoupling of *what* needs done from *how* it gets done. As systems get
more complex, keeping the interface separate from the implementation like this improves maintainability.

Ross Tuck gave an [exemplary talk](https://www.youtube.com/watch?v=ajhqScWECMo) outlining service layers
and command busses - I recommend you watch this if you think you need a command bus.

## Installation

Via composer: `composer require austinhyde/minibus`

# Usage

Minibus provides an extremely simple command bus implementation out of the box: `Minibus\Bus`.

This gives you everything you need to begin dispatching commands and publishing events:

```php
$bus = new Minibus\Bus;

$bus->addHandler(function(NewUserCommand $cmd) use ($db, $bus) {
  $row = $db->query("INSERT INTO users (username, email) VALUES (?, ?) RETURNING *",
    [$cmd->username, $cmd->email]);
  $user = User::fromRow($row);
  $bus->publish(new UserAddedEvent($user));
  return $user;
});

$bus->addListener(function(UserAddedEvent $event) use ($log) {
  $log->info("Added user: " . $event->getUser()->getUsername());
});

$router->get(function($req, $res) use ($bus) {
  $cmd = new NewUserCommand($req->body->username, $req->body->email);
  $user = $bus->dispatch($cmd);
  return $user->toJson();
});
```

`addHandler` and `addListener` take any callable as their first argument, and automatically infer the type of
command or event they'll be registered for by reflecting the type hint of the first parameter. If you want to
override the "type" of the command or event (not necessarily a class name), you can specify a type as the second
parameter.

`dispatch` and `publish` will accept any value as a command or event, although if they're an object, Minibus
will use the class name to automatically find the correct command handler or event listeners. Again, you can override
the "type" by passing it as the second parameter.
