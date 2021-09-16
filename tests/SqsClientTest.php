<?php

use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
use AwsExtended\Config;
use AwsExtended\S3Pointer;
use AwsExtended\SqsClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Ramsey\Uuid\Uuid;

/**
 * Class SqsClientTest.
 *
 * @package AwsExtended
 *
 * @coversDefaultClass \AwsExtended\SqsClient
 */
class SqsClientTest extends TestCase
{
    private const AWS_ACCESS_KEY_ID = 'dummy';
    private const AWS_SECRET_ACCESS_KEY = 'dummy';
    private const AWS_DEFAULT_REGION = 'dummy';
    private const AWS_SDK_VERSION = 'latest';
    private const S3_BUCKET_NAME = 'dummy';
    private const SQS_URL = 'dummy';

    private $s3Mock;
    private $sqsMock;
    private $awsConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqsMock = Mockery::mock('overload:' . \Aws\Sqs\SqsClient::class);
        $this->s3Mock = Mockery::mock('overload:' . S3Client::class);
        $this->awsConfig = ['credentials' => [
            'key' => self::AWS_ACCESS_KEY_ID,
            'secret' => self::AWS_SECRET_ACCESS_KEY,
        ],
            'region' => self::AWS_DEFAULT_REGION,
            'version' => self::AWS_SDK_VERSION,
        ];
    }


    /**
     * @runInSeparateProcess
     * @covers ::sendMessage
     */
    public function testSendMessage()
    {
        $this->s3Mock->shouldReceive('upload')
            ->once()
            ->andReturn(Mockery::mock(Result::class)->makePartial());

        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
            'MessageId' => 'test'
        ]])->makePartial();

        $this->sqsMock->shouldReceive('sendMessage')
            ->once()
            ->andReturn($sqsResultMock);

        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'ALWAYS');
        $sqsClient = new SqsClient($configuration);

        $params['MessageBody'] = json_encode(range(1, 257 * 1024));
        $params['MessageAttributes'] = [
            "Title" => [
                'DataType' => "String",
                'StringValue' => "The Hitchhiker's Guide to the Galaxy"
            ],
            "Author" => [
                'DataType' => "String",
                'StringValue' => "Douglas Adams."
            ],
            "WeeksOn" => [
                'DataType' => "Number",
                'StringValue' => "6"
            ]
        ];
        $params['QueueUrl'] = self::SQS_URL;
        $sendMessageResult = $sqsClient->sendMessage($params);

        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
        $this->assertEquals('test', $sendMessageResult['MessageId']);
    }

    /**
     * @runInSeparateProcess
     * @covers ::sendMessage
     */
    public function testSendMessage_NoUseS3()
    {
        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
            'MessageId' => 'test'
        ]])->makePartial();

        $this->sqsMock->shouldReceive('sendMessage')
            ->once()
            ->andReturn($sqsResultMock);

        $this->s3Mock->shouldReceive('upload')->never();

        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);

        $params['MessageBody'] = json_encode('this is short message aaaaa');
        $params['MessageAttributes'] = [];
        $params['QueueUrl'] = self::SQS_URL;
        $sendMessageResult = $sqsClient->sendMessage($params);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
        $this->assertEquals('test', $sendMessageResult['MessageId']);
    }

    /**
     * @runInSeparateProcess
     * @covers ::sendMessage
     */
    public function testSendMessage_SendFail()
    {
        $this->sqsMock->shouldReceive('sendMessage')
            ->once()
            ->andThrow(new AwsException('dummy', new Command('sqs')));

        $this->s3Mock->shouldReceive('upload')->never();

        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);

        $params['MessageBody'] = json_encode('this is short message aaaaa');
        $params['MessageAttributes'] = [];
        $params['QueueUrl'] = self::SQS_URL;

        $this->expectException(Exception::class);
        $sqsClient->sendMessage($params);
    }

    /**
     * @runInSeparateProcess
     * @covers ::sendMessage
     */
    public function testSendMessage_NoBucket()
    {

        $configuration = new Config($this->awsConfig, '', 'ALWAYS');
        $sqsClient = new SqsClient($configuration);

        $params['MessageAttributes'] = [];
        $params['MessageBody'] = '';
        $params['QueueUrl'] = self::SQS_URL;

        $this->expectException(Exception::class);
        $sqsClient->sendMessage($params);
    }

    /**
     * @runInSeparateProcess
     * @covers ::sendMessage
     */
    public function testSendMessage_UseS3LimitValue()
    {
        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);
        $messageLength = ($sqsClient::MAX_SQS_SIZE_KB * 1024) + 1;

        $params['MessageAttributes'] = [
            "Title" => [
                'DataType' => "String",
                'StringValue' => "The Hitchhiker's Guide to the Galaxy"
            ],
        ];

        $params['MessageBody'] = str_repeat('z', $messageLength - strlen(json_encode($params['MessageAttributes'])));

        $this->s3Mock->shouldReceive('upload')
            ->once()
            ->andReturn(Mockery::mock(Result::class)->makePartial());

        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
            'MessageId' => 'test'
        ]])->makePartial();

        $this->sqsMock->shouldReceive('sendMessage')
            ->once()
            ->andReturn($sqsResultMock);

        $params['QueueUrl'] = self::SQS_URL;
        $sendMessageResult = $sqsClient->sendMessage($params);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
        $this->assertEquals('test', $sendMessageResult['MessageId']);
    }

    /**
     * @runInSeparateProcess
     * @covers ::sendMessageBatch
     */
    public function testSendMessageBatch()
    {
        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);

        $entry = [];
        for ($i = 0; $i <= 5; $i++) {
            //Create two types of data with and without s3
            $entry[$i]['Id'] = Uuid::uuid4()->toString();
            if ($i % 2 == 0) {
                $entry[$i]['MessageBody'] = json_encode(range(1, 257 * 1024));
            } else {
                $entry[$i]['MessageBody'] = json_encode('this is short message_' . $i);
            }
        }

        $this->s3Mock->shouldReceive('upload')
            ->times(3)
            ->andReturn(Mockery::mock(Result::class)->makePartial());

        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
            'MessageId' => 'test'
        ]])->makePartial();

        $this->sqsMock->shouldReceive('sendMessageBatch')
            ->once()
            ->andReturn($sqsResultMock);

        $params['Entries'] = $entry;
        $params['QueueUrl'] = self::SQS_URL;
        $sendMessageResult = $sqsClient->sendMessageBatch($params);
        $this->assertEquals(200, $sendMessageResult['@metadata']['statusCode']);
    }

    /**
     * @runInSeparateProcess
     * @covers ::receiveMessage
     */
    public function testReceiveMessage_NotS3()
    {
        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);
        $params = ['MaxNumberOfMessages' => 10,
            'QueueUrl' => self::SQS_URL,
            'VisibilityTimeout' => 9,
            'MessageAttributeNames' => [],
            'WaitTimeSeconds' => 20];

        $this->s3Mock->shouldReceive('getObject')
            ->once()
            ->andReturn();

        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
            'MessageId' => 'test',
            'Messages' => [],
        ]])->makePartial();

        $this->sqsMock->shouldReceive('receiveMessage')
            ->once()
            ->andReturn($sqsResultMock);

        $receiveMessageResult = $sqsClient->receiveMessage($params);
        $this->assertEquals(200, $receiveMessageResult['@metadata']['statusCode']);
        $this->assertEquals('test', $receiveMessageResult['MessageId']);
    }

    /**
     * @runInSeparateProcess
     * @covers ::receiveMessage
     */
    public function testReceiveMessage_NoMessages()
    {
        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);
        $params = ['MaxNumberOfMessages' => 10,
            'QueueUrl' => self::SQS_URL,
            'VisibilityTimeout' => 9,
            'MessageAttributeNames' => [],
            'WaitTimeSeconds' => 20];

        $this->s3Mock->shouldReceive('getObject')
            ->never();

        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
            'MessageId' => 'test'
        ]])->makePartial();

        $this->sqsMock->shouldReceive('receiveMessage')
            ->once()
            ->andReturn($sqsResultMock);

        $receiveMessageResult = $sqsClient->receiveMessage($params);
        $this->assertEquals(200, $receiveMessageResult['@metadata']['statusCode']);
        $this->assertEquals('test', $receiveMessageResult['MessageId']);
    }

    /**
     * @runInSeparateProcess
     * @covers ::receiveMessage
     */
    public function testReceiveMessage_FromS3()
    {
        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);

        $streamMock = Mockery::mock(StreamInterface::class)
            ->shouldReceive('getContents')
            ->once()
            ->andReturn(json_encode([
                'test' => 1
            ]))
            ->getMock();

        $s3ResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
            'MessageId' => 'test',
            'Body' => $streamMock,
        ]])->makePartial();

        $this->s3Mock->shouldReceive('getObject')
            ->once()
            ->andReturn($s3ResultMock);

        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
            'Messages' => [[
                'MessageAttributes' => [
                    'ExtendedPayloadSize' => [
                        'StringValue' => '[{},{"s3BucketName":"1", "s3Key":"2"},{},{}]'
                    ],
                ],
                'MessageId' => 'test',
                'ReceiptHandle' => 'test-receipt-handle',
                'Body' => []
            ]],
        ]])->makePartial();

        $this->sqsMock->shouldReceive('receiveMessage')
            ->once()
            ->andReturn($sqsResultMock);

        $receiveMessageResult = $sqsClient->receiveMessage([]);
        $this->assertEquals(200, $receiveMessageResult['@metadata']['statusCode']);
        $this->assertCount(1, $receiveMessageResult['Messages']);
        $this->assertEquals('test', $receiveMessageResult['Messages'][0]['MessageId']);
        $this->assertEquals(json_encode(['test' => 1]), $receiveMessageResult['Messages'][0]['Body']);
    }

    /**
     * @runInSeparateProcess
     * @covers ::receiveMessage
     */
    public function testDeleteMessage_NotS3()
    {
        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);
        $params = ['QueueUrl' => self::SQS_URL, 'ReceiptHandle' => 'longvalue'];

        $mock = Mockery::mock('alias:' . S3Pointer::class);
        $mock->shouldReceive('containsS3Pointer')
            ->once()
            ->andReturn(false);
        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
        ]])->makePartial();

        $this->sqsMock->shouldReceive('deleteMessage')
            ->once()
            ->andReturn($sqsResultMock);

        $deleteMessageResult = $sqsClient->deleteMessage($params);
        $this->assertEquals(200, $deleteMessageResult['@metadata']['statusCode']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers ::receiveMessage
     */
    public function testDeleteMessage_S3()
    {
        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);
        $params = ['QueueUrl' => self::SQS_URL, 'ReceiptHandle' => 'longvalue'];
        $mock = Mockery::mock('alias:' . S3Pointer::class);
        $mock->shouldReceive('getS3PointerFromReceiptHandle')
            ->once()
            ->andReturn([
                's3BucketName' => '',
                's3Key' => '',
            ]);
        $mock->shouldReceive('containsS3Pointer')
            ->once()
            ->andReturn(true);
        $mock->shouldReceive('removeS3Pointer')
            ->once()
            ->andReturn(true);

        $this->s3Mock->shouldReceive('deleteObject')
            ->once();

        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
        ]])->makePartial();

        $this->sqsMock->shouldReceive('deleteMessage')
            ->once()
            ->andReturn($sqsResultMock);

        $deleteMessageResult = $sqsClient->deleteMessage($params);
        $this->assertEquals(200, $deleteMessageResult['@metadata']['statusCode']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers ::deleteMessageBatch
     */
    public function testDeleteMessageBatch_NoS3()
    {
        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);

        $mock = Mockery::mock('alias:' . S3Pointer::class);
        $mock->shouldReceive('containsS3Pointer')
            ->twice()
            ->andReturn(false);

        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
        ]])->makePartial();

        $this->sqsMock->shouldReceive('deleteMessageBatch')
            ->once()
            ->andReturn($sqsResultMock);

        $entries = [
            [
                'Id' => '2b22d04d-cefa-4484-b0e5-9edada7c9a79',
                'ReceiptHandle' => 'long value',
            ],
            [
                'Id' => '4f90ffe4-36e3-4d43-b31f-46dce859f679',
                'ReceiptHandle' => 'long value',
            ],
        ];
        $params = ['Entries' => $entries, 'QueueUrl' => self::SQS_URL];
        $deleteMessageResult = $sqsClient->deleteMessageBatch($params);
        $this->assertEquals(200, $deleteMessageResult['@metadata']['statusCode']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @covers ::deleteMessageBatch
     */
    public function testDeleteMessageBatch_S3()
    {
        $configuration = new Config($this->awsConfig, self::S3_BUCKET_NAME, 'IF_NEEDED');
        $sqsClient = new SqsClient($configuration);

        $mock = Mockery::mock('alias:' . S3Pointer::class);
        $mock->shouldReceive('getS3PointerFromReceiptHandle')
            ->twice()
            ->andReturn([
                's3BucketName' => '',
                's3Key' => '',
            ]);
        $mock->shouldReceive('containsS3Pointer')
            ->twice()
            ->andReturn(true);
        $mock->shouldReceive('removeS3Pointer')
            ->twice()
            ->andReturn(true);
        $this->s3Mock->shouldReceive('deleteObject')
            ->twice();
        $sqsResultMock = Mockery::mock(Result::class, [[
            '@metadata' => [
                'statusCode' => 200
            ],
        ]])->makePartial();

        $this->sqsMock->shouldReceive('deleteMessageBatch')
            ->once()
            ->andReturn($sqsResultMock);

        $entries = [
            [
                'Id' => '2b22d04d-cefa-4484-b0e5-9edada7c9a79',
                'ReceiptHandle' => 'long value',
            ],
            [
                'Id' => '4f90ffe4-36e3-4d43-b31f-46dce859f679',
                'ReceiptHandle' => 'long value',
            ],
        ];
        $params = ['Entries' => $entries, 'QueueUrl' => self::SQS_URL];
        $deleteMessageResult = $sqsClient->deleteMessageBatch($params);
        $this->assertEquals(200, $deleteMessageResult['@metadata']['statusCode']);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
