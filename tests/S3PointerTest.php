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
        $receiptHandle = '-..s3BucketName..-sonic-anapoket-sqs-test-..s3Key..-bc082ed1-7a1a-42c6-bfd0-5a9da744b972.jsonAQEB9OyuQaDnYBnc0jofFm5pySedJrpOuB7BhQljrQP/mUpzwVbOju9Hs/C7Exc+RDXMKghq5JbJZ0NCA37T20m9Ld0Ba7Ayfe2Suzv/GifsOqBPidURDZK8vYEdlGQdpLrJxcYfUcLDhNqfpbcGq47v0y4GXAD1j0WHJ50exdsn9q3guiEMeds90e+8fKv1XKvmcmBF3n/SNIo/4I9XLik58VEgqfHtH17HqIlxjIZTCH/UBskw0NZvH6XpreX1+Q6lT4D5l+Fzx98bGpAw1vnLFzkeUHbMlKIBAgwgF44eM/UnQctX9fZuP7Y4VKZIpGUliE2L2MTdjLZYYTexqymuvdVEOiK7XDySFe5LrQBwXiQjozEJVmMJJQOjNeW4eQ3K4ksLym4PNerHmGJsJw6S5A==';
        $this->assertEquals(true, (bool)S3Pointer::containsS3Pointer($receiptHandle));
    }

    public function testGetS3PointerFromReceiptHandle()
    {
        $receiptHandle = '-..s3BucketName..-sonic-anapoket-sqs-test-..s3Key..-bc082ed1-7a1a-42c6-bfd0-5a9da744b972.jsonAQEB9OyuQaDnYBnc0jofFm5pySedJrpOuB7BhQljrQP/mUpzwVbOju9Hs/C7Exc+RDXMKghq5JbJZ0NCA37T20m9Ld0Ba7Ayfe2Suzv/GifsOqBPidURDZK8vYEdlGQdpLrJxcYfUcLDhNqfpbcGq47v0y4GXAD1j0WHJ50exdsn9q3guiEMeds90e+8fKv1XKvmcmBF3n/SNIo/4I9XLik58VEgqfHtH17HqIlxjIZTCH/UBskw0NZvH6XpreX1+Q6lT4D5l+Fzx98bGpAw1vnLFzkeUHbMlKIBAgwgF44eM/UnQctX9fZuP7Y4VKZIpGUliE2L2MTdjLZYYTexqymuvdVEOiK7XDySFe5LrQBwXiQjozEJVmMJJQOjNeW4eQ3K4ksLym4PNerHmGJsJw6S5A==';
        $expected = [
            's3BucketName' => 'sonic-anapoket-sqs-test',
            's3Key' => 'bc082ed1-7a1a-42c6-bfd0-5a9da744b972.json'];
        $this->assertEquals($expected, (array)S3Pointer::getS3PointerFromReceiptHandle($receiptHandle));
    }

    public function testRemoveS3Pointer()
    {
        $receiptHandle = '-..s3BucketName..-sonic-anapoket-sqs-test-..s3Key..-bc082ed1-7a1a-42c6-bfd0-5a9da744b972.jsonAQEB9OyuQaDnYBnc0jofFm5pySedJrpOuB7BhQljrQP/mUpzwVbOju9Hs/C7Exc+RDXMKghq5JbJZ0NCA37T20m9Ld0Ba7Ayfe2Suzv/GifsOqBPidURDZK8vYEdlGQdpLrJxcYfUcLDhNqfpbcGq47v0y4GXAD1j0WHJ50exdsn9q3guiEMeds90e+8fKv1XKvmcmBF3n/SNIo/4I9XLik58VEgqfHtH17HqIlxjIZTCH/UBskw0NZvH6XpreX1+Q6lT4D5l+Fzx98bGpAw1vnLFzkeUHbMlKIBAgwgF44eM/UnQctX9fZuP7Y4VKZIpGUliE2L2MTdjLZYYTexqymuvdVEOiK7XDySFe5LrQBwXiQjozEJVmMJJQOjNeW4eQ3K4ksLym4PNerHmGJsJw6S5A==';
        $expected = 'AQEB9OyuQaDnYBnc0jofFm5pySedJrpOuB7BhQljrQP/mUpzwVbOju9Hs/C7Exc+RDXMKghq5JbJZ0NCA37T20m9Ld0Ba7Ayfe2Suzv/GifsOqBPidURDZK8vYEdlGQdpLrJxcYfUcLDhNqfpbcGq47v0y4GXAD1j0WHJ50exdsn9q3guiEMeds90e+8fKv1XKvmcmBF3n/SNIo/4I9XLik58VEgqfHtH17HqIlxjIZTCH/UBskw0NZvH6XpreX1+Q6lT4D5l+Fzx98bGpAw1vnLFzkeUHbMlKIBAgwgF44eM/UnQctX9fZuP7Y4VKZIpGUliE2L2MTdjLZYYTexqymuvdVEOiK7XDySFe5LrQBwXiQjozEJVmMJJQOjNeW4eQ3K4ksLym4PNerHmGJsJw6S5A==';
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
