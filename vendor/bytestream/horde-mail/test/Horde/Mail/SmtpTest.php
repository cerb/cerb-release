<?php
/**
 * @author     Christoph Wurst
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Mail
 * @subpackage UnitTests
 */

/**
 * Minimal mock of Net_SMTP for hermetic SMTP transport testing.
 */
class Horde_Mail_Test_Stub_NetSmtp
{
    public $lastBody = null;

    public function mailFrom($from, $params = '')
    {
        return true;
    }

    public function rcptTo($rcpt)
    {
        return true;
    }

    public function data($body, $headers = '')
    {
        $this->lastBody = $body;
        return true;
    }

    public function getResponse()
    {
        return array('250', 'OK');
    }

    public function getGreeting()
    {
        return 'smtp.example.com ESMTP';
    }

    public function connect($timeout = null)
    {
        return true;
    }

    public function disconnect()
    {
    }

    public function setDebug($debug)
    {
    }

    public function auth($username, $password, $method = '')
    {
        return true;
    }

    public function rset()
    {
        return true;
    }

    public function pipelining()
    {
    }
}

class Horde_Mail_SmtpTest extends \PHPUnit\Framework\TestCase
{
    public function testBodyAlreadyEndingInCrlfNotDoublingNewline()
    {
        $body = "Message content\r\n";
        $stub = new Horde_Mail_Test_Stub_NetSmtp();

        $transport = new Horde_Mail_Transport_Smtp();

        $reflection = new ReflectionProperty($transport, '_smtp');
        $reflection->setAccessible(true);
        $reflection->setValue($transport, $stub);

        $transport->send(
            'test@example.com',
            array(
                'From' => 'sender@example.com',
                'To' => 'test@example.com',
                'Subject' => 'Test',
            ),
            $body
        );

        $this->assertEquals($body, $stub->lastBody, 'Body ending in CRLF should not be modified');
    }

    public function testBodyNotEndingInCrlfGetsOneApplied()
    {
        $body = "Message content";
        $expected = "Message content\r\n";
        $stub = new Horde_Mail_Test_Stub_NetSmtp();

        $transport = new Horde_Mail_Transport_Smtp();

        $reflection = new ReflectionProperty($transport, '_smtp');
        $reflection->setAccessible(true);
        $reflection->setValue($transport, $stub);

        $transport->send(
            'test@example.com',
            array(
                'From' => 'sender@example.com',
                'To' => 'test@example.com',
                'Subject' => 'Test',
            ),
            $body
        );

        $this->assertEquals($expected, $stub->lastBody, 'Body missing CRLF should have exactly one CRLF appended');
    }

    public function testBodyWithJustLfGetsProperCrlf()
    {
        $body = "Line 1\nLine 2\n";
        $expected = "Line 1\nLine 2\n\r\n";
        $stub = new Horde_Mail_Test_Stub_NetSmtp();

        $transport = new Horde_Mail_Transport_Smtp();

        $reflection = new ReflectionProperty($transport, '_smtp');
        $reflection->setAccessible(true);
        $reflection->setValue($transport, $stub);

        $transport->send(
            'test@example.com',
            array(
                'From' => 'sender@example.com',
                'To' => 'test@example.com',
                'Subject' => 'Test',
            ),
            $body
        );

        $this->assertEquals($expected, $stub->lastBody, 'Body ending in LF should get CRLF appended');
    }
}
