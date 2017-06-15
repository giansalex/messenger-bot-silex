<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html.twig', array());
})
->bind('homepage')
;

$app->get('/webhook', function (Request $request) use ($app) {
    $q = $request->query;
    if ($q->get('hub.mode') === 'subscribe' &&
        $q->get('hub.verify_token') === 'YOUR_TOKEN') {
        return $q->get('hub.challenge');
    } else {
        $app->abort(403, 'Failed validation. Make sure the validation tokens match.');
    }
});

$app->post('/webhook', function (Request $request) use ($app) {
    $data = json_decode($request->getContent());

    // Make sure this is a page subscription
    if ($data->object === 'page') {
        foreach ($data->entry as $entry) {
            $pageID = $entry->id;
            $timeOfEvent = $entry->time;
            foreach ($entry->messaging as $event) {
                if ($event->message) {
                    //receivedMessage(event);
                } else if ($event->postback) {
                    //receivedPostback(event);
                } else {
                    //console.log("Webhook received unknown event: ", $event);
                }
            }
        }

    // Assume all went well.
    //
    // You must send back a 200, within 20 seconds, to let us know
    // you've successfully received the callback. Otherwise, the request
    // will time out and we will keep trying to resend.
  }
});

$app->error(function (\Exception $e, Request $request, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html.twig',
        'errors/'.substr($code, 0, 2).'x.html.twig',
        'errors/'.substr($code, 0, 1).'xx.html.twig',
        'errors/default.html.twig',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
