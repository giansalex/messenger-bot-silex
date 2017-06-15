<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//Request::setTrustedProxies(array('127.0.0.1'));

/**@var \Silex\Application $app*/
$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html.twig', array());
})
->bind('homepage')
;

$app->get('/webhook', function (Request $request) use ($app) {
    $q = $request->query;
    if ($q->get('hub.mode') === 'subscribe' &&
        $q->get('hub.verify_token') === $app['VERIFY_TOKEN']
    ) {
        $app['fblog']->info('Validating webhook');
        return $q->get('hub.challenge');
    } else {
        $app['fblog']->error('Failed validation. Make sure the validation tokens match.');
        $app->abort(403);
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
                    receivedMessage($event);
                } else if ($event->postback) {
                    receivedPostback($event);
                } else {
                    $app['fblog']->warning('Webhook received unknown event: ', [$event]);
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

function receivedMessage($event) {
    global $app;
    $senderID = $event->sender->id;
    $recipientID = $event->recipient->id;
    $timeOfMessage = $event->timestamp;
    $message = $event->message;

    $app['fblog']->info(sprintf('Received message for user %d and page %d at %d with message:',
        $senderID, $recipientID, $timeOfMessage));

    $app['fblog']->info($app['serializer']->serialize($message, 'json'));

    //$messageId = $message->mid;

    $messageText = $message->text;
    $messageAttachments = $message->attachments;

    if ($messageText) {
        // If we receive a text message, check to see if it matches a keyword
        // and send back the template example. Otherwise, just echo the text we received.
        switch ($messageText) {
            case 'generic':
                sendGenericMessage($senderID);
                break;

            default:
                sendTextMessage($senderID, $messageText);
        }
    } else if ($messageAttachments) {
        sendTextMessage($senderID, "Message with attachment received");
    }
}

function receivedPostback($event) {
    global $app;

    $senderID = $event->sender->id;
    $recipientID = $event->recipient->id;
    $timeOfPostback = $event->timestamp;

    // The 'payload' param is a developer-defined field which is set in a postback
    // button for Structured Messages.
    $payload = $event->postback->payload;

    $app['fblog']->info(sprintf("Received postback for user %d and page %d with payload '%s' at %d",
        $senderID, $recipientID, $payload, $timeOfPostback));

    // When a postback is called, we'll send a message back to the sender to
    // let them know it was successful
    sendTextMessage($senderID, "Postback called");
}
function sendTextMessage($recipientId, $messageText) {
    $messageData = [
        'recipient'=> [
            'id'=> $recipientId
        ],
        'message'=> [
            'text' =>  $messageText
        ]
    ];

  callSendAPI($messageData);
}
function sendGenericMessage($recipientId) {
    $messageData = [
        'recipient'=> [
            'id'=> $recipientId
        ],
        'message'=> [
            'attachment'=> [
                'type'=> "template",
                'payload'=> [
                    'template_type'=> "generic",
                    'elements'=> [[
                        'title'=> "rift",
                        'subtitle'=> "Next-generation virtual reality",
                        'item_url'=> "https://www.oculus.com/en-us/rift/",
                        'image_url'=> "http://messengerdemo.parseapp.com/img/rift.png",
                        'buttons'=> [[
                            'type'=> "web_url",
                            'url'=> "https://www.oculus.com/en-us/rift/",
                            'title'=> "Open Web URL"
                        ], [
                            'type'=> "postback",
                            'title'=> "Call Postback",
                            'payload'=> "Payload for first bubble",
                        ]],
                    ], [
                        'title'=> "touch",
                        'subtitle'=> "Your Hands, Now in VR",
                        'item_url'=> "https://www.oculus.com/en-us/touch/",
                        'image_url'=> "http://messengerdemo.parseapp.com/img/touch.png",
                        'buttons'=> [[
                            'type'=> "web_url",
                            'url'=> "https://www.oculus.com/en-us/touch/",
                            'title'=> "Open Web URL"
                        ], [
                            'type'=> "postback",
                            'title'=> "Call Postback",
                            'payload'=> "Payload for second bubble",
                        ]]
                    ]]
                ]
            ]
        ]
    ];
    callSendAPI($messageData);
}
function callSendAPI($messageData) {
    global $app;
    $uri = 'https://graph.facebook.com/v2.6/me/messages?access_token' . $app['PAGE_ACCESS_TOKEN'];
    $response = \Httpful\Request::post($uri)
        ->addHeader('Content-Type', 'application/json')
        ->body(json_encode($messageData))
        ->send();

    if ($response->code == 200) {
        $body = json_decode($response->raw_body);
        $recipientId = $body->recipient_id;
        $messageId = $body->message_id;
        $app['fblog']->info(sprintf('Successfully sent generic message with id %s to recipient %s', $messageId, $recipientId));
    } else {
        $app['fblog']->error('Unable to send message.');
    }
}