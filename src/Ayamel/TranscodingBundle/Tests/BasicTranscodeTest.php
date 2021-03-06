<?php

use Ayamel\ApiBundle\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

/**
 * This tests initiating the transcode for a Resource by:
 *
 * - using the TranscodingManager directly
 * - running the TranscodeResourceCommand from the CLI
 * - asynchronously via the RabbitMQ consumer
 *
 * These tests are not part of the "transcoding" group ignored on Travis for a reason. Underlying these
 * transcodes are relying on overridden config that just transcodes text files, so the overhead is low, wheras
 * the "transcoding" grouped tests all require installation of software that may take a long time, and the tests
 * themselves are very slow.
 *
 * This group of tests ensures the the transcoding system is architecturally sound without require
 * extreme overhead.
 *
 * @package AyamelTranscodingBundle
 * @author Evan Villemez
 */
class BasicTranscodeTest extends ApiTestCase
{

    public function testTranscodeManagerTranscodeResource()
    {
        $data = array(
            'title' => 'test',
            'type' => 'data'
        );

        $response = $this->getJson('POST', '/api/v1/resources?_key=45678isafgd56789asfgdhf4567', array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ), json_encode($data));
        $this->assertSame(201, $response['response']['code']);
        $this->assertFalse(isset($response['resource']['content']));
        $this->assertSame('awaiting_content', $response['resource']['status']);
        $resourceId = $response['resource']['id'];
        $uploadUrl = substr($response['contentUploadUrl'], strlen('http://localhost'));

        //create uploaded file
        $testFilePath = __DIR__."/sample_files/lorem.txt";
        $uploadedFile = new UploadedFile(
            $testFilePath,
            'lorem.txt',
            'text/plain',
            filesize($testFilePath)
        );

        $content = $this->getJson('POST', $uploadUrl.'?_key=45678isafgd56789asfgdhf4567', array(), array('file' => $uploadedFile));
        $this->assertSame(202, $content['response']['code']);
        $this->assertSame('awaiting_processing', $content['resource']['status']);
        $this->assertSame($data['title'], $content['resource']['title']);
        $this->assertTrue(isset($content['resource']['content']));
        $this->assertTrue(isset($content['resource']['content']['files']));
        $this->assertSame(1, count($content['resource']['content']['files']));
        $data = $content['resource']['content']['files'][0];
        $this->assertTrue(isset($data['downloadUri']));
        $this->assertSame('text/plain', $data['mime']);
        $this->assertSame('text/plain', $data['mimeType']);
        $this->assertSame(filesize($testFilePath), $data['bytes']);

        $resource = $this->getContainer()->get('ayamel.transcoding.manager')->transcodeResource($resourceId);
        $this->assertTrue(isset($resource->content));
        $files = $resource->content->getFiles();
        $this->assertSame(2, count($files));
    }

    public function testTranscodeResourceCommand()
    {
        $data = array(
            'title' => 'test',
            'type' => 'data'
        );

        $response = $this->getJson('POST', '/api/v1/resources?_key=45678isafgd56789asfgdhf4567', array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ), json_encode($data));
        $this->assertSame(201, $response['response']['code']);
        $this->assertFalse(isset($response['resource']['content']));
        $this->assertSame('awaiting_content', $response['resource']['status']);
        $resourceId = $response['resource']['id'];
        $uploadUrl = substr($response['contentUploadUrl'], strlen('http://localhost'));

        //create uploaded file
        $testFilePath = __DIR__."/sample_files/lorem.txt";
        $uploadedFile = new UploadedFile(
            $testFilePath,
            'lorem.txt',
            'text/plain',
            filesize($testFilePath)
        );

        $content = $this->getJson('POST', $uploadUrl.'?_key=45678isafgd56789asfgdhf4567', array(), array('file' => $uploadedFile));

        $this->assertSame(202, $content['response']['code']);
        $this->assertSame('awaiting_processing', $content['resource']['status']);
        $this->assertSame($data['title'], $content['resource']['title']);
        $this->assertTrue(isset($content['resource']['content']));
        $this->assertTrue(isset($content['resource']['content']['files']));
        $this->assertSame(1, count($content['resource']['content']['files']));
        $data = $content['resource']['content']['files'][0];
        $this->assertTrue(isset($data['downloadUri']));
        $this->assertSame('text/plain', $data['mime']);
        $this->assertSame('text/plain', $data['mimeType']);
        $this->assertSame(filesize($testFilePath), $data['bytes']);

        //now run transcode command directly, the --force flag makes it run immediately, instead
        //of dispatching the transcode job into the queue to be handled by rabbit
        $this->runCommand(sprintf('api:resource:transcode %s --force', $resourceId));

        //now get resource - expect 2 files and changed status
        $json = $this->getJson('GET', '/api/v1/resources/'.$resourceId.'?_key=45678isafgd56789asfgdhf4567', array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ));

        $expected = array(
            'mime' => 'text/plain',
            'mimeType' => 'text/plain',
            'representation' => 'transcoding',
            'quality' => 0,
            'bytes' => filesize($testFilePath)
        );

        $this->assertSame(200, $json['response']['code']);
        $this->assertSame('normal', $json['resource']['status']);
        $this->assertSame(2, count($json['resource']['content']['files']));
        $transcoded = $json['resource']['content']['files'][1];
        $this->assertSame($expected['mime'], $transcoded['mime']);
        $this->assertSame($expected['mimeType'], $transcoded['mimeType']);
        $this->assertSame($expected['representation'], $transcoded['representation']);
        $this->assertSame($expected['quality'], $transcoded['quality']);
        $this->assertSame($expected['bytes'], $transcoded['bytes']);
        $this->assertTrue(isset($transcoded['downloadUri']));
    }

    public function testTranscodeViaRabbitMQ()
    {
        //start the rabbitmq consumer, clear the queue
        $container = $this->getContainer();
        try {
            $container->get('old_sound_rabbit_mq.transcoding_producer')->getChannel()->queue_purge('transcoding');
        } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
            //swallow this error because of travis
        }

        //start rabbit process
        $consolePath = $container->getParameter('kernel.root_dir').DIRECTORY_SEPARATOR."console";
        $rabbitProcess = new Process(sprintf('%s --env=test rabbitmq:consumer transcoding --messages=1 --verbose', $consolePath));
        $rabbitProcess->start();
        usleep(500000); //wait half a second, check to make sure process is still up
        if (!$rabbitProcess->isRunning()) {
            throw new \RuntimeException(($rabbitProcess->isSuccessful()) ? $rabbitProcess->getOutput() : $rabbitProcess->getErrorOutput());
        }

        //create resource
        $data = array(
            'title' => 'test',
            'type' => 'data'
        );
        $response = $this->getJson('POST', '/api/v1/resources?_key=45678isafgd56789asfgdhf4567', array(), array(), array(
            'CONTENT_TYPE' => 'application/json'
        ), json_encode($data));
        $this->assertSame(201, $response['response']['code']);
        $this->assertFalse(isset($response['resource']['content']));
        $this->assertSame('awaiting_content', $response['resource']['status']);
        $resourceId = $response['resource']['id'];
        $uploadUrl = substr($response['contentUploadUrl'], strlen('http://localhost'));

        //upload the test file
        $testFilePath = __DIR__."/sample_files/lorem.txt";
        $uploadedFile = new UploadedFile(
            $testFilePath,
            'lorem.txt',
            'text/plain',
            filesize($testFilePath)
        );
        $content = $this->getJson('POST', $uploadUrl.'?_key=45678isafgd56789asfgdhf4567', array(), array('file' => $uploadedFile));
        $this->assertSame(202, $content['response']['code']);
        $this->assertSame('awaiting_processing', $content['resource']['status']);
        $this->assertSame($data['title'], $content['resource']['title']);
        $this->assertTrue(isset($content['resource']['content']));
        $this->assertTrue(isset($content['resource']['content']['files']));
        $this->assertSame(1, count($content['resource']['content']['files']));
        $data = $content['resource']['content']['files'][0];
        $this->assertTrue(isset($data['downloadUri']));
        $this->assertSame('text/plain', $data['mime']);
        $this->assertSame('text/plain', $data['mimeType']);
        $this->assertSame(filesize($testFilePath), $data['bytes']);

        //wait for the rabbit process to exit after it has
        //transcoded the resource, then make some assertions
        $tester = $this;
        $rabbitProcess->wait(function($type, $output) use ($tester, $resourceId, $rabbitProcess) {
            //I'm not sure why I have to wait here in this loop... theoretically this should have only
            //been triggered if the process was actually done... ?
            while ($rabbitProcess->isRunning()) {
                usleep(500000); //wait half a second
            }

            //var_dump($output);

            if (!$rabbitProcess->isSuccessful()) {
                throw new \RuntimeException($rabbitProcess->getErrorOutput());
            }

            $data = $tester->getJson('GET', '/api/v1/resources/'.$resourceId);
            $tester->assertSame(200, $data['response']['code']);
            $tester->assertSame('normal', $data['resource']['status']);
            $tester->assertTrue(isset($data['resource']['content']['files']));
            $files = $data['resource']['content']['files'];
            $tester->assertSame(2, count($files));
        });
    }
}
