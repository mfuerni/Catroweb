<?php

namespace App\Api\Services\User;

use App\Api\Services\Base\AbstractResponseManager;
use App\Api\Services\ResponseCache\ResponseCacheManager;
use App\DB\Entity\User\User;
use App\Security\Authentication\CookieService;
use OpenAPI\Server\Model\BasicUserDataResponse;
use OpenAPI\Server\Model\ExtendedUserDataResponse;
use OpenAPI\Server\Model\JWTResponse;
use OpenAPI\Server\Service\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UserResponseManager extends AbstractResponseManager
{

  private CookieService $cookie_service;

  public function __construct(
      TranslatorInterface  $translator,
      SerializerInterface  $serializer,
      ResponseCacheManager $response_cache_manager,
      CookieService        $cookie_service
  ) {
    parent::__construct($translator, $serializer, $response_cache_manager);
    $this->cookie_service = $cookie_service;
  }

  public function createBasicUserDataResponse(User $user): BasicUserDataResponse
  {
    return new BasicUserDataResponse([
      'id' => $user->getId(),
      'username' => $user->getUsername(),
      'about' => $user->getAbout(),
      'currentlyWorkingOn' => $user->getCurrentlyWorkingOn(),
      'projects' => $user->getPrograms()->count(),
      'followers' => $user->getFollowers()->count(),
      'following' => $user->getFollowing()->count(),
    ]);
  }

  public function createExtendedUserDataResponse(User $user): ExtendedUserDataResponse
  {
    return new ExtendedUserDataResponse([
      'id' => $user->getId(),
      'username' => $user->getUsername(),
      'about' => $user->getAbout(),
      'currentlyWorkingOn' => $user->getCurrentlyWorkingOn(),
      'email' => $user->getEmail(),
      'projects' => $user->getPrograms()->count(),
      'followers' => $user->getFollowers()->count(),
      'following' => $user->getFollowing()->count(),
    ]);
  }

  public function createUsersDataResponse(array $users): array
  {
    $users_data_response = [];
    foreach ($users as $user) {
      $user_data = $this->createBasicUserDataResponse($user);
      $users_data_response[] = $user_data;
    }

    return $users_data_response;
  }

  public function createUserRegisteredResponse(string $token, string $refresh_token): JWTResponse
  {
    return new JWTResponse(
      [
        'token' => $token,
        'refresh_token' => $refresh_token,
      ]
    );
  }

  public function addAuthenticationCookiesToHeader(string $token, string $refresh_token, array &$responseHeaders = null): void
  {
    $responseHeaders['Set-Cookie'] = [
        $this->cookie_service->createBearerTokenCookie($token),
        $this->cookie_service->createRefreshTokenCookie($refresh_token)
    ];
  }
}
