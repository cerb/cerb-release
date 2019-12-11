<?php

class UserAgentParserTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider userAgentDataProvider
	 */
	public function test_parse_user_agent( $string, $expected ) {
		$result = parse_user_agent($string);
		$this->assertSame($expected, $result, $string . " test failed!");
	}

	public function userAgentDataProvider() {
		$out = array();
		$uas = json_decode(file_get_contents(__DIR__ . '/user_agents.json'), true);
		foreach( $uas as $string => $parts ) {
			$out[] = array( $string, $parts );
		}

		return $out;
	}

	public function test_parse_user_agent_empty() {
		$expected = array(
			'platform' => null,
			'browser'  => null,
			'version'  => null,
		);

		$result = parse_user_agent('');
		$this->assertSame($result, $expected);

		$result = parse_user_agent('Mozilla (asdjkakljasdkljasdlkj) BlahBlah');
		$this->assertSame($result, $expected);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function test_no_user_agent_exception() {
		unset($_SERVER['HTTP_USER_AGENT']);
		parse_user_agent();
	}

	public function test_global_user_agent() {
		$_SERVER['HTTP_USER_AGENT'] = 'Test/1.0';
		$this->assertSame(array( 'platform' => null, 'browser' => 'Test', 'version' => '1.0' ), parse_user_agent());
	}

}
