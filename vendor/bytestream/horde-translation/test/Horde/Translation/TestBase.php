<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Translation
 * @subpackage UnitTests
 */
class Horde_Translation_TestBase extends \PHPUnit\Framework\TestCase
{
    private $_env;

    public function setUp(): void
    {
        parent::setUp();

        try {
            $this->setLocale(LC_ALL, 'de_DE.UTF-8');
        } catch (\PHPUnit\Framework\Exception $e) {
            $this->markTestSkipped('Setting the locale failed. de_DE.UTF-8 might not be supported.');
        }
        $this->_setEnv('de_DE.UTF-8');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->_restoreEnv();
    }

    private function _setEnv($value)
    {
        foreach (array('LC_ALL', 'LANG', 'LANGUAGE') as $env) {
            $this->_env[$env] = getenv($env);
            putenv($env . '=' . $value);
        }
    }

    private function _restoreEnv()
    {
        foreach (array('LC_ALL', 'LANG', 'LANGUAGE') as $env) {
            putenv($env . '=' . $this->_env[$env]);
        }
    }
}
