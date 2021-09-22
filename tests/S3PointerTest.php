<?php

use Aws\Result;
use AwsExtended\S3Pointer;
use PHPUnit\Framework\TestCase;

/**
 * Class S3PointerTest.
 *
 * @package AwsExtended
 */
class S3PointerTest extends TestCase
{

    /**
     * @dataProvider toStringProvider
     */
    public function testToString($bucket, $key, $receipt, $expected)
    {
        $pointer = $receipt ?
            new S3Pointer($bucket, $key, $receipt) :
            new S3Pointer($bucket, $key);
        $this->assertSame($expected, (string)$pointer);
    }

    public function testContainsS3Pointer()
    {
        $receiptHandle = '-..s3BucketName..-tegos-anapoket-sqs-test-..s3BucketName..--..s3Key..-9b4d4278-2aa9-477c-b4d1-a4a5bfc1bf61.json-..s3Key..-AQEBAR0nX3Jw9ZOYYtXasJn';
        $this->assertEquals(true, S3Pointer::containsS3Pointer($receiptHandle));
        $this->assertEquals(false, S3Pointer::containsS3Pointer('aaaaaa'));
        $this->assertEquals(false, S3Pointer::containsS3Pointer('test'));
        $this->assertEquals(false, S3Pointer::containsS3Pointer(''));
    }

    public function testGetS3PointerFromReceiptHandle()
    {
        $receiptHandle = '-..s3BucketName..-tegos-anapoket-sqs-test-..s3BucketName..--..s3Key..-9b4d4278-2aa9-477c-b4d1-a4a5bfc1bf61.json-..s3Key..-AQEBAR0nX3Jw9ZOYYtXasJn';
        $expected = [
            's3BucketName' => 'tegos-anapoket-sqs-test',
            's3Key' => '9b4d4278-2aa9-477c-b4d1-a4a5bfc1bf61.json'];
        $this->assertEquals($expected, S3Pointer::getS3PointerFromReceiptHandle($receiptHandle));
    }

    public function testRemoveS3Pointer()
    {
        $receiptHandle = '-..s3BucketName..-tegos-anapoket-sqs-test-..s3BucketName..--..s3Key..-9b4d4278-2aa9-477c-b4d1-a4a5bfc1bf61.json-..s3Key..-AQEBAR0nX3Jw9ZOYYtXasJn';
        $expected = 'AQEBAR0nX3Jw9ZOYYtXasJn';
        $this->assertEquals($expected, S3Pointer::removeS3Pointer($receiptHandle));
        $this->assertEquals('test', S3Pointer::removeS3Pointer('test'));

    }

    /**
     * @dataProvider messageAttributeProvider
     */
    public function testIsS3Pointer($expect, $attr)
    {
        $this->assertEquals($expect, S3Pointer::isS3Pointer($attr));
        $this->assertEquals($expect, S3Pointer::isS3Pointer($attr));

    }

    public function messageAttributeProvider()
    {
        return [
            [true,  [
                'MessageAttributes' => [
                    'ExtendedPayloadSize' => [],
                ],
                'Body' => json_encode([
                    's3BucketName' => 'test',
                    's3Key' => 'test',
                ])
            ]],
            [false,  [
                'MessageAttributes' => [
                    'ExtendedPayloadSize' => [],
                ],
                'Body' => json_encode([
                    's3BucketName' => 'test',
                ])
            ]],
            [false,  [
                'MessageAttributes' => [
                    'ExtendedPayloadSize' => [],
                ],
                'Body' => json_encode([
                ])
            ]],
            [false,  [
                'MessageAttributes' => [
                    'ExtendedPayloadSize' => [],
                ],
                'Body' => 'test'
            ]],
            [false,  [
                'MessageAttributes' => [
                ],
                'Body' => json_encode([
                    's3BucketName' => 'test',
                    's3Key' => 'test',
                ])
            ]],
            [false, []],
        ];
    }


    /**
     * Test data for the __toString test.
     *
     * @return array
     *   The data for the test method.
     */
    public function toStringProvider()
    {
        return [
            ['lorem', 'ipsum', NULL, '[[],{"s3BucketName":"lorem","s3Key":"ipsum"}]'],
            ['lorem', 'ipsum', new Result([]), '[[null,null],{"s3BucketName":"lorem","s3Key":"ipsum"}]'],
            [NULL, NULL, NULL, '[[],{"s3BucketName":null,"s3Key":null}]'],
            ['lorem', TRUE, NULL, '[[],{"s3BucketName":"lorem","s3Key":true}]'],
            ['lorem', 'ipsum', new Result([
                '@metadata' => 'fake_metadata',
                'ObjectUrl' => 'fake_object_url'
            ]), '[["fake_metadata","fake_object_url"],{"s3BucketName":"lorem","s3Key":"ipsum"}]'],
        ];
    }

}
