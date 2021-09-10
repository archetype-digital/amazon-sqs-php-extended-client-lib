<?php

namespace AwsExtended;

use Aws\Result;
use Aws\S3\S3Client;
use Aws\Sqs\SqsClient as AwsSqsClient;
use Prophecy\Argument;

/**
 * Class SqsClientTest.
 *
 * @package AwsExtended
 *
 * @coversDefaultClass \AwsExtended\SqsClient
 */
class SqsClientTest extends \Tests\TestCase
{

    /**
     * @var \AwsExtended\SqsClientInterface
     */
    protected $client;

    /**
     * @covers ::sendMessage
     */
    public function testSendMessage()
    {
        //Localのものを使う
        $awsconfig = config('queue.connections.sqsunittest.awsconfig');
        $configs = config('queue.connections.sqsunittest');
        $client = $this->getClientMock(new Config(
            $awsconfig,
            $configs['queue'],
            $configs['prefix'],
            ConfigInterface::NEVER
        ));
        $message['Message'] = 'this is test message';
        $message['MessageAttributes'] = [
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
        $que_url = $configs['prefix'] . $configs['queue'];
        $response = $client->sendMessage(message, $que_url);
        $this->assertEquals([
            'QueueUrl' => 'bar',
            'MessageBody' => $message['Message'],
        ], $response);

        $client = $this->getClientMock(new Config(
            [],
            'lorem',
            'ipsum',
            ConfigInterface::IF_NEEDED
        ));
        // Our mock returns the arguments passed to the AWS class.
        $response = $client->sendMessage('foo', 'bar');
        $this->assertEquals([
            'QueueUrl' => 'bar',
            'MessageBody' => 'foo',
        ], $response);
        $response = $client->sendMessage(json_encode(range(1, 257 * 1024)), 'bar');
        $this->assertEquals([
            'QueueUrl' => 'bar',
            'MessageBody' => '[[{"Lorem":"lorem","Ipsum":"1234-fake-uuid.json"},"fake_object_url"],{"s3BucketName":"lorem","s3Key":"1234-fake-uuid.json"}]',
        ], $response);

        $client = $this->getClientMock(new Config(
            [],
            'lorem',
            'ipsum',
            ConfigInterface::ALWAYS
        ));
        $response = $client->sendMessage('foo', 'bar');
        $this->assertEquals([
            'QueueUrl' => 'bar',
            'MessageBody' => '[[{"Lorem":"lorem","Ipsum":"1234-fake-uuid.json"},"fake_object_url"],{"s3BucketName":"lorem","s3Key":"1234-fake-uuid.json"}]',
        ], $response);
    }

    /**
     * @covers ::receiveMessage
     */
    public function testRecieveMessage()
    {
        $client = $this->getClientMock(new Config(
            [],
            'lorem',
            'ipsum',
            ConfigInterface::NEVER
        ));
        $response = $client->receiveMessage($queue_url);
        $this->assertEquals([
            'QueueUrl' => 'bar',
            'MessageBody' => '[[{"Lorem":"lorem","Ipsum":"1234-fake-uuid.json"},"fake_object_url"],{"s3BucketName":"lorem","s3Key":"1234-fake-uuid.json"}]',
        ], $response);
    }

    /**
     * @covers ::deleteMessage
     */
    public function testDeleteMessage()
    {
        $client = $this->getClientMock(new Config(
            [],
            'lorem',
            'ipsum',
            ConfigInterface::NEVER
        ));
        $response = $client->deleteMessage($queue_url);
        $this->assertEquals([
            'QueueUrl' => 'bar',
            'MessageBody' => '[[{"Lorem":"lorem","Ipsum":"1234-fake-uuid.json"},"fake_object_url"],{"s3BucketName":"lorem","s3Key":"1234-fake-uuid.json"}]',
        ], $response);
    }

    /**
     * @param ConfigInterface $config
     *   The configuration for the client.
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getClientMock(ConfigInterface $config)
    {
        // Mock the AWS clients.
        $client = $this->getMockBuilder(SqsClient::class)
            ->setMethods(['getS3Client', 'getSqsClient', 'generateUuid'])
            ->setConstructorArgs([$config])
            ->getMock();

        $client->method('generateUuid')->willReturn('1234-fake-uuid');
        $s3_client = $this->prophesize(S3Client::class);
        $client->method('getS3Client')->willReturn($s3_client->reveal());
        $s3_client->upload(Argument::type('string'), Argument::type('string'), Argument::type('string'))
            ->will(function ($args) {
                return new Result([
                    '@metadata' => ['Lorem' => $args[0], 'Ipsum' => $args[1]],
                    'ObjectUrl' => 'fake_object_url',
                ]);
            });
        $sqs_client = $this->prophesize(AwsSqsClient::class);
        $sqs_client->sendMessage(Argument::type('array'))->willReturnArgument(0);
        $client->method('getSqsClient')->willReturn($sqs_client->reveal());

        return $client;
    }

    /**
     * @covers ::isTooBig
     * @dataProvider isTooBigProvider
     */
    public function testIsTooBig($message, $is_too_big)
    {
        $client = new SqsClient(new Config(
            [],
            'lorem',
            'ipsum',
            ConfigInterface::NEVER
        ));
        // Data with more than 2 bytes is considered too big.
        $this->assertSame($is_too_big, $client->isTooBig($message, 2 / 1024));
    }

    /**
     * Test data for the isTooBig test.
     *
     * @return array
     *   The data for the test method.
     */
    public function isTooBigProvider()
    {
        return [
            ['', FALSE],
            [NULL, FALSE],
            ['a', FALSE],
            ['aa', FALSE],
            ['aaa', TRUE],
            [TRUE, FALSE],
            [FALSE, FALSE],
            // Multi byte single characters.
            ['😱', TRUE], // 3 bytes character.
            ['añ', TRUE],  // 2 bytes character.
        ];
    }

}
