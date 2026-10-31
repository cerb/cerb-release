<?php
/**
 * @author     Christoph Wurst
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Mail
 * @subpackage UnitTests
 */

class Horde_Mail_SendmailTest extends \PHPUnit\Framework\TestCase
{
    public function testExitCode74ErrorMessageCorrect()
    {
        $stub = sys_get_temp_dir() . '/horde_sendmail_stub_' . uniqid() . '.sh';
        file_put_contents($stub, "#!/bin/sh\ncat >/dev/null\nexit 74\n");
        chmod($stub, 0755);

        try {
            $transport = new Horde_Mail_Transport_Sendmail(array(
                'sendmail_path' => $stub,
                'sendmail_args' => '-i'
            ));

            $exceptionThrown = false;
            $exceptionMessage = '';
            $exceptionCode = 0;

            try {
                $transport->send(
                    'test@example.com',
                    array(
                        'From' => 'sender@example.com',
                        'To' => 'test@example.com',
                        'Subject' => 'Test',
                    ),
                    "Test body\r\n"
                );
            } catch (Horde_Mail_Exception $e) {
                $exceptionThrown = true;
                $exceptionMessage = $e->getMessage();
                $exceptionCode = $e->getCode();
            }

            $this->assertTrue($exceptionThrown, 'Exception should be thrown for exit code 74');
            $this->assertStringContainsString('input/output error', $exceptionMessage, 'Exit code 74 should report input/output error, not temporary failure');
            $this->assertEquals(74, $exceptionCode, 'Exception code should be 74');
        } finally {
            @unlink($stub);
        }
    }
}
