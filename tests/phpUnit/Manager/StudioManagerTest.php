<?php

namespace Tests\phpUnit\Manager;

use App\Catrobat\Services\TestEnv\DataFixtures\ProjectDataFixtures;
use App\Catrobat\Services\TestEnv\DataFixtures\UserDataFixtures;
use App\Entity\Studio;
use App\Entity\StudioActivity;
use App\Entity\StudioProgram;
use App\Entity\StudioUser;
use App\Entity\User;
use App\Entity\UserComment;
use App\Entity\UserManager;
use App\Manager\StudioManager;
use Doctrine\ORM\EntityManager;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\phpUnit\CatrowebPhpUnit\CatrowebTestCase;

/**
 * @internal
 * @coversNothing
 */
class StudioManagerTest extends CatrowebTestCase
{
  /**
   * @var StudioManager|MockObject
   */
  protected $object;
  /**
   * @var UserDataFixtures|null
   */
  protected $user_fixture;
  /**
   * @var ProjectDataFixtures|null
   */
  protected $project_fixture;
  /**
   * @var UserManager|null
   */
  protected $user_manager;
  /**
   * @var Studio|null
   */
  protected $studio;
  /**
   * @var User
   */
  protected $user;
  /**
   * @var EntityManager
   */
  protected $entity_manager;

  protected function setUp(): void
  {
    $kernel = self::bootKernel();
    $this->entity_manager = $kernel->getContainer()->get('doctrine')->getManager();
    $studio_repository = $this->entity_manager->getRepository(Studio::class);
    $studio_activity_repository = $this->entity_manager->getRepository(StudioActivity::class);
    $studio_project_repository = $this->entity_manager->getRepository(StudioProgram::class);
    $studio_user_repository = $this->entity_manager->getRepository(StudioUser::class);
    $user_comment_repository = $this->entity_manager->getRepository(UserComment::class);
    $this->object = $this->getMockBuilder(StudioManager::class)->setConstructorArgs(
      [$this->entity_manager, $studio_repository, $studio_activity_repository,
        $studio_project_repository, $studio_user_repository, $user_comment_repository, ])
      ->getMockForAbstractClass()
    ;
    $this->user_manager = $kernel->getContainer()->get(UserManager::class);
    $this->user_fixture = $kernel->getContainer()->get(UserDataFixtures::class);
    $this->project_fixture = $kernel->getContainer()->get(ProjectDataFixtures::class);
    $this->user = $this->user_manager->findUserByUsername('catroweb') ?? $this->user_fixture->insertUser(['name' => 'catroweb', 'password' => '123456']);
    $this->studio = $this->object->createStudio($this->user, 'test name', 'test description');
  }

  protected function tearDown(): void
  {
    $this->object->deleteStudio($this->studio, $this->user);
    $this->entity_manager->close();
  }

  /**
   * @group integration
   * @small
   */
  public function testTestClassExists(): void
  {
    $this->assertTrue(class_exists(StudioManager::class));
    $this->assertInstanceOf(StudioManager::class, $this->object);
  }

  /**
   * @group integration
   * @small
   */
  public function testCreateDeleteStudio(): void
  {
    $this->assertInstanceOf(Studio::class, $this->studio);
    $this->assertNotNull($this->object->findStudioUser($this->user, $this->studio));
    $this->assertCount(1, $this->object->findAllStudioActivities($this->studio));
    $this->assertSame($this->studio, $this->object->findStudioById($this->studio->getId()));
    $studio_cloned = clone $this->studio;
    $this->object->deleteStudio($this->studio, $this->user);
    $this->assertNull($this->object->findStudioById($studio_cloned->getId()));
    $this->assertNull($this->object->findStudioUser($this->user, $studio_cloned));
    $this->assertEmpty($this->object->findAllStudioActivities($studio_cloned));
  }

  /**
   * @group integration
   * @small
   */
  public function testEditStudio(): void
  {
    $newStudio = clone $this->studio;
    $newStudio->setName('new studio name');
    $newStudio->setDescription('new studio description');
    $this->assertSame($newStudio, $this->object->changeStudio($this->user, $newStudio));
    $this->assertNotSame($this->studio, $newStudio);
  }

  /**
   * @group integration
   * @small
   */
  public function testAddAndRemoveStudioUsers(): void
  {
    $newUser = $this->user_fixture->insertUser(['name' => 'amr diab', 'password' => '123456']);
    $this->assertFalse($this->object->isUserInStudio($newUser, $this->studio));
    $newStudioUser = $this->object->addUserToStudio($this->user, $this->studio, $newUser);
    $this->assertInstanceOf(StudioUser::class, $newStudioUser);
    $this->assertSame($newStudioUser, $this->object->findStudioUser($newUser, $this->studio));
    $this->assertNotNull($this->object->findStudioUser($newUser, $this->studio));
    $newUser_cloned = clone $newUser;
    $this->object->deleteUserFromStudio($this->user, $this->studio, $newUser);
    $this->assertNull($this->object->findStudioUser($newUser_cloned, $this->studio));
  }

  /**
   * @group integration
   * @small
   */
  public function testChangeStudioUserRole(): void
  {
    $newUser = $this->user_fixture->insertUser(['name' => 'leo messi', 'password' => '123456']);
    $this->assertNull($this->object->getStudioUserRole($newUser, $this->studio));
    if (is_null($this->object->addUserToStudio($this->user, $this->studio, $newUser))) {
      $this->markTestSkipped('unable to add new user to the studio');
    }
    $this->assertEquals(StudioUser::ROLE_ADMIN, $this->object->getStudioUserRole($this->user, $this->studio));
    $this->assertEquals(StudioUser::ROLE_MEMBER, $this->object->getStudioUserRole($newUser, $this->studio));
    $this->assertNull($this->object->changeStudioUserRole($newUser, $this->studio, $newUser, StudioUser::ROLE_ADMIN));
    $this->assertInstanceOf(StudioUser::class, $this->object->changeStudioUserRole($this->user, $this->studio, $newUser, StudioUser::ROLE_ADMIN));
    $this->assertEquals(StudioUser::ROLE_ADMIN, $this->object->getStudioUserRole($newUser, $this->studio));
  }

  /**
   * @group integration
   * @small
   */
  public function testChangeStudioUserStatus(): void
  {
    $newUser = $this->user_fixture->insertUser(['name' => 'luther king', 'password' => '123456']);
    $this->assertNull($this->object->getStudioUserStatus($newUser, $this->studio));
    if (is_null($this->object->addUserToStudio($this->user, $this->studio, $newUser))) {
      $this->markTestSkipped('unable to add new user to the studio');
    }
    $this->assertEquals(StudioUser::STATUS_ACTIVE, $this->object->getStudioUserStatus($newUser, $this->studio));
    $this->assertNull($this->object->changeStudioUserStatus($newUser, $this->studio, $newUser, StudioUser::STATUS_BANNED));
    $this->assertInstanceOf(StudioUser::class, $this->object->changeStudioUserStatus($this->user, $this->studio, $newUser, StudioUser::STATUS_BANNED));
    $this->assertEquals(StudioUser::STATUS_BANNED, $this->object->getStudioUserStatus($newUser, $this->studio));
  }

  /**
   * @group integration
   * @small
   */
  public function testAddEditRemoveStudioComment(): void
  {
    $adminComment = $this->object->addCommentToStudio($this->user, $this->studio, 'test comment');
    $this->assertNotNull($adminComment);

    $newUser = $this->user_fixture->insertUser(['name' => 'eminem', 'password' => '123456']);
    $this->assertNull($this->object->addCommentToStudio($newUser, $this->studio, 'Only members of a studio can add comments'));

    $this->object->addUserToStudio($this->user, $this->studio, $newUser);

    $userComment = $this->object->addCommentToStudio($newUser, $this->studio, 'normal member comment');
    $this->assertNotNull($userComment);

    $this->assertNotNull($adminComment->getId());
    $userComment_2 = $this->object->addCommentToStudio($newUser, $this->studio, 'normal user comment 2');

    $this->assertNull($this->object->editStudioComment($newUser, $adminComment->getId(), "can't edit comments that are not your own"));

    $this->object->deleteCommentFromStudio($newUser, $adminComment->getId());
    $this->assertNotNull($adminComment->getId(), "Can't delete comments that are not your own");

    $this->object->deleteCommentFromStudio($newUser, $userComment->getId());
    $this->assertNull($userComment->getId());

    $this->object->deleteCommentFromStudio($newUser, $userComment_2->getId());
    $this->assertNull($userComment_2->getId());

    $this->assertCount(1, $this->object->findAllStudioComments($this->studio));
    $this->assertEquals(1, $this->object->countStudioComments($this->studio));

    $this->object->deleteCommentFromStudio($this->user, $adminComment->getId());
    $this->assertNull($adminComment->getId());

    $this->assertCount(0, $this->object->findAllStudioComments($this->studio));
    $this->assertEquals(0, $this->object->countStudioComments($this->studio));
  }

  /**
   * @group integration
   * @small
   *
   * @throws Exception
   */
  public function testAddRemoveStudioProject(): void
  {
    $newUser = $this->user_fixture->insertUser(['name' => 'kit kat', 'password' => '123456']);
    $newUser_2 = $this->user_fixture->insertUser(['name' => 'peanut butter', 'password' => '123456']);
    $project = $this->project_fixture->insertProject(['owned by' => $newUser, 'name' => 'test prog',
      'description' => 'test desc', 'credit' => $newUser, ]);
    $studio_project = $this->object->addProjectToStudio($newUser, $this->studio, $project);
    $this->assertNull($studio_project);
    $this->object->addUserToStudio($this->user, $this->studio, $newUser);
    $this->object->addUserToStudio($this->user, $this->studio, $newUser_2);
    $studio_project = $this->object->addProjectToStudio($newUser, $this->studio, $project);
    $this->assertInstanceOf(StudioProgram::class, $studio_project);
    $this->object->deleteProjectFromStudio($newUser_2, $this->studio, $project);
    $this->assertNotNull($this->object->findStudioProject($this->studio, $project));
    $this->object->deleteProjectFromStudio($newUser, $this->studio, $project);
    $this->assertNull($this->object->findStudioProject($this->studio, $project));
    $this->object->addProjectToStudio($newUser_2, $this->studio, $project);
    $this->object->deleteProjectFromStudio($newUser, $this->studio, $project);
    $this->assertNull($this->object->findStudioProject($this->studio, $project));
    $this->object->addProjectToStudio($newUser, $this->studio, $project);
    $this->object->deleteProjectFromStudio($this->user, $this->studio, $project);
    $this->assertNull($this->object->findStudioProject($this->studio, $project));
    $this->assertCount(0, $this->object->findAllStudioProjects($this->studio));
  }

  /**
   * @group integration
   * @small
   */
  public function testAddRemoveStudioCommentReplies(): void
  {
    $studioComment = $this->object->addCommentToStudio($this->user, $this->studio, 'test comment');
    $replies = ['test reply 1', 'test reply 2'];
    $this->object->addCommentToStudio($this->user, $this->studio, $replies[0], $studioComment->getId());
    $this->object->addCommentToStudio($this->user, $this->studio, $replies[1], $studioComment->getId());
    $this->assertEquals(2, $this->object->countCommentReplies($studioComment->getId()));
    $i = 0;
    foreach ($this->object->findCommentReplies($studioComment->getId()) as $reply) {
      $this->assertInstanceOf(UserComment::class, $reply);
      $this->assertEquals($replies[$i], $reply->getText());
      ++$i;
    }
    $this->object->deleteCommentFromStudio($this->user, $studioComment->getId());

    $this->assertEquals(0, $this->object->countStudioComments($this->studio));
  }
}