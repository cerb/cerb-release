<?php
/**
 * @author     Christoph Wurst
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Mail
 * @subpackage UnitTests
 */

/**
 * Test subclass of Smtpmx exposing protected _getMx method and allowing
 * override of _loadNetDns for testing without real DNS/Net_DNS2 dependency.
 */
class Horde_Mail_Test_Smtpmx extends Horde_Mail_Transport_Smtpmx
{
    public $netdnsLoadCalled = false;

    public function getMx($host)
    {
        return $this->_getMx($host);
    }

    protected function _loadNetDns()
    {
        $this->netdnsLoadCalled = true;
        throw new Horde_Mail_Exception('Sentinel: _loadNetDns was called');
    }
}

class Horde_Mail_SmtpmxTest extends \PHPUnit\Framework\TestCase
{
    public function testNetdnsParamTriggers_loadNetDns()
    {
        $transport = new Horde_Mail_Test_Smtpmx(array('netdns' => true));

        $exceptionThrown = false;
        try {
            $transport->getMx('example.com');
        } catch (Horde_Mail_Exception $e) {
            if ($e->getMessage() === 'Sentinel: _loadNetDns was called') {
                $exceptionThrown = true;
            }
        }

        $this->assertTrue(
            $exceptionThrown,
            '_loadNetDns should have been called when netdns => true, indicating the typo has been fixed'
        );
        $this->assertTrue(
            $transport->netdnsLoadCalled,
            'Flag confirms _loadNetDns was invoked'
        );
    }
}
