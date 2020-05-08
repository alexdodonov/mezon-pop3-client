# Mezon POP3 Client
[![Build Status](https://travis-ci.org/alexdodonov/mezon-pop3-client.svg?branch=master)](https://travis-ci.org/alexdodonov/mezon-pop3-client) [![codecov](https://codecov.io/gh/alexdodonov/mezon-pop3-client/branch/master/graph/badge.svg)](https://codecov.io/gh/alexdodonov/mezon-pop3-client) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/alexdodonov/mezon-pop3-client/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/alexdodonov/mezon-pop3-client/?branch=master)

## Installation

Just type

```
composer require mezon/pop3-client
```

## Usage

Firts of all you need to connect

```PHP
$client = new \Mezon\Pop3\Client('ssl://your-email-server', 'login', 'password');
```

And then you can fetch necessary information from server. Such as:

```PHP
client->getCount(); // getting count of emails on the server
```

Or get headers of the message by it's id, get message's subject or even delete it:

```PHP
for($i=0; $i<$client->getCount(); $i++) {
	$headers = $client->getMessageHeaders($i);
	$subject = $client->getMessageSubject($i);

	$client->deleteMessage($i);
}
```

And after all things done you should close connection:

```PHP
$client->quit();
```

## Utility functions

You can also use more high level functions.

Such as deleting email by it's subject:

```PHP
$client->deleteMessagesWithSubject('Re: some subject');
```

Or check if the email with the specified subject exists:

```PHP
$client->messageWithSubjectExists('Re: some subject');// true or false will be returned
```

Or parse header wich were fetched by the getMessageHeaders(int $i): string and fetch Message-ID field:

```PHP
$messageId = \Mezon\Pop3\Client::getMessageId($headers);
```
