<?php
require_once (__DIR__ . '/../Client.php');

class Pop3ClientUnitTest extends \PHPUnit\Framework\TestCase
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
    public function testInvalidLogin()
    {
        $this->expectException(\Exception::class);

        new \Mezon\Pop3\Client($this->server, 'unexisting-1024', 'password', 5, 995);
    }

    /**
     * Password validation
     */
    public function testInvalidPassword()
    {
        $this->expectException(\Exception::class);

        new \Mezon\Pop3\Client($this->server, $this->login, 'password', 5, 995);
    }

    /**
     * Normal connect
     */
    public function testConnect()
    {
        new \Mezon\Pop3\Client($this->server, $this->login, $this->password, 5, 995);

        $this->addToAssertionCount(1);
    }

    /**
     * Get emails count
     */
    public function testGetCount()
    {
        $client = new \Mezon\Pop3\Client($this->server, $this->login, $this->password, 5, 995);

        $this->assertEquals($client->getCount() > 0, true, 'No emails were fetched');
    }

    /**
     * Get emails headers
     */
    public function testGetHeaders()
    {
        $client = new \Mezon\Pop3\Client($this->server, $this->login, $this->password, 5, 995);

        $headers = $client->getMessageHeaders(1);

        $this->assertNotEquals(strpos($headers, 'From: '), false, 'No "From" header');
        $this->assertNotEquals(strpos($headers, 'To: '), false, 'No "To" header');
        $this->assertNotEquals(strpos($headers, 'Subject: '), false, 'No "Subject" header');
    }

    /**
     * Delete email
     */
    public function testDeleteEmail()
    {
        $client = new \Mezon\Pop3\Client($this->server, $this->login, $this->password, 5, 995);

        $headers = $client->getMessageHeaders(1);

        $messageId = \Mezon\Pop3\Client::getMessageId($headers);

        $client->deleteMessage(1);

        $client->quit();

        $client->connect($this->server, $this->login, $this->password, 5, 995);

        $headers = $client->getMessageHeaders(1);

        $messageId2 = \Mezon\Pop3\Client::getMessageId($headers);

        $this->assertNotEquals($messageId, $messageId2, 'Message was not deleted');
    }
}
