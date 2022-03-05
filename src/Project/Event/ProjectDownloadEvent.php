<?php

namespace App\Project\Event;

use App\DB\Entity\Project\Program;
use App\DB\Entity\User\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class ProjectDownloadEvent extends Event
{
  protected ?User $user;
  protected Program $project;
  protected Request $request;
  protected string $download_type;

  public function __construct(?User $user, Program $project, string $download_type, Request $request)
  {
    $this->user = $user;
    $this->project = $project;
    $this->download_type = $download_type;
    $this->request = $request;
  }

  public function getRequest(): Request
  {
    return $this->request;
  }

  public function getUser(): ?User
  {
    return $this->user;
  }

  public function getProject(): Program
  {
    return $this->project;
  }

  public function getDownloadType(): string
  {
    return $this->download_type;
  }
}