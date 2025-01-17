<?php

/*
 * This file is part of the FOSCommentBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace FOS\CommentBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Configures the DI container for CommentBundle.
 *
 * @author Thibault Duplessis <thibault.duplessis@gmail.com>
 */
class FOSCommentExtension extends Extension
{
    /**
     * Loads and processes configuration to configure the Container.
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        if ('custom' !== $config['db_driver']) {
            $loader->load(sprintf('%s.xml', $config['db_driver']));
            $def = new Definition('Doctrine\ORM\EntityManager', ['%fos_comment.model_manager_name%']);
            $def->setPublic(false);
            $def->setFactory([new Reference('doctrine'), 'getManager']);

            $container->setDefinition('fos_comment.entity_manager', $def);
        }

        foreach (['controller', 'events', 'form', 'twig', 'sorting', 'model', 'view_handler'] as $basename) {
            $loader->load(sprintf('%s.xml', $basename));
        }

        // only load acl services if acl is enabled for the project
        if (array_key_exists('acl', $config)) {
            $this->loadAcl($container, $config);
        }

        $container->setParameter('fos_comment.template.engine', $config['template']['engine']);

        $container->setParameter('fos_comment.model.comment.class', $config['class']['model']['comment']);
        $container->setParameter('fos_comment.model.thread.class', $config['class']['model']['thread']);

        if (array_key_exists('vote', $config['class']['model'])) {
            $container->setParameter('fos_comment.model.vote.class', $config['class']['model']['vote']);
        }

        $container->setParameter('fos_comment.model_manager_name', $config['model_manager_name']);

        // handle the MongoDB document manager name in a specific way as it does not have a registry to make it easy
        // TODO: change it if https://github.com/symfony/DoctrineMongoDBBundle/pull/31 is merged
        if ('mongodb' === $config['db_driver']) {
            if (null === $config['model_manager_name']) {
                $container->setAlias('fos_comment.document_manager', new Alias('doctrine.odm.mongodb.document_manager', false));
                $container->getAlias('fos_comment.document_manager')->setPublic(true);
            } else {
                $container->setAlias('fos_comment.document_manager', new Alias(sprintf('doctrine.odm.%s_mongodb.document_manager', $config['model_manager_name']), false));
                $container->getAlias('fos_comment.document_manager')->setPublic(true);
            }
        }

        $container->setParameter('fos_comment.form.comment.type', $config['form']['comment']['type']);
        $container->setParameter('fos_comment.form.comment.name', $config['form']['comment']['name']);

        $container->setParameter('fos_comment.form.thread.type', $config['form']['thread']['type']);
        $container->setParameter('fos_comment.form.thread.name', $config['form']['thread']['name']);

        $container->setParameter('fos_comment.form.commentable_thread.type', $config['form']['commentable_thread']['type']);
        $container->setParameter('fos_comment.form.commentable_thread.name', $config['form']['commentable_thread']['name']);

        $container->setParameter('fos_comment.form.delete_comment.type', $config['form']['delete_comment']['type']);
        $container->setParameter('fos_comment.form.delete_comment.name', $config['form']['delete_comment']['name']);

        $container->setParameter('fos_comment.form.vote.type', $config['form']['vote']['type']);
        $container->setParameter('fos_comment.form.vote.name', $config['form']['vote']['name']);

        $container->setParameter('fos_comment.sorting_factory.default_sorter', $config['service']['sorting']['default']);

        $container->setAlias('fos_comment.form_factory.comment', $config['service']['form_factory']['comment']);
        $container->getAlias('fos_comment.form_factory.comment')->setPublic(true);
        $container->setAlias('fos_comment.form_factory.commentable_thread', $config['service']['form_factory']['commentable_thread']);
        $container->getAlias('fos_comment.form_factory.commentable_thread')->setPublic(true);
        $container->setAlias('fos_comment.form_factory.delete_comment', $config['service']['form_factory']['delete_comment']);
        $container->getAlias('fos_comment.form_factory.delete_comment')->setPublic(true);
        $container->setAlias('fos_comment.form_factory.thread', $config['service']['form_factory']['thread']);
        $container->getAlias('fos_comment.form_factory.thread')->setPublic(true);
        $container->setAlias('fos_comment.form_factory.vote', $config['service']['form_factory']['vote']);
        $container->getAlias('fos_comment.form_factory.vote')->setPublic(true);

        if (isset($config['service']['spam_detection'])) {
            $loader->load('spam_detection.xml');
            $container->setAlias('fos_comment.spam_detection.comment', $config['service']['spam_detection']['comment']);
            $container->getAlias('fos_comment.spam_detection.comment')->setPublic(true);
        }

        if (isset($config['service']['markup'])) {
            $container->setAlias('fos_comment.markup', new Alias($config['service']['markup'], false));
            $container->getAlias('fos_comment.markup')->setPublic(true);
            $loader->load('markup.xml');
        }

        $container->setAlias('fos_comment.manager.thread', $config['service']['manager']['thread']);
        $container->getAlias('fos_comment.manager.thread')->setPublic(true);
        $container->setAlias('fos_comment.manager.comment', $config['service']['manager']['comment']);
        $container->getAlias('fos_comment.manager.comment')->setPublic(true);
        $container->setAlias('fos_comment.manager.vote', $config['service']['manager']['vote']);
        $container->getAlias('fos_comment.manager.vote')->setPublic(true);
    }

    protected function loadAcl(ContainerBuilder $container, array $config)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('acl.xml');
        $loader->load('commands.xml');

        foreach ([1 => 'create', 'view', 'edit', 'delete'] as $index => $perm) {
            $container->getDefinition('fos_comment.acl.comment.roles')->replaceArgument($index, $config['acl_roles']['comment'][$perm]);
            $container->getDefinition('fos_comment.acl.thread.roles')->replaceArgument($index, $config['acl_roles']['thread'][$perm]);
            $container->getDefinition('fos_comment.acl.vote.roles')->replaceArgument($index, $config['acl_roles']['vote'][$perm]);
        }

        $container->setAlias('fos_comment.acl.thread', $config['service']['acl']['thread']);
        $container->getAlias('fos_comment.acl.thread')->setPublic(true);
        $container->setAlias('fos_comment.acl.comment', $config['service']['acl']['comment']);
        $container->getAlias('fos_comment.acl.comment')->setPublic(true);
        $container->setAlias('fos_comment.acl.vote', $config['service']['acl']['vote']);
        $container->getAlias('fos_comment.acl.vote')->setPublic(true);
    }
}
