<?php
/**
 * @author     Christoph Wurst
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Mime
 * @subpackage UnitTests
 */

class Horde_Mime_Part_Upgrade_V1Test extends \PHPUnit\Framework\TestCase
{
    public function testUpgradeV1PartWithStatusFlags()
    {
        $v1_data = array(
            2,    // version
            'text',  // type
            'plain', // subtype
            array('charset' => 'UTF-8'),  // params
            null,    // lang
            null,    // cd (description)
            'inline', // disposition
            array(), // disp params
            array(), // content params
            null,    // body
            null,    // charset
            null,    // id
            null,    // location
            null,    // language
            null,    // description
            true,    // REINDEX flag
            true,    // BASEPART flag
        );

        $upgraded = new Horde_Mime_Part_Upgrade_V1($v1_data);

        $this->assertNotNull($upgraded->data);
        $this->assertIsArray($upgraded->data);
    }
}
