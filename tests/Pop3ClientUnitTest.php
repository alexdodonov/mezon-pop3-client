<?php
namespace Mezon\Pop3\Tests;

use PHPUnit\Framework\TestCase;
use Mezon\Pop3\Client;

class Pop3ClientUnitTest extends TestCase
{

    /**
     * Email server
     *
     * @var string
     */
    private $server = 'ssl://pop.yandex.ru';

    /**
     * Email login
     *
     * @var string
     */
    private $login = 'pop-m-test@yandex.ru';

    /**
     * Email password
     *
     * @var string
     */
    private $password = 'pop3test';

    /**
     * Login validation
     */
    public function testInvalidLogin(): void
    {
        // setup
        $client = new Client();

        // assertions
        $this->expectException(\Exception::class);

        // test body
        $client->connect($this->server, 'unexisting-1024', 'password', 5, 995);
    }

    /**
     * Password validation
     */
    public function testInvalidPassword(): void
    {
        // setup
        $client = new Client();

        // assertions
        $this->expectException(\Exception::class);

        // test body
        $client->connect($this->server, $this->login, 'password', 5, 995);
    }

    /**
     * Normal connect
     */
    public function testConnect(): void
    {
        // setup
        $client = new Client();

        // test body
        $client->connect($this->server, $this->login, $this->password, 5, 995);

        // assertions
        $this->addToAssertionCount(1);
    }

    /**
     * Get emails count
     */
    public function testGetCount(): void
    {
        $client = new Client($this->server, $this->login, $this->password, 5, 995);

        $this->assertGreaterThan(0, $client->getCount(), 'No emails were fetched');
    }

    /**
     * Get emails headers
     */
    public function testGetHeaders(): void
    {
        $client = new Client($this->server, $this->login, $this->password, 5, 995);

        $headers = $client->getMessageHeaders(1);

        $this->assertStringNotContainsString($headers, 'From: ', 'No "From" header');
        $this->assertStringNotContainsString($headers, 'To: ', 'No "To" header');
        $this->assertStringNotContainsString($headers, 'Subject: ', 'No "Subject" header');
    }

    /**
     * Delete email
     */
    public function testDeleteEmail(): void
    {
        $client = new Client($this->server, $this->login, $this->password, 5, 995);

        $headers = $client->getMessageHeaders(1);

        $messageId = Client::getMessageId($headers);

        $client->deleteMessage(1);

        $client->quit();

        $client->connect($this->server, $this->login, $this->password, 5, 995);

        $headers = $client->getMessageHeaders(1);

        $messageId2 = Client::getMessageId($headers);

        $this->assertNotEquals($messageId, $messageId2, 'Message was not deleted');
    }

    /**
     * Testing getMessageSubject method
     */
    public function testGetMessageSubject(): void
    {
        // setup
        $client = new Client($this->server, $this->login, $this->password, 5, 995);

        // test body
        $subject = $client->getMessageSubject(1);

        // assertions
        $this->assertNotEmpty($subject);
    }
}
