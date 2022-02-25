<?php

namespace App\Security\Authentication\WebView;

use App\DB\Entity\User\User;
use App\Security\Authentication\CookieService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class WebviewAuthenticator.
 *
 * @deprecated
 */
class WebviewAuthenticator extends AbstractGuardAuthenticator
{
  /**
   * @required request cookie CATRO_LOGIN_TOKEN to automatically log in a user in the webview
   *
   *  Must be sent as cookie containing the user token
   *  Must not be empty
   *
   * @var string
   */
  public const COOKIE_TOKEN_KEY = 'CATRO_LOGIN_TOKEN';

  protected TranslatorInterface $translator;
  protected SessionInterface $session;
  private EntityManagerInterface $em;
  protected LoggerInterface $logger;
  protected UrlGeneratorInterface $url_generator;

  public function __construct(EntityManagerInterface $em, TranslatorInterface $translator, SessionInterface $session, LoggerInterface $logger, UrlGeneratorInterface $url_generator)
  {
    $this->em = $em;
    $this->translator = $translator;
    $this->session = $session;
    $this->logger = $logger;
    $this->url_generator = $url_generator;
  }

  /**
   * Called on every request to decide if this authenticator should be
   * used for the request. Returning false will cause this authenticator
   * to be skipped.
   *
   * {@inheritdoc}
   */
  public function supports(Request $request)
  {
    $this->session->set('webview-auth', false);

    return $this->hasValidTokenCookieSet($request);
  }

  /**
   * Called on every request. Return whatever credentials you want to
   * be passed to getUser() as $credentials.
   *
   * {@inheritdoc}
   */
  public function getCredentials(Request $request)
  {
    return [
      self::COOKIE_TOKEN_KEY => $request->cookies->get(self::COOKIE_TOKEN_KEY, null),
    ];
  }

  /**
   * @param mixed $credentials
   *
   * {@inheritdoc}
   */
  public function getUser($credentials, UserProviderInterface $userProvider)
  {
    $token = $credentials[self::COOKIE_TOKEN_KEY];

    if (null === $token || '' === $token) {
      throw new AuthenticationException('Empty token!');
    }

    $user = $this->em->getRepository(User::class)
      ->findOneBy(['upload_token' => $token])
    ;

    if (null === $user) {
      throw new AuthenticationException('User not found!');
    }

    // if a User object, checkCredentials() is called
    return $user;
  }

  /**
   *  Called to make sure the credentials are valid
   *    - E.g mail, username, or password
   *    - no additional checks are also valid.
   *
   * @param mixed $credentials
   *
   * {@inheritdoc}
   */
  public function checkCredentials($credentials, UserInterface $user)
  {
    // return true to cause authentication success
    return true;
  }

  /**
   * @param string $providerKey
   *
   * {@inheritdoc}
   */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
  {
    $this->session->set('webview-auth', true);

    // on success, let the request continue
    return null;
  }

  /**
   * @throws HttpException
   *
   * {@inheritdoc}
   */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
  {
    $this->logger->error('Legacy Webview Authentication failed (start): '.$exception->getMessage());

    return $this->getAuthenticateRedirect($request);
  }

  /**
   * @throws AuthenticationException
   *
   * {@inheritDoc}
   */
  public function start(Request $request, AuthenticationException $authException = null)
  {
    $this->logger->warning('Legacy Webview Authentication failed (start): '.$authException->getMessage());

    return $this->getAuthenticateRedirect($request);
  }

  /**
   * {@inheritDoc}
   */
  public function supportsRememberMe()
  {
    return false;
  }

  /**
   * @return bool
   */
  private function hasValidTokenCookieSet(Request $request)
  {
    return $request->cookies->has(self::COOKIE_TOKEN_KEY) && '' !== $request->cookies->get(self::COOKIE_TOKEN_KEY);
  }

  protected function getAuthenticateRedirect(Request $request): RedirectResponse
  {
    CookieService::clearCookie('LOGGED_IN');
    CookieService::clearCookie('CATRO_LOGIN_TOKEN');
    $request->getSession()->invalidate();

    return new RedirectResponse($this->url_generator->generate('login', ['theme' => 'app']));
  }
}