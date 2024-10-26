<?php

return [
	Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class  => ['all' => true],
	Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
	BugCatcher\BugCatcherBundle::class => ['all' => true],
	Symfony\Bundle\SecurityBundle\SecurityBundle::class   => ['all' => true],
	Zenstruck\Foundry\ZenstruckFoundryBundle::class       => ['all' => true],
	Symfony\UX\TwigComponent\TwigComponentBundle::class   => ['all' => true],
	Symfony\UX\LiveComponent\LiveComponentBundle::class   => ['all' => true],
	Symfony\Bundle\TwigBundle\TwigBundle::class           => ['all' => true],
	Symfony\UX\Icons\UXIconsBundle::class                 => ['all' => true],
	Twig\Extra\TwigExtraBundle\TwigExtraBundle::class     => ['all' => true],
	ApiPlatform\Symfony\Bundle\ApiPlatformBundle::class   => ['all' => true],
	Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
    DAMA\DoctrineTestBundle\DAMADoctrineTestBundle::class => ['all' => true],
];
