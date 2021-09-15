<?php

namespace AwsExtended;

use Aws\Result;
use Aws\ResultInterface;

/**
 * Class S3PointerTest.
 *
 * @package AwsExtended
 * * @coversDefaultClass \ArchetypeDigital\AwsExtended\S3Pointer
 */
class S3PointerTest extends \Tests\TestCase
{

    /**
     * @covers ::__toString
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
        $receiptHandle = '-..s3BucketName..-tegos-anapoket-sqs-test-..s3BucketName..--..s3Key..-9b4d4278-2aa9-477c-b4d1-a4a5bfc1bf61.json-..s3Key..-AQEBAR0nX3Jw9ZOYYtXasJn/vIdf03pifXsZ3HW49/U/5RfhcQVdLgYyYrlADLHLaXc3HfxwT8IILPJ/k2SFRvan+m+zOnEVknfEe4nETZkGJY+KU3DPAEmMsgl5hKbBNfuUE2wmQOMmANYAf4lFgDsqsJ5VM/ODsUzUiVbQDVzp5lsyYkV6VnZzckVFi4VNmkLYL3eUTybW+a1O10gQIF9/L8juCcGMm2aIPZEcokElPNCUMutzy60qU3bsQOzDkpDFqlVyHdTkTmyt9WFymfz66UbYSGjVaS9Xpdff2VmsfSJ97wBzHY/LkBuZWxZPpAdqM7pkKVn/o0Oo63JFdVtWcowceo8MH8Bn4v7p3YpwUX1pVJ2DrIXfN0/4EF8DaKVgXyuho8r2s7fK5afJjwFLfg==';
        $this->assertEquals(true, (bool)S3Pointer::containsS3Pointer($receiptHandle));
    }

    public function testGetS3PointerFromReceiptHandle()
    {
        $receiptHandle = '-..s3BucketName..-tegos-anapoket-sqs-test-..s3BucketName..--..s3Key..-9b4d4278-2aa9-477c-b4d1-a4a5bfc1bf61.json-..s3Key..-AQEBAR0nX3Jw9ZOYYtXasJn/vIdf03pifXsZ3HW49/U/5RfhcQVdLgYyYrlADLHLaXc3HfxwT8IILPJ/k2SFRvan+m+zOnEVknfEe4nETZkGJY+KU3DPAEmMsgl5hKbBNfuUE2wmQOMmANYAf4lFgDsqsJ5VM/ODsUzUiVbQDVzp5lsyYkV6VnZzckVFi4VNmkLYL3eUTybW+a1O10gQIF9/L8juCcGMm2aIPZEcokElPNCUMutzy60qU3bsQOzDkpDFqlVyHdTkTmyt9WFymfz66UbYSGjVaS9Xpdff2VmsfSJ97wBzHY/LkBuZWxZPpAdqM7pkKVn/o0Oo63JFdVtWcowceo8MH8Bn4v7p3YpwUX1pVJ2DrIXfN0/4EF8DaKVgXyuho8r2s7fK5afJjwFLfg==';
        $expected = [
            's3BucketName' => 'tegos-anapoket-sqs-test',
            's3Key' => '9b4d4278-2aa9-477c-b4d1-a4a5bfc1bf61.json'];
        $this->assertEquals($expected, (array)S3Pointer::getS3PointerFromReceiptHandle($receiptHandle));
    }

    public function testRemoveS3Pointer()
    {
        $receiptHandle = '-..s3BucketName..-tegos-anapoket-sqs-test-..s3BucketName..--..s3Key..-9b4d4278-2aa9-477c-b4d1-a4a5bfc1bf61.json-..s3Key..-AQEBAR0nX3Jw9ZOYYtXasJn/vIdf03pifXsZ3HW49/U/5RfhcQVdLgYyYrlADLHLaXc3HfxwT8IILPJ/k2SFRvan+m+zOnEVknfEe4nETZkGJY+KU3DPAEmMsgl5hKbBNfuUE2wmQOMmANYAf4lFgDsqsJ5VM/ODsUzUiVbQDVzp5lsyYkV6VnZzckVFi4VNmkLYL3eUTybW+a1O10gQIF9/L8juCcGMm2aIPZEcokElPNCUMutzy60qU3bsQOzDkpDFqlVyHdTkTmyt9WFymfz66UbYSGjVaS9Xpdff2VmsfSJ97wBzHY/LkBuZWxZPpAdqM7pkKVn/o0Oo63JFdVtWcowceo8MH8Bn4v7p3YpwUX1pVJ2DrIXfN0/4EF8DaKVgXyuho8r2s7fK5afJjwFLfg==';
        $expected = 'AQEBAR0nX3Jw9ZOYYtXasJn/vIdf03pifXsZ3HW49/U/5RfhcQVdLgYyYrlADLHLaXc3HfxwT8IILPJ/k2SFRvan+m+zOnEVknfEe4nETZkGJY+KU3DPAEmMsgl5hKbBNfuUE2wmQOMmANYAf4lFgDsqsJ5VM/ODsUzUiVbQDVzp5lsyYkV6VnZzckVFi4VNmkLYL3eUTybW+a1O10gQIF9/L8juCcGMm2aIPZEcokElPNCUMutzy60qU3bsQOzDkpDFqlVyHdTkTmyt9WFymfz66UbYSGjVaS9Xpdff2VmsfSJ97wBzHY/LkBuZWxZPpAdqM7pkKVn/o0Oo63JFdVtWcowceo8MH8Bn4v7p3YpwUX1pVJ2DrIXfN0/4EF8DaKVgXyuho8r2s7fK5afJjwFLfg==';
        $this->assertEquals($expected, (string)S3Pointer::removeS3Pointer($receiptHandle));
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
