<?php
/**
 * @package
 * @subpackage
 */

/**
 * TITLE
 *
 * DESCRIPTION
 *
 * @author      Dave Marshall <david.marshall@atstsolutions.co.uk>
 */

$app = include __DIR__.'/app.php';
$ctx = new ZMQContext();
$sub = $ctx->getSocket(ZMQ::SOCKET_SUB);
$sub->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, '');
$sub->connect('tcp://localhost:5566');
$poll = new ZMQPoll();
$poll->add($sub, ZMQ::POLL_IN);
$read = $wri = array();
while(true) {
    $ev = $poll->poll($read, $wri, 5000000);
    if ($ev > 0) {
        list($eventName, $event) = unserialize($sub->recv());
        echo "Refiring $eventName\n";
        $app['dispatcher']->oneTimeDisableFireHose();
        $app['dispatcher']->dispatch($app['dispatcher.async_prefix'] .  $eventName, $event);
    }
}



