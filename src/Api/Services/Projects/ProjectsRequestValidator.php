<?php

namespace App\Api\Services\Projects;

use App\Api\Services\Base\AbstractRequestValidator;
use App\Api\Services\ValidationWrapper;
use App\User\UserManager;
use OpenAPI\Server\Model\UpdateProjectRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProjectsRequestValidator extends AbstractRequestValidator
{

  public final const MIN_NAME_LENGTH = 1;
  public final const MAX_NAME_LENGTH = 255;
  public final const MAX_DESCRIPTION_LENGTH = 10_000;
  public final const MAX_CREDITS_LENGTH = 3_000;

  public function __construct(ValidatorInterface $validator, TranslatorInterface $translator, private readonly UserManager $user_manager)
  {
    parent::__construct($validator, $translator);
  }

  public function validateUserExists(string $user_id): bool
  {
    return '' !== trim($user_id)
      && !ctype_space($user_id)
      && null !== $this->user_manager->findOneBy(['id' => $user_id]);
  }

  public function validateUploadFile(string $checksum, UploadedFile $file, string $locale): ValidationWrapper
  {
    $KEY = 'error';

    if (!$file->isValid()) {
      return $this->getValidationWrapper()->addError(
        $this->__('api.projectsPost.upload_error', [], $locale), $KEY
      );
    }

    $calculated_checksum = md5_file($file->getPathname());
    if (strtolower((string) $calculated_checksum) != strtolower($checksum)) {
      return $this->getValidationWrapper()->addError(
        $this->__('api.projectsPost.invalid_checksum', [], $locale), $KEY
      );
    }

    return $this->getValidationWrapper();
  }

  public function validateUpdateRequest(UpdateProjectRequest $request, string $locale): ValidationWrapper
  {
    if (!is_null($request->getName())) {
      $this->validateName($request->getName(), $locale);
    }

    if (!is_null($request->getDescription())) {
      $this->validateDescription($request->getDescription(), $locale);
    }

    if (!is_null($request->getCredits())) {
      $this->validateCredits($request->getCredits(), $locale);
    }

    if (!is_null($request->getScreenshot())) {
      $this->validateScreenshot($request->getScreenshot(), $locale);
    }

    return $this->getValidationWrapper();
  }

  private function validateName(string $name, string $locale): void
  {
    $KEY = 'name';

    if (strlen($name) < self::MIN_NAME_LENGTH) {
      $this->getValidationWrapper()->addError($this->__('api.project.nameEmpty', [], $locale), $KEY);
    } elseif (strlen($name) > self::MAX_NAME_LENGTH) {
      $this->getValidationWrapper()->addError($this->__('api.project.nameTooLong', [], $locale), $KEY);
    }
  }

  private function validateDescription(string $description, string $locale): void
  {
    $KEY = 'description';

    if (strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
      $this->getValidationWrapper()->addError($this->__('api.project.descriptionTooLong', [], $locale), $KEY);
    }
  }

  private function validateCredits(string $credits, string $locale): void
  {
    $KEY = 'credits';

    if (strlen($credits) > self::MAX_CREDITS_LENGTH) {
      $this->getValidationWrapper()->addError($this->__('api.project.creditsTooLong', [], $locale), $KEY);
    }
  }

  private function validateScreenshot(string $screenshot, string $locale): void
  {
    $KEY = 'screenshot';

    if (preg_match('/^data:image\/([^;]+);base64,([A-Za-z0-9\/+=]+)$/', $screenshot, $matches) === 1) {
      // $image_type = $matches[1];
      $image_binary = base64_decode($matches[2]);
      if ($image_binary === false) {
        $this->getValidationWrapper()->addError($this->__('api.project.screenshotInvalid', [], $locale), $KEY);
      } else {
        try {
          $imagick = new \Imagick();
          $imagick->readImageBlob($image_binary);
        } catch (\ImagickException $e) {
          $this->getValidationWrapper()->addError($this->__('api.project.screenshotInvalid', [], $locale), $KEY);
        }
      }
    } else {
      $this->getValidationWrapper()->addError($this->__('api.project.screenshotInvalid', [], $locale), $KEY);
    }
  }
}
