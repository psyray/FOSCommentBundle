<?php

/*
 * This file is part of the FOSCommentBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace FOS\CommentBundle\Tests\Acl;

use FOS\CommentBundle\Acl\AclCommentManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Tests the functionality provided by Acl\AclCommentManager.
 *
 * @author Tim Nagel <tim@nagel.com.au>
 */
class AclCommentManagerTest extends TestCase
{
    protected $realManager;
    protected $commentSecurity;
    protected $threadSecurity;
    protected $thread;
    protected $comment;
    protected $sorting_strategy;
    protected $depth;
    protected $parent;

    public function setUp(): void
    {
        $this->realManager = $this->getMockBuilder('FOS\CommentBundle\Model\CommentManagerInterface')->getMock();
        $this->commentSecurity = $this->getMockBuilder('FOS\CommentBundle\Acl\CommentAclInterface')->getMock();
        $this->threadSecurity = $this->getMockBuilder('FOS\CommentBundle\Acl\ThreadAclInterface')->getMock();
        $this->thread = $this->getMockBuilder('FOS\CommentBundle\Model\ThreadInterface')->getMock();
        $this->comment = $this->getMockBuilder('FOS\CommentBundle\Model\CommentInterface')->getMock();
        $this->sorting_strategy = 'date_asc';
        $this->depth = 0;
        $this->parent = null;
    }

    public function testFindCommentTreeByThreadNestedResult()
    {
        $expectedResult = [
            ['comment' => $this->comment, 'children' => [
                ['comment' => $this->comment, 'children' => []],
                ['comment' => $this->comment, 'children' => []],
            ]],
        ];

        $this->realManager->expects($this->once())
             ->method('findCommentTreeByThread')
             ->with($this->equalTo($this->thread),
                   $this->equalTo($this->sorting_strategy),
                   $this->equalTo($this->depth))
             ->will($this->returnValue($expectedResult));
        $this->configureCommentSecurity('canView', true);
        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);

        $result = $manager->findCommentTreeByThread($this->thread, $this->sorting_strategy, $this->depth);
        $this->assertSame($expectedResult, $result);
    }

    public function testFindCommentTreeByThread()
    {
        self::expectException(AccessDeniedException::class);

        $expectedResult = [['comment' => $this->comment, 'children' => []]];
        $this->realManager->expects($this->once())
             ->method('findCommentTreeByThread')
             ->with($this->equalTo($this->thread),
                   $this->equalTo($this->sorting_strategy),
                   $this->equalTo($this->depth))
             ->will($this->returnValue($expectedResult));
        $this->configureCommentSecurity('canView', false);
        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);

        $manager->findCommentTreeByThread($this->thread, $this->sorting_strategy, $this->depth);
    }

    public function testFindCommentsByThreadCanView()
    {
        $expectedResult = [$this->comment];
        $this->realManager->expects($this->once())
            ->method('findCommentsByThread')
            ->with($this->thread,
                   $this->depth)
            ->will($this->returnValue($expectedResult));
        $this->configureCommentSecurity('canView', true);
        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);

        $result = $manager->findCommentsByThread($this->thread, $this->depth);
        $this->assertSame($expectedResult, $result);
    }

    public function testFindCommentsByThread()
    {
        self::expectException(AccessDeniedException::class);

        $expectedResult = [$this->comment];
        $this->realManager->expects($this->once())
            ->method('findCommentsByThread')
            ->with($this->thread,
                   $this->depth)
            ->will($this->returnValue($expectedResult));
        $this->configureCommentSecurity('canView', false);
        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);

        $manager->findCommentsByThread($this->thread, $this->depth);
    }

    public function testFindCommentById()
    {
        self::expectException(AccessDeniedException::class);

        $commentId = 123;
        $expectedResult = $this->comment;

        $this->realManager->expects($this->once())
            ->method('findCommentById')
            ->with($commentId)
            ->will($this->returnValue($expectedResult));

        $this->configureCommentSecurity('canView', false);
        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);

        $manager->findCommentById($commentId);
    }

    public function testFindCommentByIdCanView()
    {
        $commentId = 123;
        $expectedResult = $this->comment;

        $this->realManager->expects($this->once())
            ->method('findCommentById')
            ->with($commentId)
            ->will($this->returnValue($expectedResult));

        $this->configureCommentSecurity('canView', true);
        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);

        $result = $manager->findCommentById($commentId);
        $this->assertSame($expectedResult, $result);
    }

    public function testFindCommentTreeByCommentId()
    {
        self::expectException(AccessDeniedException::class);

        $commentId = 123;
        $expectedResult = [['comment' => $this->comment, 'children' => []]];

        $this->realManager->expects($this->once())
            ->method('findCommentTreeByCommentId')
            ->with($commentId,
                   $this->sorting_strategy)
            ->will($this->returnValue($expectedResult));

        $this->configureCommentSecurity('canView', false);
        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);

        $manager->findCommentTreeByCommentId($commentId, $this->sorting_strategy);
    }

    public function testFindCommentTreeByCommentIdCanView()
    {
        $commentId = 123;
        $expectedResult = [['comment' => $this->comment, 'children' => []]];

        $this->realManager->expects($this->once())
            ->method('findCommentTreeByCommentId')
            ->with($commentId,
                   $this->sorting_strategy)
            ->will($this->returnValue($expectedResult));

        $this->configureCommentSecurity('canView', true);
        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);

        $result = $manager->findCommentTreeByCommentId($commentId, $this->sorting_strategy);
        $this->assertSame($expectedResult, $result);
    }

    public function testSaveCommentNoReplyPermission()
    {
        self::expectException(AccessDeniedException::class);

        $this->saveCommentSetup();
        $this->configureThreadSecurity('canView', true);
        $this->configureCommentSecurity('canReply', false);

        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);
        $manager->saveComment($this->comment, $this->parent);
    }

    public function testSaveCommentNoThreadViewPermission()
    {
        self::expectException(AccessDeniedException::class);

        $this->saveCommentSetup();
        $this->configureThreadSecurity('canView', false);

        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);
        $manager->saveComment($this->comment);
    }

    public function testSaveComment()
    {
        $this->saveCommentSetup();
        $this->configureCommentSecurity('canReply', true);
        $this->configureThreadSecurity('canView', true);
        $this->commentSecurity->expects($this->once())
            ->method('setDefaultAcl')
            ->with($this->comment);

        $this->realManager->expects($this->once())
             ->method('isNewComment')
             ->with($this->equalTo($this->comment))
             ->will($this->returnValue(true));

        $this->realManager->expects($this->once())
             ->method('saveComment')
             ->with($this->equalTo($this->comment))
             ->will($this->returnValue(true));

        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);
        $manager->saveComment($this->comment, $this->parent);
    }

    public function testSaveEditedComment()
    {
        $this->editCommentSetup();
        $this->configureCommentSecurity('canEdit', true);
        $this->commentSecurity->expects($this->never())
            ->method('setDefaultAcl');

        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);
        $manager->saveComment($this->comment, $this->parent);
    }

    public function testSaveEditedCommentNoEditPermission()
    {
        self::expectException(AccessDeniedException::class);

        $this->editCommentSetup();
        $this->configureCommentSecurity('canEdit', false);

        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);
        $manager->saveComment($this->comment);
    }

    public function testCreateComment()
    {
        $this->parent = $this->getMockBuilder('FOS\CommentBundle\Model\CommentInterface')->getMock();

        $this->realManager->expects($this->once())
            ->method('createComment')
            ->with($this->thread,
                   $this->parent)
            ->will($this->returnValue($this->comment));

        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);
        $return = $manager->createComment($this->thread, $this->parent);

        $this->assertSame($this->comment, $return);
    }

    public function testGetClass()
    {
        $class = 'Test\\Class';

        $this->realManager->expects($this->once())
            ->method('getClass')
            ->will($this->returnValue($class));

        $manager = new AclCommentManager($this->realManager, $this->commentSecurity, $this->threadSecurity);
        $result = $manager->getClass();

        $this->assertSame($class, $result);
    }

    protected function commentReturnsThread()
    {
        $this->comment->expects($this->once())
            ->method('getThread')
            ->will($this->returnValue($this->thread));
    }

    protected function configureCommentSecurity($method, $return)
    {
        $this->commentSecurity->expects($this->any())
             ->method($method)
             ->will($this->returnValue($return));
    }

    protected function configureThreadSecurity($method, $return)
    {
        $this->threadSecurity->expects($this->any())
             ->method($method)
             ->will($this->returnValue($return));
    }

    protected function saveCommentSetup()
    {
        $this->parent = $this->getMockBuilder('FOS\CommentBundle\Model\CommentInterface')->getMock();
        $this->commentReturnsThread();
    }

    protected function editCommentSetup()
    {
        $this->saveCommentSetup();
        $this->configureCommentSecurity('canReply', true);
        $this->configureThreadSecurity('canView', true);

        $this->realManager->expects($this->once())
             ->method('isNewComment')
             ->with($this->equalTo($this->comment))
             ->will($this->returnValue(false));
    }
}
