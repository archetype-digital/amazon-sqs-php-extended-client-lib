<?php
namespace AwsExtended;

/**
 * Class ConfigTest.
 *
 * @package AwsExtended
 *
 * @coversDefaultClass \AwsExtended\Config
 */
class ConfigTest extends \Tests\TestCase {

  /**
   * @covers ::__construct
   * @dataProvider constructorProvider
   */
  public function testConstructor($args, $bucketName, $sendToS3) {
    $configuration = new Config($args, $bucketName, $sendToS3);
    $this->assertSame($args, $configuration->getConfig());
    $this->assertSame($bucketName, $configuration->getBucketName());
    $this->assertSame($sendToS3, $configuration->getSendToS3());
  }

  /**
   * Test data for the contructor test.
   *
   * @return array
   *   The data for the test method.
   */
  public function constructorProvider() {
    return [
      [[], 'lorem', 'ipsum', 'ALWAYS'],
      [[1, 2, 'c'], 'dolor', 'sid', 'NEVER'],
    ];
  }

  /**
   * @covers ::__construct
   * @expectedException \InvalidArgumentException
   */
  public function testConstructorFail() {
    new Config([], 'lorem', 'ipsum', 'INVALID');
  }

}
