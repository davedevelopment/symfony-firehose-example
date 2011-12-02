symfony-firehose-example
========================

A quick example for a blog post about queueing symfony events and refiring them
asynchronously.

Blog Post: 

Usage
-----

    $ git clone git@github.com:davedevelopment/symfony-firehose-example.git
    $ cd symfony-firehose-example
    $ wget http://silex.sensiolabs.org/get/silex.phar
    $ php server.php < /dev/null &
    $ php refire.php < /dev/null &
    $ curl -d '{"comment":"You crazy"}' http://localhost/symfony-firehost-example/blog/1/comments
