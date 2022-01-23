<?php

namespace Translation;

use App\Translation\GoogleTranslateApi;
use Google\Cloud\Core\Exception\ServiceException;
use Google\Cloud\Translate\V2\TranslateClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @coversNothing
 */
class GoogleTranslateApiTest extends TestCase
{
  private GoogleTranslateApi $api;

  /**
   * @var MockObject|TranslateClient
   */
  private $client;

  protected function setUp(): void
  {
    $this->client = $this->createMock(TranslateClient::class);
    $this->api = new GoogleTranslateApi($this->client, $this->createMock(LoggerInterface::class));
  }

  public function testSuccessDetectedLanguage(): void
  {
    $response = [
      'source' => 'en',
      'text' => 'translated',
    ];
    $this->client->expects($this->once())->method('translate')->willReturn($response);

    $result = $this->api->translate('testing', null, 'fr');

    $this->assertEquals('en', $result->detected_source_language);
    $this->assertEquals('translated', $result->translation);
  }

  public function testSuccessSpecifiedLanguage(): void
  {
    $response = [
      'source' => 'en',
      'text' => 'test',
    ];
    $this->client->expects($this->once())->method('translate')->willReturn($response);

    $result = $this->api->translate('testing', 'en', 'fr');

    $this->assertNull($result->detected_source_language);
    $this->assertEquals('test', $result->translation);
  }

  public function testNullResponse(): void
  {
    $this->client->expects($this->once())->method('translate')->willReturn(null);
    $result = $this->api->translate('testing', null, 'fr');
    $this->assertNull($result);
  }

  public function testExceptionThrown(): void
  {
    $exception = $this->createMock(ServiceException::class);
    $this->client->expects($this->once())->method('translate')->willThrowException($exception);
    $result = $this->api->translate('testing', null, 'fr');
    $this->assertNull($result);
  }
}