<?php

namespace App\Api\Services\User;

use App\Api\Services\Base\AbstractRequestValidator;
use App\Api\Services\ValidationWrapper;
use App\DB\Entity\User\User;
use App\User\UserManager;
use Imagick;
use ImagickException;
use OpenAPI\Server\Model\RegisterRequest;
use OpenAPI\Server\Model\ResetPasswordRequest;
use OpenAPI\Server\Model\UpdateUserRequest;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UserRequestValidator extends AbstractRequestValidator
{
  public const MIN_PASSWORD_LENGTH = 6;
  public const MAX_PASSWORD_LENGTH = 4096;

  public const MIN_USERNAME_LENGTH = 3;
  public const MAX_USERNAME_LENGTH = 180;

  public const MODE_REGISTER = 'register_mode';
  public const MODE_RESET_PASSWORD = 'reset_password_mode';
  public const MODE_UPDATE = 'update_mode';

  public function __construct(ValidatorInterface $validator, TranslatorInterface $translator, private readonly UserManager $user_manager, private readonly EncoderFactoryInterface $encoder_factory)
  {
    parent::__construct($validator, $translator);
  }

  public function validateRegistration(RegisterRequest $request, string $locale): ValidationWrapper
  {
    $this->validateEmail($request->getEmail(), $locale, self::MODE_REGISTER);
    $this->validateUsername($request->getUsername(), $locale, self::MODE_REGISTER);
    $this->validatePassword($request->getPassword(), $locale, self::MODE_REGISTER);

    if (!is_null($request->getPicture())) {
      $picture_out = null;
      $this->validateAndResizePicture($request->getPicture(), $picture_out, $locale);
      $request->setPicture($picture_out);
    }

    return $this->getValidationWrapper();
  }

  public function validateUpdateRequest(User $user, UpdateUserRequest $request, string $locale): ValidationWrapper
  {
    if (!is_null(($request->getEmail())) && $user->getEmail() !== $request->getEmail()) {
      $this->validateEmail($request->getEmail(), $locale, self::MODE_UPDATE);
    }

    if (!is_null(($request->getUsername())) && $user->getUsername() !== $request->getUsername()) {
      $this->validateUsername($request->getUsername(), $locale, self::MODE_UPDATE);
    }

    if (!is_null(($request->getPassword()))) {
      $this->validateCurrentPassword($user, $request->getCurrentPassword(), $locale, self::MODE_UPDATE);
      $this->validatePassword($request->getPassword(), $locale, self::MODE_UPDATE);
    }

    if (!is_null($request->getPicture())) {
      $picture_out = null;
      $this->validateAndResizePicture($request->getPicture(), $picture_out, $locale);
      $request->setPicture($picture_out);
    }

    return $this->getValidationWrapper();
  }

  public function validateResetPasswordRequest(ResetPasswordRequest $request, string $locale): ValidationWrapper
  {
    $this->validateEmail($request->getEmail(), $locale, self::MODE_RESET_PASSWORD);

    return $this->getValidationWrapper();
  }

  private function validateEmail(?string $email, string $locale, string $mode): void
  {
    $KEY = 'email';

    if (self::MODE_UPDATE !== $mode && (is_null($email) || '' === trim($email))) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.emailMissing', [], $locale), $KEY);
    } elseif (self::MODE_UPDATE === $mode && '' === trim($email)) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.emailEmpty', [], $locale), $KEY);
    } elseif (0 !== count($this->validate($email, new Email()))) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.emailInvalid', [], $locale), $KEY);
    } elseif (self::MODE_RESET_PASSWORD !== $mode && null != $this->user_manager->findUserByEmail($email)) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.emailAlreadyInUse', [], $locale), $KEY);
    }
  }

  private function validateUsername(?string $username, string $locale, string $mode): void
  {
    $KEY = 'username';

    if (self::MODE_UPDATE !== $mode && (is_null($username) || '' === trim($username))) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.usernameMissing', [], $locale), $KEY);
    } elseif (self::MODE_UPDATE === $mode && '' === trim($username)) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.usernameEmpty', [], $locale), $KEY);
    } elseif (strlen((string) $username) < self::MIN_USERNAME_LENGTH) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.usernameTooShort', [], $locale), $KEY);
    } elseif (strlen((string) $username) > self::MAX_USERNAME_LENGTH) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.usernameTooLong', [], $locale), $KEY);
    } elseif (filter_var(str_replace(' ', '', (string) $username), FILTER_VALIDATE_EMAIL)) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.usernameContainsEmail', [], $locale), $KEY);
    } elseif (null != $this->user_manager->findUserByUsername($username)) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.usernameAlreadyInUse', [], $locale), $KEY);
    } elseif (0 === strncasecmp($username, User::$SCRATCH_PREFIX, strlen(User::$SCRATCH_PREFIX))) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.usernameInvalid', [], $locale), $KEY);
    }
  }

  private function validatePassword(?string $password, string $locale, string $mode): void
  {
    $KEY = 'password';

    if (self::MODE_UPDATE !== $mode && (is_null($password) || '' === trim($password))) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.passwordMissing', [], $locale), $KEY);
    } elseif (self::MODE_UPDATE === $mode && '' === trim($password)) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.passwordEmpty', [], $locale), $KEY);
    } elseif (strlen((string) $password) < self::MIN_PASSWORD_LENGTH) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.passwordTooShort', [], $locale), $KEY);
    } elseif (strlen((string) $password) > self::MAX_PASSWORD_LENGTH) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.passwordTooLong', [], $locale), $KEY);
    } elseif (!mb_detect_encoding($password, 'ASCII', true)) {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.passwordInvalidChars', [], $locale), $KEY);
    }
  }

  private function validateCurrentPassword(User $user, ?string $current_password, string $locale, string $mode): void
  {
    $KEY = 'current_password';
    if (self::MODE_UPDATE === $mode) { // non-update mode doesn't need current password
      if (empty($current_password)) {
        $this->getValidationWrapper()->addError($this->__('api.updateUser.currentPasswordMissing', [], $locale), $KEY);
      } else {
        $encoder = $this->encoder_factory->getEncoder($user);
        if (!$encoder->isPasswordValid($user->getPassword(), $current_password, $user->getSalt())) {
          $this->getValidationWrapper()->addError($this->__('api.updateUser.currentPasswordWrong', [], $locale), $KEY);
        }
      }
    }
  }

  private function validateAndResizePicture(string $picture_in, ?string &$picture_out, string $locale)
  {
    $KEY = 'picture';
    $image_size = 300;
    if (1 === preg_match('/^data:image\/([^;]+);base64,([A-Za-z0-9\/+=]+)$/', $picture_in, $matches)) {
      // $image_type = $matches[1];
      $image_binary = base64_decode($matches[2], true);
      if (false === $image_binary) {
        $this->getValidationWrapper()->addError($this->__('api.registerUser.pictureInvalid', [], $locale), $KEY);
      } else {
        try {
          $imagick = new Imagick();
          $imagick->readImageBlob($image_binary);
          $imagick->cropThumbnailImage($image_size, $image_size);
          $picture_out = 'data:'.$imagick->getImageMimeType().';base64,'.base64_encode($imagick->getImageBlob());
        } catch (ImagickException) {
          $this->getValidationWrapper()->addError($this->__('api.registerUser.pictureInvalid', [], $locale), $KEY);
        }
      }
    } else {
      $this->getValidationWrapper()->addError($this->__('api.registerUser.pictureInvalid', [], $locale), $KEY);
    }
  }
}
