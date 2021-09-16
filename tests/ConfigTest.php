<?php

use AwsExtended\Config;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigTest.
 *
 * @package AwsExtended
 *
 * @coversDefaultClass \AwsExtended\Config
 */
class ConfigTest extends TestCase
{

    /**
     * @covers ::__construct
     * @dataProvider constructorProvider
     */
    public function testConstructor($args, $bucketName, $sendToS3)
    {
        $configuration = new Config($args, $bucketName, $sendToS3);
        $this->assertSame($args, $configuration->getConfig());
        $this->assertSame($bucketName, $configuration->getBucketName());
        $this->assertSame($sendToS3, $configuration->getSendToS3());
    }

    /**
     * @covers ::getConfig
     * @dataProvider constructorProvider
     */
    public function testGetConfig($args, $bucketName, $sendToS3)
    {
        $configuration = new Config($args, $bucketName, $sendToS3);
        $this->assertSame($args, $configuration->getConfig());
    }

    /**
     * @covers ::getBucketName
     * @dataProvider constructorProvider
     */
    public function testGetBucketName($args, $bucketName, $sendToS3)
    {
        $configuration = new Config($args, $bucketName, $sendToS3);
        $this->assertSame($bucketName, $configuration->getBucketName());
    }

    /**
     * @covers ::getSendToS3
     * @dataProvider constructorProvider
     */
    public function testGetSendToS3($args, $bucketName, $sendToS3)
    {
        $configuration = new Config($args, $bucketName, $sendToS3);
        $this->assertSame($sendToS3, $configuration->getSendToS3());
    }

    /**
     * Test data for the contructor test.
     *
     * @return array
     *   The data for the test method.
     */
    public function constructorProvider()
    {
        return [
            [[], 'lorem', 'ALWAYS'],
            [[1, 2, 'c'], 'dolor', 'NEVER'],
        ];
    }

    /**
     * @covers ::__construct
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorFail()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Config([], 'lorem', 'INVALID');
    }

}
