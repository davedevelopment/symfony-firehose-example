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

require_once __DIR__.'/silex.phar';

use Silex\Application;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\Event;
use Atst\Symfony\Component\EventDispatcher\Decorator\FireHose;

$app = new Application();

$app['autoloader']->registerNamespace('Atst', __DIR__.'/vendor/atst/src');

$app['base_dispatcher'] = $app['dispatcher'];
$app['dispatcher.async_prefix'] = 'async.';
$app['dispatcher'] = $app->share(function($c) {
    $firehose = new FireHose($c['base_dispatcher']);
    return $firehose;
});

$app['dispatcher']->addFireHoseListener(function($eventName, Event $event) use ($app) {
    /**
     * Dont bother with the silex or kernel events, in real life you'd probably
     * want to switch this on class type of the event
     */ 
    if (0 !== strpos($eventName, 'kernel.') && 0 !== strpos($eventName, 'silex.')) { 
        $app['dispatcher.queue.pub']->send(serialize(array($eventName, $event)));
    }
});

$app['dispatcher.queue.pub.dsn'] = 'tcp://localhost:5567';
$app['dispatcher.queue.sub.dsn'] = 'tcp://localhost:5566';
$app['dispatcher.queue.pub'] = $app->share(function($c) {
    $ctx = new ZMQContext();
    $send = $ctx->getSocket(ZMQ::SOCKET_PUSH);
    $send->connect($c['dispatcher.queue.pub.dsn']);
    return $send;
});

$app['author'] = "dave.marshall@atstsolutions.co.uk";
$app['posts'] = array(
    1 => array(
        'id' => 1,
        'title' => 'Hello World',
        'date'  => new DateTime('2011-12-01 10:12:45'),
        'content' => 'Hello World',
        'comments' => array(
            1 => array(
                'id' => 1,
                'comment' => 'Great post',
            ),
        ),
    ),
    2 => array(
        'id' => 2,
        'title' => 'Second Post',
        'date'  => new DateTime('2011-12-02 10:12:45'),
        'content' => 'Blah Blah Blah',
        'comments' => array(),
    ),
);

/**
 * Used to turn a post id into a post
 */
$postLookup = function($id) use ($app) {
    if (!array_key_exists($id, $app['posts'])) {
        $app->abort(404, 'Post not found');
    }

    return $app['posts'][$id];
};

/**
 * Get a post
 */
$app->get('/blog/{post}', function(Application $app, array $post) {
    return new Response(
        json_encode($post), 
        200, 
        array('Content-type' => 'application/json')
    );
})->convert('post', $postLookup);

/**
 * Get a specific comment
 */
$app->get('/blog/{post}/comment/{comment}', function(Application $app, array $post, $comment) {

    if (!array_key_exists($comment, $post['comments'])) {
        $app->abort(404, 'Comment not found');
    }

    return new Response(
        json_encode($post['comments'][$comment]), 
        200, 
        array('Content-type' => 'application/json')
    );
})->convert('post', $postLookup);


/**
 * Send an email to the author on new comments
 */
$app['dispatcher']->addListener($app['dispatcher.async_prefix'] . 'blog.comments.new', function(Event $event) use ($app) {

    if (php_sapi_name() == 'cli') {
        echo "Sending mail...\n";
    }

    mail($app['author'], 'New comment on ' . $event->post['title'], $event->comment['comment']);
});

/**
 * Add a comment
 */
$app->post('/blog/{post}/comments', function(Application $app, array $post) {

    $id = !empty($post['comments']) ? max(array_keys($post['comments'])) + 1 : 1;
    $comment = json_decode($app['request']->getContent(), true);
    $comment['id'] = $id;
    $post['comments'][$id] = $comment;

    /**
     * Send an email to the author
     */
    $event = new Event;
    $event->post = $post;
    $event->comment = $comment;
    $app['dispatcher']->dispatch('blog.comments.new', $event);

    return new Response(
        json_encode($comment) . "\n", 
        201, 
        array(
            'Content-type' => 'application/json',
            'Location' => "/blog/{$post['id']}/comment/{$id}",
        )
    );
})->convert('post', $postLookup);

return $app;
